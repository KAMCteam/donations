<?php

namespace App\Controllers;

use App\Models\LabsModel;
use App\Models\MrpModel;
use App\Models\PairsModel;
use App\Models\PatientModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * 
 * الكود مسؤول عن وظيفتين:
 * 
 * 1) إذا لم يتم تمرير MRN في الرابط → يعرض قائمة المرضى مع فلاتر
 * 2) إذا تم تمرير MRN → يعرض نموذج تحديث بيانات المريض
 * 
 * 
 * Update-patient screen. Migrated from the CodeIgniter 3 UpdatePatient controller.
 *
 * index() doubles as the patient picker: with no MRN in the URL it lists
 * patients, with one it renders that patient's form.
 */
class UpdatePatient extends BaseController
{
    public function index(int|string|null $patientMrn = null): string
    {
        $patients    = model(PatientModel::class);
        $patientInfo = $patients->get_patient_info($patientMrn);

        if (empty($patientInfo)) {
            return $this->choosePatient();
        }

        $pairs = model(PairsModel::class);

        $hasRecipient = $pairs->has_recipient($patientMrn);
        $hasDonor     = $pairs->has_donor($patientMrn);

        $myDonor     = [];
        $myRecipient = [];

        if (! empty($hasDonor)) {
            $myDonor = [$patients->get_patient_info($hasDonor['donor_mrn'])];
        }

        if (! empty($hasRecipient)) {
            $myRecipient = [$patients->get_patient_info($hasRecipient['recipient_mrn'])];
        }

        // The view reads the labs from $patientInfo[0]['labs'].
        $patientInfo[] = [
            'labs' => model(LabsModel::class)->get_results_by_mrn($patientMrn),
        ];

        return view('update_patient', [
            'patientInfo'         => $patientInfo,
            'mrps'                => model(MrpModel::class)->get_mrps(),
            'hasDonor'            => $hasDonor,
            'myDonor'             => $myDonor,
            'unmatchedRecipients' => $pairs->get_unmatched_recipients(),
            'unmatchedDonors'     => $pairs->get_unmatched_donors(),
            'hasRecipient'        => $hasRecipient,
            'myRecipient'         => $myRecipient,
            'confirmation'        => $this->session->getFlashdata('confirmation'),
            'error'               => $this->session->getFlashdata('error'),
        ]);
    }

    /**
     * The filtered patient list shown when the URL carries no MRN.
     */
    private function choosePatient(): string
    {
        $mrn         = $this->request->getGet('mrn');
        $type        = $this->request->getGet('type');
        $status      = $this->request->getGet('status');
        $blood_group = $this->request->getGet('blood_group');
        $mrp         = $this->request->getGet('mrp');

        // Clicking an already-active filter button clears it.
        if ($this->request->getGet('set_type')) {
            $temp = $this->request->getGet('set_type');
            $type = ($type === $temp) ? null : $temp;
        }

        if ($this->request->getGet('set_status')) {
            $temp   = $this->request->getGet('set_status');
            $status = ($status === $temp) ? null : $temp;
        }

        if ($this->request->getGet('set_blood_group')) {
            $temp        = $this->request->getGet('set_blood_group');
            $blood_group = ($blood_group === $temp) ? null : $temp;
        }

        return view('choose_patient', [
            'error'       => $this->session->getFlashdata('error'),
            'allPatients' => model(PatientModel::class)->get_some_patients($mrn, $type, $status, $blood_group, $mrp),
            'mrn'         => $mrn,
            'type'        => $type,
            'status'      => $status,
            'blood_group' => $blood_group,
            'mrp'         => $mrp,
        ]);
    }

    public function update(): RedirectResponse
    {
        $mrn     = $this->request->getPost('mrn');
        $pair    = [];
        $patient = [
            'name'         => $this->request->getPost('name'),
            'city'         => $this->request->getPost('city'),
            'phone_number' => $this->request->getPost('phone_number'),
            'gender'       => $this->request->getPost('gender'),
            'age'          => $this->request->getPost('age'),
            'blood_group'  => $this->request->getPost('blood_group'),
            'mrp_id'       => $this->request->getPost('mrp'),
            'type'         => $this->request->getPost('patient_type'),
            'status'       => $this->request->getPost('status'),
            'note'         => $this->request->getPost('note'),
            'dialysis'     => $this->request->getPost('dialysis'),
            'entry_date'   => $this->request->getPost('entry_date'),
        ];

        $patients = model(PatientModel::class);

        if (empty($mrn) || empty($patient['type'])) {
            $this->session->setFlashdata('error', lang('Form.ctrl_error_missing'));

            return redirect()->to(site_url('UpdatePatient/' . $mrn));
        }

        if (! $patients->patient_exists($mrn)) {
            $this->session->setFlashdata('error', lang('Form.ctrl_error_missing_db'));

            return redirect()->to(site_url('UpdatePatient/' . $mrn));
        }

        if (empty($patient['mrp_id'])) {
            $patient['mrp_id'] = null;
        }

        $labs = model(LabsModel::class);

        foreach ($labs->get_lab_ids() as $lab_id) {
            $lab_result = $this->request->getPost($lab_id);

            if (empty($lab_result)) {
                continue;
            }

            if ($labs->exists($lab_id, $mrn)) {
                $labs->update_lab_result($lab_id, $mrn, $lab_result);

                continue;
            }

            $labs->insert_lab_result($lab_id, $mrn, $lab_result);
        }

        if (! empty($this->request->getPost('recipient_pair'))) {
            $pair = [
                'recipient_mrn' => $this->request->getPost('recipient_pair'),
                'donor_mrn'     => $mrn,
                'match_status'  => $this->request->getPost('match_status_recipient'),
                'relationship'  => $this->request->getPost('relationship_recipient'),
            ];
        } elseif ($this->request->getPost('has_donor') === 'true') {
            $pair = [
                'recipient_mrn' => $mrn,
                'donor_mrn'     => $this->request->getPost('donor_pair'),
                'match_status'  => $this->request->getPost('match_status_donor'),
                'relationship'  => $this->request->getPost('relationship_donor'),
            ];
        }

        $patients->update_patient($patient, $mrn);

        if ($pair !== []) {
            $pairs   = model(PairsModel::class);
            $pair_id = $pairs->pair_exists($pair['recipient_mrn'], $pair['donor_mrn']);

            if (empty($pair_id)) {
                $pairs->insert_pair($pair);
            } else {
                $pairs->update_pair($pair_id, $pair);
            }
        }

        $this->session->setFlashdata('confirmation', lang('Form.ctrl_confirmation_update'));

        return redirect()->to(site_url('UpdatePatient/' . $mrn));
    }
}
