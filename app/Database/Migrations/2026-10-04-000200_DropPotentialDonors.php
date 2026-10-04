<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * There is no such thing as a potential donor any more.
 *
 * The middle state turned out to be the wrong shape. A recipient does not
 * collect candidates and then make a pair out of one of them — they have a
 * pair, and the pair has donors: the one it is going ahead with, the ones it
 * is keeping in reserve, and the ones it has finished with. That is one table,
 * not two, and `pairs` has been that table all along: one row per donor ever
 * linked to a recipient, with its own status and its own closing.
 *
 * So `potential_donors` goes. Every row in it that has no pair of its own
 * becomes one first, because a candidate nobody paired is exactly a donor the
 * pair has not gone ahead with:
 *
 *   - active and on hold carry their word straight across
 *   - declined becomes a closed pair, which is what archived means now
 *
 * A candidate whose donor is in somebody else's open pair is not carried: that
 * donor belongs to the other pair, and `pairs` will not hold them twice.
 *
 * `donors.is_listed` is left standing but set everywhere: it existed so that a
 * donor entered as somebody's candidate stayed off the register until a pair
 * was made, and a donor is only ever entered into a pair now. The column is
 * kept rather than dropped because dropping it costs the whole test suite its
 * rollback, and a column nothing writes 0 to does no harm.
 */
class DropPotentialDonors extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('potential_donors')) {
            $this->db->query(
                'INSERT INTO ' . $this->table('pairs')
                . ' (recipient_mrn, donor_mrn, status, closed_reason, created_at, updated_at)'
                . " SELECT pd.recipient_mrn, pd.donor_mrn,"
                . " CASE WHEN pd.status = 'declined' THEN 'closed' ELSE pd.status END,"
                . " CASE WHEN pd.status = 'declined'"
                . " THEN 'Set aside before a pair could hold more than one donor.' END,"
                . ' pd.created_at, pd.updated_at'
                . ' FROM ' . $this->table('potential_donors') . ' pd'
                // Already a pair: the row it was carried from, or the pair it
                // became. Either way there is nothing to add.
                . ' WHERE NOT EXISTS (SELECT 1 FROM ' . $this->table('pairs') . ' p'
                . ' WHERE p.recipient_mrn = pd.recipient_mrn AND p.donor_mrn = pd.donor_mrn)'
                // And not somebody else's donor: one donor, one open pair.
                . ' AND NOT EXISTS (SELECT 1 FROM ' . $this->table('pairs') . ' o'
                . " WHERE o.donor_mrn = pd.donor_mrn AND o.status <> 'closed')"
            );

            $this->forge->dropTable('potential_donors', true);
        }

        if ($this->hasColumn('donors', 'is_listed')) {
            $this->db->query('UPDATE ' . $this->table('donors') . ' SET is_listed = 1 WHERE is_listed = 0');
        }
    }

    /**
     * Irreversible by design.
     *
     * Putting the table back would mean deciding which of a pair's donors had
     * once been a candidate, and nothing records that any more because nothing
     * needs to. The rows themselves are not lost: they are pairs.
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
