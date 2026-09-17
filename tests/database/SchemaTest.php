<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\UiStore;
use App\Models\CoordinatorsModel;
use App\Models\LabsModel;
use App\Models\ListsModel;
use App\Models\MrpModel;
use App\Models\PairsModel;
use App\Models\PatientModel;
use App\Models\QueriesModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Checks the migrated schema against the two things it has to satisfy: the
 * models kept from the CodeIgniter 3 application, and the fields the platform's
 * screens collect.
 *
 * MySQL/MariaDB only — the schema uses ENUMs, generated columns, a view and
 * TIMESTAMPDIFF, so it is skipped unless the `tests` database group points at
 * MySQLi. Set it in phpunit.xml or .env:
 *
 *     database.tests.hostname = 127.0.0.1
 *     database.tests.database = donations_test
 *     database.tests.username = ...
 *     database.tests.password = ...
 *     database.tests.DBDriver = MySQLi
 *     database.tests.DBPrefix =
 *
 * The empty prefix matters: `PairsModel`'s `NOT IN (SELECT ... FROM pairs)`
 * sub-select and `ListsModel`'s `SHOW COLUMNS FROM <table>` were carried over
 * from CodeIgniter 3 naming their tables directly, so neither survives a
 * DBPrefix. The `default` group has no prefix either, so this matches how the
 * application actually runs; the framework's own `tests` group sets `db_`.
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
            $this->markTestSkipped('The donations schema is MySQL-specific; the tests group uses ' . $this->db->DBDriver . '.');
        }
    }

    /**
     * A recipient, a second recipient with no dialysis date, and a donor —
     * each written to its own table, since `patients` is a read-only union.
     */
    private function seedPeople(): void
    {
        $this->db->table('mrp')->insert(['mrp_id' => 'MRP-001', 'name' => 'Test Physician']);
        $this->db->table('coordinators')->insert(['coordinator_name' => 'Test Coordinator']);

        $this->db->table('recipients')->insert([
            'mrn' => 1001, 'name' => 'Recipient Twenty', 'city' => 'Riyadh',
            'phone_number' => '+966500000001', 'gender' => 'M', 'age' => 42,
            'blood_group' => 'A', 'organs' => 'kidney',
            'status' => 'ready', 'urgency' => 'high', 'mrp_id' => 1, 'coordinator_id' => 1,
            'hospital' => 'KAMC', 'diagnosis' => 'ESRD',
            'dialysis' => date('Y-m-d', strtotime('-30 months')),
            'entry_date' => date('Y-m-d', strtotime('-20 months')),
            'note' => 'clinical note',
        ]);

        $this->db->table('recipients')->insert([
            'mrn' => 1002, 'name' => 'Recipient NoDialysis', 'blood_group' => 'O',
            'organs' => 'kidney', 'urgency' => 'critical',
            'entry_date' => date('Y-m-d', strtotime('-12 months')),
        ]);

        $this->db->table('donors')->insert([
            'mrn' => 2001, 'name' => 'Donor One', 'blood_group' => 'A',
            'organs' => 'kidney', 'hospital' => 'KAMC',
            'entry_date' => date('Y-m-d'), 'donation_type' => 'living',
            'relationship' => 'Brother of 1001',
        ]);
    }

    // ---- The score -------------------------------------------------------

    public function testScoreMatchesTheOriginalExpression(): void
    {
        $this->seedPeople();

        // 20 months waiting + 30 months on dialysis, at 0.1 each.
        $row = model(PairsModel::class)->get_some_unmatched_recipients('A')[0];
        $this->assertEqualsWithDelta(5.0, (float) $row['score'], 0.001);
    }

    public function testScoreKeepsItsNullWhenThereIsNoDialysisDate(): void
    {
        $this->seedPeople();

        // The original expression is NULL + number = NULL. Preserved on
        // purpose; `score_entry_only` on the view is the waiting-time half.
        $row = $this->db->table('waiting_list')->where('mrn', 1002)->get()->getRowArray();
        $this->assertNull($row['score']);
        $this->assertEqualsWithDelta(1.2, (float) $row['score_entry_only'], 0.001);
    }

    public function testUrgencyKeepsBothTheOldBooleanAndASortOrder(): void
    {
        $this->seedPeople();

        // ORDER BY urgency DESC must still mean most-urgent-first.
        $rows = $this->db->table('recipients')->orderBy('urgency', 'DESC')->get()->getResultArray();

        $this->assertSame('critical', $rows[0]['urgency']);
        $this->assertSame('1', (string) $rows[0]['urgency_rank']);
        $this->assertSame('1', (string) $rows[0]['is_urgent']);

        $this->assertSame('high', $rows[1]['urgency']);
        $this->assertSame('2', (string) $rows[1]['urgency_rank']);
        $this->assertSame('1', (string) $rows[1]['is_urgent']);
    }

    // ---- The retained models still work ----------------------------------

    public function testPatientModelReads(): void
    {
        $this->seedPeople();
        $patients = model(PatientModel::class);

        $this->assertCount(3, $patients->get_all_patients());
        $this->assertTrue($patients->patient_exists(1001));
        $this->assertSame('Recipient Twenty', $patients->get_patient_info(1001)['name']);
        $this->assertCount(1, $patients->get_some_patients(null, 'donor'));

        $info = $patients->get_patient_info_modified(1001);
        $this->assertSame('Test Physician', $info['mrp_name']);
        $this->assertArrayHasKey('labs', $info[0]);
    }

    public function testUnmatchedListsAndPairing(): void
    {
        $this->seedPeople();
        $pairs = model(PairsModel::class);

        $this->assertCount(2, $pairs->get_unmatched_recipients());
        $this->assertCount(1, $pairs->get_unmatched_donors());
        $this->assertSame('critical', $pairs->get_unmatched_recipients()[0]['urgency'], 'most urgent first');

        $pairs->insert_pair([
            'recipient_mrn' => 1001, 'donor_mrn' => 2001, 'match_status' => 'active',
            'relationship' => 'Brother', 'matched_on' => '2026-09-01', 'surgery_on' => '2026-10-05',
        ]);
        $pairId = (int) $this->db->insertID();

        $this->assertNotNull($pairs->pair_exists(1001, 2001));
        $this->assertCount(1, $pairs->get_unmatched_recipients(), 'the recipient leaves the waitlist');
        $this->assertCount(0, $pairs->get_unmatched_donors());
        $this->assertSame('2001', (string) $pairs->has_donor(1001)['donor_mrn']);
        $this->assertSame('1001', (string) $pairs->has_recipient(2001)['recipient_mrn']);
        $this->assertCount(2, $pairs->get_all_pairs_info()[0]['pair'], 'both sides expanded');
        $this->assertCount(1, $pairs->get_custom_pairs_info('A', 'active'));

        // Closing a pair frees both sides again — the NOT IN sub-selects skip
        // it, which the whole unmatched notion depends on.
        $pairs->update_pair($pairId, ['match_status' => 'closed']);
        $this->assertCount(2, $pairs->get_unmatched_recipients());
        $this->assertNull($pairs->pair_exists(1001, 2001));
    }

    public function testDashboardCounters(): void
    {
        $this->seedPeople();
        $queries = model(QueriesModel::class);

        $this->assertSame(3, $queries->number_of_patients());
        $this->assertSame(0, $queries->number_of_pairs());
    }

    public function testReferenceTableModels(): void
    {
        $this->seedPeople();

        $this->assertCount(1, model(MrpModel::class)->get_mrps());
        $this->assertCount(1, model(CoordinatorsModel::class)->get_coordinators());
        $this->assertCount(18, model(LabsModel::class)->get_labs());
    }

    // ---- The catalogue matches what the screens ask for ------------------

    /**
     * The strongest check that `labs` is complete: for each organ and person
     * type, the catalogue holds exactly the workup UiStore hands a new record.
     */
    public function testCatalogueCoversThePlatformsDefaultWorkup(): void
    {
        $labs = model(LabsModel::class);

        foreach (['kidney', 'liver'] as $organ) {
            foreach (['recipient', 'donor'] as $personType) {
                $fromCatalogue = $labs->get_custom_labs($personType, $organ);
                $fromPlatform  = UiStore::defaultLabTests($organ, $personType);

                $this->assertCount(
                    count($fromPlatform),
                    $fromCatalogue,
                    "{$organ}/{$personType}: catalogue and UiStore::defaultLabTests() disagree"
                );

                $catalogueNames = array_column($fromCatalogue, 'lab_name');

                foreach (array_column($fromPlatform, 'name') as $name) {
                    $this->assertContains($name, $catalogueNames, "{$organ}/{$personType}: {$name} missing from the catalogue");
                }
            }
        }
    }

    public function testListsModelStillDiscoversEveryDropdown(): void
    {
        $lists = model(ListsModel::class);

        $this->assertSame(['kidney', 'liver'], $lists->get_enum_values('recipients', 'organs'));
        $this->assertSame(['A', 'B', 'AB', 'O'], $lists->get_enum_values('recipients', 'blood_group'));
        $this->assertSame(['M', 'F'], $lists->get_enum_values('donors', 'gender'));
        $this->assertSame(['R_LRD', 'R_LURD', 'R_DD', 'R_PE', 'D_D'], $lists->get_enum_values('pairs', 'programs'));

        // The original five match statuses survive alongside the platform's.
        $statuses = $lists->get_enum_values('pairs', 'match_status');

        foreach (['pending', 'confirmed', 'completed', 'closed', 'paired_exchange'] as $original) {
            $this->assertContains($original, $statuses, "original match_status '{$original}' was dropped");
        }

        foreach (['active', 'scheduled', 'on_hold'] as $added) {
            $this->assertContains($added, $statuses);
        }

        $programs = $lists->get_programs();
        $this->assertCount(4, $programs['recipients']);
        $this->assertCount(1, $programs['donors']);

        $this->assertNotEmpty($lists->get_labs('donor', 'kidney'), 'labs group under their parent');
    }

    // ---- Lab results, including the new columns --------------------------

    public function testLabResultsRoundTripWithTheNewColumns(): void
    {
        $this->seedPeople();
        $labs  = model(LabsModel::class);
        $labId = (string) $labs->get_lab_ids()[0];

        $labs->insert_lab_result($labId, 1001, 'NE');
        $this->assertTrue($labs->exists($labId, 1001));

        $labs->update_lab_result($labId, 1001, 'PO');
        $row = $this->db->table('lab_results')->where(['patient_id' => 1001, 'lab_id' => $labId])->get()->getRowArray();
        $this->assertSame('PO', $row['result']);
        $this->assertSame('pending', $row['status'], 'the new status column defaults to pending');

        $this->db->table('lab_results')->where('result_id', $row['result_id'])->update([
            'status'      => 'flagged',
            'result_date' => '2026-09-01',
            'lab_comment' => 'needs review',
        ]);
        $row = $this->db->table('lab_results')->where('result_id', $row['result_id'])->get()->getRowArray();

        $this->assertSame('flagged', $row['status']);
        $this->assertSame('2026-09-01', $row['result_date']);
        $this->assertSame('needs review', $row['lab_comment']);

        // Every catalogue row, left-joined with this patient's result.
        $this->assertCount(18, $labs->get_results_by_mrn(1001));
    }

    public function testOneResultPerPatientPerLab(): void
    {
        $this->seedPeople();
        $labId = (int) model(LabsModel::class)->get_lab_ids()[0];

        $this->db->table('lab_results')->insert(['patient_id' => 1001, 'lab_id' => $labId]);

        $this->expectException(Throwable::class);
        $this->db->table('lab_results')->insert(['patient_id' => 1001, 'lab_id' => $labId]);
    }

    // ---- Referential integrity -------------------------------------------

    public function testAPairedPatientCannotBeDeleted(): void
    {
        $this->seedPeople();
        model(PairsModel::class)->insert_pair([
            'recipient_mrn' => 1001, 'donor_mrn' => 2001, 'match_status' => 'active',
        ]);

        $this->expectException(Throwable::class);
        $this->db->table('recipients')->where('mrn', 1001)->delete();
    }

    public function testAnUnknownMrpIsRejected(): void
    {
        $this->expectException(Throwable::class);
        $this->db->table('recipients')->insert([
            'mrn' => 9999, 'name' => 'x', 'blood_group' => 'A', 'organs' => 'kidney',
            'entry_date' => '2026-01-01', 'mrp_id' => 424242,
        ]);
    }

    public function testCorrectingAnMrnFollowsThroughToThePair(): void
    {
        $this->seedPeople();
        model(PairsModel::class)->insert_pair([
            'recipient_mrn' => 1001, 'donor_mrn' => 2001, 'match_status' => 'active',
        ]);

        // ON UPDATE CASCADE: this is what the pairs table gives up a unique
        // open-pair index for.
        $this->db->table('recipients')->where('mrn', 1001)->update(['mrn' => 1010]);

        $this->assertSame('1010', (string) $this->db->table('pairs')->get()->getRowArray()['recipient_mrn']);
    }

    // ---- The platform's own fields have somewhere to live ----------------

    public function testEveryPlatformFieldPersists(): void
    {
        $this->seedPeople();

        $row = $this->db->table('recipients')->where('mrn', 1001)->get()->getRowArray();

        // The columns added for the platform's screens.
        $this->assertSame('KAMC', $row['hospital']);
        $this->assertSame('ESRD', $row['diagnosis']);
        $this->assertSame('1', (string) $row['coordinator_id']);

        $donor = $this->db->table('donors')->where('mrn', 2001)->get()->getRowArray();
        $this->assertSame('living', $donor['donation_type']);
        $this->assertSame('Brother of 1001', $donor['relationship']);

        // And on the pair.
        model(PairsModel::class)->insert_pair([
            'recipient_mrn' => 1001, 'donor_mrn' => 2001,
            'match_status'  => 'scheduled', 'note' => 'awaiting cardiac clearance',
        ]);
        $pair = $this->db->table('pairs')->get()->getRowArray();

        $this->assertSame('scheduled', $pair['match_status']);
        $this->assertSame('awaiting cardiac clearance', $pair['note']);
    }

    // ---- One database object per screen in the design -------------------

    public function testTheOrganPickerComesFromTheDatabase(): void
    {
        $rows = $this->db->table('organ_programs')->orderBy('sort_order')->get()->getResultArray();

        $this->assertCount(2, $rows);
        $this->assertSame(['kidney', 'liver'], array_column($rows, 'code'));
        $this->assertSame('Renal transplant program', $rows[0]['description']);
        $this->assertSame('kidney.svg', $rows[0]['icon']);
    }

    /**
     * `organ_programs.code` and the two organ ENUMs have to agree, or a
     * patient can be on a programme the picker cannot offer.
     */
    public function testProgrammeCodesMatchTheOrganEnums(): void
    {
        $codes = array_column(
            $this->db->table('organ_programs')->get()->getResultArray(),
            'code'
        );
        sort($codes);

        foreach (['recipients', 'donors'] as $table) {
            $organs = model(ListsModel::class)->get_enum_values($table, 'organs');
            sort($organs);
            $this->assertSame($codes, $organs, "organ_programs and {$table}.organs have drifted apart");
        }

        $labOrgans = model(ListsModel::class)->get_enum_values('labs', 'organ_type');
        sort($labOrgans);
        $this->assertSame($codes, $labOrgans, 'organ_programs and labs.organ_type have drifted apart');
    }

    /** The Donors List screen: its eight columns, out of `donors_list`. */
    public function testDonorsListMatchesTheDonorsListScreen(): void
    {
        $this->seedPeople();

        // A second donor, deceased and with no labs, plus a pair so that one
        // donor is matched and the other is not.
        $this->db->table('donors')->insert([
            'mrn' => 2002, 'name' => 'Donor Two', 'age' => 55, 'blood_group' => 'O',
            'organs' => 'kidney', 'entry_date' => date('Y-m-d'),
            'donation_type' => 'deceased', 'hospital' => 'PSMMC',
        ]);

        $labId = (int) model(LabsModel::class)->get_lab_ids()[0];
        $this->db->table('lab_results')->insert(['patient_id' => 2001, 'lab_id' => $labId, 'status' => 'completed']);

        model(PairsModel::class)->insert_pair([
            'recipient_mrn' => 1001, 'donor_mrn' => 2001, 'match_status' => 'active',
        ]);

        $rows = $this->db->table('donors_list')->orderBy('mrn')->get()->getResultArray();
        $this->assertCount(2, $rows, 'donors only, not recipients');

        [$first, $second] = $rows;

        // Every column the screen's table renders.
        $this->assertSame('Donor One', $first['name']);
        $this->assertSame('A', $first['blood_group']);
        $this->assertSame('living', $first['donation_type']);
        $this->assertSame('Brother of 1001', $first['relationship']);
        $this->assertSame('1', (string) $first['labs_completed']);
        $this->assertSame('1', (string) $first['labs_total']);
        $this->assertSame('1', (string) $first['is_matched'], 'paired, so off the unmatched list');

        $this->assertSame('deceased', $second['donation_type']);
        $this->assertSame('PSMMC', $second['hospital']);
        $this->assertSame('0', (string) $second['labs_total']);
        $this->assertSame('0', (string) $second['is_matched']);

        // What the screen actually renders is the unmatched half.
        $unmatched = $this->db->table('donors_list')->where('is_matched', 0)->get()->getResultArray();
        $this->assertCount(1, $unmatched);
        $this->assertSame('Donor Two', $unmatched[0]['name']);
    }

    public function testWaitingListCarriesTheProgrammeAndPeopleByName(): void
    {
        $this->seedPeople();

        $rows = $this->db->table('waiting_list')->orderBy('mrn')->get()->getResultArray();
        $this->assertCount(2, $rows, 'unmatched recipients only');

        $this->assertSame('Kidney', $rows[0]['program_label']);
        $this->assertSame('Test Physician', $rows[0]['mrp_name']);
        $this->assertSame('Test Coordinator', $rows[0]['coordinator_name']);
    }

    // ---- recipients and donors are tables, patients is the view ----------

    /**
     * The point of the restructure: the two registers are real tables and the
     * old single table is the derived one, not the other way round.
     */
    public function testTheRegistersAreTablesAndPatientsIsTheView(): void
    {
        $types = [];

        foreach (['recipients', 'donors', 'pairs', 'lab_results', 'patients', 'waiting_list', 'donors_list'] as $name) {
            $row = $this->db->query(
                'SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$this->db->prefixTable($name)]
            )->getRowArray();

            $types[$name] = $row['TABLE_TYPE'] ?? 'MISSING';
        }

        $this->assertSame('BASE TABLE', $types['recipients']);
        $this->assertSame('BASE TABLE', $types['donors']);
        $this->assertSame('BASE TABLE', $types['pairs']);
        $this->assertSame('BASE TABLE', $types['lab_results']);
        $this->assertSame('VIEW', $types['patients'], 'the old single table is now the compatibility view');
        $this->assertSame('VIEW', $types['waiting_list']);
        $this->assertSame('VIEW', $types['donors_list']);
    }

    /** Each register carries only the columns its own form collects. */
    public function testEachRegisterHoldsOnlyItsOwnRoleColumns(): void
    {
        $recipient = array_keys($this->db->getFieldNames('recipients') ? array_flip($this->db->getFieldNames('recipients')) : []);
        $donor     = array_keys($this->db->getFieldNames('donors') ? array_flip($this->db->getFieldNames('donors')) : []);

        foreach (['diagnosis', 'dialysis', 'urgency', 'is_urgent', 'urgency_rank'] as $recipientOnly) {
            $this->assertContains($recipientOnly, $recipient);
            $this->assertNotContains($recipientOnly, $donor, "donors should not carry {$recipientOnly}");
        }

        foreach (['donation_type', 'relationship'] as $donorOnly) {
            $this->assertContains($donorOnly, $donor);
            $this->assertNotContains($donorOnly, $recipient, "recipients should not carry {$donorOnly}");
        }

        // Shared columns are on both, under the original schema's names.
        foreach (['mrn', 'name', 'city', 'phone_number', 'gender', 'age', 'blood_group', 'organs', 'status', 'hospital', 'mrp_id', 'coordinator_id', 'note'] as $shared) {
            $this->assertContains($shared, $recipient);
            $this->assertContains($shared, $donor);
        }
    }

    /** The compatibility view puts `type` back and reassembles both halves. */
    public function testPatientsViewUnionsBothRegisters(): void
    {
        $this->seedPeople();

        $rows = $this->db->table('patients')->orderBy('mrn')->get()->getResultArray();
        $this->assertCount(3, $rows);

        $this->assertSame('recipient', $rows[0]['type']);
        $this->assertSame('ESRD', $rows[0]['diagnosis']);
        $this->assertNull($rows[0]['donation_type'], 'recipient-side rows have no donation type');

        $this->assertSame('donor', $rows[2]['type']);
        $this->assertSame('living', $rows[2]['donation_type']);
        $this->assertNull($rows[2]['diagnosis'], 'donor-side rows have no diagnosis');
        $this->assertNull($rows[2]['urgency']);
    }

    /**
     * `lab_results.patient_id` cannot have a foreign key — no column can point
     * at either of two tables — so triggers do the cascade instead.
     */
    public function testDeletingAPersonTakesTheirLabResultsWithThem(): void
    {
        $this->seedPeople();
        $labId = (int) model(LabsModel::class)->get_lab_ids()[0];

        $this->db->table('lab_results')->insert(['patient_id' => 2001, 'lab_id' => $labId, 'status' => 'completed']);
        $this->assertSame(1, $this->db->table('lab_results')->where('patient_id', 2001)->countAllResults());

        $this->db->table('donors')->where('mrn', 2001)->delete();
        $this->assertSame(0, $this->db->table('lab_results')->where('patient_id', 2001)->countAllResults());
    }

    /** A recipient MRN can no longer be filed as the donor half of a pair. */
    public function testEachSideOfAPairMustExistInItsOwnRegister(): void
    {
        $this->seedPeople();

        $this->expectException(Throwable::class);
        model(PairsModel::class)->insert_pair([
            'recipient_mrn' => 1001,
            'donor_mrn'     => 1002, // a recipient, not a donor
            'match_status'  => 'active',
        ]);
    }

    /** The Pairs List screen: one row per pair, both sides flattened. */
    public function testPairsOverviewFlattensBothSides(): void
    {
        $this->seedPeople();
        model(PairsModel::class)->insert_pair([
            'recipient_mrn' => 1001, 'donor_mrn' => 2001, 'match_status' => 'scheduled',
            'relationship'  => 'Brother', 'surgery_on' => '2026-10-05', 'note' => 'awaiting cardiac',
        ]);

        $rows = $this->db->table('pairs_overview')->get()->getResultArray();
        $this->assertCount(1, $rows);
        $row = $rows[0];

        $this->assertSame('kidney', $row['organ'], 'taken from the recipient; pairs has no organ of its own');
        $this->assertSame('scheduled', $row['match_status']);
        $this->assertSame('awaiting cardiac', $row['note']);

        $this->assertSame('Recipient Twenty', $row['r_name']);
        $this->assertSame('A', $row['r_blood_group']);
        $this->assertSame('high', $row['r_urgency']);
        $this->assertSame('Test Physician', $row['r_mrp_name']);

        $this->assertSame('Donor One', $row['d_name']);
        $this->assertSame('living', $row['d_donation_type']);
    }

    /** The Dashboard's four bars, per programme. */
    public function testDashboardStatsCountPerProgramme(): void
    {
        $this->seedPeople();

        $before = $this->statsFor('kidney');
        $this->assertSame(2, (int) $before['total_recipients']);
        $this->assertSame(2, (int) $before['unmatched_recipients']);
        $this->assertSame(1, (int) $before['total_donors']);
        $this->assertSame(1, (int) $before['unmatched_donors']);
        $this->assertSame(0, (int) $before['total_pairs']);
        $this->assertSame(0, (int) $before['active_or_scheduled_pairs']);

        model(PairsModel::class)->insert_pair([
            'recipient_mrn' => 1001, 'donor_mrn' => 2001, 'match_status' => 'active',
        ]);
        $pairId = (int) $this->db->insertID();

        $after = $this->statsFor('kidney');
        $this->assertSame(1, (int) $after['unmatched_recipients'], 'the paired recipient drops off');
        $this->assertSame(0, (int) $after['unmatched_donors']);
        $this->assertSame(1, (int) $after['total_pairs']);
        $this->assertSame(1, (int) $after['active_or_scheduled_pairs']);

        // `completed` is a pair but no longer active/scheduled.
        model(PairsModel::class)->update_pair($pairId, ['match_status' => 'completed']);
        $done = $this->statsFor('kidney');
        $this->assertSame(1, (int) $done['total_pairs']);
        $this->assertSame(0, (int) $done['active_or_scheduled_pairs']);

        // A programme with nobody on it still appears, with zeroes.
        $liver = $this->statsFor('liver');
        $this->assertSame('Liver', $liver['program_label']);
        $this->assertSame(0, (int) $liver['total_recipients']);
    }

    /** @return array<string, mixed> */
    private function statsFor(string $organ): array
    {
        return $this->db->table('dashboard_stats')->where('organ', $organ)->get()->getRowArray();
    }

    public function testStaffCanHoldAHashedPassword(): void
    {
        $this->db->table('staff')->insert([
            'staff_id'      => 'DR-00421',
            'name'          => 'Test Staff',
            'password_hash' => password_hash('correct horse battery staple', PASSWORD_DEFAULT),
            'role'          => 'admin',
        ]);

        $row = $this->db->table('staff')->where('staff_id', 'DR-00421')->get()->getRowArray();

        $this->assertTrue(password_verify('correct horse battery staple', $row['password_hash']));
        $this->assertFalse(password_verify('wrong', $row['password_hash']));
    }
}
