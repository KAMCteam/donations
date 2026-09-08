<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Migrated from the CodeIgniter 3 Mrp_model.
 */
class MrpModel extends Model
{
    protected $table         = 'mrp';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['mrp_id', 'name'];

    /**
     * @param array<string, mixed> $mrp
     */
    public function insert_mrp(array $mrp): bool
    {
        return $this->db->table('mrp')->insert($mrp);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_mrps(): array
    {
        return $this->db->table('mrp')
            ->select('*')
            ->get()
            ->getResultArray();
    }
}
