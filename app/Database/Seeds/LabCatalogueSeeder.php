<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Fills `lab_parents` and `labs` with the workup the platform asks for.
 *
 * These are the tests `UiStore::defaultLabTests()` hands a new recipient or
 * donor, so a freshly migrated database produces the same lab cards the
 * screens show today — the difference being that they now come from the
 * catalogue rather than a hard-coded PHP array, which is what `labs` and
 * `lab_parents` existed for in the first place.
 *
 * `result_shape` uses the original codes, whose labels live in the Form file
 * of each locale under app/Language as form_lab_option_<code>:
 *
 *     PO/NE  Positive / Negative
 *     C/NC   Cleared / Not Cleared
 *     ND/P/D Not Done / Pending / Done
 *
 * NULL means the result is free text — a value, a finding, a report line —
 * which is what the platform's result editor collects.
 *
 * Deliberately seeds no people. `mrp`, `coordinators` and `staff` are left
 * empty rather than filled with invented physicians; see the README for the
 * one-liner that inserts a real staff account.
 *
 *     php spark db:seed LabCatalogueSeeder
 */
class LabCatalogueSeeder extends Seeder
{
    /** Group => the labs under it, in the order the screens list them. */
    private const CATALOGUE = [
        'Renal Function' => [
            ['eGFR / Creatinine', null, 'both', 'kidney'],
        ],
        'Hepatic Function' => [
            ['LFTs (ALT / AST / Bilirubin)', null, 'both', 'liver'],
            ['INR / Coagulation', null, 'both', 'liver'],
        ],
        'Scoring' => [
            ['MELD Score', null, 'recipient', 'liver'],
        ],
        'Immunology' => [
            ['Crossmatch', 'PO/NE', 'both', 'kidney'],
            ['HLA Typing', null, 'both', 'kidney'],
            ['Crossmatch', 'PO/NE', 'both', 'liver'],
        ],
        'Virology' => [
            ['Virology Panel (HIV, HBV, HCV)', 'PO/NE', 'both', 'kidney'],
            ['Virology Panel (HIV, HBV, HCV)', 'PO/NE', 'both', 'liver'],
        ],
        'Imaging' => [
            ['Renal Ultrasound', null, 'both', 'kidney'],
            ['Renal CT Angiogram', null, 'donor', 'kidney'],
            ['Liver CT / MRI', null, 'both', 'liver'],
            ['Liver Volumetry (CT)', null, 'donor', 'liver'],
        ],
        'Histopathology' => [
            ['Liver Biopsy', null, 'donor', 'liver'],
        ],
        'Cardiac' => [
            ['Cardiac Clearance', 'C/NC', 'both', 'kidney'],
            ['Cardiac Clearance', 'C/NC', 'both', 'liver'],
        ],
        'Psychosocial' => [
            ['Psychiatric Evaluation', 'C/NC', 'donor', 'kidney'],
            ['Psychiatric Evaluation', 'C/NC', 'donor', 'liver'],
        ],
    ];

    public function run(): void
    {
        $parents = $this->db->table('lab_parents');
        $labs    = $this->db->table('labs');
        $order   = 0;

        foreach (self::CATALOGUE as $parentName => $rows) {
            $existing = $parents->getWhere(['parent_name' => $parentName])->getRowArray();

            if ($existing === null) {
                $parents->insert(['parent_name' => $parentName]);
                $parentId = (int) $this->db->insertID();
            } else {
                $parentId = (int) $existing['parent_id'];
            }

            foreach ($rows as [$name, $shape, $patientType, $organ]) {
                $order++;

                // Re-runnable: a lab is identified by its name, type and organ.
                $already = $labs->getWhere([
                    'lab_name'     => $name,
                    'patient_type' => $patientType,
                    'organ_type'   => $organ,
                ])->getRowArray();

                if ($already !== null) {
                    continue;
                }

                $labs->insert([
                    'lab_name'      => $name,
                    'result_shape'  => $shape,
                    'lab_parent_id' => $parentId,
                    'patient_type'  => $patientType,
                    'organ_type'    => $organ,
                    'sort_order'    => $order,
                    'is_active'     => 1,
                ]);
            }
        }
    }
}
