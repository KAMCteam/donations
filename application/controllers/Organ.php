<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Organ extends CI_Controller {
    public function __construct() {
        parent::__construct();
        set_language('english');
        $this->load->model(['Lists_model']);
    }

    public function index() {
        $organs = $this->Lists_model->get_enum_values('patients', 'organs');
        $selcted_organ = $this->input->get('selcted_organ');

        if (!empty($selcted_organ) && in_array($selcted_organ, $organs)) {
            $this->session->set_userdata('organ', $selcted_organ);
            redirect('Patient');
        }

        $data = [
            'organs' => $organs,
        ];

        $this->load->view('organs.php', $data);
    }

    public function destroy_sess() {
        $this->session->sess_destroy();
        redirect('Organ');
    }
}