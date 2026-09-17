<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The lookup tables: lab parents, the lab catalogue, MRPs and coordinators.
 *
 * Carried over from the original `donations` schema with the same table and
 * column names, so `LabsModel`, `MrpModel`, `CoordinatorsModel` and
 * `ListsModel` keep working unchanged — including `ListsModel::get_labs()`,
 * which groups labs under their parent, and `get_enum_values()`, which reads
 * the ENUMs below with `SHOW COLUMNS`.
 *
 * Created first because `labs` points at `lab_parents` and `patients` points
 * at `mrp` and `coordinators`.
 */
class CreateReferenceTables extends Migration
{
    public function up(): void
    {
        // ---- lab_parents: the group each lab is listed under -----------------
        $this->forge->addField([
            'parent_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'parent_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
        ]);
        $this->forge->addPrimaryKey('parent_id');
        $this->forge->addUniqueKey('parent_name');
        $this->forge->createTable('lab_parents', true, ['ENGINE' => 'InnoDB']);

        // ---- labs: the catalogue of tests, not a patient's results ----------
        $this->forge->addField([
            'lab_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'lab_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            // Slash-separated result codes, exactly as before: "ND/P/D",
            // "PO/NE", "C/NC"... The labels live in app/Language/*/Form.php
            // under form_lab_option_<code>.
            'result_shape' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'lab_parent_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'patient_type' => [
                'type'       => 'ENUM',
                'constraint' => ['recipient', 'donor', 'both'],
                'default'    => 'both',
            ],
            'organ_type' => [
                'type'       => 'ENUM',
                'constraint' => ['kidney', 'liver'],
            ],
            // New: lets a programme order its workup without relying on
            // lab_id order, which ListsModel::get_labs() used to depend on.
            'sort_order' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
        ]);
        $this->forge->addPrimaryKey('lab_id');
        $this->forge->addKey(['organ_type', 'patient_type']);
        $this->forge->addForeignKey('lab_parent_id', 'lab_parents', 'parent_id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('labs', true, ['ENGINE' => 'InnoDB']);

        // ---- mrp: most responsible physicians -------------------------------
        // Two identifiers as before: `id` is the key rows point at, `mrp_id`
        // is the hospital's own code (the "MRP-004" the Add MRP screen asks
        // for). MrpModel::get_mrps() returns both.
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'mrp_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('mrp_id');
        $this->forge->createTable('mrp', true, ['ENGINE' => 'InnoDB']);

        // ---- coordinators ---------------------------------------------------
        $this->forge->addField([
            'coordinator_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'coordinator_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
        ]);
        $this->forge->addPrimaryKey('coordinator_id');
        $this->forge->createTable('coordinators', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('coordinators', true);
        $this->forge->dropTable('mrp', true);
        $this->forge->dropTable('labs', true);
        $this->forge->dropTable('lab_parents', true);
    }
}
