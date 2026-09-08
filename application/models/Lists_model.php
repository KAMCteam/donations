<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Lists_model extends CI_Model {
    public function get_enum_values($table, $field) {
        $query = "show columns from $table where field = '$field'";
        $enumValues = $this->db->query($query)->row()->Type;
        $enumValues = substr($enumValues, 5, strlen($enumValues) - 6);
        $enumValues = str_replace("'", '', $enumValues);
        $enumValues = explode(',', $enumValues);
        
        return $enumValues;
    }

    public function get_options($table, $field, $lang) {
        $options = $this->get_enum_values($table, $field);

        $result = [];
        foreach ($options as $option) {
            $result[] = [
                'label' => lang($lang . $option),
                'value' => $option,
            ];
        }

        return $result;
    }

    public function get_mrps() {
        $this->load->model('Mrp_model');
        $mrps = $this->Mrp_model->get_mrps();

        $result = [
            [
                'label' => lang('form_choose_mrp'),
                'value' => '',
            ],
        ];
        foreach ($mrps as $mrp) {
            $result[] = [
                'label' => $mrp['name'],
                'value' => $mrp['id'],
            ];
        }

        return $result;
    }

    public function get_coordinators() {
        $this->load->model('Coordinators_model');
        $coordinators = $this->Coordinators_model->get_coordinators();

        $result = [
            [
                'label' => lang('form_choose_coordinator'),
                'value' => '',
            ],
        ];
        foreach ($coordinators as $coordinator) {
            $result[] = [
                'label' => $coordinator['coordinator_name'],
                'value' => $coordinator['coordinator_id'],
            ];
        }

        return $result;
    }

    public function get_programs() {
        $programs = $this->get_enum_values('pairs', 'programs');
        $donors = [];
        $recipients = [];

        foreach ($programs as $program) {
            if (substr($program, 0, 1) === 'D') {
                $donors[] = [
                    'program' => $program,
                    'patient_type' => 'donor',
                ];
            } elseif (substr($program, 0, 1) === 'R') {
                $recipients[] = [
                    'program' => $program,
                    'patient_type' => 'recipient',
                ];
            }
        }

        $result = [
            'recipients' => $recipients,
            'donors' => $donors,
        ];
        return $result;
    }

    public function get_add_form_content($patient_type, $organ, $program) {
        if ($patient_type != 'recipient' && $patient_type != 'donor') exit('no such patient type');
        if ($program != 'R_LRD' && $program != 'R_LURD' && $program != 'R_DD' && $program != 'R_PE' && $program != 'D_D') exit('no such program');
        // R_: Recipient Program.
        // D_: Donor Program.
        // LRD: Living Related Donor.
        // LURD: Living Unrelated Donor.
        // DD: Deceased Donor.
        // PE: Paired Exchange.
        // D: Donor.

        if ($program === 'R_LRD') {
            $data = [
                'recipient_title' => lang('form_new_recipient'),
                'donor_title' => lang('form_new_donor'),
                'pair_title' => lang('form_new_pair'),
                'recipient' => $this->get_recipient($organ),
                'donor' => $this->get_donor($organ),

                'pair' => [
                    [
                        'label' => lang('form_match_status'),
                        'type' => 'select',
                        'name' => 'match_status',
                        'id' => '',
                        'options' => $this->get_options('pairs', 'match_status', 'form_'),
                    ],
                    [
                        'label' => lang('form_relationship'),
                        'type' => 'text',
                        'name' => 'relationship',
                        'id' => '',
                    ],
                    [
                        'label' => lang('form_crossmatch_date'),
                        'type' => 'date',
                        'name' => 'crossmatch_date',
                        'id' => '',
                    ],
                ],
            ];

        } elseif ($program === 'R_LURD') {
            $data = [
                'recipient_title' => lang('form_new_recipient'),
                'donor_title' => lang('form_new_donor'),
                'committee_title' => 'change me',
                'pair_title' => lang('form_new_pair'),
                'recipient' => $this->get_recipient($organ),
                'donor' => $this->get_donor($organ),

                'committee' => [],

                'pair' => [
                    [
                        'label' => lang('form_match_status'),
                        'type' => 'select',
                        'name' => 'match_status',
                        'id' => '',
                        'options' => $this->get_options('pairs', 'match_status', 'form_'),
                    ],
                    [
                        'label' => lang('form_relationship'),
                        'type' => 'text',
                        'name' => 'relationship',
                        'id' => '',
                    ],
                    [
                        'label' => lang('form_crossmatch_date'),
                        'type' => 'date',
                        'name' => 'crossmatch_date',
                        'id' => '',
                    ],
                ],
            ];

        } elseif ($program === 'R_DD') {
            $data = [
                'recipient_title' => lang('form_new_recipient'),
                'recipient' => $this->get_recipient($organ),
            ];

        } elseif ($program === 'D_D') {
            $data = [
                'donor_title' => lang('form_new_donor'),
                'donor' => $this->get_donor($organ),
            ];

        } else {
            return null;
        }

        return $data;
    }

    public function get_recipient($organ) {
        $info = [
            [
                'label' => lang('form_recipient_mrn'),
                'type' => 'text',
                'name' => 'mrn',
                'id' => 'mrn',
            ],
            [
                'label' => lang('form_recipient_name'),
                'type' => 'text',
                'name' => 'name',
                'id' => 'name',
            ],
            [
                'label' => lang('form_recipient_city'),
                'type' => 'text',
                'name' => 'city',
                'id' => 'city',
            ],
            [
                'label' => lang('form_recipient_phone_number'),
                'type' => 'text',
                'name' => 'phone_number',
                'id' => 'phone_number',
            ],
            [
                'label' => lang('form_recipient_gender'),
                'type' => 'select',
                'name' => 'gender',
                'id' => 'gender',
                'options' => $this->get_options('patients', 'gender', 'form_'),
            ],
            [
                'label' => lang('form_recipient_age'),
                'type' => 'text',
                'name' => 'age',
                'id' => 'age',
            ],
            [
                'label' => lang('form_recipient_blood_group'),
                'type' => 'select',
                'name' => 'blood_group',
                'id' => '',
                'options' => $this->get_options('patients', 'blood_group', 'form_blood_group_'),
            ],
            [
                'label' => lang('form_recipient_mrp'),
                'type' => 'select',
                'name' => 'mrp',
                'id' => '',
                'options' => $this->get_mrps(),
            ],
            [
                'label' => lang('form_recipient_coordinator'),
                'type' => 'select',
                'name' => 'coordinator',
                'id' => '',
                'options' => $this->get_coordinators(),
            ],
            [
                'label' => lang('form_recipient_status'),
                'type' => 'select',
                'name' => 'status',
                'id' => '',
                'options' => $this->get_options('patients', 'status', 'form_'),
            ],
            [
                'label' => lang('form_dialysis'),
                'type' => 'date',
                'name' => 'dialysis',
                'id' => '',
            ],
            [
                'label' => lang('form_waiting_since'),
                'type' => 'date',
                'name' => 'entry_date',
                'id' => '',
            ],
            [
                'label' => lang('form_urgency'),
                'type' => 'select',
                'name' => 'urgency',
                'id' => '',
                'options' => [
                    ['label' => lang('form_urgent_0'), 'value' => '0'],
                    ['label' => lang('form_urgent_1'), 'value' => '1'],
                ],
            ],

            'labs' => $this->get_labs('recipient', $organ),
        ];

        return $info;
    }

    public function get_donor($organ) {
        $offset = '_2';
        $info = [
            [
                'label' => lang('form_donor_mrn'),
                'type' => 'text',
                'name' => 'mrn' . $offset,
                'id' => 'mrn_2',
            ],
            [
                'label' => lang('form_donor_name'),
                'type' => 'text',
                'name' => 'name' . $offset,
                'id' => 'name_2',
            ],
            [
                'label' => lang('form_donor_city'),
                'type' => 'text',
                'name' => 'city' . $offset,
                'id' => 'city_2',
            ],
            [
                'label' => lang('form_donor_phone_number'),
                'type' => 'text',
                'name' => 'phone_number' . $offset,
                'id' => 'phone_number_2',
            ],
            [
                'label' => lang('form_donor_gender'),
                'type' => 'select',
                'name' => 'gender' . $offset,
                'id' => 'gender_2',
                'options' => $this->get_options('patients', 'gender', 'form_'),
            ],
            [
                'label' => lang('form_donor_age'),
                'type' => 'text',
                'name' => 'age' . $offset,
                'id' => 'age_2',
            ],
            [
                'label' => lang('form_donor_blood_group'),
                'type' => 'select',
                'name' => 'blood_group' . $offset,
                'id' => '',
                'options' => $this->get_options('patients', 'blood_group', 'form_blood_group_'),
            ],
            [
                'label' => lang('form_donor_mrp'),
                'type' => 'select',
                'name' => 'mrp' . $offset,
                'id' => '',
                'options' => $this->get_mrps(),
            ],
            [
                'label' => lang('form_donor_coordinator'),
                'type' => 'select',
                'name' => 'coordinator' . $offset,
                'id' => '',
                'options' => $this->get_coordinators(),
            ],
            [
                'label' => lang('form_donor_status'),
                'type' => 'select',
                'name' => 'status' . $offset,
                'id' => '',
                'options' => $this->get_options('patients', 'status', 'form_'),
            ],
            
            'labs' => $this->get_labs('donor', $organ, $offset),
        ];

        return $info;
    }

    public function get_labs($patient_type, $organ, $offset = null) {
        $this->load->model('Labs_model');
        $labs = $this->Labs_model->get_custom_labs($patient_type, $organ);
        if (empty($offset)) $offset = '';

        $result_labs = [];
        $temp_labs = [];
        $temp_name = null;
        foreach ($labs as $lab) {
            if ($temp_name != null && $lab['parent_name'] != $temp_name) {
                $result_labs[] = [
                    'parent_name' => $temp_name,
                    'labs' => $temp_labs,
                ];

                $temp_labs = [];
            }

            $options = explode('/', $lab['result_shape']);

            $lab_options = [];
            foreach ($options as $option) {
                $lab_options[] = [
                    'label' => lang('form_lab_option_' . $option), 'value' => $option,
                ];
            }

            $temp_labs[] = [
                'label' => $lab['lab_name'],
                'type' => 'select',
                'name' => $lab['lab_id'] . $offset,
                'options' => $lab_options,
            ];

            $temp_name = $lab['parent_name'];
        }

        if ($temp_name != null) {
            $result_labs[] = [
                'parent_name' => $temp_name,
                'labs'        => $temp_labs,
            ];
        }


        return $result_labs;
    }
}