<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class UpdatePatient extends CI_Controller {
    public function __construct() {
        parent::__construct();
        organ_chosen();
        set_language('english');
        $this->load->model(['Patient_model', 'Mrp_model', 'Pairs_model', 'Labs_model']);
    }

    public function index($patientMrn = null) {
        $patientInfo = $this->Patient_model->get_patient_info($patientMrn);
        if (empty($patientInfo)) {
            $mrn = $this->input->get('mrn') ?? null;
            $type = $this->input->get('type') ?? null;
            $status = $this->input->get('status') ?? null;
            $blood_group = $this->input->get('blood_group') ?? null;
            $mrp = $this->input->get('mrp') ?? null;
        
            if ($this->input->get('set_type')) {
                $temp = $this->input->get('set_type');
                if ($type === $temp) {
                    $type = null;
                } else {
                    $type = $temp;
                }
            }

            if ($this->input->get('set_status')) {
                $temp = $this->input->get('set_status');
                if ($status === $temp) {
                    $status = null;
                } else {
                    $status = $temp;
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

            $allPatients = $this->Patient_model->get_some_patients($mrn, $type, $status, $blood_group, $mrp);

            $data = [
                'error' => $this->session->flashdata('error'),
                'allPatients' => $allPatients,
                'mrn' => $mrn,
                'type' => $type,
                'status' => $status,
                'blood_group' => $blood_group,
                'mrp' => $mrp,
            ];

            $this->load->view('choose_patient', $data);

        } else {
            $mrps = $this->Mrp_model->get_mrps();
            $unmatchedRecipients = $this->Pairs_model->get_unmatched_recipients();
            $unmatchedDonors = $this->Pairs_model->get_unmatched_donors();
            $hasRecipient = $this->Pairs_model->has_recipient($patientMrn);
            $hasDonor = $this->Pairs_model->has_donor($patientMrn);
            $myRecipient = [];
            $myDonor = [];
            if (!empty($hasDonor)) {
                $myDonor = array($this->Patient_model->get_patient_info($hasDonor['donor_mrn']));
            }
            if (!empty($hasRecipient)) {
                $myRecipient = array($this->Patient_model->get_patient_info($hasRecipient['recipient_mrn']));
            }
            
            $patientInfo[] = [
                'labs' => $this->Labs_model->get_results_by_mrn($patientMrn),
            ];
            
            $data = [
                'patientInfo' => $patientInfo,
                'mrps' => $mrps,
                'hasDonor' => $hasDonor,
                'myDonor' => $myDonor,
                'unmatchedRecipients' => $unmatchedRecipients,
                'unmatchedDonors' => $unmatchedDonors,
                'hasRecipient' => $hasRecipient,
                'myRecipient' => $myRecipient,
                'confirmation' => $this->session->flashdata('confirmation'),
                'error' => $this->session->flashdata('error'),
            ];

            $this->load->view('update_patient', $data);
        }
    }

    public function update() {
        $mrn = $this->input->post('mrn');
        $pair = [];
        $patient = [
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
        ];

        if (empty($mrn) || empty($patient['type'])) {
            $this->session->set_flashdata('error', lang('ctrl_error_missing'));
            redirect('UpdatePatient/' . $mrn);
        }

        if (!$this->Patient_model->patient_exists($mrn)) {
            $this->session->set_flashdata('error', lang('ctrl_error_missing_db'));
            redirect('UpdatePatient/' . $mrn);
        }

        if (empty($patient['mrp_id'])) {
            $patient['mrp_id'] = null;
        }

        foreach ($this->Labs_model->get_lab_ids() as $lab_id) {
            $lab_result = $this->input->post($lab_id);
            if (empty($lab_result)) {
                continue;
            }
            if ($this->Labs_model->exists($lab_id, $mrn)) {
                $this->Labs_model->update_lab_result($lab_id, $mrn, $lab_result);
                continue;
            }
            
            $this->Labs_model->insert_lab_result($lab_id, $mrn, $lab_result);
        }

        if (!empty($this->input->post('recipient_pair'))) {
            $pair = [
                'recipient_mrn' => $this->input->post('recipient_pair'),
                'donor_mrn' => $mrn,
                'match_status' => $this->input->post('match_status_recipient'),
                'relationship' => $this->input->post('relationship_recipient'),
            ];
            
        } else {
            if ($this->input->post('has_donor') === 'true') {
                $pair = [
                    'recipient_mrn' => $mrn,
                    'donor_mrn' => $this->input->post('donor_pair'),
                    'match_status' => $this->input->post('match_status_donor'),
                    'relationship' => $this->input->post('relationship_donor'),
                ];
            }
        }

        $this->Patient_model->update_patient($patient, $mrn);

        $pair_id = $this->Pairs_model->pair_exists($pair['recipient_mrn'], $pair['donor_mrn']);
        if (empty($pair_id) && !empty($pair)) {
            $this->Pairs_model->insert_pair($pair);
        } else if (!empty($pair_id) && !empty($pair)) {
            $this->Pairs_model->update_pair($pair_id, $pair);
        }

        $this->session->set_flashdata('confirmation', lang('ctrl_confirmation_update'));
        redirect('UpdatePatient/' . $mrn);
    }
}