<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Makes room for the two groups that close both sheets.
 *
 * **Transplant Clinic** holds the two appointments — Nephrology and Surgery —
 * and they are answered Seen or Not seen, which is a kind of answer the
 * columns had no words for.
 *
 * **Other** is the sheet's blank line: one box for whatever the workup has no
 * row for. It has no answer at all, only the comment every test already
 * carries, so `free_text` is a result type that offers nothing to press. The
 * progress bar leaves those cards out rather than counting one that can never
 * be ticked.
 *
 * Widening only — nothing stored changes. The rows themselves come from
 * `DatabaseSeeder`, which is re-runnable and adds what is missing, so this
 * migration and `php spark db:seed DatabaseSeeder` together are the upgrade.
 */
class AddClinicAndOtherToTheWorkup extends Migration
{
    public function up(): void
    {
        if (! $this->enumHas('labs', 'result_type', 'seen_not_seen')) {
            $this->db->query('ALTER TABLE ' . $this->table('labs')
                . " MODIFY result_type ENUM('text','numeric','free_text','blood_group','done',"
                . "'positive_negative','acceptable_abnormal','acceptable_abnormal_na',"
                . "'cleared_not_cleared','given_not_given','seen_not_seen') NOT NULL DEFAULT 'text'");
        }

        if (! $this->enumHas('lab_results', 'status', 'seen')) {
            $this->db->query('ALTER TABLE ' . $this->table('lab_results')
                . " MODIFY status ENUM('not_done','pending','done','positive','negative','acceptable',"
                . "'abnormal','cleared','not_cleared','given','not_required','not_given','not_applicable',"
                . "'seen','not_seen','blood_a','blood_b','blood_ab','blood_o') NOT NULL DEFAULT 'not_done'");
        }
    }

    /**
     * Irreversible by design.
     *
     * Narrowing the columns again would have to decide what a result of Seen
     * becomes, and there is no honest answer. The Create… migrations' own
     * down() still drops the tables.
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
