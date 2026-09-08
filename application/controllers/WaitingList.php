<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class WaitingList extends CI_Controller {
    public function __construct() {
        parent::__construct();
        organ_chosen();
        set_language('english');
        $this->load->model(['Pairs_model']);
    }

    public function index() {
        $blood_group = $this->input->get('blood_group') ?? null;
    
        if ($this->input->get('set_blood_group')) {
            $temp = $this->input->get('set_blood_group');
            if ($blood_group === $temp) {
                $blood_group = null;
            } else {
                $blood_group = $temp;
            }
        }

        $unmatchedRecipients = $this->Pairs_model->get_some_unmatched_recipients($blood_group);
        
        $data = [
            'unmatchedRecipients' => $unmatchedRecipients,
            'blood_group'         => $blood_group,
        ];

        $this->load->view('waiting_list', $data);
    }
}