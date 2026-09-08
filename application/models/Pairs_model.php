<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pairs_model extends CI_Model {
    public function insert_pair($pair) {
        return $this->db->insert('pairs', $pair);
    }

    public function get_unmatched_recipients() {
        $scoreCalc = '
            (
                (0.1 * TIMESTAMPDIFF(MONTH, entry_date, CURDATE())) +
                (0.1 * TIMESTAMPDIFF(MONTH, dialysis, CURDATE()))
            ) AS score
        ';

        return $this->db
            ->select("patients.*, $scoreCalc", false)
            ->from('patients')
            ->where('type', 'recipient')
            ->where("mrn NOT IN (SELECT recipient_mrn FROM pairs WHERE match_status NOT IN ('closed'))", null, false)
            ->order_by('urgency', 'DESC')
            ->order_by('score', 'DESC')
            ->get()
            ->result_array();
    }

    public function get_some_unmatched_recipients($blood_group) {
        if (empty($blood_group)) {
            return $this->get_unmatched_recipients();
        }
        
        $scoreCalc = '
            (
                (0.1 * TIMESTAMPDIFF(MONTH, entry_date, CURDATE())) +
                (0.1 * TIMESTAMPDIFF(MONTH, dialysis, CURDATE()))
            ) AS score
        ';

        return $this->db
            ->select("patients.*, $scoreCalc", false)
            ->from('patients')
            ->where('type', 'recipient')
            ->where("mrn NOT IN (SELECT recipient_mrn FROM pairs WHERE match_status NOT IN ('closed'))", null, false)
            ->where('blood_group', $blood_group)
            ->order_by('urgency', 'DESC')
            ->order_by('score', 'DESC')
            ->get()
            ->result_array();
    }

    public function get_unmatched_donors() {
        return $this->db->select('*')
            ->from('patients')
            ->where('type', 'donor')
            ->where('mrn NOT IN (SELECT donor_mrn FROM pairs)', null, false)
            ->get()->result_array();
    }

    public function has_donor($patient_mrn) {
        return $this->db->select('donor_mrn, match_status, relationship, matched_on, surgery_on')
            ->from('pairs')
            ->where('recipient_mrn', $patient_mrn)
            ->order_by('pair_id', 'desc')
            ->get()->row_array();
    }

    public function has_recipient($patient_mrn) {
        return $this->db->select('recipient_mrn, match_status, relationship, matched_on, surgery_on')
            ->from('pairs')
            ->where('donor_mrn', $patient_mrn)
            ->order_by('pair_id', 'desc')
            ->get()->row_array();
    }

    public function pair_exists($recipient_mrn, $donor_mrn) {
        return $this->db
            ->from('pairs')
            ->where('recipient_mrn', $recipient_mrn)
            ->where('donor_mrn', $donor_mrn)
            ->where("match_status not in ('closed')")
            ->get()->row('pair_id');
    }

    public function update_pair($pair_id, $pair) {
        return $this->db->where('pair_id', $pair_id)->update('pairs', $pair);
    }

    public function get_all_pairs() {
        return $this->db->select('*')
            ->from('pairs')
            ->order_by('match_status')
            ->get()->result_array();
    }

    public function get_all_pairs_info() {
        $this->load->model('Patient_model');
        $pairs = $this->get_all_pairs();
        $myPairs = [];
        foreach ($pairs as $pair) {
            $myPairs[] = [
                'relationship' => $pair['relationship'],
                'match_status' => $pair['match_status'],
                'pair' => [
                    $this->Patient_model->get_patient_info_modified($pair['recipient_mrn']),
                    $this->Patient_model->get_patient_info_modified($pair['donor_mrn']),
                ],
            ];
        }

        return $myPairs;
    }

    public function get_custom_pairs($blood_group, $match_status, $recipient_mrn = null) {
        $this->db->select('p.*');
        $this->db->from('pairs as p');

        if (!empty($blood_group)) {
            $this->db->join('patients as pa', 'p.recipient_mrn = pa.mrn');
            $this->db->where('blood_group', $blood_group);
        }

        if (!empty($match_status)) {
            $this->db->where('match_status', $match_status);
        }

        if (!empty($recipient_mrn)) {
            $this->db->where('p.recipient_mrn', $recipient_mrn);
        }

        $this->db->order_by('match_status');
        return $this->db->get()->result_array();
    }

    public function get_custom_pairs_info($blood_group, $match_status, $recipient_mrn = null) {
        $this->load->model('Patient_model');
        $pairs = $this->get_custom_pairs($blood_group, $match_status, $recipient_mrn);
        $myPairs = [];
        foreach ($pairs as $pair) {
            $myPairs[] = [
                'relationship' => $pair['relationship'],
                'match_status' => $pair['match_status'],
                'matched_on'   => $pair['matched_on'],
                'surgery_on'   => $pair['surgery_on'],
                'pair' => [
                    $this->Patient_model->get_patient_info_modified($pair['recipient_mrn']),
                    $this->Patient_model->get_patient_info_modified($pair['donor_mrn']),
                ],
            ];
        }

        return $myPairs;
    }

    public function get_relation($recipient_mrn, $donor_mrn) {
        return;
    }
}