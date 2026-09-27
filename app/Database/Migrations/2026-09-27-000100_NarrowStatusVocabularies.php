<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gives a person and a pair the statuses each of them is actually asked for.
 *
 * The three screens now offer three different menus. A recipient and a donor
 * are On Hold, Active or Declined; a pair is one of those three plus
 * Transplanted, Paired Exchange or Closed. The difference is the point: a
 * person is not "transplanted" or "closed", their *case* is, and that is the
 * pair's to say.
 *
 * Two words the columns did not have:
 *
 *   `donors.status` had no `declined` — it had `cancelled`, which is the same
 *   thing under the name the screens have stopped using, so those rows move
 *   across.
 *
 *   `pairs.status` had no `transplanted` — it had `completed`, likewise, and
 *   likewise moved.
 *
 * Nothing is narrowed. `pending`, `confirmed`, `completed` and `cancelled`
 * stay in the columns even though no menu offers them again, because a record
 * saved under one is a record of what was true then, and rewriting it to fit a
 * newer menu would be inventing history. `UiStore::STATUS_OPTIONS` still names
 * all of them so an old record reads as words rather than as a bare key.
 */
class NarrowStatusVocabularies extends Migration
{
    public function up(): void
    {
        // ---- Donors: cancelled becomes declined --------------------------
        if (! $this->enumHas('donors', 'status', 'declined')) {
            $this->db->query('ALTER TABLE ' . $this->table('donors')
                . " MODIFY status ENUM('on_hold','active','declined','completed','cancelled')"
                . " NOT NULL DEFAULT 'on_hold'");
        }

        $this->db->query('UPDATE ' . $this->table('donors') . " SET status = 'declined' WHERE status = 'cancelled'");

        // ---- Pairs: completed becomes transplanted -----------------------
        if (! $this->enumHas('pairs', 'status', 'transplanted')) {
            $this->db->query('ALTER TABLE ' . $this->table('pairs')
                . " MODIFY status ENUM('pending','confirmed','closed','completed','transplanted',"
                . "'paired_exchange','on_hold','active','declined') NOT NULL DEFAULT 'pending'");
        }

        $this->db->query('UPDATE ' . $this->table('pairs') . " SET status = 'transplanted' WHERE status = 'completed'");

        // A recipient can no longer be set to a status only a pair can hold,
        // so the ones already carrying one are put back to something their own
        // screen can show. `closed` and `completed` came from a pair that had
        // ended, which leaves the person themselves simply not active.
        $this->db->query('UPDATE ' . $this->table('recipients')
            . " SET status = 'on_hold' WHERE status IN ('pending','confirmed','closed','completed')");
    }

    /**
     * Irreversible by design.
     *
     * The words it moved across mean the same thing under two names; putting
     * them back would only be choosing the older name again. The Create…
     * migrations' own down() still drops the tables.
     */
    public function down(): void
    {
    }

    private function table(string $name): string
    {
        return $this->db->protectIdentifiers($this->db->prefixTable($name), true, false, false);
    }

    /**
     * Whether an ENUM column already offers a value.
     *
     * From information_schema, not getFieldData(), which reports an ENUM's
     * type as the bare word "enum" with the values nowhere in it.
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
