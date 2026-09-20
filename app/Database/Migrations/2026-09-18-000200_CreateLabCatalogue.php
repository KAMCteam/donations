<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The lab catalogue: which tests exist, and how they are grouped.
 *
 * This is the definition of a workup, not anybody's results — those are in
 * `lab_results`. Splitting the two is what lets the workup change without
 * touching a single patient record, and lets a test be retired without
 * deleting the history of everyone who took it.
 *
 *   lab_parents  the group a test is listed under (Virology, Imaging, ...)
 *   labs         one row per test, per programme and per person type
 *
 * `labs` is keyed by programme and person type because a kidney donor's
 * workup is not a liver recipient's: the screens ask the catalogue "what does
 * a donor on the kidney programme need?" and render a card per answer.
 */
class CreateLabCatalogue extends Migration
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
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'sort_order' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('lab_parents', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'lab_parent_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'organ_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            // `both` where the same test is on each side's workup.
            'person_type' => [
                'type'       => 'ENUM',
                'constraint' => ['recipient', 'donor', 'both'],
                'default'    => 'both',
            ],
            // How the result is captured, in the check list's own wording.
            // `text` is free entry — a value, a finding, a report line — and
            // `numeric` a figure. The rest are the answers the sheet offers:
            //   blood_group          A / B / AB / O
            //   done                 Not done / pending / done
            //   positive_negative    Not done / pending / Positive / Negative
            //   acceptable_abnormal  Not done / pending / acceptable / Abnormal
            //   cleared_not_cleared  Not done / pending / Cleared / not cleared
            //   given_not_given      Given / not required / not given
            // "Not applicable" is added to several of them on the sheet; it is
            // an answer any test can need, so it is not a vocabulary of its own.
            'result_type' => [
                'type'       => 'ENUM',
                'constraint' => ['text', 'numeric', 'blood_group', 'done', 'positive_negative', 'acceptable_abnormal', 'cleared_not_cleared', 'given_not_given'],
                'default'    => 'text',
            ],
            'sort_order' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
            ],
            // Retiring a test hides it from new workups without touching the
            // results already recorded against it.
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
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
        // The lookup every record screen makes: this programme, this side.
        $this->forge->addKey(['organ_code', 'person_type', 'is_active']);
        // The same test may appear once per group, programme and side, not
        // twice. The group belongs in the key: the check list has VZV under
        // Infectious workup as a serology and under Vaccinations as a jab,
        // and they are two different things to record.
        $this->forge->addUniqueKey(['name', 'lab_parent_id', 'organ_code', 'person_type']);
        $this->forge->addForeignKey('lab_parent_id', 'lab_parents', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('organ_code', 'organ_programs', 'code', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('labs', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('labs', true);
        $this->forge->dropTable('lab_parents', true);
    }
}
