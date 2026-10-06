<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Every attempt to sign in, kept.
 *
 * Written by the login screen and read by the Admin page. Nothing edits a line
 * of it and nothing deletes one: a log somebody can tidy is not a log, and the
 * reason this table exists is the attempts nobody meant to be read — a column
 * of failures against one User ID is the only thing that would say so.
 *
 * The attempt is recorded whether or not the User ID belongs to anybody, and
 * what was typed is kept either way. The password never is, successful or
 * otherwise.
 */
class LoginActivityModel extends Model
{
    protected $table         = 'login_activity';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id', 'login_id', 'name', 'succeeded', 'reason'];

    /** Why an attempt failed, in the words the screen reads back. */
    public const BAD_CREDENTIALS = 'Wrong User ID or password';
    public const INACTIVE        = 'Account deactivated';
    public const NO_PASSWORD     = 'No password set';

    /**
     * Records one attempt.
     *
     * @param array<string, mixed>|null $user The account, when there was one
     */
    public function record(string $loginId, bool $succeeded, ?array $user = null, string $reason = ''): void
    {
        $this->insert([
            'user_id'   => $user === null ? null : (int) $user['id'],
            // Trimmed to the column rather than refused: this is a log of what
            // somebody did, and a very long User ID is itself worth seeing.
            'login_id'  => mb_substr($loginId, 0, 50),
            'name'      => mb_substr((string) ($user['name'] ?? ''), 0, 150),
            'succeeded' => $succeeded ? 1 : 0,
            'reason'    => $succeeded ? '' : $reason,
        ]);
    }

    /**
     * The log as the Admin page shows it: newest first, and capped.
     *
     * Capped because the screen is a screen. Somebody who needs the whole of
     * it needs a query, not a page that takes a minute to paint.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 100): array
    {
        return $this->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll($limit);
    }
}
