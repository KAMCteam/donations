<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets a pair be put forward for a paired exchange, and not before.
 *
 * The exchange screen used to list every open pair, which made every pair
 * fair game for a swap whether or not anyone responsible for it had proposed
 * one. Now it lists only the pairs whose own screen has had Pair Exchange
 * pressed on it.
 *
 * A flag rather than a status, because it is not one: a pair on offer is still
 * active, still on the Pairs List, still that recipient's pair. The only thing
 * that has changed is that its team have said they are open to a swap. Keeping
 * it out of `status` also keeps it clear of `paired_exchange`, which is what a
 * pair *made by* an exchange is — the two would otherwise collide, and every
 * new pair an exchange created would go straight back on offer.
 *
 * Nothing already stored is put forward: existing pairs default to 0, so the
 * exchange list starts empty and fills as pairs are offered.
 */
class AddPairForExchange extends Migration
{
    public function up(): void
    {
        if ($this->hasColumn('pairs', 'for_exchange')) {
            return;
        }

        $this->db->query('ALTER TABLE ' . $this->table('pairs')
            . ' ADD COLUMN for_exchange TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER status');
        $this->db->query('ALTER TABLE ' . $this->table('pairs')
            . ' ADD INDEX for_exchange_status (for_exchange, status)');
    }

    public function down(): void
    {
        if (! $this->hasColumn('pairs', 'for_exchange')) {
            return;
        }

        $this->db->query('ALTER TABLE ' . $this->table('pairs') . ' DROP INDEX for_exchange_status');
        $this->forge->dropColumn($this->db->prefixTable('pairs'), 'for_exchange');
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
