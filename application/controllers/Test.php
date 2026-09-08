<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Test extends CI_Controller {
    public function index() {
        $this->load->model(['Services_model', 'Labs_model']);
        $test = $this->Services_model->get_patient_info('1180765');
        $test = $this->Labs_model->get_results_by_mrn('1');
        // var_dump($test);
        // exit;

        $data = [
            'test' => $test,
        ];

        // $this->output->enable_profiler(TRUE);
        $this->load->view('test', $data);
    }

    public function test() {
        $this->output->enable_profiler(TRUE);
        $name = $this->input->post('name');
        // redirect('Test');
    }
}