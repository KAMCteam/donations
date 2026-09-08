<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Dashboard counters, migrated from the CodeIgniter 3 Queries_model.
 *
 * CI3 chained ->from(...)->count_all_results(); in CI4 the table comes from
 * ->table(...) and the method is countAllResults().
 */
class QueriesModel extends Model
{
    protected $table      = 'patients';
    protected $primaryKey = 'mrn';
    protected $returnType = 'array';

    public function number_of_patients(): int
    {
        return $this->db->table('patients')->countAllResults();
    }

    public function number_of_pairs(): int
    {
        return $this->db->table('pairs')->countAllResults();
    }

    public function number_for_mrp(): int
    {
        return $this->db->table('patients')
            ->where('mrp_id', '1')
            ->countAllResults();
    }
}
