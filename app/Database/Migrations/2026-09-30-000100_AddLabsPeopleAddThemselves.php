<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets a record carry a test the check list has no line for.
 *
 * The workup has always come from one catalogue: every recipient on a
 * programme is asked the same questions, and the sheet's blank line was a
 * single free-text box called Other. That box is now a place to add a real
 * test — typed name, the answers every other card offers, the same comment
 * line — and a test added on one person's record belongs to that person.
 *
 * Three columns' worth of room for it:
 *
 *   `labs.person_mrn` is NULL for the catalogue everyone shares and set for a
 *   test one record added. The queries that build a workup ask for both, so a
 *   patient sees the sheet plus their own additions and nobody sees anybody
 *   else's. A column rather than a table because such a test is a lab in every
 *   other respect — it has a group, an order, an answer list, and results are
 *   recorded against it through the same foreign key.
 *
 *   `labs.result_type` gains `custom`, which offers every answer the platform
 *   has rather than the handful a printed line would. Whoever adds the test
 *   knows what it answers; the sheet cannot.
 *
 *   `lab_results.status` gains `applicable` and `required`. Their opposites
 *   were already there — the columns could say Not applicable and Not
 *   required but not the other half of either pair.
 *
 * Widening only. Nothing stored changes, and no existing lab becomes custom.
 */
class AddLabsPeopleAddThemselves extends Migration
{
    public function up(): void
    {
        if (! $this->hasColumn('labs', 'person_mrn')) {
            $this->forge->addColumn('labs', [
                'person_mrn' => [
                    'type'     => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null'     => true,
                    'after'    => 'person_type',
                ],
            ]);

            // Every workup query filters on it, alongside the organ and the
            // side, so it is worth an index of its own.
            $this->db->query('ALTER TABLE ' . $this->table('labs') . ' ADD INDEX labs_person_mrn (person_mrn)');

            // And a test's name is unique within its group *for its owner*:
            // two records may each add one called the same thing under Other.
            $this->db->query('ALTER TABLE ' . $this->table('labs')
                . ' DROP INDEX name_lab_parent_id_organ_code_person_type,'
                . ' ADD UNIQUE KEY name_lab_parent_id_organ_code_person_type'
                . ' (name, lab_parent_id, organ_code, person_type, person_mrn)');
        }

        if (! $this->enumHas('labs', 'result_type', 'custom')) {
            $this->db->query('ALTER TABLE ' . $this->table('labs')
                . " MODIFY result_type ENUM('text','numeric','free_text','blood_group','done',"
                . "'positive_negative','acceptable_abnormal','acceptable_abnormal_na',"
                . "'cleared_not_cleared','given_not_given','seen_not_seen','custom') NOT NULL DEFAULT 'text'");
        }

        if (! $this->enumHas('lab_results', 'status', 'applicable')) {
            $this->db->query('ALTER TABLE ' . $this->table('lab_results')
                . " MODIFY status ENUM('not_done','pending','done','positive','negative','acceptable',"
                . "'abnormal','cleared','not_cleared','given','not_required','required','not_given',"
                . "'not_applicable','applicable','seen','not_seen',"
                . "'blood_a','blood_b','blood_ab','blood_o') NOT NULL DEFAULT 'not_done'");
        }
    }

    /**
     * Irreversible by design.
     *
     * Dropping `person_mrn` would throw away every test a record added for
     * itself, and narrowing the answers again would have to decide what a
     * result of Required becomes. The Create… migrations' own down() still
     * drops the tables.
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
