<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The reference rows the system cannot start without: the two programmes the
 * picker offers, and the workup each side of each one is made of.
 *
 *     php spark db:seed DatabaseSeeder
 *
 * Seeds no people — no patients, no donors, no pairs, no staff accounts, no
 * physicians or coordinators. Re-runnable: it skips anything already there, so
 * it will not undo edits made through the screens.
 */
class DatabaseSeeder extends Seeder
{
    private const PROGRAMS = [
        ['kidney', 'Kidney', 'Renal transplant program',   'kidney.svg', 1],
        ['liver',  'Liver',  'Hepatic transplant program', 'liver.svg',  2],
    ];

    /** Group => [name, organ, person type, how the result is captured]. */
    private const WORKUP = [
        'Renal Function' => [
            ['eGFR / Creatinine', 'kidney', 'both', 'numeric'],
        ],
        'Hepatic Function' => [
            ['LFTs (ALT / AST / Bilirubin)', 'liver', 'both', 'text'],
            ['INR / Coagulation', 'liver', 'both', 'numeric'],
            ['MELD Score', 'liver', 'recipient', 'numeric'],
        ],
        'Immunology' => [
            ['Crossmatch', 'kidney', 'both', 'positive_negative'],
            ['Crossmatch', 'liver', 'both', 'positive_negative'],
            ['HLA Typing', 'kidney', 'both', 'text'],
        ],
        'Virology' => [
            ['Virology Panel (HIV, HBV, HCV)', 'kidney', 'both', 'positive_negative'],
            ['Virology Panel (HIV, HBV, HCV)', 'liver', 'both', 'positive_negative'],
        ],
        'Imaging' => [
            ['Renal Ultrasound', 'kidney', 'both', 'text'],
            ['Renal CT Angiogram', 'kidney', 'donor', 'text'],
            ['Liver CT / MRI', 'liver', 'both', 'text'],
            ['Liver Volumetry (CT)', 'liver', 'donor', 'text'],
        ],
        'Histopathology' => [
            ['Liver Biopsy', 'liver', 'donor', 'text'],
        ],
        'Cardiac' => [
            ['Cardiac Clearance', 'kidney', 'both', 'cleared_not_cleared'],
            ['Cardiac Clearance', 'liver', 'both', 'cleared_not_cleared'],
        ],
        'Psychosocial' => [
            ['Psychiatric Evaluation', 'kidney', 'donor', 'cleared_not_cleared'],
            ['Psychiatric Evaluation', 'liver', 'donor', 'cleared_not_cleared'],
        ],
    ];

    public function run(): void
    {
        $this->seedPrograms();
        $this->seedWorkup();
    }

    private function seedPrograms(): void
    {
        $table = $this->db->table('organ_programs');

        foreach (self::PROGRAMS as [$code, $label, $description, $icon, $order]) {
            if ($table->getWhere(['code' => $code])->getRowArray() !== null) {
                continue;
            }

            $table->insert([
                'code'        => $code,
                'label'       => $label,
                'description' => $description,
                'icon'        => $icon,
                'sort_order'  => $order,
                'is_active'   => 1,
            ]);
        }
    }

    private function seedWorkup(): void
    {
        $parents    = $this->db->table('lab_parents');
        $labs       = $this->db->table('labs');
        $groupOrder = 0;
        $labOrder   = 0;

        foreach (self::WORKUP as $groupName => $rows) {
            $groupOrder++;
            $group = $parents->getWhere(['name' => $groupName])->getRowArray();

            if ($group === null) {
                $parents->insert(['name' => $groupName, 'sort_order' => $groupOrder]);
                $groupId = (int) $this->db->insertID();
            } else {
                $groupId = (int) $group['id'];
            }

            foreach ($rows as [$name, $organ, $personType, $resultType]) {
                $labOrder++;

                $exists = $labs->getWhere([
                    'name'        => $name,
                    'organ_code'  => $organ,
                    'person_type' => $personType,
                ])->getRowArray();

                if ($exists !== null) {
                    continue;
                }

                $labs->insert([
                    'name'          => $name,
                    'lab_parent_id' => $groupId,
                    'organ_code'    => $organ,
                    'person_type'   => $personType,
                    'result_type'   => $resultType,
                    'sort_order'    => $labOrder,
                    'is_active'     => 1,
                ]);
            }
        }
    }
}
