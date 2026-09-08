<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Migrated from the CodeIgniter 3 Patient_model.
 */
class PatientModel extends Model
{
    protected $table         = 'patients';
    protected $primaryKey    = 'mrn';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'mrn', 'name', 'city', 'phone_number', 'gender', 'age', 'blood_group',
        'mrp_id', 'type', 'status', 'note', 'dialysis', 'entry_date', 'urgency',
    ];

    /**
     * @param array<string, mixed> $patient
     */
    public function insert_patient(array $patient): bool
    {
        return $this->db->table('patients')->insert($patient);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_patient_info(int|string|null $patient_mrn): ?array
    {
        return $this->db->table('patients')
            ->select('*')
            ->where('mrn', $patient_mrn)
            ->get()
            ->getRowArray();
    }

    /**
     * The patient row plus their MRP name, with the lab results appended as an
     * extra numerically-keyed element (the shape the views expect).
     *
     * @return array<array-key, mixed>
     */
    public function get_patient_info_modified(int|string|null $patient_mrn): array
    {
        $query = $this->db->table('patients as p')
            ->select('p.*, m.name as mrp_name, m.mrp_id')
            ->join('mrp as m', 'p.mrp_id = m.id', 'left')
            ->where('p.mrn', $patient_mrn)
            ->get()
            ->getRowArray() ?? [];

        $query[] = [
            'labs' => model(LabsModel::class)->get_results_by_mrn($patient_mrn),
        ];

        return $query;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_all_patients(): array
    {
        return $this->db->table('patients as p')
            ->select('p.*, m.name as mrp_name, m.mrp_id')
            ->join('mrp as m', 'p.mrp_id = m.id', 'left')
            ->get()
            ->getResultArray();
    }

    /**
     * Patient list narrowed by whichever filters the request supplied.
     *
     * @return list<array<string, mixed>>
     */
    public function get_some_patients(
        int|string|null $mrn = null,
        ?string $type = null,
        ?string $status = null,
        ?string $blood_group = null,
        int|string|null $mrp = null,
    ): array {
        $builder = $this->db->table('patients as p')
            ->select('p.*, m.name as mrp_name, m.mrp_id')
            ->join('mrp as m', 'p.mrp_id = m.id', 'left');

        if (! empty($mrn)) {
            $builder->like('mrn', $mrn);
        }

        if (! empty($type)) {
            $builder->where('type', $type);
        }

        if (! empty($status)) {
            $builder->where('status', $status);
        }

        if (! empty($blood_group)) {
            $builder->where('blood_group', $blood_group);
        }

        if (! empty($mrp)) {
            $builder->where('mrp', $mrp);
        }

        return $builder->get()->getResultArray();
    }

    public function patient_exists(int|string|null $patient_mrn): bool
    {
        return $this->db->table('patients')
            ->where('mrn', $patient_mrn)
            ->countAllResults() > 0;
    }

    /**
     * @param array<string, mixed> $patient
     */
    public function update_patient(array $patient, int|string $patient_mrn): bool
    {
        return $this->db->table('patients')
            ->where('mrn', $patient_mrn)
            ->update($patient);
    }
}
