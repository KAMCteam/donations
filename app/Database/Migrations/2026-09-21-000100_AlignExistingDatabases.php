<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Brings a database built before the field changes up to the current schema.
 *
 * The six Create… migrations were edited in place as the screens changed,
 * rather than being added to. That is fine for a database made from scratch —
 * it gets the current shape — but a database that ran them earlier keeps the
 * old columns, and `php spark migrate` has nothing new to run. The screens
 * then offer values the columns cannot hold, and saving fails with a
 * truncation error that says nothing about why.
 *
 * So this one does the catching up. Every step asks the database what it
 * currently has and does nothing when the answer is already right, which
 * makes it a no-op on a fresh install and safe to run twice.
 *
 * It keeps the records: High and Critical urgency become urgent, a pair's
 * `scheduled` becomes Confirmed, a recipient's `ready` and `cancelled` land in
 * the shared list, and a recipient in an open pair takes that pair's status.
 */
class AlignExistingDatabases extends Migration
{
    /** The status vocabulary `recipients` and `pairs` now share. */
    private const STATUSES = "'pending','confirmed','closed','completed','paired_exchange','on_hold','active','declined'";

    public function up(): void
    {
        $this->alignRecipients();
        $this->alignDonors();
        $this->alignPairs();
        $this->carryPairStatusToRecipients();
    }

    /**
     * Irreversible by design.
     *
     * Rolling back would mean inventing the four-level urgency, the diagnosis
     * and the hospital this dropped — there is nothing to restore them from.
     * The Create… migrations' own down() still drops the tables.
     */
    public function down(): void
    {
    }

    private function alignRecipients(): void
    {
        // Urgency was a four-level scale that only the waiting list read. It is
        // one yes/no question now; High and Critical carry over as urgent.
        if ($this->hasColumn('recipients', 'urgency') && ! $this->hasColumn('recipients', 'is_urgent')) {
            $this->db->query('ALTER TABLE ' . $this->table('recipients')
                . ' ADD COLUMN is_urgent TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER dialysis_start');
            $this->db->query('UPDATE ' . $this->table('recipients')
                . " SET is_urgent = 1 WHERE urgency IN ('high', 'critical')");
            $this->db->query('ALTER TABLE ' . $this->table('recipients') . ' ADD INDEX (is_urgent)');
        }

        $this->dropColumn('recipients', 'urgency');
        $this->dropColumn('recipients', 'diagnosis');
        $this->dropColumn('recipients', 'hospital');

        // `ready` had no screen behind it and `cancelled` is `closed` now.
        // Widen to both lists first: a value can only be moved while the
        // column still accepts where it is going, and MySQL answers an
        // impossible one by writing an empty string rather than refusing.
        if ($this->enumHas('recipients', 'status', 'ready')) {
            $this->widen('recipients', "'ready','cancelled'");
            $this->db->query('UPDATE ' . $this->table('recipients') . " SET status = 'active' WHERE status = 'ready'");
            $this->db->query('UPDATE ' . $this->table('recipients') . " SET status = 'closed' WHERE status = 'cancelled'");
            $this->narrow('recipients');
        }
    }

    private function alignDonors(): void
    {
        // The donor screen never collected Hospital; the column was only ever
        // filled from the recipient form's box, which is gone.
        $this->dropColumn('donors', 'hospital');

        // A living donor can now be recorded as related or unrelated to the
        // recipient. Rows already saying `living` stay as they are: on those,
        // the question was never asked.
        if (! $this->enumHas('donors', 'donation_type', 'living_related')) {
            $this->db->query('ALTER TABLE ' . $this->table('donors')
                . " MODIFY donation_type ENUM('living','living_related','living_unrelated','deceased')"
                . " NOT NULL DEFAULT 'living'");
        }
    }

    private function alignPairs(): void
    {
        if (! $this->enumHas('pairs', 'status', 'scheduled')) {
            return;
        }

        // Same three steps: `scheduled` meant agreed but not yet done, which
        // is Confirmed.
        $this->widen('pairs', "'scheduled'");
        $this->db->query('UPDATE ' . $this->table('pairs') . " SET status = 'confirmed' WHERE status = 'scheduled'");
        $this->narrow('pairs');
    }

    /** The shared list plus the retired values, so nothing is stranded. */
    private function widen(string $table, string $retired): void
    {
        $this->db->query('ALTER TABLE ' . $this->table($table)
            . ' MODIFY status ENUM(' . self::STATUSES . ',' . $retired . ") NOT NULL DEFAULT 'pending'");
    }

    /** The shared list alone, once everything has been moved into it. */
    private function narrow(string $table): void
    {
        $this->db->query('ALTER TABLE ' . $this->table($table)
            . ' MODIFY status ENUM(' . self::STATUSES . ") NOT NULL DEFAULT 'pending'");
    }

    /**
     * A recipient in an open pair carries that pair's status.
     *
     * Only once both columns are the shared list — on a database this has not
     * finished aligning, copying one into the other would write a value the
     * other cannot hold, which MySQL stores as an empty string rather than
     * refusing.
     */
    private function carryPairStatusToRecipients(): void
    {
        if (! $this->enumHas('recipients', 'status', 'paired_exchange')
            || ! $this->enumHas('pairs', 'status', 'paired_exchange')) {
            return;
        }

        $this->db->query(
            'UPDATE ' . $this->table('recipients') . ' r'
            . ' JOIN ' . $this->table('pairs') . " p ON p.recipient_mrn = r.mrn AND p.status <> 'closed'"
            . ' SET r.status = p.status'
        );
    }

    // ---- Asking the database what it already has ---------------------------

    private function table(string $name): string
    {
        return $this->db->protectIdentifiers($this->db->prefixTable($name), true, false, false);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->db->getFieldNames($this->db->prefixTable($table)), true);
    }

    private function dropColumn(string $table, string $column): void
    {
        if ($this->hasColumn($table, $column)) {
            $this->forge->dropColumn($this->db->prefixTable($table), $column);
        }
    }

    /**
     * Whether an ENUM column still offers (or already offers) a value.
     *
     * Read from information_schema rather than getFieldData(), which reports
     * an ENUM's type as the bare word "enum" with the values nowhere in it —
     * so every check here silently answered "no" until this was corrected.
     */
    private function enumHas(string $table, string $column, string $value): bool
    {
        $row = $this->db->query(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->db->prefixTable($table), $column]
        )->getRowArray();

        return $row !== null && str_contains((string) $row['COLUMN_TYPE'], "'" . $value . "'");
    }
}
