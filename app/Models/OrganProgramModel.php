<?php

namespace App\Models;

use CodeIgniter\Model;

/** The programmes the picker offers. */
class OrganProgramModel extends Model
{
    protected $table         = 'organ_programs';
    protected $primaryKey    = 'code';
    protected $returnType    = 'array';
    protected $allowedFields = ['code', 'label', 'description', 'icon', 'sort_order', 'is_active'];

    /** @return list<array<string, mixed>> */
    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('sort_order')->findAll();
    }
}
