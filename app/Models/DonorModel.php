<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The donor register.
 */
class DonorModel extends Model
{
    protected $table         = 'donors';
    protected $primaryKey    = 'mrn';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'mrn', 'name', 'organ_code', 'blood_group', 'gender', 'age', 'city', 'phone',
        'donation_type', 'relationship', 'status', 'mrp_id',
        'coordinator_id', 'registered_on', 'notes',
    ];

    /**
     * The donors screen: every donor with their workup progress and whether an
     * open pair already holds them. `$unmatchedOnly` is what the screen itself
     * renders.
     *
     * @return list<array<string, mixed>>
     */
    public function register(?string $organCode = null, bool $unmatchedOnly = false): array
    {
        $builder = $this->db->table('donors d')
            ->select('d.*, op.label AS program_label, m.name AS mrp_name, c.name AS coordinator_name')
            ->select('(SELECT COUNT(*) FROM lab_results lr WHERE lr.person_mrn = d.mrn AND lr.person_type = \'donor\') AS labs_total', false)
            ->select('(SELECT COUNT(*) FROM lab_results lr WHERE lr.person_mrn = d.mrn AND lr.person_type = \'donor\' AND lr.status = \'completed\') AS labs_completed', false)
            ->select('EXISTS (SELECT 1 FROM pairs p WHERE p.donor_mrn = d.mrn AND p.status <> \'closed\') AS is_matched', false)
            ->join('organ_programs op', 'op.code = d.organ_code', 'left')
            ->join('mrp m', 'm.id = d.mrp_id', 'left')
            ->join('coordinators c', 'c.id = d.coordinator_id', 'left');

        if ($organCode !== null && $organCode !== '') {
            $builder->where('d.organ_code', $organCode);
        }

        if ($unmatchedOnly) {
            $builder->where('NOT EXISTS (SELECT 1 FROM pairs p WHERE p.donor_mrn = d.mrn AND p.status <> \'closed\')', null, false);
        }

        return $builder->orderBy('d.mrn')->get()->getResultArray();
    }

    /** True when an open pair already holds this donor. */
    public function isMatched(int|string $mrn): bool
    {
        return $this->db->table('pairs')
            ->where('donor_mrn', $mrn)
            ->where('status !=', 'closed')
            ->countAllResults() > 0;
    }
}
