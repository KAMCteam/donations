<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Made-up people, for trying the paired-exchange screen out.
 *
 *     php spark db:seed ExchangeDemoSeeder     # put them in
 *     php spark db:seed ExchangeDemoRemover    # take them out again
 *
 * Deliberately not part of `DatabaseSeeder`, which seeds no people at all:
 * this is a patient register, and invented patients do not belong in one by
 * accident. Nothing runs this but somebody typing it.
 *
 * Every record here is fictional and every MRN is in the 990000 block, which
 * no real file number uses, so they are recognisable at a glance in any table
 * and removable by range. `ExchangeDemoRemover` deletes exactly this block.
 *
 * The blood groups are chosen so the screen's three endings can all be tried,
 * given that a donor gives to their own group and to AB, and O gives to all:
 *
 *   - a closed circle   — 990101 (A) needs O, 990104 (O) can give; and 990103
 *                         (O) takes A from 990102. Two pairs, swapped, done.
 *   - a spare donor     — the chain through 990111 (AB) ends on a donor nobody
 *                         compatible is left for, who must be sent back to the
 *                         register or removed.
 *   - a spare recipient — 990121 (O) can only take from an O donor; work
 *                         through the B pairs and an O recipient is left
 *                         needing one, which is what the red rule is for.
 *
 * Three donors sit on the available register and three recipients on the
 * waiting list, so a chain can also end outside the pairs entirely.
 */
class ExchangeDemoSeeder extends Seeder
{
    /** The block these records live in, and nothing else does. */
    public const MRN_FROM = 990000;
    public const MRN_TO   = 990999;

    /** [recipient mrn, name, group, donor mrn, name, group, relationship] */
    private const PAIRS = [
        [990101, 'Faisal Al-Harbi',     'A',  990102, 'Maha Al-Harbi',     'A',  'Sister'],
        [990103, 'Nadia Al-Otaibi',     'O',  990104, 'Sami Al-Otaibi',    'O',  'Brother'],
        [990105, 'Tariq Al-Ghamdi',     'B',  990106, 'Reem Al-Ghamdi',    'A',  'Wife'],
        [990107, 'Latifa Al-Shehri',    'A',  990108, 'Bandar Al-Shehri',  'B',  'Husband'],
        [990109, 'Waleed Al-Qahtani',   'O',  990110, 'Hessa Al-Qahtani',  'AB', 'Daughter'],
        [990111, 'Amal Al-Dosari',      'AB', 990112, 'Khalid Al-Dosari',  'O',  'Son'],
        [990113, 'Ibrahim Al-Zahrani',  'B',  990114, 'Sara Al-Zahrani',   'AB', 'Sister'],
        [990115, 'Munira Al-Anzi',      'A',  990116, 'Yousef Al-Anzi',    'B',  'Brother'],
    ];

    /** Donors on the register, in no pair. [mrn, name, group] */
    private const FREE_DONORS = [
        [990201, 'Rakan Al-Mutairi', 'O'],
        [990202, 'Dalal Al-Subaie',  'A'],
        [990203, 'Turki Al-Rashid',  'B'],
    ];

    /** Recipients on the waiting list, in no pair. [mrn, name, group, urgent] */
    private const FREE_RECIPIENTS = [
        [990301, 'Salma Al-Juhani', 'O',  1],
        [990302, 'Nawaf Al-Balawi', 'AB', 0],
        [990303, 'Huda Al-Maliki',  'B',  0],
    ];

    public function run(): void
    {
        $organ = 'kidney';

        foreach (self::PAIRS as [$rMrn, $rName, $rGroup, $dMrn, $dName, $dGroup, $relationship]) {
            $this->recipient($rMrn, $rName, $rGroup, $organ, 0);
            $this->donor($dMrn, $dName, $dGroup, $organ, $relationship);

            if ($this->db->table('pairs')->getWhere(['recipient_mrn' => $rMrn])->getRowArray() !== null) {
                continue;
            }

            $this->db->table('pairs')->insert([
                'recipient_mrn' => $rMrn,
                'donor_mrn'     => $dMrn,
                'status'        => 'paired_exchange',
                // Already put forward: that is the point of the fixture.
                'for_exchange'  => 1,
                'relationship'  => $relationship,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            // The pair is what is in a paired exchange; the person is Active.
            $this->db->table('recipients')->where('mrn', $rMrn)->update(['status' => 'active']);
        }

        foreach (self::FREE_DONORS as [$mrn, $name, $group]) {
            $this->donor($mrn, $name, $group, $organ, null);
        }

        foreach (self::FREE_RECIPIENTS as [$mrn, $name, $group, $urgent]) {
            $this->recipient($mrn, $name, $group, $organ, $urgent);
        }
    }

    private function recipient(int $mrn, string $name, string $group, string $organ, int $urgent): void
    {
        if ($this->db->table('recipients')->getWhere(['mrn' => $mrn])->getRowArray() !== null) {
            return;
        }

        $this->db->table('recipients')->insert([
            'mrn'         => $mrn,
            'name'        => $name,
            'organ_code'  => $organ,
            'blood_group' => $group,
            'gender'      => $mrn % 2 === 1 ? 'male' : 'female',
            'age'         => 30 + ($mrn % 35),
            'city'        => 'Makkah',
            'entry_date'  => date('Y-m-d'),
            'is_urgent'   => $urgent,
            'status'      => 'on_hold',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function donor(int $mrn, string $name, string $group, string $organ, ?string $relationship): void
    {
        if ($this->db->table('donors')->getWhere(['mrn' => $mrn])->getRowArray() !== null) {
            return;
        }

        $this->db->table('donors')->insert([
            'mrn'           => $mrn,
            'name'          => $name,
            'organ_code'    => $organ,
            'blood_group'   => $group,
            'gender'        => $mrn % 2 === 1 ? 'male' : 'female',
            'age'           => 25 + ($mrn % 30),
            'city'          => 'Makkah',
            'donation_type' => $relationship === null ? 'living' : 'living_related',
            'relationship'  => $relationship,
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}
