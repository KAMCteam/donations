<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Takes the paired-exchange fixture back out.
 *
 *     php spark db:seed ExchangeDemoRemover
 *
 * Deletes exactly the 990000 block {@see ExchangeDemoSeeder} writes, and
 * nothing else — pairs first, since a pair names people who cannot be deleted
 * while it does. Lab results go with their people through the triggers on
 * `recipients` and `donors`.
 *
 * Safe to run twice, and safe to run when the fixture was never seeded.
 */
class ExchangeDemoRemover extends Seeder
{
    public function run(): void
    {
        $from = ExchangeDemoSeeder::MRN_FROM;
        $to   = ExchangeDemoSeeder::MRN_TO;

        $this->db->table('pairs')
            ->groupStart()->where('recipient_mrn >=', $from)->where('recipient_mrn <=', $to)->groupEnd()
            ->orGroupStart()->where('donor_mrn >=', $from)->where('donor_mrn <=', $to)->groupEnd()
            ->delete();

        foreach (['recipients', 'donors'] as $table) {
            $this->db->table($table)->where('mrn >=', $from)->where('mrn <=', $to)->delete();
        }
    }
}
