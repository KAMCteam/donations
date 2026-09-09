<?php

namespace App\Controllers;

use App\Models\LabsModel;
use App\Models\ListsModel;
use App\Models\PairsModel;
use App\Models\PatientModel;
use App\Models\ServicesModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

/**
 * Add-patient screen. Migrated from the CodeIgniter 3 Patient controller.
 *
 * CI3 read input through $this->input->post()/get() and ended the request from
 * inside helper methods; CI4 reads $this->request and every redirect has to be
 * returned up to the routing layer, which is the bulk of the change here.
 * 
 * الي يعرض البيانات المربوطة بالعضو
 */
class Patient extends BaseController
{
    public function index(): string
    {
        $lists = model(ListsModel::class);

        $organ        = $this->session->get('organ'); // the organ filter guarantees this is set
        $program      = $this->request->getGet('program');
        $patient_type = $this->request->getGet('patient_type');

        $form = [];

        if (! empty($program) && ! empty($patient_type)) {
            $form = $lists->get_add_form_content($patient_type, $organ, $program) ?? [];
        }

        return view('add_form', [
            'form'         => $form,
            'program'      => $program,
            'programs'     => $lists->get_programs(),
            'confirmation' => $this->session->getFlashdata('confirmation'),
            'error'        => $this->session->getFlashdata('error'),
        ]);
    }

    /**
     * Collects the submitted recipient and donor.
     *
     * NOTE: this was still unfinished in the CodeIgniter 3 project - it dumped
     * the two arrays and stopped instead of writing anything. The behaviour is
     * preserved; see add_deprecated() below for the version that did persist.
     */
    public function add(): ResponseInterface
    {
        $organ = $this->session->get('organ');

        $recipient = $this->patientPostData('recipient', $organ);
        $donor     = $this->patientPostData('donor', $organ);

        // TODO: persist $recipient and $donor instead of dumping them.
        return $this->response->setContentType('text/plain')->setBody(
            print_r($recipient, true) . "\n---------\n" . print_r($donor, true)
        );
    }

    /**
     * Reads one side of the add form out of the POST body.
     *
     * @return array{info: list<array<string, mixed>>, labs: list<array<string, mixed>>}
     */
    public function patientPostData(string $patient_type, ?string $organ): array
    {
        $lists = model(ListsModel::class);

        $list = match ($patient_type) {
            'recipient' => $lists->get_recipient($organ),
            'donor'     => $lists->get_donor($organ),
            default     => throw new InvalidArgumentException('incorrect patient type'),
        };

        $patient_info = [];

        foreach (array_column($list, 'name') as $name) {
            if ($patient_type === 'recipient') {
                $patient_info[] = [
                    $name => $this->request->getPost($name),
                ];
            } else {
                // This assumes that the $offset in ListsModel::get_donor() is _2.
                $patient_info[] = [
                    substr($name, 0, -2) => $this->request->getPost($name),
                ];
            }
        }

        $patient_labs = [];

        foreach ($list['labs'] as $labs) {
            foreach ($labs['labs'] as $lab) {
                $lab_id = ($patient_type === 'recipient')
                    ? $lab['name']
                    : substr($lab['name'], 0, -2); // again, the donor "_2" offset

                $patient_labs[] = [
                    'lab_id'      => $lab_id,
                    'result'      => $this->request->getPost($lab['name']),
                    'lab_comment' => $this->request->getPost($lab['name'] . '_comment'),
                ];
            }
        }

        return [
            'info' => $patient_info,
            'labs' => $patient_labs,
        ];
    }

