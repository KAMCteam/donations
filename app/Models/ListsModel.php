<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Builds the option lists and form definitions the add-patient screen renders.
 *
 * Migrated from the CodeIgniter 3 Lists_model. Language keys gained the
 * "Form." prefix CodeIgniter 4 needs to find app/Language/<locale>/Form.php.
 */
class ListsModel extends Model
{
    protected $table      = 'patients';
    protected $primaryKey = 'mrn';
    protected $returnType = 'array';

    /**
     * The values of a MySQL ENUM column.
     *
     * @return list<string>
     */
    public function get_enum_values(string $table, string $field): array
    {
        $sql = 'SHOW COLUMNS FROM ' . $this->db->protectIdentifiers($table, true) . ' WHERE Field = ?';
        $row = $this->db->query($sql, [$field])->getRow();

        if ($row === null) {
            return [];
        }

        // Strip the surrounding enum(...) and the quotes around each value.
        $enumValues = substr($row->Type, 5, strlen($row->Type) - 6);
        $enumValues = str_replace("'", '', $enumValues);

        return explode(',', $enumValues);
    }

    /**
     * The same ENUM values, shaped as <option> label/value pairs.
     *
     * @return list<array{label: string, value: string}>
     */
    public function get_options(string $table, string $field, string $lang): array
    {
        $result = [];

        foreach ($this->get_enum_values($table, $field) as $option) {
            $result[] = [
                'label' => lang('Form.' . $lang . $option),
                'value' => $option,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function get_mrps(): array
    {
        $result = [
            [
                'label' => lang('Form.form_choose_mrp'),
                'value' => '',
            ],
        ];

        foreach (model(MrpModel::class)->get_mrps() as $mrp) {
            $result[] = [
                'label' => $mrp['name'],
                'value' => $mrp['id'],
            ];
        }

        return $result;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function get_coordinators(): array
    {
        $result = [
            [
                'label' => lang('Form.form_choose_coordinator'),
                'value' => '',
            ],
        ];

        foreach (model(CoordinatorsModel::class)->get_coordinators() as $coordinator) {
            $result[] = [
                'label' => $coordinator['coordinator_name'],
                'value' => $coordinator['coordinator_id'],
            ];
        }

        return $result;
    }

    /**
     * The programs enum split into the recipient ones (R_*) and donor ones (D_*).
     *
     * @return array{recipients: list<array{program: string, patient_type: string}>, donors: list<array{program: string, patient_type: string}>}
     */
    public function get_programs(): array
    {
        $donors     = [];
        $recipients = [];

        foreach ($this->get_enum_values('pairs', 'programs') as $program) {
            if (str_starts_with($program, 'D')) {
                $donors[] = [
                    'program'      => $program,
                    'patient_type' => 'donor',
                ];
            } elseif (str_starts_with($program, 'R')) {
                $recipients[] = [
                    'program'      => $program,
                    'patient_type' => 'recipient',
                ];
            }
        }

        return [
            'recipients' => $recipients,
            'donors'     => $donors,
        ];
    }

    /**
     * The field definitions the add form renders for the chosen program.
     *
     * R_: Recipient Program.
     * D_: Donor Program.
     * LRD: Living Related Donor.
     * LURD: Living Unrelated Donor.
     * DD: Deceased Donor.
     * PE: Paired Exchange.
     * D: Donor.
     *
     * @return array<string, mixed>|null
     */
    public function get_add_form_content(string $patient_type, ?string $organ, string $program): ?array
    {
        if ($patient_type !== 'recipient' && $patient_type !== 'donor') {
            return null;
        }

        if (! in_array($program, ['R_LRD', 'R_LURD', 'R_DD', 'R_PE', 'D_D'], true)) {
            return null;
        }

        $pairFields = [
            [
                'label'   => lang('Form.form_match_status'),
                'type'    => 'select',
                'name'    => 'match_status',
                'id'      => '',
                'options' => $this->get_options('pairs', 'match_status', 'form_'),
            ],
            [
                'label' => lang('Form.form_relationship'),
                'type'  => 'text',
                'name'  => 'relationship',
                'id'    => '',
            ],
            [
                'label' => lang('Form.form_crossmatch_date'),
                'type'  => 'date',
                'name'  => 'crossmatch_date',
                'id'    => '',
            ],
        ];

        return match ($program) {
            'R_LRD' => [
                'recipient_title' => lang('Form.form_new_recipient'),
                'donor_title'     => lang('Form.form_new_donor'),
                'pair_title'      => lang('Form.form_new_pair'),
                'recipient'       => $this->get_recipient($organ),
                'donor'           => $this->get_donor($organ),
                'pair'            => $pairFields,
            ],

            'R_LURD' => [
                'recipient_title' => lang('Form.form_new_recipient'),
                'donor_title'     => lang('Form.form_new_donor'),
                'committee_title' => 'change me',
                'pair_title'      => lang('Form.form_new_pair'),
                'recipient'       => $this->get_recipient($organ),
                'donor'           => $this->get_donor($organ),
                'committee'       => [],
                'pair'            => $pairFields,
            ],

            'R_DD' => [
                'recipient_title' => lang('Form.form_new_recipient'),
                'recipient'       => $this->get_recipient($organ),
            ],

            'D_D' => [
                'donor_title' => lang('Form.form_new_donor'),
                'donor'       => $this->get_donor($organ),
            ],

            default => null,
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    public function get_recipient(?string $organ): array
    {
        return [
            [
                'label' => lang('Form.form_recipient_mrn'),
                'type'  => 'text',
                'name'  => 'mrn',
                'id'    => 'mrn',
            ],
            [
                'label' => lang('Form.form_recipient_name'),
                'type'  => 'text',
                'name'  => 'name',
                'id'    => 'name',
            ],
            [
                'label' => lang('Form.form_recipient_city'),
                'type'  => 'text',
                'name'  => 'city',
                'id'    => 'city',
            ],
            [
                'label' => lang('Form.form_recipient_phone_number'),
                'type'  => 'text',
                'name'  => 'phone_number',
                'id'    => 'phone_number',
            ],
            [
                'label'   => lang('Form.form_recipient_gender'),
                'type'    => 'select',
                'name'    => 'gender',
                'id'      => 'gender',
                'options' => $this->get_options('patients', 'gender', 'form_'),
            ],
            [
                'label' => lang('Form.form_recipient_age'),
                'type'  => 'text',
                'name'  => 'age',
                'id'    => 'age',
            ],
            [
                'label'   => lang('Form.form_recipient_blood_group'),
                'type'    => 'select',
                'name'    => 'blood_group',
                'id'      => '',
                'options' => $this->get_options('patients', 'blood_group', 'form_blood_group_'),
            ],
            [
                'label'   => lang('Form.form_recipient_mrp'),
                'type'    => 'select',
                'name'    => 'mrp',
                'id'      => '',
                'options' => $this->get_mrps(),
            ],
            [
                'label'   => lang('Form.form_recipient_coordinator'),
                'type'    => 'select',
                'name'    => 'coordinator',
                'id'      => '',
                'options' => $this->get_coordinators(),
            ],
            [
                'label'   => lang('Form.form_recipient_status'),
                'type'    => 'select',
                'name'    => 'status',
                'id'      => '',
                'options' => $this->get_options('patients', 'status', 'form_'),
            ],
            [
                'label' => lang('Form.form_dialysis'),
                'type'  => 'date',
                'name'  => 'dialysis',
                'id'    => '',
            ],
            [
                'label' => lang('Form.form_waiting_since'),
                'type'  => 'date',
                'name'  => 'entry_date',
                'id'    => '',
            ],
            [
                'label'   => lang('Form.form_urgency'),
                'type'    => 'select',
                'name'    => 'urgency',
                'id'      => '',
                'options' => [
                    ['label' => lang('Form.form_urgent_0'), 'value' => '0'],
                    ['label' => lang('Form.form_urgent_1'), 'value' => '1'],
                ],
            ],

            'labs' => $this->get_labs('recipient', $organ),
        ];
    }

    /**
     * The donor half of the form. Every field name carries the "_2" suffix that
     * the controller strips back off when reading the POST.
     *
     * @return array<array-key, mixed>
     */
    public function get_donor(?string $organ): array
    {
        $offset = '_2';

        return [
            [
                'label' => lang('Form.form_donor_mrn'),
                'type'  => 'text',
                'name'  => 'mrn' . $offset,
                'id'    => 'mrn_2',
            ],
            [
                'label' => lang('Form.form_donor_name'),
                'type'  => 'text',
                'name'  => 'name' . $offset,
                'id'    => 'name_2',
            ],
            [
                'label' => lang('Form.form_donor_city'),
                'type'  => 'text',
                'name'  => 'city' . $offset,
                'id'    => 'city_2',
            ],
            [
                'label' => lang('Form.form_donor_phone_number'),
                'type'  => 'text',
                'name'  => 'phone_number' . $offset,
                'id'    => 'phone_number_2',
            ],
            [
                'label'   => lang('Form.form_donor_gender'),
                'type'    => 'select',
                'name'    => 'gender' . $offset,
                'id'      => 'gender_2',
                'options' => $this->get_options('patients', 'gender', 'form_'),
            ],
            [
                'label' => lang('Form.form_donor_age'),
                'type'  => 'text',
                'name'  => 'age' . $offset,
                'id'    => 'age_2',
            ],
            [
                'label'   => lang('Form.form_donor_blood_group'),
                'type'    => 'select',
                'name'    => 'blood_group' . $offset,
                'id'      => '',
                'options' => $this->get_options('patients', 'blood_group', 'form_blood_group_'),
            ],
            [
                'label'   => lang('Form.form_donor_mrp'),
                'type'    => 'select',
                'name'    => 'mrp' . $offset,
                'id'      => '',
                'options' => $this->get_mrps(),
            ],
            [
                'label'   => lang('Form.form_donor_coordinator'),
                'type'    => 'select',
                'name'    => 'coordinator' . $offset,
                'id'      => '',
                'options' => $this->get_coordinators(),
            ],
            [
                'label'   => lang('Form.form_donor_status'),
                'type'    => 'select',
                'name'    => 'status' . $offset,
                'id'      => '',
                'options' => $this->get_options('patients', 'status', 'form_'),
            ],

            'labs' => $this->get_labs('donor', $organ, $offset),
        ];
    }

    /**
     * The labs for this patient type and organ, grouped under their lab parent.
     *
     * @return list<array{parent_name: string|null, labs: list<array<string, mixed>>}>
     */
    public function get_labs(string $patient_type, ?string $organ, ?string $offset = null): array
    {
        $labs   = model(LabsModel::class)->get_custom_labs($patient_type, $organ);
        $offset ??= '';

        $result_labs = [];
        $temp_labs   = [];
        $temp_name   = null;

        foreach ($labs as $lab) {
            if ($temp_name !== null && $lab['parent_name'] !== $temp_name) {
                $result_labs[] = [
                    'parent_name' => $temp_name,
                    'labs'        => $temp_labs,
                ];

                $temp_labs = [];
            }

            $lab_options = [];

            foreach (explode('/', (string) $lab['result_shape']) as $option) {
                $lab_options[] = [
                    'label' => lang('Form.form_lab_option_' . $option),
                    'value' => $option,
                ];
            }

            $temp_labs[] = [
                'label'   => $lab['lab_name'],
                'type'    => 'select',
                'name'    => $lab['lab_id'] . $offset,
                'options' => $lab_options,
            ];

            $temp_name = $lab['parent_name'];
        }

        if ($temp_name !== null) {
            $result_labs[] = [
                'parent_name' => $temp_name,
                'labs'        => $temp_labs,
            ];
        }

        return $result_labs;
    }
}
