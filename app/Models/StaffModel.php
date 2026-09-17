<?php

namespace App\Models;

use CodeIgniter\Model;

/** Sign-in accounts for the login screen. */
class StaffModel extends Model
{
    protected $table         = 'staff';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['staff_id', 'name', 'password_hash', 'role', 'is_active', 'last_login_at'];

    /**
     * The staff member behind these credentials, or null.
     *
     * Always runs password_verify, even when the account does not exist, so a
     * wrong Staff ID and a wrong password take the same time to answer.
     */
    public function authenticate(string $staffId, string $password): ?array
    {
        $staff = $this->where('staff_id', $staffId)->where('is_active', 1)->first();
        $hash  = $staff['password_hash'] ?? '$2y$10$usesomesillystringfoeswhichisneveravalidhashxxxxxxxxxxxxx';

        if (! password_verify($password, $hash) || $staff === null) {
            return null;
        }

        $this->update($staff['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        return $staff;
    }
}
