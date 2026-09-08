<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Migrated from the CodeIgniter 3 Coordinators_model.
 */
class CoordinatorsModel extends Model
{
    protected $table         = 'coordinators';
    protected $primaryKey    = 'coordinator_id';
    protected $returnType    = 'array';
    protected $allowedFields = ['coordinator_name'];

    /**
     * @return list<array<string, mixed>>
     */
    public function get_coordinators(): array
    {
        return $this->db->table('coordinators')
            ->select('*')
            ->get()
            ->getResultArray();
    }
}
