<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Patient_model extends CI_Model {
    public function insert_patient($patient) {
        return $this->db->insert('patients', $patient);
    }

    public function get_patient_info($patient_mrn) {
        return $this->db->select('*')
            ->from('patients')
            ->where('mrn', $patient_mrn)
            ->get()->row_array();
    }

    public function get_patient_info_modified($patient_mrn) {
        $this->load->model('Labs_model');
        $query = $this->db->select('p.*, m.name as mrp_name, m.mrp_id')
        ->from('patients as p')
        ->join('mrp as m', 'p.mrp_id = m.id', 'left')
        ->where('p.mrn', $patient_mrn)
        ->get()->row_array();

        $query[] = [
            'labs' => $this->Labs_model->get_results_by_mrn($patient_mrn),
        ];

        return $query;
    }

    public function get_all_patients() {
        return $this->db->select('p.*, m.name as mrp_name, m.mrp_id')
        ->from('patients as p')
        ->join('mrp as m', 'p.mrp_id = m.id', 'left')
        ->get()->result_array();
    }

    public function get_some_patients($mrn = null, $type = null, $status = null, $blood_group = null, $mrp = null) {
        $this->db->select('p.*, m.name as mrp_name, m.mrp_id');
        $this->db->from('patients as p');
        $this->db->join('mrp as m', 'p.mrp_id = m.id', 'left');

        if (!empty($mrn)) {
            $this->db->like('mrn', $mrn);
        }

        if (!empty($type)) {
            $this->db->where('type', $type);
        }

        if (!empty($status)) {
            $this->db->where('status', $status);
        }

        if (!empty($blood_group)) {
            $this->db->where('blood_group', $blood_group);
        }

        if (!empty($mrp)) {
            $this->db->where('mrp', $mrp);
        }

        return $this->db->get()->result_array();
    }

    public function patient_exists($patient_mrn) {
        return (bool) $this->db
            ->where('mrn', $patient_mrn)
            ->count_all_results('patients') > 0;
    }

    public function update_patient($patient, $patient_mrn) {
        return $this->db->where('mrn', $patient_mrn)->update('patients', $patient);
    }
}