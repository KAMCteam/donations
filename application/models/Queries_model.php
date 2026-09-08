<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Queries_model extends CI_Model {
    public function number_of_patients() {
        return $this->db->from('patients')->count_all_results();
    }

    public function number_of_pairs() {
        return $this->db->from('pairs')->count_all_results();
    }

    public function number_for_mrp() {
        return $this->db->from('patients')->where('mrp_id', '1')->count_all_results();
    }
}