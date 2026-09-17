<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The directory tables: who can sign in, which programmes exist, and the
 * people a record is assigned to.
 *
 * Nothing here is clinical. These four are looked up by every other table, so
 * they are created first.
 *
 *   staff           the login screen authenticates against this
 *   organ_programs  the programme picker's cards
 *   mrp             most responsible physicians, the MRP dropdowns
 *   coordinators    transplant coordinators, the Coordinator dropdowns
 */
class CreateDirectory extends Migration
{
    public function up(): void
    {
        $this->createOrganPrograms();
        $this->createStaff();
        $this->createMrp();
        $this->createCoordinators();
    }

    public function down(): void
    {
        $this->forge->dropTable('coordinators', true);
        $this->forge->dropTable('staff', true);
        $this->forge->dropTable('mrp', true);
        $this->forge->dropTable('organ_programs', true);
    }

    /**
     * The programmes the picker offers. Content, not derivation: the heading,
     * subtitle and icon were hardcoded in the controller, which made adding a
     * programme a code change.
     *
     * `code` is also the URL segment the picker links to (/organ/kidney) and
     * the value stored in `recipients.organ`, `donors.organ` and
     * `labs.organ_code`.
     */
    private function createOrganPrograms(): void
    {
        $this->forge->addField([
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            // File under public/assets/ui/img, e.g. kidney.svg.
            'icon' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'sort_order' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
            ],
            // Retire a programme without deleting the people on it.
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
        ]);

        $this->forge->addPrimaryKey('code');
        $this->forge->createTable('organ_programs', true, ['ENGINE' => 'InnoDB']);
    }

    /**
     * Sign-in accounts.
     *
     * `password_hash` holds a PHP `password_hash()` digest, never a plaintext
     * or unsalted one. If sign-in should come from the hospital directory
     * instead, this table can go; nothing else points at it.
     */
    private function createStaff(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // What the login screen calls Staff ID.
            'staff_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'password_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['coordinator', 'physician', 'admin'],
                'default'    => 'coordinator',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'last_login_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addUniqueKey('staff_id');
        $this->forge->createTable('staff', true, ['ENGINE' => 'InnoDB']);
    }

    /** Most responsible physicians. `code` is the hospital's own "MRP-004". */
    private function createMrp(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
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
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('mrp', true, ['ENGINE' => 'InnoDB']);
    }

    private function createCoordinators(): void
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
                'constraint' => 150,
            ],
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
        $this->forge->createTable('coordinators', true, ['ENGINE' => 'InnoDB']);
    }
}
