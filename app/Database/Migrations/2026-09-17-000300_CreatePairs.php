<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `pairs` — the recipient/donor matches. Column names are unchanged, so
 * `PairsModel` (including its `NOT IN (SELECT recipient_mrn FROM pairs ...)`
 * sub-selects and the `match_status NOT IN ('closed')` guard) keeps working.
 *
 * Each side points at its own table now that recipients and donors have one
 * each, which is stricter than before: a recipient MRN can no longer be
 * entered as the donor half by mistake.
 */
class CreatePairs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'pair_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'recipient_mrn' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'donor_mrn' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            // `programs` keeps its original name and values: this is the ENUM
            // ListsModel::get_programs() reads by name and splits into the
            // R_* and D_* lists that drive the add-patient form.
            'programs' => [
                'type'       => 'ENUM',
                'constraint' => ['R_LRD', 'R_LURD', 'R_DD', 'R_PE', 'D_D'],
                'null'       => true,
            ],
            // The original five values plus `active`, `scheduled` and
            // `on_hold`, which the platform's Match Status dropdown offers.
            // `closed` keeps its meaning: PairsModel treats a closed pair as
            // freeing both sides back onto the unmatched lists.
            'match_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'pending', 'confirmed', 'active', 'scheduled',
                    'on_hold', 'completed', 'closed', 'paired_exchange',
                ],
                'default' => 'pending',
            ],
            'relationship' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            // Kept under their original names and meanings: matched_on is the
            // committee date, surgery_on is what both the old form and the
            // platform label "Date of Crossmatch".
            'matched_on' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'surgery_on' => [
                'type' => 'DATE',
                'null' => true,
            ],
            // NEW. The pairs table has a Note column and the pair screen a
            // notes box; there was nowhere to keep either.
            'note' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('pair_id');
        $this->forge->addKey('recipient_mrn');
        $this->forge->addKey('donor_mrn');
        $this->forge->addKey('match_status');
        // RESTRICT, not CASCADE: deleting a patient who is half of a pair
        // should fail loudly rather than quietly drop the match.
        $this->forge->addForeignKey('recipient_mrn', 'recipients', 'mrn', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('donor_mrn', 'donors', 'mrn', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('pairs', true, ['ENGINE' => 'InnoDB']);

        // No database-level "one open pair per recipient/donor" constraint.
        // The natural way to express it is a unique index over a generated
        // column that is NULL while the pair is closed, but MariaDB rejects a
        // generated column whose expression reads a column belonging to an
        // ON UPDATE CASCADE foreign key (error 1901) — and both MRN columns
        // do, so that a corrected MRN still propagates here. The cascade is
        // worth more than the index: the rule stays where it already lived,
        // in PairsModel::pair_exists(), which looks for an existing pair whose
        // match_status is not 'closed' before inserting another.
    }

    public function down(): void
    {
        $this->forge->dropTable('pairs', true);
    }
}
