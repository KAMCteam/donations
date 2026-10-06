<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Every attempt to sign in, kept.
 *
 * The failed ones above all: a row of them against one User ID at three in
 * the morning is the only thing that would say so. It is written to by the
 * login screen and read by the Admin page, and by nothing else — there is
 * no screen that edits or deletes a line of it, because a log somebody can
 * tidy is not a log.
 *
 * `user_id` is null for an attempt on a number nobody holds, which is most
 * of what a bad one looks like. What was typed is kept either way, because
 * that is the thing worth reading.
 */
class CreateLoginActivity extends Migration
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
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'The account, when the User ID matched one.',
            ],
            'login_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'What was typed into User ID.',
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'default'    => '',
                'comment'    => "The account's name, or '' when there was none.",
            ],
            'succeeded' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => '',
                'comment'    => 'Why a failed attempt failed.',
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
        // The screen reads it newest first, and nothing else reads it at all.
        $this->forge->addKey('created_at');
        $this->forge->createTable('login_activity', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('login_activity', true);
    }
}
