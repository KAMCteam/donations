<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Admin stops being a kind of person and becomes something a person has.
 *
 * `users.role` held three words — admin, doctor, coordinator — as if being an
 * administrator were a third job. It is not. Everybody who uses this system is
 * a doctor or a coordinator; some of them also look after the register, and
 * that is a permission laid over the job rather than instead of it. An admin
 * who was "an admin" had no clinical role at all, which is why they had a
 * dashboard of their own with nothing on it.
 *
 * So: `is_admin` beside the role, the role narrowed to the two real ones, and
 * every account that held `admin` becomes a doctor who has the permission —
 * the reading that loses nothing, since what those accounts were being used
 * for was administration on top of ordinary work.
 *
 * `mrp_id` is the other half of it. The register of people and the list of
 * sign-ins were two tables that happened to describe the same staff, and the
 * Admin screen asks for one list: a row to edit, deactivate, reset the
 * password of, and grant the permission on. So an account now says which
 * registered person it belongs to, and this migration gives every person
 * already registered an account to be that row.
 *
 * The log of sign-in attempts the Admin screen reads is its own migration,
 * because it is an ordinary new table and can be rolled back like one.
 *
 * Those accounts are made **without a password**: an empty hash matches
 * nothing, so they exist and cannot be signed into until somebody sets one.
 * That is deliberate and is the same rule the Add MRP screen has always
 * stated — the hospital directory returns a name and an ID, never a
 * credential to copy into this database.
 */
class AdminIsAPermission extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'is_admin' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'role',
                'comment'    => 'Looks after the register, on top of whatever their role is.',
            ],
            'mrp_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'is_admin',
                'comment'    => 'The registered person this account signs in as.',
            ],
        ]);

        // Before the ENUM is narrowed, or the word would be truncated away and
        // the permission lost with it.
        $this->db->table('users')
            ->where('role', 'admin')
            ->update(['role' => 'doctor', 'is_admin' => 1]);

        $this->forge->modifyColumn('users', [
            'role' => [
                'name'       => 'role',
                'type'       => 'ENUM',
                'constraint' => ['doctor', 'coordinator'],
            ],
        ]);

        $this->accountsForTheRegister();
    }

    /**
     * Irreversible by design.
     *
     * Putting `admin` back as a role would mean deciding which doctors had
     * been administrators before, and `is_admin` would be gone by then.
     */
    public function down(): void
    {
    }

    /**
     * An account for everybody already in the register.
     *
     * Keyed on the staff number, which both tables hold: `mrp.code` is what
     * somebody is registered under and `users.login_id` is what they type to
     * sign in, and they were always the same number written in two places.
     * Where an account under that number already exists it is linked rather
     * than duplicated — one person, one account, whichever came first.
     */
    private function accountsForTheRegister(): void
    {
        $users = $this->db->table('users');

        foreach ($this->db->table('mrp')->get()->getResultArray() as $person) {
            $code     = trim((string) $person['code']);
            $existing = $code === '' ? null : $users->getWhere(['login_id' => $code])->getRowArray();

            if ($existing !== null) {
                $users->where('id', $existing['id'])->update(['mrp_id' => $person['id']]);

                continue;
            }

            if ($code === '' || $users->getWhere(['mrp_id' => $person['id']])->getRowArray() !== null) {
                continue;
            }

            $users->insert([
                'login_id' => $code,
                'name'     => $person['name'],
                // The register's two kinds and the account's two roles are the
                // same two words, which is what makes this a copy and not a
                // decision.
                'role'     => $person['kind'] === 'coordinator' ? 'coordinator' : 'doctor',
                // Matches no password at all. The account is there; signing
                // into it waits for somebody to set one.
                'password_hash' => '',
                'is_active'     => (int) $person['is_active'],
                'mrp_id'        => $person['id'],
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }

}
