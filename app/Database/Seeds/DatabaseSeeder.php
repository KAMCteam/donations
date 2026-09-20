<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The reference rows the system cannot start without: the two programmes the
 * picker offers, and the workup each side of each one is made of.
 *
 *     php spark db:seed DatabaseSeeder
 *
 * The workup is the transplant check list, group for group and test for test:
 * a recipient's pre-transplant list and the donor's pre-Tx list. Where both
 * ask for the same test it is stored once, marked `both`; where only one side
 * does, it is marked for that side. The spellings that differ between the two
 * sheets for the same test ("Ca/Phos/Mg" and "Calcium/Phosphorus/Mg") are
 * reconciled in ALIASES rather than stored twice.
 *
 * The check list names no organ, so both programmes get both lists.
 *
 * Seeds no people — no patients, no donors, no pairs, no staff accounts, no
 * physicians or coordinators. Re-runnable: it adds what is missing and leaves
 * what is there, so it will not undo edits made through the screens.
 */
class DatabaseSeeder extends Seeder
{
    private const PROGRAMS = [
        ['kidney', 'Kidney', 'Renal transplant program',   'kidney.svg', 1],
        ['liver',  'Liver',  'Hepatic transplant program', 'liver.svg',  2],
    ];

    /**
     * How the check list records each kind of test, from its own wording:
     *
     *   blood_group          A / B / AB / O
     *   done                 Not done / pending / done
     *   positive_negative    Not done / pending / Positive / Negative
     *   acceptable_abnormal  Not done / pending / acceptable / Abnormal
     *   cleared_not_cleared  Not done / pending / Cleared / not cleared
     *   given_not_given      Given / not required / not given
     *
     * The sheet adds "Not applicable" to several of these; it is an answer any
     * test can need, so it is not a vocabulary of its own.
     */
    private const RECIPIENT = [
        'Immunology tests' => [
            ['Blood group', 'blood_group'],
            ['HLA Typing', 'done'],
            ['PRA', 'done'],
            ['DSA', 'positive_negative'],
            ['Cross match', 'positive_negative'],
        ],
        'Hematology/Biochemistry' => [
            ['CBC', 'acceptable_abnormal'],
            ['Creatinine', 'acceptable_abnormal'],
            ['Electrolyte', 'acceptable_abnormal'],
            ['Calcium/Phosphorus/Mg', 'acceptable_abnormal'],
            ['Lipid profile', 'acceptable_abnormal'],
            ['Liver profile', 'acceptable_abnormal'],
            ['Albumin', 'acceptable_abnormal'],
            ['Coagulation profile', 'acceptable_abnormal'],
            ['FBG', 'acceptable_abnormal'],
            ['HbA1C', 'acceptable_abnormal'],
            ['G6PD', 'acceptable_abnormal'],
            ['Sickle cell', 'acceptable_abnormal'],
            ['PTH', 'acceptable_abnormal'],
            ['B-HCG', 'acceptable_abnormal'],
        ],
        'Infectious workup' => [
            ['HbsAg', 'positive_negative'],
            ['HCV', 'positive_negative'],
            ['HbsAb', 'positive_negative'],
            ['HIV', 'positive_negative'],
            ['AbcAb', 'positive_negative'],
            ['Brucella', 'positive_negative'],
            ['TB', 'positive_negative'],
            ['Syphilis', 'positive_negative'],
            ['Leishmania', 'positive_negative'],
            ['Strongyloides', 'positive_negative'],
            ['Mumps', 'positive_negative'],
            ['Measles', 'positive_negative'],
            ['Rubella', 'positive_negative'],
            ['VZV', 'positive_negative'],
            ['CMV', 'positive_negative'],
            ['EBV', 'positive_negative'],
            ['Toxoplasma', 'positive_negative'],
            ['Schistosomiasis', 'positive_negative'],
        ],
        'Urine/stool' => [
            ['Urinalysis', 'acceptable_abnormal'],
            ['Protein/Creatinine ratio', 'acceptable_abnormal'],
            ['Urine Culture', 'acceptable_abnormal'],
            ['24h-urine for protein', 'acceptable_abnormal'],
            ['Cr clearance', 'acceptable_abnormal'],
            ['Stool exam', 'acceptable_abnormal'],
            ['Stool cultures', 'positive_negative'],
        ],
        'Cancer screening' => [
            ['PSA', 'acceptable_abnormal'],
            ['PAP smear', 'acceptable_abnormal'],
            ['Stool OB', 'acceptable_abnormal'],
            ['Mammogram', 'acceptable_abnormal'],
            ['Colonoscopy', 'acceptable_abnormal'],
            ['U/S gyn', 'acceptable_abnormal'],
        ],
        'Imaging' => [
            ['CXR', 'acceptable_abnormal'],
            ['US KUB', 'acceptable_abnormal'],
            ['ECG', 'acceptable_abnormal'],
            ['Echo', 'acceptable_abnormal'],
            ['CT angio pelvis', 'acceptable_abnormal'],
            ['Coronary Angio', 'acceptable_abnormal'],
        ],
        'Referrals and Clearances' => [
            ['Dental', 'cleared_not_cleared'],
            ['Cardiology', 'cleared_not_cleared'],
            ['Anaesthesia', 'cleared_not_cleared'],
            ['Transplant Surgeons', 'cleared_not_cleared'],
            ['Gynaecology', 'cleared_not_cleared'],
            ['Social worker', 'cleared_not_cleared'],
            ['I.D', 'cleared_not_cleared'],
            ['Gastroenterology', 'cleared_not_cleared'],
        ],
        'Vaccinations' => [
            ['MMR', 'given_not_given'],
            ['Hepatitis B vaccine', 'given_not_given'],
            ['VZV', 'given_not_given'],
            ['Influenza vaccine', 'given_not_given'],
            ['Pneumococcal 13', 'given_not_given'],
            ['Pneumococcal 23', 'given_not_given'],
            ['Meningococcal', 'given_not_given'],
        ],
    ];

