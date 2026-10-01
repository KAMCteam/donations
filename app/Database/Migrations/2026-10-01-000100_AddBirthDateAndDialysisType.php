<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Two things the registers could not say, and one they could only say wrong.
 *
 * `birth_date` on both registers. Age was collected as a number, which is a
 * fact with a shelf life: it is right on the day it is typed and quietly wrong
 * every year after, and nothing in the system knew to ask again. A date of
 * birth is the fact that does not move, and the age is worked out from it
 * whenever it is shown. The `age` column stays — every list, filter and report
 * reads it, and the records entered before this have nothing else — and is
 * rewritten from the birth date each time one is saved.
 *
 * `dialysis_type` on recipients. The screens have asked for "Type Dialysis"
 * since the Reports page was built and no column answered, so the report
 * printed an empty cell for every row. Three answers: haemodialysis,
 * peritoneal, and pre-emptive — which means no dialysis yet, and is why a
 * pre-emptive recipient has no first dialysis date to give.
 *
 * Widening only. Both columns are nullable, nothing stored changes, and a
 * record without a birth date keeps the age it was entered with.
 */
class AddBirthDateAndDialysisType extends Migration
{
    public function up(): void
    {
        foreach (['recipients', 'donors'] as $table) {
            if (! $this->hasColumn($table, 'birth_date')) {
                $this->forge->addColumn($table, [
                    'birth_date' => [
                        'type'  => 'DATE',
                        'null'  => true,
                        'after' => 'age',
                    ],
                ]);
            }
        }

        if (! $this->hasColumn('recipients', 'dialysis_type')) {
            $this->db->query('ALTER TABLE ' . $this->table('recipients')
                . " ADD COLUMN dialysis_type ENUM('hemo','peritoneal','preemptive') NULL"
                . ' AFTER birth_date');
        }
    }

    /**
     * Irreversible by design.
     *
     * Dropping `birth_date` would throw away the one date that cannot be
     * worked back out of an age, and dropping `dialysis_type` would throw away
     * which kind of dialysis somebody is on. The Create… migrations' own
     * down() still drops the tables.
     */
    public function down(): void
    {
    }

    private function table(string $name): string
    {
        return $this->db->protectIdentifiers($this->db->prefixTable($name), true, false, false);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->db->getFieldNames($this->db->prefixTable($table)), true);
    }
}
