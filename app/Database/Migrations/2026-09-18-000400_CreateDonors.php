<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `donors` — the donor register.
 *
 * A table of its own rather than a `type` column on one shared table, because
 * the two sides are not the same record: a donor has no score, no urgency, no
 * diagnosis and no dialysis date, and instead has how they came to donate.
 * Putting them together means four columns that are always NULL on one side.
 *
 * The same person can hold a row here and in `recipients` under one MRN —
 * someone may donate on one programme and be listed on another — and their lab
 * results are shared, since a person's HLA typing is their HLA typing.
 */
class CreateDonors extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
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

            // ---- Donor-only ------------------------------------------------
            // A deceased donor has no consent conversation, no relationship to
            // a recipient and no follow-up; the two behave differently enough
            // that the register is filtered on this.
            'donation_type' => [
                'type'       => 'ENUM',
                'constraint' => ['living', 'deceased'],
                'default'    => 'living',
            ],
            // How they relate to the person they are donating to, when known
            // before a pair exists — "Brother", "Spouse".
            'relationship' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],

            // Exactly the four the Donor Status picker offers, and its own
            // default. A wider list would let a donor reach a state no screen
            // can show, which reads as the record having lost its status.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['on_hold', 'active', 'completed', 'cancelled'],
                'default'    => 'on_hold',
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
            // When they joined the register. Nullable, unlike a recipient's
            // entry_date: no score depends on it.
            'registered_on' => [
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

        $this->forge->addPrimaryKey('mrn');
        // Matching looks for a donor on the same programme with a compatible
        // blood group; the register filters on donation type.
        $this->forge->addKey(['organ_code', 'blood_group']);
        $this->forge->addKey('donation_type');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('organ_code', 'organ_programs', 'code', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('mrp_id', 'mrp', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('coordinator_id', 'coordinators', 'id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('donors', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('donors', true);
    }
}
