<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Coordinators_model extends CI_Model {
    public function get_coordinators() {
        return $this->db->select('*')
            ->from('coordinators')
            ->get()->result_array();
    }
}