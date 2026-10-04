<?php

namespace App\Database\Seeds;

use App\Models\LabModel;
use CodeIgniter\Database\Seeder;

/**
 * Made-up people, for trying the donor tabs and the added tests out.
 *
 *     php spark db:seed MultiDonorDemoSeeder     # put them in
 *     php spark db:seed MultiDonorDemoRemover    # take them out again
 *
 * Deliberately not part of `DatabaseSeeder`, which seeds no people at all:
 * this is a patient register, and invented patients do not belong in one by
 * accident. Nothing runs this but somebody typing it.
 *
 * Every record is fictional and every MRN is in the 980000 block, which no
 * real file number uses and which nothing else writes to — the paired-exchange
 * fixture has 990000 to itself. `MultiDonorDemoRemover` deletes exactly this
 * block, so the two can be put in and taken out independently.
 *
 * What there is to look at:
 *
 *   980101  four donors, one of each ending — the Active one, one On Hold,
 *           one Declined, and one already archived and greyed. This is a
 *           pair's tabs with everything on them at once.
 *   980201  two donors, one Active and one On Hold: the ordinary case, and
 *           the one to press Swap on to watch the two change places.
 *   980301  one donor, and three tests added to the record under Other, so
 *           the Add lab card can be seen holding real answers.
 *   980401  no donors at all, to see a recipient with nothing but the
 *           Link with Donor button.
 *
 * Nothing here is offered for exchange: this fixture is about one recipient's
 * donors, and the exchange screen has a fixture of its own.
 */
class MultiDonorDemoSeeder extends Seeder
{
    /** The block these records live in, and nothing else does. */
    public const MRN_FROM = 980000;
    public const MRN_TO   = 980999;

    /**
     * [recipient mrn, name, group, [[donor mrn, name, group, relationship, status]]]
     *
     * The status is the donor's own, and one donor to a pair may hold Active —
     * that is the rule the screen is built around, so the fixture keeps it.
     * `closed` is not a status but the archived mode: the link is finished
     * with, the tab is grey, and the word the donor was given stands.
     */
    private const RECIPIENTS = [
        [980101, 'Mishal Al-Harthy', 'A', [
            [980111, 'Reem Al-Harthy',   'A',  'Sister',   'active'],
            [980112, 'Faisal Al-Harthy', 'O',  'Brother',  'on_hold'],
            [980113, 'Lama Al-Harthy',   'A',  'Daughter', 'declined'],
            [980114, 'Saad Al-Harthy',   'B',  'Cousin',   'closed'],
        ]],
        [980201, 'Ghadah Al-Shamrani', 'O', [
            [980211, 'Nouf Al-Shamrani',  'O', 'Sister', 'active'],
            [980212, 'Majed Al-Shamrani', 'O', 'Son',    'on_hold'],
        ]],
        [980301, 'Abdullah Al-Qarni', 'B', [
            [980311, 'Hind Al-Qarni', 'B', 'Wife', 'active'],
        ]],
        [980401, 'Rana Al-Otaibi', 'AB', []],
    ];

    /**
     * Tests added to 980301's own record, under Other.
     *
     * [name, answers, status, comment] — a test added under Other says what it
     * answers, so each of these is given a set of its own, and only some of
     * them a colour: an answer carries none until somebody picks one.
     */
    private const ADDED_TESTS = [
        [
            'Ultrasound Doppler Hepatic Vein',
            [['not_done', 'Not done', ''], ['acceptable', 'Acceptable', 'tone-emerald'], ['abnormal', 'Abnormal', 'tone-red']],
            'acceptable',
            'Reported normal on 12/09/2026.',
        ],
        [
            'Bone densitometry',
            [['not_done', 'Not done', ''], ['pending', 'Pending', 'tone-amber'], ['done', 'Done', '']],
            'pending',
            'Booked for next week.',
        ],
        [
            'Genetic panel (Alport)',
            [['not_done', 'Not done', ''], ['applicable', 'Applicable', ''], ['not_applicable', 'Not applicable', '']],
            'not_applicable',
            'No family history.',
        ],
    ];

    public function run(): void
    {
        $organ = 'kidney';

        foreach (self::RECIPIENTS as [$rMrn, $rName, $rGroup, $donors]) {
            $this->recipient($rMrn, $rName, $rGroup, $organ);

            foreach ($donors as [$dMrn, $dName, $dGroup, $relationship, $status]) {
                // An archived donor keeps the word they were given; the one
                // in the fixture was declined before the pair let them go.
                $donorStatus = $status === 'closed' ? 'declined' : $status;

                $this->donor($dMrn, $dName, $dGroup, $organ, $relationship, $donorStatus);

                if ($this->db->table('pairs')->getWhere(['donor_mrn' => $dMrn])->getRowArray() !== null) {
                    continue;
                }

                $this->db->table('pairs')->insert([
                    'recipient_mrn' => $rMrn,
                    'donor_mrn'     => $dMrn,
                    'status'        => $status,
                    'relationship'  => $relationship,
                    'closed_reason' => $status === 'closed' ? 'Delinked from the pair.' : null,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->addedTests(980301, $organ);
    }

    /** The three tests 980301 added to their own record, answered. */
    private function addedTests(int $mrn, string $organ): void
    {
        $parentId = model(LabModel::class)->customGroupId(DatabaseSeeder::CUSTOM_GROUP, 'recipient');

        if ($parentId === null) {
            return;
        }

        $order = model(LabModel::class)->lastSortOrder($organ, 'recipient');

        foreach (self::ADDED_TESTS as [$name, $answers, $status, $comment]) {
            if ($this->db->table('labs')->getWhere(['name' => $name, 'person_mrn' => $mrn])->getRowArray() !== null) {
                continue;
            }

            $this->db->table('labs')->insert([
                'name'          => $name,
                'lab_parent_id' => $parentId,
                'organ_code'    => $organ,
                'person_type'   => 'recipient',
                'person_mrn'    => $mrn,
                'result_type'   => 'custom',
                'answer_set'    => json_encode(array_map(
                    static fn (array $a): array => ['key' => $a[0], 'label' => $a[1], 'tone' => $a[2]],
                    $answers
                ), JSON_UNESCAPED_UNICODE),
                'sort_order'    => ++$order,
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('lab_results')->insert([
                'person_mrn'  => $mrn,
                'person_type' => 'recipient',
                'lab_id'      => $this->db->insertID(),
                'status'      => $status,
                'notes'       => $comment,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function recipient(int $mrn, string $name, string $group, string $organ): void
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
            'age'         => 32 + ($mrn % 30),
            'city'        => 'Makkah',
            'entry_date'  => date('Y-m-d'),
            'is_urgent'   => 0,
            'status'      => 'active',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function donor(int $mrn, string $name, string $group, string $organ, string $relationship, string $status): void
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
            'age'           => 26 + ($mrn % 28),
            'city'          => 'Makkah',
            'donation_type' => 'living_related',
            'relationship'  => $relationship,
            'status'        => $status,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}
