<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `pairs` — the link between a recipient and a donor.
 *
 * The linking system in full:
 *
 * 1. A pair names one recipient and one donor, each by a foreign key into its
 *    own register. A recipient's MRN cannot be filed as the donor half, and
 *    neither side can name somebody who does not exist.
 *
 * 2. `status` decides whether the link is live. Every status except `closed`
 *    means the two are spoken for, which is what "unmatched" is defined
 *    against: a recipient is on the waiting list when no pair holds them in a
 *    status other than `closed`, and the same for a donor on the register.
 *
 * 3. Closing a pair therefore releases both sides back onto their lists, and
 *    they can be paired again — with each other or with somebody else. That is
 *    why `closed` is a status rather than a deleted row: the attempt stays on
 *    the record.
 *
 * 4. `ON DELETE RESTRICT` on both sides: deleting someone who is half of a
 *    pair fails loudly instead of quietly dropping the match. Close the pair
 *    first.
 *
 * There is no database-level "one open pair per couple" index. The natural way
 * to write it is a unique index over a generated column that goes NULL once
 * closed, but MySQL rejects a generated column that reads a column belonging
 * to an `ON UPDATE CASCADE` foreign key — and both MRN columns do, so that a
 * corrected MRN still follows through to the pair. The cascade is worth more;
 * the rule lives in `PairModel::openPairFor()`, which is checked before a pair
 * is created.
 */
class CreatePairs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
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
            // active     linked and being worked up
            // scheduled  surgery has a date
            // on_hold    paused, but still spoken for
            // completed  transplanted
            // closed     the only status that frees both sides again
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'scheduled', 'on_hold', 'completed', 'closed'],
                'default'    => 'active',
            ],
            // How the two are related, as recorded for this pair.
            'relationship' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'crossmatch_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'surgery_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            // Why the pair was closed, when it was — kept because a closed
            // pair is history, not a mistake.
            'closed_reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'notes' => [
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

        $this->forge->addPrimaryKey('id');
        // The three lookups the linking system makes: is this recipient
        // spoken for, is this donor spoken for, and list pairs by status.
        $this->forge->addKey(['recipient_mrn', 'status']);
        $this->forge->addKey(['donor_mrn', 'status']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('recipient_mrn', 'recipients', 'mrn', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('donor_mrn', 'donors', 'mrn', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('pairs', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('pairs', true);
    }
}
