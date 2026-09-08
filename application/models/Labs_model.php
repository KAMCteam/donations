<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Labs_model extends CI_Model {
    public function insert_lab($lab) {
        return $this->db->insert('labs', $lab);
    }

    public function get_results_by_mrn($mrn) {
        return $this->db->select('l.lab_id as actual_id, l.*, r.*')
            ->from('labs as l')
            ->join('lab_results as r', 'r.lab_id = l.lab_id AND r.patient_id = ' . $this->db->escape($mrn), 'left')
            ->get()
            ->result_array();
    }

    public function get_lab_ids() {
        $query = $this->db->select('lab_id')
            ->from('labs')
            ->get()->result_array();
        
        return array_column($query, 'lab_id');
    }

    public function get_labs() {
        return $this->db->select('*')
            ->from('labs')
            ->get()->result_array();
    }

    public function get_custom_labs($patient_type, $organ) {
        return $this->db->select('l.*, p.parent_name')
            ->from('labs as l')
            ->join('lab_parents as p', 'p.parent_id = l.lab_parent_id', 'left')
            ->where("patient_type in('$patient_type', 'both')")
            ->where('organ_type', $organ)
            ->order_by('p.parent_id')
            ->get()->result_array();
    }

    public function insert_lab_result($lab_id, $mrn, $lab_result) {
        return $this->db->insert('lab_results', [
            'lab_id' => $lab_id,
            'patient_id' => $mrn,
            'result' => $lab_result,
        ]);
    }

    public function update_lab_result($lab_id, $mrn, $lab_result) {
        return $this->db->where('lab_id', $lab_id)
            ->where('patient_id', $mrn)
            ->update('lab_results', [
                'result' => $lab_result,
            ]);
    }

    public function exists($lab_id, $mrn) {
        return (bool) $this->db->where('lab_id', $lab_id)
            ->where('patient_id', $mrn)
            ->count_all_results('lab_results');
    }
}