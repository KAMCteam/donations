<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MRP extends CI_Controller {
    public function __construct() {
        parent::__construct();
        organ_chosen();
        set_language('english');
        $this->load->model(['Mrp_model', 'Labs_model']);
    }

    public function index() {      
        $data = [
            'confirmation' => $this->session->flashdata('confirmation'),
            'error' => $this->session->flashdata('error'),
        ];

        $this->load->view('mrp_form', $data);
    }

    public function addLab() {
        $lab_id = $this->input->post('lab_name');
        $lab_shape = $this->input->post('lab_shape');

        if (empty($lab_id) || empty($lab_shape)) exit('how');

        $lab = [
            'lab_name' => $lab_id,
            'result_shape' => $lab_shape,
        ];

        $this->Labs_model->insert_lab($lab);
        $this->session->set_flashdata('confirmation', lang('ctrl_confirmation_lab_add'));
        redirect('MRP');
    }

    public function addMRP() {
        $mrp_id = $this->input->post('id');
        $mrp_name = $this->input->post('name');

        if (empty($mrp_id) || empty($mrp_name)) exit('what');
        
        $mrp = [
            'mrp_id' => $mrp_id,
            'name' => $mrp_name
        ];

        $this->Mrp_model->insert_mrp($mrp);
        $this->session->set_flashdata('confirmation', lang('ctrl_confirmation_mrp_add'));
        redirect('MRP');
    }
}