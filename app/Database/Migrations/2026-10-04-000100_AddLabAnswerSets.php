<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets a test a record added decide what it answers, and in what colours.
 *
 * The check list's own tests each answer a fixed question — a serology says
 * Positive or Negative, a referral says Cleared or not — and those lists are
 * code, because the sheet they come from is. A test somebody adds under
 * **Other** has no sheet behind it: only the person adding it knows what it
 * answers. It was offered all seventeen words the platform has, which is a
 * list nobody reads and a question nobody asked.
 *
 * `labs.answer_set` is that test's own answers: which of the seventeen it
 * offers, any it was given that the platform had no word for, and the colour
 * each carries on this test. NULL means the standard list for the test's
 * result type, which is every test the check list seeded.
 *
 * `lab_results.status` becomes a VARCHAR. It was an ENUM of the seventeen,
 * which cannot hold an answer somebody invents — and an invented answer is the
 * point. Widening an ENUM to a string keeps every value exactly as it was;
 * what it gives up is the column refusing a word nobody defined, which is now
 * `answer_set`'s job rather than the column's.
 */
class AddLabAnswerSets extends Migration
{
    public function up(): void
    {
        if (! $this->hasColumn('labs', 'answer_set')) {
            $this->db->query('ALTER TABLE ' . $this->table('labs')
                . ' ADD COLUMN answer_set JSON NULL AFTER result_type');
        }

        if ($this->isEnum('lab_results', 'status')) {
            $this->db->query('ALTER TABLE ' . $this->table('lab_results')
                . " MODIFY status VARCHAR(60) NOT NULL DEFAULT 'not_done'");
        }
    }

    /**
     * Irreversible by design.
     *
     * Narrowing the column back to an ENUM would have to decide what becomes
     * of an answer somebody wrote themselves, and dropping `answer_set` would
     * throw away what each of those tests asks. The Create… migrations' own
     * down() still drops the tables.
     */
    public function down(): void
    {
    }

    private function table(string $name): string
    {
        return $this->db->protectIdentifiers($this->db->prefixTable($name), true, false, false);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->db->getFieldNames($this->db->prefixTable($table)), true);
    }

    /** From information_schema: `getFieldData()` reports an ENUM as "enum". */
    private function isEnum(string $table, string $column): bool
    {
        $row = $this->db->query(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->db->prefixTable($table), $column]
        )->getRowArray();

        return $row !== null && strtolower((string) $row['DATA_TYPE']) === 'enum';
    }
}
