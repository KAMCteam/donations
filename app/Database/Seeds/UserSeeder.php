<?php

namespace App\Database\Seeds;

use App\Models\UserModel;
use CodeIgniter\Database\Seeder;

/**
 * Three accounts to develop against, one per role.
 *
 *     php spark db:seed UserSeeder
 *
 * **Development only, and it enforces that itself.** The passwords are a
 * single letter; they exist so that somebody building a screen can get past
 * the login without being handed a real account, and they would be a way in
 * for anybody who can read this file if they ever reached a running service.
 * So the first thing this does is read `ENVIRONMENT`, and on anything but
 * `development` it writes nothing and says why.
 *
 * It is not in {@see DatabaseSeeder}, which is run on every install. These are
 * made people, and nothing invents people into this system unless somebody
 * asks for it by name.
 *
 * Re-runnable: an account whose User ID is already there is left exactly as it
 * is, password included, so running this twice does not quietly reset one that
 * has been given a real password.
 */
class UserSeeder extends Seeder
{
    /** [login_id, name, role] — the password is the same single letter for all three. */
    private const USERS = [
        ['1', 'Development Admin', 'admin'],
        ['2', 'Development Doctor', 'doctor'],
        ['3', 'Development Coordinator', 'coordinator'],
    ];

    private const PASSWORD = 'A';

    public function run(): void
    {
        if (ENVIRONMENT !== 'development') {
            // Not an exception: `php spark db:seed` is run as a step of
            // setting a machine up, and a step that stops the run would stop
            // the seeders after it too.
            echo 'UserSeeder: skipped — it only runs in the development environment, and this is '
                . ENVIRONMENT . '.' . PHP_EOL;

            return;
        }

        $users  = model(UserModel::class);
        $added  = 0;
        $kept   = 0;

        foreach (self::USERS as [$loginId, $name, $role]) {
            if ($users->where('login_id', $loginId)->first() !== null) {
                $kept++;

                continue;
            }

            // Through the model, so the password is hashed by the same line
            // that hashes every other password. Nothing writes this column
            // directly, one letter or not.
            $users->store([
                'login_id'  => $loginId,
                'name'      => $name,
                'role'      => $role,
                'is_active' => 1,
            ], self::PASSWORD);

            $added++;
        }

        echo sprintf(
            'UserSeeder: %d added, %d already there. Sign in with User ID 1, 2 or 3 and the password %s.%s',
            $added,
            $kept,
            self::PASSWORD,
            PHP_EOL
        );
    }
}
