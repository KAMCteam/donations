<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `organ_programs` — the programme picker, from the design.
 *
 * The design opens on a screen offering "Kidney — Renal transplant program"
 * and "Liver — Hepatic transplant program", each with its own icon. All of
 * that was hardcoded in `Ui::organSelector()`, which means adding a third
 * programme — pancreas, heart, a paired-exchange track — meant editing PHP.
 * It is data, so it lives in a table.
 *
 * `patients.organs` and `labs.organ_type` stay ENUMs, because `ListsModel`
 * reads their values back with `SHOW COLUMNS` to build dropdowns. Their values
 * are the `code` column here; `SchemaTest` asserts the two never drift apart.
 * Adding a programme is therefore two steps: a row here, and the value added
 * to those two ENUMs.
 */
class CreateOrganPrograms extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Matches the patients.organs / labs.organ_type ENUM values, and
            // the URL segment the picker links to (/organ/kidney).
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            // "Kidney" — the card's heading.
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            // "Renal transplant program" — the line under it.
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            // File under public/assets/ui/img, e.g. kidney.svg.
            'icon' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'sort_order' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
            ],
            // Retire a programme without deleting the patients on it.
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
        ]);

        $this->forge->addPrimaryKey('code');
        $this->forge->createTable('organ_programs', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('organ_programs', true);
    }
}
