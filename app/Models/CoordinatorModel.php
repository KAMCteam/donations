<?php

namespace App\Models;

use CodeIgniter\Model;

/** Transplant coordinators. */
class CoordinatorModel extends Model
{
    protected $table         = 'coordinators';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'is_active'];

    /** @return list<array<string, mixed>> */
    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('name')->findAll();
    }
}
