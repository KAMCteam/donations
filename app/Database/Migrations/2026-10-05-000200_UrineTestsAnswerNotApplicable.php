<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Two of the urine tests can be "not applicable", and now say so.
 *
 * **24h-urine for protein** and **Cr clearance** are collections, not bench
 * tests: a patient who is anuric has no urine to collect, and the answer to
 * "was it acceptable or abnormal?" is neither — it is that the question does
 * not arise. The sheet's other tests of that kind already offer the word, as
 * the cancer screening ones do; these two were asking for an answer nobody
 * could give and getting "Not done" instead, which says something different.
 *
 * `acceptable_abnormal_na` is the answer set that already exists for exactly
 * this, so the change is one of result type and nothing else. The donor's
 * sheet carries the same two tests — its Cr clearance is spelled "Creatinine
 * Clearance" — and they move with them: the same test answers the same way
 * whichever sheet it is read from.
 *
 * Catalogue rows only. A test somebody added to one record carries its own
 * answers in `labs.answer_set` and is nobody's business but that record's.
 */
class UrineTestsAnswerNotApplicable extends Migration
{
    /** As the catalogue names them, per sheet. */
    private const TESTS = ['24h-urine for protein', 'Cr clearance', 'Creatinine Clearance'];

    public function up(): void
    {
        $this->db->table('labs')
            ->whereIn('name', self::TESTS)
            ->where('person_mrn', null)
            ->where('result_type', 'acceptable_abnormal')
            ->update(['result_type' => 'acceptable_abnormal_na']);
    }

    /**
     * Irreversible by design.
     *
     * Putting the narrower set back would leave every record that answered
     * "Not applicable" holding a word its own test no longer offers, and the
     * card would show a key where an answer should be.
     */
    public function down(): void
    {
    }
}
