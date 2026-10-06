<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Who may sign in, and as what.
 *
 * The login screen used to accept any non-empty pair of boxes. This is the
 * table behind it: one row per person who may use the platform, holding the
 * number they type into **User ID**, the hash of their password, and the one
 * word that decides which screens they see.
 *
 * `login_id` is a string and not an integer although the form only accepts
 * digits. It is a staff number from the hospital, not a count of anything — it
 * can be given leading zeros, and nothing should ever add one to it.
 *
 * The password is never stored. `password_hash` holds what `password_hash()`
 * returned, which is 60 characters for bcrypt today and longer for whatever
 * PHP's default becomes, so the column is sized for the algorithm after next.
 *
 * `is_active` is how somebody is taken out without taking their history with
 * them: the row stays, and `last_login_at` keeps saying when they were last
 * here.
 */
class CreateUsersTable extends Migration
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
            'login_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'The staff number typed into User ID.',
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'password_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'comment'    => 'password_hash() output. Never the password.',
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['admin', 'doctor', 'coordinator'],
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

        $this->forge->addKey('id', true);
        // One account per staff number. The database says so as well as the
        // model, because two rows answering to the same User ID would make
        // signing in a question of which one the query happened to find.
        $this->forge->addUniqueKey('login_id');
        $this->forge->createTable('users', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('users', true);
    }
}
