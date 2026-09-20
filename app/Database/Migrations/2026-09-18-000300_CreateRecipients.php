<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `recipients` — the waiting list.
 *
 * Two columns here carry the whole scoring system, and nothing else in the
 * schema does:
 *
 *   entry_date   when they joined the list
 *   dialysis     when dialysis started
 *
 * The score is months-since-each, at 0.1 a month, exactly as the original
 * system computed it:
 *
 *   (0.1 * TIMESTAMPDIFF(MONTH, entry_date, CURDATE())) +
 *   (0.1 * TIMESTAMPDIFF(MONTH, dialysis,   CURDATE()))
 *
 * That expression lives in `RecipientModel::SCORE_CALC`, which is its single
 * home — it is computed at read time and never stored, so it keeps counting up
 * on its own and cannot go stale. `entry_date` is therefore NOT NULL: a
 * recipient without one has no score. `dialysis` is nullable and NULL plus a
 * number is NULL, which is the original behaviour and is kept.
 */
class CreateRecipients extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // The hospital's own medical record number, entered by staff or
            // read from TrakCare. Not auto-increment.
            'mrn' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'organ_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'blood_group' => [
                'type'       => 'ENUM',
                'constraint' => ['A', 'B', 'AB', 'O'],
            ],
            'gender' => [
                'type'       => 'ENUM',
                'constraint' => ['male', 'female'],
                'null'       => true,
            ],
            'age' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'null'       => true,
            ],
            'city' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],

            // ---- The scoring inputs ----------------------------------------
            // Required: the score is meaningless without it.
            'entry_date' => [
                'type' => 'DATE',
            ],
            // "First Dialysis" on the record screen.
            'dialysis_start' => [
                'type' => 'DATE',
                'null' => true,
            ],

            // Urgent or not, which is the whole of it: the screens ask one
            // yes/no question and there is no four-level scale behind it. The
            // waiting list reads `ORDER BY is_urgent DESC, score DESC`, so
            // urgent patients come first and the score orders each group.
            'is_urgent' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'ready', 'active', 'on_hold', 'completed', 'cancelled'],
                'default'    => 'pending',
            ],
            'mrp_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'coordinator_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
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

        $this->forge->addPrimaryKey('mrn');
        // The waiting list filters on programme and blood group, and orders on
        // urgency; the score is computed, so it cannot be indexed.
        $this->forge->addKey(['organ_code', 'blood_group']);
        $this->forge->addKey('is_urgent');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('organ_code', 'organ_programs', 'code', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('mrp_id', 'mrp', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('coordinator_id', 'coordinators', 'id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('recipients', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('recipients', true);
    }
}
