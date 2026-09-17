<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `staff` — NEW, and the whole table is new.
 *
 * The platform opens on a Staff ID / password screen, and the old schema had
 * nowhere to check either, which is why `Ui::attemptLogin()` still accepts any
 * non-empty pair. This gives that screen something to authenticate against.
 *
 * `password_hash` holds a PHP `password_hash()` digest — never a plaintext or
 * unsalted-hash password. If sign-in is meant to come from the hospital
 * directory or TrakCare instead, drop this table and keep the rest of the
 * schema; nothing else references it.
 */
class CreateStaff extends Migration
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
            // What the login screen calls Staff ID, e.g. "DR-00421".
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
            // A physician who is also an MRP can be tied to their mrp row, so
            // the MRP dropdowns can default to whoever is signed in.
            'mrp_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
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
        $this->forge->addForeignKey('mrp_id', 'mrp', 'id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('staff', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('staff', true);
    }
}
