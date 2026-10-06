<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\LabProgress;
use App\Libraries\UiStore;
use App\Models\CoordinatorModel;
use App\Models\DonorModel;
use App\Models\LabModel;
use App\Models\LabResultModel;
use App\Models\MrpModel;
use App\Models\OrganProgramModel;
use App\Models\PairModel;
use App\Models\RecipientModel;
use App\Models\StaffModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * The schema, checked against the two things it exists to carry: the waiting-
 * list score, and the pair-linking rules.
 *
 * MySQL/MariaDB only — ENUMs, triggers and TIMESTAMPDIFF — so it skips unless
 * the `tests` group points at MySQLi:
 *
 *     database.tests.hostname = 127.0.0.1
 *     database.tests.database = donations_test
 *     database.tests.username = ...
 *     database.tests.password = ...
 *     database.tests.DBDriver = MySQLi
 *     database.tests.DBPrefix =
 *
 * @internal
 */
final class SchemaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db->DBDriver !== 'MySQLi') {
            $this->markTestSkipped('This schema is MySQL-specific; the tests group uses ' . $this->db->DBDriver . '.');
        }
    }

    // ---- Nothing is shipped but reference data ---------------------------

    public function testAFreshDatabaseHoldsNoPeople(): void
    {
        foreach (['recipients', 'donors', 'pairs', 'lab_results', 'staff', 'mrp', 'coordinators'] as $table) {
            $this->assertSame(0, $this->db->table($table)->countAllResults(), "{$table} should start empty");
        }

        // Only the reference rows the system cannot start without: the two
        // programmes, and the check list's workup for each.
        $this->assertCount(2, model(OrganProgramModel::class)->active());
        // Eight group headings on the recipient's sheet, six on the donor's.
        // The sheet's own groups, plus Transplant Clinic and Other, which
        // close both of them.
        $this->assertCount(10, $this->db->table('lab_parents')->where('person_type', 'recipient')->get()->getResultArray());
        $this->assertCount(8, $this->db->table('lab_parents')->where('person_type', 'donor')->get()->getResultArray());
        $this->assertSame(
            $this->db->table('labs')->where('organ_code', 'kidney')->countAllResults(),
            $this->db->table('labs')->where('organ_code', 'liver')->countAllResults(),
            'the check list names no organ, so both programmes carry it'
        );
    }

    // ---- The score -------------------------------------------------------

    /**
     * A tenth of a point per month waiting plus a tenth per month on dialysis:
     * 20 months on the list and 30 on dialysis is 2.0 + 3.0.
     */
    public function testScoreIsMonthsWaitingPlusMonthsOnDialysis(): void
    {
        $this->addRecipient(1001, ['entry_date' => $this->monthsAgo(20), 'dialysis_start' => $this->monthsAgo(30)]);

        $row = model(RecipientModel::class)->withScore(1001);
        $this->assertEqualsWithDelta(5.0, (float) $row['score'], 0.001);
    }

    public function testScoreIsNullWithoutADialysisDate(): void
    {
        $this->addRecipient(1002, ['entry_date' => $this->monthsAgo(12), 'dialysis_start' => null]);

        $row = model(RecipientModel::class)->withScore(1002);

        // NULL plus a number is NULL in SQL. Kept deliberately — the waiting
        // half on its own is there for anyone who needs a number.
        $this->assertNull($row['score']);
        $this->assertEqualsWithDelta(1.2, (float) $row['score_waiting_only'], 0.001);
    }

    public function testScoreCountsUpOnItsOwn(): void
    {
        $this->addRecipient(1003, ['entry_date' => $this->monthsAgo(10), 'dialysis_start' => $this->monthsAgo(10)]);
        $before = (float) model(RecipientModel::class)->withScore(1003)['score'];

        // Nothing is stored, so moving the entry date back is the same as time
        // passing: the score follows immediately.
        model(RecipientModel::class)->update(1003, ['entry_date' => $this->monthsAgo(22)]);
        $after = (float) model(RecipientModel::class)->withScore(1003)['score'];

        $this->assertEqualsWithDelta(2.0, $before, 0.001);
        $this->assertEqualsWithDelta(3.2, $after, 0.001);
    }

    public function testTheWaitingListRunsMostUrgentThenHighestScore(): void
    {
        $this->addRecipient(1001, ['is_urgent' => 0, 'entry_date' => $this->monthsAgo(40), 'dialysis_start' => $this->monthsAgo(40)]);
        $this->addRecipient(1002, ['is_urgent' => 1, 'entry_date' => $this->monthsAgo(2), 'dialysis_start' => $this->monthsAgo(2)]);
        $this->addRecipient(1003, ['is_urgent' => 0, 'entry_date' => $this->monthsAgo(60), 'dialysis_start' => $this->monthsAgo(60)]);

        $list = model(RecipientModel::class)->waitingList();

        // Urgency wins outright; score breaks the tie inside it.
        $this->assertSame([1002, 1003, 1001], array_map('intval', array_column($list, 'mrn')));
    }

    public function testTheWaitingListFiltersByProgrammeAndBloodGroup(): void
    {
        $this->addRecipient(1001, ['organ_code' => 'kidney', 'blood_group' => 'A']);
        $this->addRecipient(1002, ['organ_code' => 'kidney', 'blood_group' => 'O']);
        $this->addRecipient(1003, ['organ_code' => 'liver', 'blood_group' => 'A']);

        $this->assertCount(3, model(RecipientModel::class)->waitingList());
        $this->assertCount(2, model(RecipientModel::class)->waitingList('kidney'));
        $this->assertCount(1, model(RecipientModel::class)->waitingList('kidney', 'A'));
    }

    // ---- The linking system ----------------------------------------------

    public function testLinkingTakesBothSidesOffTheirLists(): void
    {
        $this->addRecipient(1001);
        $this->addDonor(2001);
        $recipients = model(RecipientModel::class);
        $donors     = model(DonorModel::class);

        $this->assertCount(1, $recipients->waitingList());
        $this->assertCount(1, $donors->register(null, true));

        model(PairModel::class)->link(1001, 2001, ['relationship' => 'Brother']);

        $this->assertCount(0, $recipients->waitingList(), 'the recipient leaves the waiting list');
        $this->assertCount(0, $donors->register(null, true), 'the donor leaves the register');
        $this->assertTrue($recipients->isMatched(1001));
        $this->assertTrue($donors->isMatched(2001));
    }

    public function testEndingAPairReleasesBothSides(): void
    {
        $this->addRecipient(1001);
        $this->addDonor(2001);
        $pairs = model(PairModel::class);

        $pairId = $pairs->link(1001, 2001);
        $this->assertCount(0, model(RecipientModel::class)->waitingList());

        $pairs->close($pairId, 'Crossmatch positive');

        $this->assertCount(1, model(RecipientModel::class)->waitingList(), 'and comes back');
        $this->assertCount(1, model(DonorModel::class)->register(null, true));
        $this->assertNull($pairs->openPairFor(1001, 2001));

        // The attempt stays on the record rather than disappearing.
        $closed = $pairs->find($pairId);
        $this->assertSame('closed', $closed['status']);
        $this->assertSame('Crossmatch positive', $closed['closed_reason']);
    }

    /**
     * The word on a pair holds nobody back, and releases nobody.
     *
     * Every status a pair can wear — `closed` among them — leaves both of its
     * people in it. Only `ended_at` lets them go, which is why describing a
     * pair as Closed is a description and not an ending.
     */
    public function testNoStatusAtAllReleasesEitherSide(): void
    {
        $this->addRecipient(1001);
        $this->addDonor(2001);
        $pairs  = model(PairModel::class);
        $pairId = $pairs->link(1001, 2001);

        $statuses = ['active', 'confirmed', 'on_hold', 'completed', 'pending', 'paired_exchange', 'declined', 'closed'];

        foreach ($statuses as $status) {
            $pairs->update($pairId, ['status' => $status]);
            $this->assertCount(0, model(RecipientModel::class)->waitingList(), "{$status} should still hold the pair");
            $this->assertCount(0, model(DonorModel::class)->register(null, true), "{$status} should still hold the donor");
        }

        // The ending does it, whatever word the row is left on.
        $pairs->update($pairId, ['ended_at' => date('Y-m-d H:i:s')]);
        $this->assertCount(1, model(RecipientModel::class)->waitingList());
        $this->assertCount(1, model(DonorModel::class)->register(null, true));
    }

    /**
     * A recipient may hold several donors; a donor may not hold several
     * recipients.
     *
     * Donors are looked at one after another, and sometimes together, so the
     * recipient's half is not exclusive. A donor promised to two recipients is
     * a thing the register should not be able to say, so theirs is.
     */
    public function testARecipientMayHoldSeveralDonorsButNotTheOtherWayRound(): void
    {
        $this->addRecipient(1001);
        $this->addRecipient(1002);
        $this->addDonor(2001);
        $this->addDonor(2002);
        $pairs = model(PairModel::class);

        $pairs->link(1001, 2001);
        $pairs->link(1001, 2002);

        $this->assertCount(2, $pairs->pairsForRecipient(1001));

        $this->expectException(RuntimeException::class);
        $pairs->link(1002, 2001);
    }

    /** The same two, twice, is a duplicate rather than a second opinion. */
    public function testTheSamePairCannotBeMadeTwice(): void
    {
        $this->addRecipient(1003);
        $this->addDonor(2003);
        $pairs = model(PairModel::class);

        $pairs->link(1003, 2003);

        $this->expectException(RuntimeException::class);
        $pairs->link(1003, 2003);
    }

    public function testAReleasedPersonCanBePairedAgain(): void
    {
        $this->addRecipient(1001);
        $this->addDonor(2001);
        $this->addDonor(2002);
        $pairs = model(PairModel::class);

        $first = $pairs->link(1001, 2001);
        $pairs->close($first, 'Donor withdrew');

        $second = $pairs->link(1001, 2002);
        $this->assertNotSame($first, $second);
        $this->assertNotNull($pairs->openPairFor(1001, 2002));
        // Both attempts are on the record.
        $this->assertSame(2, $this->db->table('pairs')->where('recipient_mrn', 1001)->countAllResults());
    }

    public function testAPairCannotNameSomebodyWhoIsNotThere(): void
    {
        $this->addRecipient(1001);

        $this->expectException(Throwable::class);
        model(PairModel::class)->link(1001, 9999);
    }

    public function testARecipientCannotBeFiledAsTheDonorHalf(): void
    {
        $this->addRecipient(1001);
        $this->addRecipient(1002);

        // 1002 is a recipient, so it is not in the donors register.
        $this->expectException(Throwable::class);
        model(PairModel::class)->link(1001, 1002);
    }

    public function testAPairedPersonCannotBeDeleted(): void
    {
        $this->addRecipient(1001);
        $this->addDonor(2001);
        model(PairModel::class)->link(1001, 2001);

        $this->expectException(Throwable::class);
        $this->db->table('recipients')->where('mrn', 1001)->delete();
    }

    public function testCorrectingAnMrnFollowsThroughToThePair(): void
    {
        $this->addRecipient(1001);
        $this->addDonor(2001);
        model(PairModel::class)->link(1001, 2001);

        $this->db->table('recipients')->where('mrn', 1001)->update(['mrn' => 1010]);

        $this->assertSame(1010, (int) $this->db->table('pairs')->get()->getRowArray()['recipient_mrn']);
    }

    public function testThePairsOverviewJoinsBothSides(): void
    {
        $this->addRecipient(1001, ['name' => 'Recipient One', 'blood_group' => 'A']);
        $this->addDonor(2001, ['name' => 'Donor One', 'blood_group' => 'O', 'donation_type' => 'deceased']);
        model(PairModel::class)->link(1001, 2001, ['status' => 'confirmed', 'surgery_date' => '2026-10-05']);

        $rows = model(PairModel::class)->overview();
        $this->assertCount(1, $rows);

        $this->assertSame('Recipient One', $rows[0]['r_name']);
        $this->assertSame('Donor One', $rows[0]['d_name']);
        $this->assertSame('deceased', $rows[0]['d_donation_type']);
        $this->assertSame('kidney', $rows[0]['organ_code'], 'taken from the recipient');
        $this->assertSame('confirmed', $rows[0]['status']);

        // The blood-group filter matches a pair on either side.
        $this->assertCount(1, model(PairModel::class)->overview(null, null, 'A'));
        $this->assertCount(1, model(PairModel::class)->overview(null, null, 'O'));
        $this->assertCount(0, model(PairModel::class)->overview(null, null, 'AB'));
    }

    // ---- The workup ------------------------------------------------------

    public function testTheWorkupComesFromTheCatalogue(): void
    {
        $labs = model(LabModel::class);

        // Each side of each programme gets its own sheet's tests, and only
        // those: 72 on the recipient's sheet and 52 on the donor's, plus the
        // clinic's two appointments. Other is a heading with nothing under it
        // until a record adds something, so it contributes none.
        $this->assertCount(74, $labs->workupFor('kidney', 'recipient'));
        $this->assertCount(54, $labs->workupFor('kidney', 'donor'));
        $this->assertCount(74, $labs->workupFor('liver', 'recipient'));
        $this->assertCount(54, $labs->workupFor('liver', 'donor'));

        $recipientNames = array_column($labs->workupFor('kidney', 'recipient'), 'name');
        $donorNames     = array_column($labs->workupFor('kidney', 'donor'), 'name');

        // On both sheets, so a row on each — and neither side sees the
        // other's, which is what makes the two orders and spellings possible.
        $this->assertContains('Cross match', $recipientNames);
        $this->assertContains('Cross match', $donorNames);

        // The same test, spelled the way each sheet spells it.
        $this->assertContains('Calcium/Phosphorus/Mg', $recipientNames);
        $this->assertContains('Ca/Phos/Mg', $donorNames);
        $this->assertNotContains('Ca/Phos/Mg', $recipientNames);
        $this->assertNotContains('Calcium/Phosphorus/Mg', $donorNames);

        // On the recipient's sheet alone.
        $this->assertContains('PRA', $recipientNames);
        $this->assertNotContains('PRA', $donorNames);

        // And on the donor's alone.
        $this->assertContains('Advocate', $donorNames);
        $this->assertNotContains('Advocate', $recipientNames);

        // The workup arrives grouped, in the order the check list lists them.
        $groups = array_values(array_unique(array_column($labs->workupFor('kidney', 'recipient'), 'parent_name')));
        $this->assertSame([
            'Immunology tests',
            'Hematology/Biochemistry',
            'Infectious workup',
            'Urine/Stool',
            'Cancer screening',
            'Imaging',
            'Referrals and Clearances',
            'Vaccinations',
            'Transplant Clinic',
        ], $groups);

        // The donor's sheet has its own headings, shorter and fewer.
        $donorGroups = array_values(array_unique(array_column($labs->workupFor('kidney', 'donor'), 'parent_name')));
        $this->assertSame([
            'Immunology',
            'Hematology/Biochem',
            'Infectious workup',
            'Urine/Stool',
            'Imaging',
            'Clearances',
            'Transplant Clinic',
        ], $donorGroups);
    }

    /** Two tests that close the group they are in, on both sheets. */
    public function testTheSheetsPutTheseTestsLast(): void
    {
        $labs = model(LabModel::class);

        $inGroup = static function (array $workup, string $group): array {
            $rows = array_filter($workup, static fn (array $r): bool => $r['parent_name'] === $group);

            return array_column($rows, 'name');
        };

        $recipient = $labs->workupFor('kidney', 'recipient');
        $referrals = $inGroup($recipient, 'Referrals and Clearances');
        $jabs      = $inGroup($recipient, 'Vaccinations');

        $this->assertSame('Anaesthesia', end($referrals));
        $this->assertSame('Pneumococcal 13', end($jabs));

        // The donor's sheet calls the group Clearances, and closes it the same.
        $clearances = $inGroup($labs->workupFor('kidney', 'donor'), 'Clearances');
        $this->assertSame('Anaesthesia', end($clearances));
    }

    public function testATestOffersOnlyTheAnswersItsSheetPrints(): void
    {
        $types = array_column(
            model(LabModel::class)->workupFor('kidney', 'recipient'),
            'result_type',
            'name'
        );

        // Not applicable is where the sheet puts it — on a test a patient's
        // sex or history can rule out — and nowhere else.
        $this->assertSame('acceptable_abnormal_na', $types['B-HCG']);
        $this->assertSame('acceptable_abnormal_na', $types['Mammogram']);
        $this->assertSame('acceptable_abnormal', $types['CBC'], 'asked of everyone, so it cannot not apply');
        $this->assertNotContains('not_applicable', UiStore::RESULT_OPTIONS['acceptable_abnormal']);
        $this->assertContains('not_applicable', UiStore::RESULT_OPTIONS['acceptable_abnormal_na']);

        // The imaging is asked of everyone, on both sheets.
        $donorTypes = array_column(
            model(LabModel::class)->workupFor('kidney', 'donor'),
            'result_type',
            'name'
        );

        foreach ([$types, $donorTypes] as $sheet) {
            foreach (['CXR', 'ECG', 'Echo'] as $test) {
                $this->assertSame('acceptable_abnormal', $sheet[$test], $test . ' cannot not apply');
            }
        }

        // The two urine collections can: an anuric patient has no urine to
        // collect, which is not the same as nobody having collected it.
        $this->assertSame('acceptable_abnormal_na', $types['Cr clearance']);
        $this->assertSame('acceptable_abnormal_na', $types['24h-urine for protein']);
        $this->assertSame('acceptable_abnormal_na', $donorTypes['Creatinine Clearance']);
        $this->assertSame('acceptable_abnormal_na', $donorTypes['24h-urine for protein']);

        // US KUB is off both sheets.
        $this->assertArrayNotHasKey('US KUB', $types);
        $this->assertArrayNotHasKey('US KUB', $donorTypes);

        // The two Dopplers differ from each other by exactly one answer.
        foreach ([$types, $donorTypes] as $sheet) {
            $this->assertSame('acceptable_abnormal', $sheet['Ultrasound Doppler Renal Transplant']);
            $this->assertSame('acceptable_abnormal_na', $sheet['Ultrasound Doppler Abdomen Complete']);
        }

        // The clinic's two appointments are answered Seen or Not seen.
        $this->assertSame('seen_not_seen', $types['Transplant Nephrology Clinic']);
        $this->assertSame('seen_not_seen', $types['Transplant Surgery Clinic']);

        // Other is a heading now, not a test: the check list seeds nothing
        // under it, and what a record adds there offers every answer.
        $this->assertArrayNotHasKey('Other', $types);
        $this->assertSame([], UiStore::RESULT_OPTIONS['free_text']);
        $this->assertCount(17, UiStore::RESULT_OPTIONS['custom']);

        // A vaccination that was not given says so; "Not done" beside
        // "Not given" was the same answer under two names, so it is not
        // offered. The record still holds `not_done` until one of the four
        // is pressed — that is how the workup knows it is outstanding — but
        // the card shows no answer rather than one it cannot give.
        $this->assertSame('given_not_given', $types['MMR']);
        $this->assertNotContains('not_done', UiStore::RESULT_OPTIONS['given_not_given']);
        $this->assertFalse(UiStore::offersAnswer('given_not_given', 'not_done'));
        $this->assertContains('not_done', UiStore::RESULT_UNANSWERED);

        // Every other test that asks something starts where nobody has looked.
        foreach (UiStore::RESULT_OPTIONS as $vocabulary => $answers) {
            if ($answers === [] || $vocabulary === 'given_not_given') {
                continue;
            }

            $this->assertSame('not_done', $answers[0], "{$vocabulary} should start at Not done");
        }
    }

    /**
     * A card with nothing to answer is not something to complete.
     *
     * Counting Other would hold the bar under 100% for ever, on a card
     * nobody can ever tick.
     */
    public function testTheProgressBarLeavesTheFreeTextCardOut(): void
    {
        $workup = array_map(
            static fn (array $row): array => [
                'resultType' => $row['result_type'],
                'status'     => 'not_done',
            ],
            model(LabModel::class)->workupFor('kidney', 'recipient')
        );

        $this->assertCount(74, $workup);
        $this->assertSame(74, LabProgress::counted($workup)['total'], 'the catalogue has no free-text card left');

        // The rule itself, on a card that does have nothing to answer.
        $withBox = array_merge($workup, [['resultType' => 'free_text', 'status' => 'not_done']]);
        $this->assertCount(75, $withBox);
        $this->assertSame(74, LabProgress::counted($withBox)['total']);
    }

    public function testAnUnrecordedTestStillComesBackAsNotDone(): void
    {
        $this->addRecipient(1001);
        $results = model(LabResultModel::class);

        $workup = $results->workupFor(1001, 'recipient', 'kidney');
        $this->assertCount(74, $workup);
        $this->assertSame('not_done', $workup[0]['status'], 'no row yet, so nobody has looked');
        $this->assertNull($workup[0]['result_id']);
    }

    public function testRecordingAResultAndTheProgressItDrives(): void
    {
        $this->addRecipient(1001);
        $results = model(LabResultModel::class);
        $labId   = (int) model(LabModel::class)->workupFor('kidney', 'recipient')[0]['id'];

        // The first test on the sheet is Blood group, which answers with one.
        $results->record(1001, 'recipient', $labId, [
            'status' => 'blood_o', 'value' => 'O positive', 'taken_on' => '2026-09-01',
        ]);

        $this->assertSame(['done' => 1, 'total' => 1, 'pct' => 100], $results->progressFor(1001, 'recipient'));

        // Recording again replaces, never duplicates: the bar counts rows.
        // Back to `pending` and it is unanswered again.
        $results->record(1001, 'recipient', $labId, ['status' => 'pending', 'value' => null]);
        $this->assertSame(1, $this->db->table('lab_results')->countAllResults());
        $this->assertSame(['done' => 0, 'total' => 1, 'pct' => 0], $results->progressFor(1001, 'recipient'));
    }

    public function testTheSamePersonKeepsTheirTwoRolesApart(): void
    {
        $this->addRecipient(1001);
        $this->addDonor(1001, ['organ_code' => 'liver']);
        $results = model(LabResultModel::class);

        $asRecipient = (int) model(LabModel::class)->workupFor('kidney', 'recipient')[0]['id'];
        $asDonor     = (int) model(LabModel::class)->workupFor('liver', 'donor')[0]['id'];

        $results->record(1001, 'recipient', $asRecipient, ['status' => 'blood_o']);
        $results->record(1001, 'donor', $asDonor, ['status' => 'pending']);

        $this->assertSame(1, $results->progressFor(1001, 'recipient')['done']);
        $this->assertSame(0, $results->progressFor(1001, 'donor')['done']);
    }

    /** `person_mrn` has no foreign key, so triggers do the cascade. */
    public function testDeletingSomeoneTakesTheirResultsWithThem(): void
    {
        $this->addRecipient(1001);
        $this->addDonor(1001, ['organ_code' => 'liver']);
        $results = model(LabResultModel::class);

        $results->record(1001, 'recipient', (int) model(LabModel::class)->workupFor('kidney', 'recipient')[0]['id'], ['status' => 'blood_o']);
        $results->record(1001, 'donor', (int) model(LabModel::class)->workupFor('liver', 'donor')[0]['id'], ['status' => 'blood_o']);
        $this->assertSame(2, $this->db->table('lab_results')->countAllResults());

        $this->db->table('recipients')->where('mrn', 1001)->delete();

        // Only the recipient-side result goes; the donor row is a different
        // person as far as the register is concerned.
        $this->assertSame(0, $this->db->table('lab_results')->where('person_type', 'recipient')->countAllResults());
        $this->assertSame(1, $this->db->table('lab_results')->where('person_type', 'donor')->countAllResults());
    }

    /**
     * The screens still build their lab cards from
     * `UiStore::defaultLabTests()` rather than reading `labs`, so the two hold
     * the same workup twice. Until that method is pointed at the table, this
     * keeps them from drifting apart.
     */
    public function testTheCatalogueAndTheHardcodedWorkupAgree(): void
    {
        foreach (['kidney', 'liver'] as $organ) {
            foreach (['recipient', 'donor'] as $personType) {
                $fromTable = array_column(model(LabModel::class)->workupFor($organ, $personType), 'name');
                $inCode    = array_column(UiStore::defaultLabTests($organ, $personType), 'name');

                sort($fromTable);
                sort($inCode);

                $this->assertSame(
                    $inCode,
                    $fromTable,
                    "{$organ}/{$personType}: the labs table and UiStore::defaultLabTests() disagree"
                );
            }
        }
    }

    // ---- The directory ---------------------------------------------------

    public function testAProgrammeCannotBeInventedOnARecord(): void
    {
        $this->expectException(Throwable::class);
        model(RecipientModel::class)->insert([
            'mrn' => 1001, 'name' => 'x', 'organ_code' => 'pancreas',
            'blood_group' => 'A', 'entry_date' => date('Y-m-d'),
        ]);
    }

    public function testStaffAuthenticateAgainstAHash(): void
    {
        $staff = model(StaffModel::class);
        $staff->insert([
            'staff_id'      => 'DR-00421',
            'name'          => 'Test Staff',
            'password_hash' => password_hash('correct horse battery staple', PASSWORD_DEFAULT),
            'role'          => 'admin',
        ]);

        $this->assertNotNull($staff->authenticate('DR-00421', 'correct horse battery staple'));
        $this->assertNull($staff->authenticate('DR-00421', 'wrong'));
        $this->assertNull($staff->authenticate('NOBODY', 'correct horse battery staple'));
    }

    public function testDeactivatedDirectoryRowsDropOutOfTheDropdowns(): void
    {
        model(MrpModel::class)->insert(['code' => 'MRP-001', 'name' => 'A Physician']);
        model(MrpModel::class)->insert(['code' => 'MRP-002', 'name' => 'Retired', 'is_active' => 0]);
        model(CoordinatorModel::class)->insert(['name' => 'A Coordinator']);

        $this->assertCount(1, model(MrpModel::class)->active());
        $this->assertCount(1, model(CoordinatorModel::class)->active());
    }

    // ---- helpers ---------------------------------------------------------

    private function monthsAgo(int $months): string
    {
        return date('Y-m-d', strtotime("-{$months} months"));
    }

    /** @param array<string, mixed> $overrides */
    private function addRecipient(int $mrn, array $overrides = []): void
    {
        model(RecipientModel::class)->insert(array_merge([
            'mrn'         => $mrn,
            'name'        => 'Recipient ' . $mrn,
            'organ_code'  => 'kidney',
            'blood_group' => 'A',
            'entry_date'  => date('Y-m-d'),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function addDonor(int $mrn, array $overrides = []): void
    {
        model(DonorModel::class)->insert(array_merge([
            'mrn'         => $mrn,
            'name'        => 'Donor ' . $mrn,
            'organ_code'  => 'kidney',
            'blood_group' => 'A',
        ], $overrides));
    }
}
