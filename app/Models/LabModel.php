<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The lab catalogue: which tests a workup is made of.
 */
class LabModel extends Model
{
    protected $table         = 'labs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'name', 'lab_parent_id', 'organ_code', 'person_type', 'result_type',
        'sort_order', 'is_active',
    ];

    /**
     * The workup for one side of one programme, in the order it is listed.
     *
     * One side only: a test both sheets ask for is a row on each, so there is
     * nothing to union in. This is what a new record's lab cards are built
     * from.
     *
     * @return list<array<string, mixed>>
     */
    public function workupFor(string $organCode, string $personType): array
    {
        return $this->db->table('labs l')
            ->select('l.*, lp.name AS parent_name')
            ->join('lab_parents lp', 'lp.id = l.lab_parent_id', 'left')
            ->where('l.organ_code', $organCode)
            ->where('l.person_type', $personType)
            ->where('l.is_active', 1)
            ->orderBy('lp.sort_order')
            ->orderBy('l.sort_order')
            ->get()
            ->getResultArray();
    }
}
