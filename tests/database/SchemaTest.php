<?php

use App\Database\Seeds\DatabaseSeeder;
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

        // Only the reference rows the system cannot start without.
        $this->assertCount(2, model(OrganProgramModel::class)->active());
        $this->assertSame(18, $this->db->table('labs')->countAllResults());
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
        $this->addRecipient(1001, ['urgency' => 'medium', 'entry_date' => $this->monthsAgo(40), 'dialysis_start' => $this->monthsAgo(40)]);
        $this->addRecipient(1002, ['urgency' => 'critical', 'entry_date' => $this->monthsAgo(2), 'dialysis_start' => $this->monthsAgo(2)]);
        $this->addRecipient(1003, ['urgency' => 'medium', 'entry_date' => $this->monthsAgo(60), 'dialysis_start' => $this->monthsAgo(60)]);

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

    public function testClosingAPairReleasesBothSides(): void
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

    public function testEveryStatusButClosedHoldsBothSides(): void
    {
        $this->addRecipient(1001);
        $this->addDonor(2001);
        $pairs  = model(PairModel::class);
        $pairId = $pairs->link(1001, 2001);

        foreach (['active', 'scheduled', 'on_hold', 'completed'] as $status) {
            $pairs->update($pairId, ['status' => $status]);
            $this->assertCount(0, model(RecipientModel::class)->waitingList(), "{$status} should still hold the pair");
        }

        $pairs->update($pairId, ['status' => 'closed']);
        $this->assertCount(1, model(RecipientModel::class)->waitingList());
    }

    public function testAPersonCannotBeInTwoOpenPairs(): void
    {
        $this->addRecipient(1001);
        $this->addDonor(2001);
        $this->addDonor(2002);
        $pairs = model(PairModel::class);

        $pairs->link(1001, 2001);

        $this->expectException(RuntimeException::class);
        $pairs->link(1001, 2002);
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
        model(PairModel::class)->link(1001, 2001, ['status' => 'scheduled', 'surgery_date' => '2026-10-05']);

        $rows = model(PairModel::class)->overview();
        $this->assertCount(1, $rows);

        $this->assertSame('Recipient One', $rows[0]['r_name']);
        $this->assertSame('Donor One', $rows[0]['d_name']);
        $this->assertSame('deceased', $rows[0]['d_donation_type']);
        $this->assertSame('kidney', $rows[0]['organ_code'], 'taken from the recipient');
        $this->assertSame('scheduled', $rows[0]['status']);

        // The blood-group filter matches a pair on either side.
        $this->assertCount(1, model(PairModel::class)->overview(null, null, 'A'));
        $this->assertCount(1, model(PairModel::class)->overview(null, null, 'O'));
        $this->assertCount(0, model(PairModel::class)->overview(null, null, 'AB'));
    }

    // ---- The workup ------------------------------------------------------

    public function testTheWorkupComesFromTheCatalogue(): void
    {
        $labs = model(LabModel::class);

        // Each side of each programme gets the tests marked for it plus `both`.
        $this->assertCount(6, $labs->workupFor('kidney', 'recipient'));
        $this->assertCount(8, $labs->workupFor('kidney', 'donor'));
        $this->assertCount(7, $labs->workupFor('liver', 'recipient'));
        $this->assertCount(9, $labs->workupFor('liver', 'donor'));

        $names = array_column($labs->workupFor('kidney', 'donor'), 'name');
        $this->assertContains('Renal CT Angiogram', $names, 'donor-only test');
        $this->assertNotContains('Renal CT Angiogram', array_column($labs->workupFor('kidney', 'recipient'), 'name'));
    }

    public function testAnUnrecordedTestStillComesBackAsPending(): void
    {
        $this->addRecipient(1001);
        $results = model(LabResultModel::class);

        $workup = $results->workupFor(1001, 'recipient', 'kidney');
        $this->assertCount(6, $workup);
        $this->assertSame('pending', $workup[0]['status'], 'no row yet, still a pending card');
        $this->assertNull($workup[0]['result_id']);
    }

    public function testRecordingAResultAndTheProgressItDrives(): void
    {
        $this->addRecipient(1001);
        $results = model(LabResultModel::class);
        $labId   = (int) model(LabModel::class)->workupFor('kidney', 'recipient')[0]['id'];

        $results->record(1001, 'recipient', $labId, [
            'status' => 'completed', 'value' => 'eGFR 8', 'taken_on' => '2026-09-01',
        ]);

        $this->assertSame(['done' => 1, 'total' => 1, 'pct' => 100], $results->progressFor(1001, 'recipient'));

        // Recording again replaces, never duplicates: the bar counts rows.
        $results->record(1001, 'recipient', $labId, ['status' => 'flagged', 'value' => 'eGFR 5']);
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

        $results->record(1001, 'recipient', $asRecipient, ['status' => 'completed']);
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

        $results->record(1001, 'recipient', (int) model(LabModel::class)->workupFor('kidney', 'recipient')[0]['id'], ['status' => 'completed']);
        $results->record(1001, 'donor', (int) model(LabModel::class)->workupFor('liver', 'donor')[0]['id'], ['status' => 'completed']);
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
