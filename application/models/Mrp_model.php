<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Mrp_model extends CI_Model {
    public function insert_mrp($mrp) {
        return $this->db->insert('mrp', $mrp);
    }

    public function get_mrps() {
        return $this->db->select('*')
            ->from('mrp')
            ->get()->result_array();
    }
}