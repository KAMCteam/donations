<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `lab_results` — one row per person per test.
 *
 * `person_mrn` is an MRN, and `person_type` says which register it belongs to.
 * That pair is the key: a result is filed against the person in the role they
 * were being worked up for, so someone who is both a donor on one programme
 * and a recipient on another keeps the two workups apart.
 *
 * The result itself is three things the screens show separately:
 *
 *   status   pending / completed / flagged — what colours the card and what
 *            the progress bar counts
 *   value    the reading, the finding, or one of the coded answers the test's
 *            `result_type` allows
 *   taken_on the date under the value
 *
 * No foreign key on `person_mrn`: MySQL cannot point one column at either of
 * two tables. The two triggers below do the `ON DELETE CASCADE` a foreign key
 * would have, so removing someone takes their results with them. `lab_id` is a
 * real foreign key, so a result can never name a test that does not exist.
 */
class CreateLabResults extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'person_mrn' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'person_type' => [
                'type'       => 'ENUM',
                'constraint' => ['recipient', 'donor'],
            ],
            'lab_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            // The answers the check list offers, across every kind of test.
            // Which of them a given test offers is its `result_type`, in
            // UiStore::RESULT_OPTIONS — a serology is Positive or Negative, a
            // referral Cleared or not. `not_done` is where they all start.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'not_done', 'pending', 'done', 'positive', 'negative', 'acceptable',
                    'abnormal', 'cleared', 'not_cleared', 'given', 'not_required',
                    'not_given', 'not_applicable', 'blood_a', 'blood_b', 'blood_ab', 'blood_o',
                ],
                'default'    => 'not_done',
            ],
            'value' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'taken_on' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        // One result per person, per role, per test — the progress bar counts
        // rows, so a duplicate would make a workup look further along.
        $this->forge->addUniqueKey(['person_mrn', 'person_type', 'lab_id']);
        // Reading a person's whole workup, and counting flagged ones.
        $this->forge->addKey(['person_mrn', 'person_type']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('lab_id', 'labs', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('lab_results', true, ['ENGINE' => 'InnoDB']);

        // Stands in for the ON DELETE CASCADE that `person_mrn` cannot have.
        foreach (['recipients' => 'recipient', 'donors' => 'donor'] as $table => $type) {
            $people     = $this->name($table);
            $labResults = $this->name('lab_results');
            $trigger    = $this->name($table . '_delete_lab_results');

            $this->db->query(<<<SQL
                CREATE TRIGGER {$trigger} AFTER DELETE ON {$people}
                FOR EACH ROW
                DELETE FROM {$labResults}
                WHERE person_mrn = OLD.mrn AND person_type = '{$type}'
                SQL);
        }
    }

    public function down(): void
    {
        foreach (['recipients', 'donors'] as $table) {
            $this->db->query('DROP TRIGGER IF EXISTS ' . $this->name($table . '_delete_lab_results'));
        }

        $this->forge->dropTable('lab_results', true);
    }

    /** Prefixed and quoted name, since raw SQL does not get DBPrefix applied. */
    private function name(string $table): string
    {
        return $this->db->protectIdentifiers($this->db->prefixTable($table), false, true);
    }
}
