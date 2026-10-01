<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Somebody being considered for a recipient, before any pair exists.
 *
 * Linking used to be one step: choose a donor and the pair was made. But a
 * recipient is worked up against several donors at once — they are collected,
 * tested, and set aside one by one — and only at the end is one of them the
 * pair. Making the pair at the start meant every candidate was a pair, which
 * is not what a pair means.
 *
 * `potential_donors` is that middle state: this donor is being considered for
 * this recipient, and the row carries how that is going — active, on hold, or
 * declined. A pair is made from one of them later, by hand, on the recipient's
 * own screen. The row stays afterwards, because who else was looked at is part
 * of what happened.
 *
 * `donors.is_listed` is the other half of it. A donor entered as somebody's
 * candidate is not on the register yet — the Donors List is who the programme
 * has, not who is being thought about — so they are stored unlisted and join
 * the register when a pair is made from them. A donor who was already on the
 * register and is then considered for somebody stays listed throughout.
 *
 * Every existing pair is carried over as a candidate of its own, so the tabs a
 * recipient already had are the tabs they still have.
 */
class AddPotentialDonors extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('potential_donors')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'recipient_mrn' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'donor_mrn'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                // The same three words a donor's own record offers, because
                // this is the same question asked about one pairing.
                'status'        => ['type' => 'ENUM', 'constraint' => ['active', 'on_hold', 'declined'], 'default' => 'active'],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            // One row per pairing considered: adding the same donor twice is
            // the same candidate, not a second one.
            $this->forge->addUniqueKey(['recipient_mrn', 'donor_mrn']);
            $this->forge->addKey('recipient_mrn');
            $this->forge->addKey('donor_mrn');
            $this->forge->createTable('potential_donors');
        }

        if (! $this->hasColumn('donors', 'is_listed')) {
            $this->forge->addColumn('donors', [
                'is_listed' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 1,
                    'after'      => 'status',
                ],
            ]);
        }

        // Every pair that exists was once a candidate, so each becomes one —
        // a closed pair as a declined candidate, which is what it is. Written
        // with INSERT IGNORE so re-running the migration adds nothing twice.
        $this->db->query(
            'INSERT IGNORE INTO ' . $this->table('potential_donors')
            . ' (recipient_mrn, donor_mrn, status, created_at, updated_at)'
            . " SELECT p.recipient_mrn, p.donor_mrn,"
            . " CASE WHEN p.status = 'closed' THEN 'declined'"
            . " WHEN p.status = 'on_hold' THEN 'on_hold' ELSE 'active' END,"
            . ' p.created_at, p.updated_at'
            . ' FROM ' . $this->table('pairs') . ' p'
        );
    }

    /**
     * Drops the table, which is reversible: every row in it was either carried
     * over from a pair that still exists, or is a candidate nobody has paired
     * — and the second kind has nowhere else to live, so the screens lose them
     * either way. `is_listed` stays, because dropping it would put donors
     * entered as candidates onto the register.
     */
    public function down(): void
    {
        $this->forge->dropTable('potential_donors', true);
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
