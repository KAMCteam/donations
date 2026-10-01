<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Which kind of person an `mrp` row is.
 *
 * The screen was called Add MRP and registered physicians, but it is the one
 * place the system makes a *user* — somebody a record can be assigned to — and
 * a transplant programme assigns two kinds: the responsible physician and the
 * coordinator. They were only ever told apart by where they were typed, which
 * meant the register could not say what it was looking at.
 *
 * Every row stored before this is a physician, because that is all the screen
 * could make, so that is what they are given.
 *
 * Coordinators keep their own table as well. It is what the record screens
 * point at — `recipients.coordinator_id`, `donors.coordinator_id` — and a
 * coordinator registered here gets a row there under the same name, so a
 * record assigned to them keeps working whichever screen entered them.
 */
class AddMrpKind extends Migration
{
    public function up(): void
    {
        if ($this->hasColumn('mrp', 'kind')) {
            return;
        }

        $this->db->query('ALTER TABLE ' . $this->table('mrp')
            . " ADD COLUMN kind ENUM('doctor','coordinator') NOT NULL DEFAULT 'doctor' AFTER name");
    }

    /**
     * Irreversible by design.
     *
     * Dropping the column would make every coordinator a physician, which is
     * not where they started — it is a different person in a different job.
     * The Create… migration's own down() still drops the table.
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
