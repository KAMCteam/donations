<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The accounts that may sign in.
 *
 * Everything about a password happens here, so there is one place to read when
 * somebody asks how this works: it is hashed on the way in, verified on the
 * way back, and never selected into a view.
 */
class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'login_id', 'name', 'password_hash', 'role', 'is_admin', 'mrp_id',
        'is_active', 'last_login_at',
    ];

    /**
     * The two words a role can be, and the only two.
     *
     * Admin is not among them, and that is the point: everybody who uses this
     * system is a doctor or a coordinator, and looking after the register is a
     * permission laid over that job rather than a third job. {@see is_admin}
     */
    public const ROLES = ['doctor', 'coordinator'];

    /**
     * A hash that no password matches, for the lookup that found nobody.
     *
     * Returning early when the User ID is unknown would answer a wrong number
     * faster than a wrong password, and a stopwatch would turn that into a
     * list of the numbers that exist. So the verify runs either way, against
     * this, and both answers cost the same.
     */
    private const NOBODY = '$2y$10$usesomesillystringfoeswhichisneveravalidhashxxxxxxxxxxxxx';

    /**
     * The account behind these credentials, or null when the password is wrong.
     *
     * Deliberately indifferent to `is_active`: an account that is switched off
     * is told so, and telling somebody that only makes sense once they have
     * proved the account is theirs. Asking the question the other way round —
     * "is this a real, switched-off number?" — would answer it for anybody
     * typing numbers in, so the caller checks the flag on what comes back.
     *
     * @return array<string, mixed>|null
     */
    public function authenticate(string $loginId, string $password): ?array
    {
        $user = $this->where('login_id', $loginId)->first();

        if (! password_verify($password, (string) ($user['password_hash'] ?? self::NOBODY))) {
            return null;
        }

        return $user;
    }

    /** The account a registered person signs in with, or null. */
    public function forMrp(int|string $mrpId): ?array
    {
        return $this->where('mrp_id', (int) $mrpId)->first();
    }

    /** Stamps the moment somebody got in. */
    public function touchLogin(int|string $id): void
    {
        $this->update($id, ['last_login_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Writes an account, hashing the password rather than storing it.
     *
     * The one way a password should ever reach this table. `PASSWORD_DEFAULT`
     * and not a named algorithm, so a PHP upgrade that picks something better
     * is picked up by writing the next password rather than by editing this.
     *
     * @param array<string, mixed> $fields
     */
    public function store(array $fields, string $password): int|string
    {
        $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);

        $this->insert($fields);

        return $this->getInsertID();
    }
}
