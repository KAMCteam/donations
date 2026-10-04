<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Takes the donor-tabs fixture back out.
 *
 *     php spark db:seed MultiDonorDemoRemover
 *
 * Deletes exactly the 980000 block {@see MultiDonorDemoSeeder} writes, and
 * nothing else — pairs first, since a pair names people who cannot be deleted
 * while it does, and the tests a record added with them, since those belong to
 * the record rather than to the catalogue.
 *
 * Safe to run twice, and safe to run when the fixture was never seeded.
 */
class MultiDonorDemoRemover extends Seeder
{
    public function run(): void
    {
        $from = MultiDonorDemoSeeder::MRN_FROM;
        $to   = MultiDonorDemoSeeder::MRN_TO;

        foreach (['pairs'] as $table) {
            $this->db->table($table)
                ->groupStart()->where('recipient_mrn >=', $from)->where('recipient_mrn <=', $to)->groupEnd()
                ->orGroupStart()->where('donor_mrn >=', $from)->where('donor_mrn <=', $to)->groupEnd()
                ->delete();
        }

        // Their own tests go with them; lab_results follows through the
        // foreign key on lab_id.
        $this->db->table('labs')->where('person_mrn >=', $from)->where('person_mrn <=', $to)->delete();

        foreach (['recipients', 'donors'] as $table) {
            $this->db->table($table)->where('mrn >=', $from)->where('mrn <=', $to)->delete();
        }
    }
}
