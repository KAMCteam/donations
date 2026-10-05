<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A pair ending and a pair being called Closed are two different facts.
 *
 * They were one column. `status = 'closed'` meant both "somebody chose the
 * word Closed on the Pair Details card" and "this link is over, release both
 * of them" — so choosing the word took the pair apart, and a pair taken apart
 * was indistinguishable from one somebody had simply described.
 *
 * `ended_at` is the second fact, on its own: null while the link stands,
 * stamped when it is ended. Everything that asks whether a pair is open —
 * the waiting list, the donor register, the exchange, the Pairs List — asks
 * this column now. **Closed** goes back to being a word like On Hold or
 * Declined: it describes the pair, and the pair goes on existing.
 *
 * The rows already holding `closed` were ended, every one of them: delinking
 * and taking a pair apart are what wrote it. So they are stamped, with the day
 * they were last touched, which is the day it happened.
 */
class PairsEndWhenTheyEnd extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('pairs', [
            'ended_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'after'   => 'closed_reason',
                'comment' => 'When this link was ended. NULL while it stands.',
            ],
        ]);

        // Every closed row is an ended link: nothing but delinking wrote the
        // word before this migration. `updated_at` is when it was written.
        $this->db->table('pairs')
            ->where('status', 'closed')
            ->where('ended_at', null)
            ->set('ended_at', 'COALESCE(updated_at, created_at, NOW())', false)
            ->update();
    }

    /**
     * Irreversible by design.
     *
     * Dropping the column would put the two facts back in one place, and the
     * pairs closed by hand since — which are open links — would be read as
     * ended, releasing people the register says are matched.
     */
    public function down(): void
    {
    }
}
