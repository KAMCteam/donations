<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * The two programmes the design's picker offers.
 *
 * `code` must stay in step with the `patients.organs` and `labs.organ_type`
 * ENUMs — `SchemaTest` asserts it does. Adding a third programme means a row
 * here plus a migration widening those two ENUMs.
 *
 *     php spark db:seed OrganProgramSeeder
 */
class OrganProgramSeeder extends Seeder
{
    private const PROGRAMS = [
        ['kidney', 'Kidney', 'Renal transplant program',   'kidney.svg', 1],
        ['liver',  'Liver',  'Hepatic transplant program', 'liver.svg',  2],
    ];

    public function run(): void
    {
        $table = $this->db->table('organ_programs');

        foreach (self::PROGRAMS as [$code, $label, $description, $icon, $order]) {
            $row = [
                'label'       => $label,
                'description' => $description,
                'icon'        => $icon,
                'sort_order'  => $order,
                'is_active'   => 1,
            ];

            // Re-runnable, and safe to run after someone has edited a label.
            if ($table->getWhere(['code' => $code])->getRowArray() !== null) {
                continue;
            }

            $table->insert($row + ['code' => $code]);
        }
    }
}
