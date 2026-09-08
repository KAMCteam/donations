<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Migrated from the CodeIgniter 3 Labs_model.
 */
class LabsModel extends Model
{
    protected $table         = 'labs';
    protected $primaryKey    = 'lab_id';
    protected $returnType    = 'array';
    protected $allowedFields = ['lab_name', 'result_shape', 'lab_parent_id', 'patient_type', 'organ_type'];

    /**
     * @param array<string, mixed> $lab
     */
    public function insert_lab(array $lab): bool
    {
        return $this->db->table('labs')->insert($lab);
    }

    /**
     * Every lab, left-joined with this patient's result for it.
     *
     * @return list<array<string, mixed>>
     */
    public function get_results_by_mrn(int|string|null $mrn): array
    {
        return $this->db->table('labs as l')
            ->select('l.lab_id as actual_id, l.*, r.*')
            ->join(
                'lab_results as r',
                'r.lab_id = l.lab_id AND r.patient_id = ' . $this->db->escape($mrn),
                'left'
            )
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<string>
     */
    public function get_lab_ids(): array
    {
        $query = $this->db->table('labs')
            ->select('lab_id')
            ->get()
            ->getResultArray();

        return array_column($query, 'lab_id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_labs(): array
    {
        return $this->db->table('labs')
            ->select('*')
            ->get()
            ->getResultArray();
    }

    /**
     * Labs that apply to this patient type and organ, ordered by lab parent.
     *
     * @return list<array<string, mixed>>
     */
    public function get_custom_labs(string $patient_type, ?string $organ): array
    {
        return $this->db->table('labs as l')
            ->select('l.*, p.parent_name')
            ->join('lab_parents as p', 'p.parent_id = l.lab_parent_id', 'left')
            ->whereIn('patient_type', [$patient_type, 'both'])
            ->where('organ_type', $organ)
            ->orderBy('p.parent_id')
            ->get()
            ->getResultArray();
    }

    public function insert_lab_result(string $lab_id, int|string $mrn, string $lab_result): bool
    {
        return $this->db->table('lab_results')->insert([
            'lab_id'     => $lab_id,
            'patient_id' => $mrn,
            'result'     => $lab_result,
        ]);
    }

    public function update_lab_result(string $lab_id, int|string $mrn, string $lab_result): bool
    {
        return $this->db->table('lab_results')
            ->where('lab_id', $lab_id)
            ->where('patient_id', $mrn)
            ->update(['result' => $lab_result]);
    }

    public function exists(string $lab_id, int|string $mrn): bool
    {
        return $this->db->table('lab_results')
            ->where('lab_id', $lab_id)
            ->where('patient_id', $mrn)
            ->countAllResults() > 0;
    }
}