    /**
     * The pre-programs version of add(), kept from the CodeIgniter 3 project.
     *
     * @deprecated Superseded by add(); no route points at it.
     */
    public function add_deprecated(): RedirectResponse
    {
        $pair    = [];
        $patient = [
            'mrn'          => $this->request->getPost('mrn'),
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
            'urgency'      => $this->request->getPost('urgency'),
        ];

        if (($failed = $this->validation($patient)) !== null) {
            return $failed;
        }

        $patients = model(PatientModel::class);

        if (! empty($this->request->getPost('recipient_pair'))) {
            $d = $this->request->getPost('recipient_committee_date');

            if ($d === '') {
                $d = null;
            }

            $patient_2 = [
                'mrn'          => $this->request->getPost('mrn_2'),
                'name'         => $this->request->getPost('name_2'),
                'city'         => $this->request->getPost('city_2'),
                'phone_number' => $this->request->getPost('phone_number_2'),
                'gender'       => $this->request->getPost('gender_2'),
                'age'          => $this->request->getPost('age_2'),
                'blood_group'  => $this->request->getPost('blood_group_2'),
                'mrp_id'       => $this->request->getPost('mrp_2'),
                'type'         => 'recipient',
                'status'       => $this->request->getPost('status_2'),
                'note'         => $this->request->getPost('note_2'),
                'dialysis'     => $this->request->getPost('dialysis_2'),
                'entry_date'   => $this->request->getPost('entry_date_2'),
                'urgency'      => $this->request->getPost('urgency_2'),
            ];

            if (($failed = $this->validation($patient_2)) !== null) {
                return $failed;
            }

            $patients->insert_patient($patient_2);
            $this->setLabData($patient_2['mrn'], '_2');

            $pair = [
                'recipient_mrn' => $this->request->getPost('recipient_pair'),
                'donor_mrn'     => $this->request->getPost('mrn'),
                'match_status'  => $this->request->getPost('match_status_recipient'),
                'relationship'  => $this->request->getPost('relationship_recipient'),
                'matched_on'    => $d,
                'surgery_on'    => $this->request->getPost('recipient_crossmatch_date'),
            ];
        } elseif ($this->request->getPost('has_donor') === 'true') {
            $d = $this->request->getPost('donor_committee_date');

            if ($d === '') {
                $d = null;
            }

            $patient_2 = [
                'mrn'          => $this->request->getPost('mrn_3'),
                'name'         => $this->request->getPost('name_3'),
                'city'         => $this->request->getPost('city_3'),
                'phone_number' => $this->request->getPost('phone_number_3'),
                'gender'       => $this->request->getPost('gender_3'),
                'age'          => $this->request->getPost('age_3'),
                'blood_group'  => $this->request->getPost('blood_group_3'),
                'mrp_id'       => $this->request->getPost('mrp_3'),
                'type'         => 'donor',
                'status'       => $this->request->getPost('status_3'),
                'note'         => $this->request->getPost('note_3'),
                'dialysis'     => $this->request->getPost('dialysis_3'),
                'entry_date'   => $this->request->getPost('entry_date_3'),
                'urgency'      => $this->request->getPost('urgency_3'),
            ];

            if (($failed = $this->validation($patient_2)) !== null) {
                return $failed;
            }

            $patients->insert_patient($patient_2);
            $this->setLabData($patient_2['mrn'], '_3');

            $pair = [
                'recipient_mrn' => $this->request->getPost('mrn'),
                'donor_mrn'     => $this->request->getPost('donor_pair'),
                'match_status'  => $this->request->getPost('match_status_donor'),
                'relationship'  => $this->request->getPost('relationship_donor'),
                'matched_on'    => $d,
                'surgery_on'    => $this->request->getPost('donor_crossmatch_date'),
            ];
        }

        $patients->insert_patient($patient);
        $this->setLabData($patient['mrn'], '');

        if (! empty($pair)) {
            model(PairsModel::class)->insert_pair($pair);
        }

        $this->session->setFlashdata('confirmation', lang('Form.ctrl_confirmation_add'));

        return redirect()->to(site_url('Patient'));
    }

    /**
     * Returns a redirect describing the first problem found, or null when the
     * patient is fine to insert. CI3 redirected from inside this method; CI4
     * needs the caller to return whatever comes back.
     *
     * @param array<string, mixed> $patient
     */
    public function validation(array $patient): ?RedirectResponse
    {
        if (empty($patient['mrn']) || empty($patient['type'])) {
            $this->session->setFlashdata('error', lang('Form.ctrl_error_missing'));

            return redirect()->to(site_url('Patient'));
        }

        if (model(PatientModel::class)->patient_exists($patient['mrn'])) {
            $this->session->setFlashdata('error', lang('Form.ctrl_error_duplicate_mrn'));

            return redirect()->to(site_url('Patient'));
        }

        if ((int) $patient['mrn'] >= 2147483647) {
            $this->session->setFlashdata('error', lang('Form.ctrl_error_too_large'));

            return redirect()->to(site_url('Patient'));
        }

        return null;
    }

    /**
     * Writes every submitted lab result for one patient, inserting or updating
     * as needed. $offset is the suffix the form gave that patient's fields.
     * 
     * حفظ نتائج التحاليل المخبرية لكل مريض، سواء كانت جديدة أو تحديث للنتائج السابقة. $offset هو اللاحقة التي أعطاها النموذج لحقول المريض.
     */
    public function setLabData(int|string $mrn, string $offset): void
    {
        $labs = model(LabsModel::class);

        foreach ($labs->get_lab_ids() as $lab_id) {
            $lab_result = $this->request->getPost($lab_id . $offset);

            if (empty($lab_result)) {
                continue;
            }

            if ($labs->exists($lab_id, $mrn)) {
                $labs->update_lab_result($lab_id, $mrn, $lab_result);

                continue;
            }

            $labs->insert_lab_result($lab_id, $mrn, $lab_result);
        }
    }

    /**
     * Demographics lookup used by the MRN fields on the add form.
     * 
     * يبحث عن بيانات المريض من خلال رقم الملف الطبي
     */
    public function getByMRN(): ResponseInterface
    {
        $mrn = $this->request->getGet('mrn');

        return $this->response->setJSON((new ServicesModel())->get_patient_info($mrn));
    }
}
