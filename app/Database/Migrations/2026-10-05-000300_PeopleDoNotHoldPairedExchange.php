<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nobody is "in a paired exchange". Their case is.
 *
 * Saving an exchange used to write **Paired Exchange** onto the recipient as
 * well as onto the pair, back when a person's status and their pair's were
 * meant to agree. They are separate facts now — a person holds On Hold,
 * Active, Declined or Transplanted, and Paired Exchange is one of the two the
 * pair keeps to itself — and nothing writes it to a person any more.
 *
 * The rows written before that stopped are still holding it, and it is not a
 * word the screens offer, so nobody can take it off by hand. Worse, the
 * exchange's own lists now ask for people who are **Active**: a recipient left
 * holding the pair's word is a recipient the next exchange cannot see, which
 * is the opposite of what the word was recording — that they are in one.
 *
 * So: Active, which is what it was always saying about the person. Only that
 * value moves; every other status is somebody's answer and is left alone.
 */
class PeopleDoNotHoldPairedExchange extends Migration
{
    public function up(): void
    {
        $this->db->table('recipients')
            ->where('status', 'paired_exchange')
            ->update(['status' => 'active']);
    }

    /**
     * Irreversible by design.
     *
     * Putting the word back would mean deciding which Active recipients had
     * once been written over, and nothing records that. The pairs still say
     * which cases are in an exchange, which is where it was always true.
     */
    public function down(): void
    {
    }
}
