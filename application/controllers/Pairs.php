<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pairs extends CI_Controller {
    public function __construct() {
        parent::__construct();
        organ_chosen();
        set_language('english');
        $this->load->model(['Mrp_model', 'Patient_model', 'Pairs_model']);
    }

    public function index($recipient_id = null) {
        if (!empty($recipient_id)) {
            $this->loadPair($recipient_id);
            return;
        }

        $match_status = $this->input->get('match_status') ?? null;
        $blood_group = $this->input->get('blood_group') ?? null;
    
        if ($this->input->get('set_match_status')) {
            $temp = $this->input->get('set_match_status');
            if ($match_status === $temp) {
                $match_status = null;
            } else {
                $match_status = $temp;
            }
        }

        if ($this->input->get('set_blood_group')) {
            $temp = $this->input->get('set_blood_group');
            if ($blood_group === $temp) {
                $blood_group = null;
            } else {
                $blood_group = $temp;
            }
        }

        $allPairs = $this->Pairs_model->get_custom_pairs_info($blood_group, $match_status);

        $data = [
            'allPairs' => $allPairs,
            'match_status' => $match_status,
            'blood_group' => $blood_group,
        ];

        $this->load->view('pairs', $data);
    }

    public function loadPair($recipient_id) {
        $pair = $this->Pairs_model->get_custom_pairs_info(null, null, $recipient_id);

        $data = [
            'pair' => $pair,
        ];

        $this->load->view('pair', $data);
        return;
    }
}