    /**
     * The donor sheet. Its group headings are the recipient's abbreviated
     * ("Immunology", "Hematology/Biochem", "Clearances"); they are written out
     * here so one set of headings serves both.
     *
     * Cross match is laid out as a heading row on the donor sheet rather than
     * a test row — it is a test, and it is here.
     */
    private const DONOR = [
        'Immunology tests' => [
            ['Blood group', 'blood_group'],
            ['HLA Typing', 'done'],
            ['Cross match', 'positive_negative'],
        ],
        'Hematology/Biochemistry' => [
            ['CBC', 'acceptable_abnormal'],
            ['Renal panel/Cr', 'acceptable_abnormal'],
            ['Electrolyte', 'acceptable_abnormal'],
            ['Calcium/Phosphorus/Mg', 'acceptable_abnormal'],
            ['Lipid profile', 'acceptable_abnormal'],
            ['Liver profile', 'acceptable_abnormal'],
            ['Albumin', 'acceptable_abnormal'],
            ['Coagulation profile', 'acceptable_abnormal'],
            ['FBG', 'acceptable_abnormal'],
            ['HbA1C', 'acceptable_abnormal'],
            ['B-HCG', 'acceptable_abnormal'],
            ['Sickle cell', 'acceptable_abnormal'],
        ],
        'Infectious workup' => [
            ['HbsAg', 'positive_negative'],
            ['HCV', 'positive_negative'],
            ['HbsAb', 'positive_negative'],
            ['HIV', 'positive_negative'],
            ['AbcAb', 'positive_negative'],
            ['Brucella', 'positive_negative'],
            ['TB', 'positive_negative'],
            ['Syphilis', 'positive_negative'],
            ['Leishmania', 'positive_negative'],
            ['Strongyloides', 'positive_negative'],
            ['Mumps', 'positive_negative'],
            ['VZV', 'positive_negative'],
            ['Measles', 'positive_negative'],
            ['Rubella', 'positive_negative'],
            ['CMV', 'positive_negative'],
            ['EBV', 'positive_negative'],
            ['Toxoplasma', 'positive_negative'],
        ],
        'Urine/stool' => [
            ['Urinalysis', 'acceptable_abnormal'],
            ['Protein/Creatinine ratio', 'acceptable_abnormal'],
            ['Urine Culture', 'acceptable_abnormal'],
            ['24h-urine for protein', 'acceptable_abnormal'],
            ['Cr clearance', 'acceptable_abnormal'],
            ['Microalbuminuria', 'acceptable_abnormal'],
            ['Stool exam', 'acceptable_abnormal'],
        ],
        'Imaging' => [
            ['CXR', 'acceptable_abnormal'],
            ['US KUB', 'acceptable_abnormal'],
            ['ECG', 'acceptable_abnormal'],
            ['Echo', 'acceptable_abnormal'],
            ['CT angio pelvis', 'acceptable_abnormal'],
            ['Mammogram', 'acceptable_abnormal'],
            ['US Gynae', 'acceptable_abnormal'],
        ],
        'Referrals and Clearances' => [
            ['Anaesthesia', 'cleared_not_cleared'],
            ['Cardio', 'cleared_not_cleared'],
            ['Advocate', 'cleared_not_cleared'],
            ['Transplant Surgery', 'cleared_not_cleared'],
            ['Social worker', 'cleared_not_cleared'],
        ],
    ];

