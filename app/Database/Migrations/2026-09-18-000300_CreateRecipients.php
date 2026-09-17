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
            'hospital' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'diagnosis' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
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

            // Declared least-urgent-first on purpose: MySQL sorts an ENUM by
            // declaration index, so `ORDER BY urgency DESC` means
            // most-urgent-first, which is how the waiting list is read. The
            // screens take their own display order from the application.
            'urgency' => [
                'type'       => 'ENUM',
                'constraint' => ['low', 'medium', 'high', 'critical'],
                'default'    => 'medium',
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
        $this->forge->addKey('urgency');
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
