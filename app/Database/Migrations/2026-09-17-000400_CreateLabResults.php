<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `lab_results` — one row per patient per lab.
 *
 * The original three columns keep their names, so `LabsModel` keeps working:
 * `patient_id`, `lab_id`, `result`, plus `lab_comment`, which the old
 * add-patient screen posted as `<lab_id>_comment`.
 *
 * The additions are what the platform's lab cards need and the old schema had
 * no room for: the pending / completed / flagged state each card shows, and
 * the date under the result.
 */
class CreateLabResults extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'result_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'patient_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'lab_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            // NEW. The lab cards colour themselves on this and the progress
            // bar counts the completed ones. The old schema only knew whether
            // a `result` string existed.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'completed', 'flagged'],
                'default'    => 'pending',
            ],
            // One of the `labs.result_shape` codes (ND, P, D, PO, NE, ...) or
            // free text for the numeric tests. Unchanged.
            'result' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            // NEW. Each card shows the date the result came back.
            'result_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            // Unchanged. The platform's editor calls this field "Notes".
            'lab_comment' => [
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

        $this->forge->addPrimaryKey('result_id');
        // LabsModel::exists() then insert-or-update assumed one row per pair
        // already; now the database enforces it.
        $this->forge->addUniqueKey(['patient_id', 'lab_id']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('patient_id', 'patients', 'mrn', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('lab_id', 'labs', 'lab_id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('lab_results', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('lab_results', true);
    }
}
