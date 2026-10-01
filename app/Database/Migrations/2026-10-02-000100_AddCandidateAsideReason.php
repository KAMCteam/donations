<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Why a potential donor was set aside — because the two reasons are not the
 * same thing and the screen has to tell them apart.
 *
 * **Delinked** is a decision about that donor: they were considered and are
 * not going ahead. The tab freezes.
 *
 * **Superseded** is a decision about somebody else: another candidate was
 * paired, so this one stopped being the one. Nothing was decided about them,
 * and the pair can be switched back to them later — which is the whole point
 * of keeping the list after a pair is made.
 *
 * Both read Declined, because both are. The column only says which door they
 * came through.
 *
 * Widening only. Every row already stored was set aside by hand, if at all,
 * which is what `delinked` means — so that is what they are given.
 */
class AddCandidateAsideReason extends Migration
{
    public function up(): void
    {
        if ($this->hasColumn('potential_donors', 'aside_reason')) {
            return;
        }

        $this->db->query('ALTER TABLE ' . $this->table('potential_donors')
            . " ADD COLUMN aside_reason ENUM('delinked','superseded') NULL AFTER status");

        $this->db->query('UPDATE ' . $this->table('potential_donors')
            . " SET aside_reason = 'delinked' WHERE status = 'declined'");
    }

    /** Dropping it loses only why, not that. */
    public function down(): void
    {
        if ($this->hasColumn('potential_donors', 'aside_reason')) {
            $this->forge->dropColumn('potential_donors', 'aside_reason');
        }
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