    /** The order the groups appear on the sheet, which is the order they show. */
    private const GROUP_ORDER = [
        'Immunology tests',
        'Hematology/Biochemistry',
        'Infectious workup',
        'Urine/stool',
        'Cancer screening',
        'Imaging',
        'Referrals and Clearances',
        'Vaccinations',
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
        $wanted = $this->checklist();

        foreach (array_column(self::PROGRAMS, 0) as $organ) {
            $this->seedProgramme($organ, $wanted);
        }

        $this->retireAnythingNotOnTheChecklist($wanted);
    }

    /**
     * The two sheets merged: group => [name => [personType, resultType, order]].
     *
     * A test on both sheets is one row marked `both`. Order runs across the
     * whole checklist so a group's tests keep the order they are listed in.
     *
     * @return array<string, array<string, array{string, string, int}>>
     */
    private function checklist(): array
    {
        $merged = [];
        $order  = 0;

        foreach (self::GROUP_ORDER as $group) {
            foreach (self::RECIPIENT[$group] ?? [] as [$name, $resultType]) {
                $merged[$group][$name] = ['recipient', $resultType, ++$order];
            }

            foreach (self::DONOR[$group] ?? [] as [$name, $resultType]) {
                if (isset($merged[$group][$name])) {
                    $merged[$group][$name][0] = 'both';

                    continue;
                }

                $merged[$group][$name] = ['donor', $resultType, ++$order];
            }
        }

        return $merged;
    }

    /** @param array<string, array<string, array{string, string, int}>> $wanted */
    private function seedProgramme(string $organ, array $wanted): void
    {
        $parents    = $this->db->table('lab_parents');
        $labs       = $this->db->table('labs');
        $groupOrder = 0;

        foreach ($wanted as $group => $tests) {
            $groupOrder++;
            $row = $parents->getWhere(['name' => $group])->getRowArray();

            if ($row === null) {
                $parents->insert(['name' => $group, 'sort_order' => $groupOrder]);
                $parentId = (int) $this->db->insertID();
            } else {
                $parentId = (int) $row['id'];
                $parents->where('id', $parentId)->update(['sort_order' => $groupOrder]);
            }

            foreach ($tests as $name => [$personType, $resultType, $order]) {
                $key = [
                    'name'          => $name,
                    'lab_parent_id' => $parentId,
                    'organ_code'    => $organ,
                ];

                $values = [
                    'person_type' => $personType,
                    'result_type' => $resultType,
                    'sort_order'  => $order,
                    'is_active'   => 1,
                ];

                // Keyed on the group as well as the name: the sheet lists VZV
                // twice, once as a serology and once as a vaccination.
                if ($labs->getWhere($key)->getRowArray() === null) {
                    $labs->insert($key + $values);
                } else {
                    $this->db->table('labs')->where($key)->update($values);
                }
            }
        }
    }

    /**
     * Takes anything the check list does not list out of the workup.
     *
     * Deleted where nothing has been recorded against it, and deactivated
     * where something has: a result already entered stays attached to the test
     * it was entered for, and only disappears from the screens.
     *
     * @param array<string, array<string, array{string, string, int}>> $wanted
     */
    private function retireAnythingNotOnTheChecklist(array $wanted): void
    {
        $names = [];

        foreach ($wanted as $tests) {
            foreach (array_keys($tests) as $name) {
                $names[$name] = true;
            }
        }

        $stale = $this->db->table('labs')
            ->whereNotIn('name', array_keys($names))
            ->get()
            ->getResultArray();

        foreach ($stale as $lab) {
            $used = $this->db->table('lab_results')->where('lab_id', $lab['id'])->countAllResults() > 0;

            if ($used) {
                $this->db->table('labs')->where('id', $lab['id'])->update(['is_active' => 0]);
            } else {
                $this->db->table('labs')->where('id', $lab['id'])->delete();
            }
        }

        // A group left with no tests in it has nothing to head.
        $this->db->query(
            'DELETE FROM ' . $this->db->protectIdentifiers($this->db->prefixTable('lab_parents'))
            . ' WHERE id NOT IN (SELECT lab_parent_id FROM '
            . $this->db->protectIdentifiers($this->db->prefixTable('labs')) . ')'
        );
    }
}
