<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The people a record can be assigned to.
 *
 * Two kinds, told apart by `kind`: the most responsible physician and the
 * coordinator. The table is still called `mrp` because that is what the screen
 * and every record column call the physician, and renaming it would rewrite
 * half the system to say the same thing.
 */
class MrpModel extends Model
{
    protected $table         = 'mrp';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['code', 'name', 'kind', 'is_active'];

    public const DOCTOR      = 'doctor';
    public const COORDINATOR = 'coordinator';

    /** What each kind is called where a screen has to name it. */
    public const KINDS = [
        self::DOCTOR      => 'Doctor',
        self::COORDINATOR => 'Coordinator',
    ];

    /**
     * The physicians a record screen can assign to, in service.
     *
     * Doctors only: the MRP field on a record asks for the responsible
     * physician, and offering coordinators there would be offering an answer
     * the question does not take.
     *
     * @return list<array<string, mixed>>
     */
    public function active(): array
    {
        return $this->where('is_active', 1)
            ->where('kind', self::DOCTOR)
            ->orderBy('name')
            ->findAll();
    }

    /**
     * Everybody this screen has registered, in service or not.
     *
     * The register shows the ones taken out of service too — greyed, with the
     * way back — because taking somebody out of service is not deleting them,
     * and the records they are on still name them.
     *
     * @return list<array<string, mixed>>
     */
    public function register(): array
    {
        return $this->orderBy('is_active', 'DESC')->orderBy('name')->findAll();
    }

    /** The user holding this ID, or null. The ID is the hospital's own. */
    public function byCode(string $code): ?array
    {
        $code = trim($code);

        return $code === '' ? null : $this->where('code', $code)->first();
    }
}
