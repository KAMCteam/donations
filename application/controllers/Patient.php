<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Patient extends CI_Controller {
    public function __construct() {
        parent::__construct();
        organ_chosen();
        set_language('english');
        $this->load->model(['Mrp_model', 'Patient_model', 'Pairs_model', 'Labs_model', 'Lists_model']);
    }

    public function index() {
        $organ = $this->session->userdata('organ');
        $program = $this->input->get('program');
        $patient_type = $this->input->get('patient_type');
        $programs = $this->Lists_model->get_programs();
        $form = [];
        if (!empty($program) && !empty($patient_type)) {
            $form = $this->Lists_model->get_add_form_content($patient_type, $organ, $program);
        }

        $data = [
            'form' => $form,
            'program' => $program,
            'programs' => $programs,
            'confirmation' => $this->session->flashdata('confirmation'),
            'error' => $this->session->flashdata('error'),
        ];

        
        $this->load->view('add_form', $data);
    }

    public function add() {
        $organ = $this->session->userdata('organ');
        $program = $this->input->post('program');

        $recipient = $this->patientPostData('recipient', $organ);
        $donor = $this->patientPostData('donor', $organ);
        var_dump($recipient, '---------', $donor);exit;
        
    }

    public function patientPostData($patient_type, $organ) {
        $list = [];
        if ($patient_type === 'recipient') {
            $list = $this->Lists_model->get_recipient($organ);
        } elseif ($patient_type === 'donor') {
            $list = $this->Lists_model->get_donor($organ);
        } else {
            exit('incorrect patient type');
        }

        $list_names = array_column($list, 'name');

        $patient_info = [];
        foreach ($list_names as $name) {
            if ($patient_type === 'recipient') {
                $patient_info[] = [
                    $name => $this->input->post($name),
                ];
            } else {// this assumes that the $offset in lists_model is _2
                $patient_info[] = [
                    substr($name, 0, strlen($name) - 2) => $this->input->post($name),
                ];
            }
        }
        
        $patient_labs = [];
        foreach ($list['labs'] as $labs) {
            foreach ($labs['labs'] as $lab) {
                if ($patient_type === 'recipient') {
                    $patient_labs[] = [
                        'lab_id' => $lab['name'],
                        'result' => $this->input->post($lab['name']),
                        'lab_comment' => $this->input->post($lab['name'] . '_comment'),
                    ];
                } else {// this assumes that the $offset in lists_model is _2
                    $patient_labs[] = [
                        'lab_id' => substr($lab['name'], 0, strlen($lab['name']) - 2),
                        'result' => $this->input->post($lab['name']),
                        'lab_comment' => $this->input->post($lab['name'] . '_comment'),
                    ];
                }
            }
        }

        $result = [
            'info' => $patient_info,
            'labs' => $patient_labs,
        ];
        return $result;
    }

    public function add_deprecated() {
        $pair = [];
        $patient = [
            'mrn' => $this->input->post('mrn'),
            'name' => $this->input->post('name'),
            'city' => $this->input->post('city'),
            'phone_number' => $this->input->post('phone_number'),
            'gender' => $this->input->post('gender'),
            'age' => $this->input->post('age'),
            'blood_group' => $this->input->post('blood_group'),
            'mrp_id' => $this->input->post('mrp'),
            'type' => $this->input->post('patient_type'),
            'status' => $this->input->post('status'),
            'note' => $this->input->post('note'),
            'dialysis' => $this->input->post('dialysis'),
            'entry_date' => $this->input->post('entry_date'),
            'urgency' => $this->input->post('urgency'),
        ];

        $patient_2 = [];

        $this->validation($patient);

        if (!empty($this->input->post('recipient_pair'))) {
            $d = $this->input->post('recipient_committee_date');

            if ($d === '') {
                $d = null;
            }

            $patient_2 = [
                'mrn' => $this->input->post('mrn_2'),
                'name' => $this->input->post('name_2'),
                'city' => $this->input->post('city_2'),
                'phone_number' => $this->input->post('phone_number_2'),
                'gender' => $this->input->post('gender_2'),
                'age' => $this->input->post('age_2'),
                'blood_group' => $this->input->post('blood_group_2'),
                'mrp_id' => $this->input->post('mrp_2'),
                'type' => 'recipient',
                'status' => $this->input->post('status_2'),
                'note' => $this->input->post('note_2'),
                'dialysis' => $this->input->post('dialysis_2'),
                'entry_date' => $this->input->post('entry_date_2'),
                'urgency' => $this->input->post('urgency_2'),
            ];

            $this->validation($patient_2);
            $this->Patient_model->insert_patient($patient_2);
            $this->setLabData($patient_2['mrn'], '_2');

            $pair = [
                'recipient_mrn' => $this->input->post('recipient_pair'),
                'donor_mrn' => $this->input->post('mrn'),
                'match_status' => $this->input->post('match_status_recipient'),
                'relationship' => $this->input->post('relationship_recipient') ?? null,
                'matched_on' => $d,
                'surgery_on' => $this->input->post('recipient_crossmatch_date') ?? null,
            ];
            
        } else {
            if ($this->input->post('has_donor') === 'true') {
                $d = $this->input->post('donor_committee_date');

                if ($d === '') {
                    $d = null;
                }

                $patient_2 = [
                    'mrn' => $this->input->post('mrn_3'),
                    'name' => $this->input->post('name_3'),
                    'city' => $this->input->post('city_3'),
                    'phone_number' => $this->input->post('phone_number_3'),
                    'gender' => $this->input->post('gender_3'),
                    'age' => $this->input->post('age_3'),
                    'blood_group' => $this->input->post('blood_group_3'),
                    'mrp_id' => $this->input->post('mrp_3'),
                    'type' => 'donor',
                    'status' => $this->input->post('status_3'),
                    'note' => $this->input->post('note_3'),
                    'dialysis' => $this->input->post('dialysis_3'),
                    'entry_date' => $this->input->post('entry_date_3'),
                    'urgency' => $this->input->post('urgency_3'),
                ];

                $this->validation($patient_2);
                $this->Patient_model->insert_patient($patient_2);
                $this->setLabData($patient_2['mrn'], '_3');

                $pair = [
                    'recipient_mrn' => $this->input->post('mrn'),
                    'donor_mrn' => $this->input->post('donor_pair'),
                    'match_status' => $this->input->post('match_status_donor'),
                    'relationship' => $this->input->post('relationship_donor') ?? null,
                    'matched_on' =>  $d,
                    'surgery_on' => $this->input->post('donor_crossmatch_date') ?? null,
                ];
            }
        }

        $this->Patient_model->insert_patient($patient);
        $this->setLabData($patient['mrn'], '');
        if (!empty($pair)) $this->Pairs_model->insert_pair($pair);
        $this->session->set_flashdata('confirmation', lang('ctrl_confirmation_add'));
        redirect('Patient');
    }

    public function validation($patient) {
        if (empty($patient['mrn']) || empty($patient['type'])) {
            $this->session->set_flashdata('error', lang('ctrl_error_missing'));
            redirect('Patient');
        }

        if ($this->Patient_model->patient_exists($patient['mrn'])) {
            $this->session->set_flashdata('error', lang('ctrl_error_duplicate_mrn'));
            redirect('Patient');
        }

        if ($patient['mrn'] >= 2147483647) {
            $this->session->set_flashdata('error', lang('ctrl_error_too_large'));
            redirect('Patient');
        }

        return;
    }

    public function setLabData($mrn, $offset) {
        foreach ($this->Labs_model->get_lab_ids() as $lab_id) {
            $lab_result = $this->input->post($lab_id . $offset);
            if (empty($lab_result)) {
                continue;
            }
            if ($this->Labs_model->exists($lab_id, $mrn)) {
                $this->Labs_model->update_lab_result($lab_id, $mrn, $lab_result);
                continue;
            }
            
            $this->Labs_model->insert_lab_result($lab_id, $mrn, $lab_result);
        }

        return;
    }

    public function getByMRN() {
        $this->load->model('Services_model');
        $mrn = $this->input->get('mrn');
        echo json_encode($this->Services_model->get_patient_info($mrn));
    }
}