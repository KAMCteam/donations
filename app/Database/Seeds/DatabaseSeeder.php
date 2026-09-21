<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The reference rows the system cannot start without: the two programmes the
 * picker offers, and the workup each side of each one is made of.
 *
 *     php spark db:seed DatabaseSeeder
 *
 * The workup is the transplant check list, group for group and test for test.
 * The two sheets are kept apart all the way down, headings included: the
 * recipient's "Hematology/Biochemistry" and the donor's "Hematology/Biochem"
 * are two groups, not one spelled two ways, because they hold different tests.
 * Four headings read almost the same on both sheets and none of them lists the
 * same tests, so a shared heading would have to be filtered by side on every
 * read — and the first heading that is genuinely shared would break it.
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
     * How the check list records each kind of test, in its own wording:
     *
     *   blood_group             A / B / AB / O
     *   done                    Not done / pending / done
     *   positive_negative       Not done / pending / Positive / Negative
     *   acceptable_abnormal     Not done / pending / acceptable / Abnormal
     *   acceptable_abnormal_na  ... / Not applicable
     *   cleared_not_cleared     Not done / pending / Cleared / not cleared / Not applicable
     *   given_not_given         Given / not required / not given / not applicable
     *
     * "Not applicable" is offered test by test rather than everywhere: the
     * sheet adds it to cancer screening, imaging, the clearances and B-HCG —
     * the tests a patient's sex or history can rule out — and withholds it
     * from the bloods and serologies, which are asked of everyone. That is why
     * `acceptable_abnormal` comes in two, one with the answer and one without.
     *
     * Every test starts at Not done, whichever list it answers from.
     */
    private const RECIPIENT = [
        'Immunology tests' => [
            ['Blood group', 'blood_group'],
            ['PRA', 'done'],
            ['HLA Typing', 'done'],
            ['Cross match', 'positive_negative'],
            ['DSA', 'positive_negative'],
        ],
        'Hematology/Biochemistry' => [
            ['CBC', 'acceptable_abnormal'],
            ['Electrolyte', 'acceptable_abnormal'],
            ['Lipid profile', 'acceptable_abnormal'],
            ['Albumin', 'acceptable_abnormal'],
            ['FBG', 'acceptable_abnormal'],
            ['G6PD', 'acceptable_abnormal'],
            ['PTH', 'acceptable_abnormal'],
            ['Creatinine', 'acceptable_abnormal'],
            ['Calcium/Phosphorus/Mg', 'acceptable_abnormal'],
            ['Liver profile', 'acceptable_abnormal'],
            ['Coagulation profile', 'acceptable_abnormal'],
            ['Sickle cell', 'acceptable_abnormal'],
            ['HbA1C', 'acceptable_abnormal'],
            ['B-HCG', 'acceptable_abnormal_na'],
        ],
        'Infectious workup' => [
            ['HbsAg', 'positive_negative'],
            ['HbsAb', 'positive_negative'],
            ['AbcAb', 'positive_negative'],
            ['TB', 'positive_negative'],
            ['Leishmania', 'positive_negative'],
            ['Mumps', 'positive_negative'],
            ['Rubella', 'positive_negative'],
            ['CMV', 'positive_negative'],
            ['Toxoplasma', 'positive_negative'],
            ['HCV', 'positive_negative'],
            ['HIV', 'positive_negative'],
            ['Brucella', 'positive_negative'],
            ['Syphilis', 'positive_negative'],
            ['Strongyloides', 'positive_negative'],
            ['Measles', 'positive_negative'],
            ['VZV', 'positive_negative'],
            ['EBV', 'positive_negative'],
            ['Schistosomiasis', 'positive_negative'],
        ],
        'Urine/Stool' => [
            ['Urinalysis', 'acceptable_abnormal'],
            ['Urine Culture', 'acceptable_abnormal'],
            ['Protein/Creatinine ratio', 'acceptable_abnormal'],
            ['24h-urine for protein', 'acceptable_abnormal'],
            ['Cr clearance', 'acceptable_abnormal'],
            ['Stool exam', 'acceptable_abnormal'],
            ['Stool cultures', 'positive_negative'],
        ],
        'Cancer screening' => [
            ['PSA', 'acceptable_abnormal_na'],
            ['Stool OB', 'acceptable_abnormal_na'],
            ['Colonoscopy', 'acceptable_abnormal_na'],
            ['PAP smear', 'acceptable_abnormal_na'],
            ['Mammogram', 'acceptable_abnormal_na'],
            ['U/S gyn', 'acceptable_abnormal_na'],
        ],
        'Imaging' => [
            ['CXR', 'acceptable_abnormal_na'],
            ['ECG', 'acceptable_abnormal_na'],
            ['Echo', 'acceptable_abnormal_na'],
            ['CT angio pelvis', 'acceptable_abnormal_na'],
            ['Coronary Angio', 'acceptable_abnormal_na'],
            ['US KUB', 'acceptable_abnormal_na'],
        ],
        'Referrals and Clearances' => [
            ['Dental', 'cleared_not_cleared'],
            ['Anaesthesia', 'cleared_not_cleared'],
            ['Cardiology', 'cleared_not_cleared'],
            ['Transplant Surgeons', 'cleared_not_cleared'],
            ['Gynaecology', 'cleared_not_cleared'],
            ['I.D', 'cleared_not_cleared'],
            ['Social worker', 'cleared_not_cleared'],
            ['Gastroenterology', 'cleared_not_cleared'],
        ],
        'Vaccinations' => [
            ['MMR', 'given_not_given'],
            ['VZV', 'given_not_given'],
            ['Pneumococcal 13', 'given_not_given'],
            ['Meningococcal', 'given_not_given'],
            ['Hepatitis B vaccine', 'given_not_given'],
            ['Influenza vaccine', 'given_not_given'],
            ['Pneumococcal 23', 'given_not_given'],
        ],
    ];

    /**
     * The donor's pre-Tx sheet, under its own headings.
     *
     * Shorter than the recipient's at every turn: no cancer screening, no
     * vaccinations, no Schistosomiasis, no PRA or DSA, and its own spellings
     * for tests the recipient sheet writes out in full ("Ca/Phos/Mg",
     * "Creatinine Clearance"). Renal panel/Cr stands where the recipient sheet
     * asks for Creatinine, and microalbuminuria is asked of donors alone.
     */
    private const DONOR = [
        'Immunology' => [
            ['Blood group', 'blood_group'],
            ['HLA Typing', 'done'],
            ['Cross match', 'positive_negative'],
        ],
        'Hematology/Biochem' => [
            ['CBC', 'acceptable_abnormal'],
            ['Electrolyte', 'acceptable_abnormal'],
            ['Lipid profile', 'acceptable_abnormal'],
            ['Albumin', 'acceptable_abnormal'],
            ['FBG', 'acceptable_abnormal'],
            ['Renal panel/Cr', 'acceptable_abnormal'],
            ['Ca/Phos/Mg', 'acceptable_abnormal'],
            ['Liver profile', 'acceptable_abnormal'],
            ['Coagulation profile', 'acceptable_abnormal'],
            ['HbA1C', 'acceptable_abnormal'],
            ['Sickle cell', 'acceptable_abnormal'],
            ['B-HCG', 'acceptable_abnormal_na'],
        ],
        'Infectious workup' => [
            ['HbsAg', 'positive_negative'],
            ['HbsAb', 'positive_negative'],
            ['AbcAb', 'positive_negative'],
            ['TB', 'positive_negative'],
            ['Leishmania', 'positive_negative'],
            ['HCV', 'positive_negative'],
            ['HIV', 'positive_negative'],
            ['Brucella', 'positive_negative'],
            ['Syphilis', 'positive_negative'],
            ['Strongyloides', 'positive_negative'],
            ['Mumps', 'positive_negative'],
            ['Measles', 'positive_negative'],
            ['CMV', 'positive_negative'],
            ['Toxoplasma', 'positive_negative'],
            ['VZV', 'positive_negative'],
            ['Rubella', 'positive_negative'],
            ['EBV', 'positive_negative'],
        ],
        'Urine/Stool' => [
            ['Urinalysis', 'acceptable_abnormal'],
            ['Urine Culture', 'acceptable_abnormal'],
            ['24h-urine for protein', 'acceptable_abnormal'],
            ['Microalbuminuria', 'acceptable_abnormal'],
            ['Protein/Creatinine ratio', 'acceptable_abnormal'],
            ['Creatinine Clearance', 'acceptable_abnormal'],
            ['Stool Exam', 'acceptable_abnormal'],
        ],
        'Imaging' => [
            ['CXR', 'acceptable_abnormal_na'],
            ['ECG', 'acceptable_abnormal_na'],
            ['Echo', 'acceptable_abnormal_na'],
            ['CT angio pelvis', 'acceptable_abnormal_na'],
            ['US Gynae', 'acceptable_abnormal_na'],
            ['US KUB', 'acceptable_abnormal_na'],
            ['Mammogram', 'acceptable_abnormal_na'],
        ],
        'Clearances' => [
            ['Anaesthesia', 'cleared_not_cleared'],
            ['Advocate', 'cleared_not_cleared'],
            ['Social worker', 'cleared_not_cleared'],
            ['Cardio', 'cleared_not_cleared'],
            ['Transplant Surgery', 'cleared_not_cleared'],
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
        $seeded = [];

        foreach (array_column(self::PROGRAMS, 0) as $organ) {
            foreach (['recipient' => self::RECIPIENT, 'donor' => self::DONOR] as $personType => $sheet) {
                foreach ($this->seedSheet($organ, $personType, $sheet) as $id) {
                    $seeded[$id] = true;
                }
            }
        }

        $this->retireAnythingNotOnTheChecklist(array_keys($seeded));
    }

    /**
     * One side of one programme: its groups in order, and its tests in each.
     *
     * @param array<string, list<array{string, string}>> $sheet
     *
     * @return list<int> The lab rows this sheet accounts for
     */
    private function seedSheet(string $organ, string $personType, array $sheet): array
    {
        $labs       = $this->db->table('labs');
        $ids        = [];
        $groupOrder = 0;
        $order      = 0;

        foreach ($sheet as $group => $tests) {
            $parentId = $this->parentId($group, $personType, ++$groupOrder);

            foreach ($tests as [$name, $resultType]) {
                // Keyed on the group as well as the name: the recipient sheet
                // lists VZV twice, once as a serology and once as a jab.
                $key = [
                    'name'          => $name,
                    'lab_parent_id' => $parentId,
                    'organ_code'    => $organ,
                    'person_type'   => $personType,
                ];

                $values = [
                    'result_type' => $resultType,
                    'sort_order'  => ++$order,
                    'is_active'   => 1,
                ];

                $row = $labs->getWhere($key)->getRowArray();

                if ($row === null) {
                    $labs->insert($key + $values);
                    $ids[] = (int) $this->db->insertID();

                    continue;
                }

                $this->db->table('labs')->where('id', $row['id'])->update($values);
                $ids[] = (int) $row['id'];
            }
        }

        return $ids;
    }

    /** The group heading, made if this side does not have it yet. */
    private function parentId(string $name, string $personType, int $order): int
    {
        $parents = $this->db->table('lab_parents');
        $key     = ['name' => $name, 'person_type' => $personType];
        $row     = $parents->getWhere($key)->getRowArray();

        if ($row === null) {
            $parents->insert($key + ['sort_order' => $order]);

            return (int) $this->db->insertID();
        }

        $parents->where('id', $row['id'])->update(['sort_order' => $order]);

        return (int) $row['id'];
    }

    /**
     * Takes anything the check list does not list out of the workup.
     *
     * Deleted where nothing has been recorded against it, and deactivated
     * where something has: a result already entered stays attached to the test
     * it was entered for, and only disappears from the screens.
     *
     * @param list<int> $keep Every lab row the two sheets account for
     */
    private function retireAnythingNotOnTheChecklist(array $keep): void
    {
        $stale = $this->db->table('labs')
            ->whereNotIn('id', $keep === [] ? [0] : $keep)
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
            . $this->db->protectIdentifiers($this->db->prefixTable('labs'))
            . ' WHERE lab_parent_id IS NOT NULL)'
        );
    }
}
