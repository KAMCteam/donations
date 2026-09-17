<?php

use App\Database\Seeds\LabCatalogueSeeder;
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
    protected $seed      = LabCatalogueSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db->DBDriver !== 'MySQLi') {
            $this->markTestSkipped('The donations schema is MySQL-specific; the tests group uses ' . $this->db->DBDriver . '.');
        }
    }

    /** A recipient, a second recipient with no dialysis date, and a donor. */
    private function seedPeople(): void
    {
        $this->db->table('mrp')->insert(['mrp_id' => 'MRP-001', 'name' => 'Test Physician']);
        $this->db->table('coordinators')->insert(['coordinator_name' => 'Test Coordinator']);

        $this->db->table('patients')->insert([
            'mrn' => 1001, 'name' => 'Recipient Twenty', 'city' => 'Riyadh',
            'phone_number' => '+966500000001', 'gender' => 'M', 'age' => 42,
            'blood_group' => 'A', 'organs' => 'kidney', 'type' => 'recipient',
            'status' => 'ready', 'urgency' => 'high', 'mrp_id' => 1, 'coordinator_id' => 1,
            'hospital' => 'KAMC', 'diagnosis' => 'ESRD',
            'dialysis' => date('Y-m-d', strtotime('-30 months')),
            'entry_date' => date('Y-m-d', strtotime('-20 months')),
            'note' => 'clinical note',
        ]);

        $this->db->table('patients')->insert([
            'mrn' => 1002, 'name' => 'Recipient NoDialysis', 'blood_group' => 'O',
            'organs' => 'kidney', 'type' => 'recipient', 'urgency' => 'critical',
            'entry_date' => date('Y-m-d', strtotime('-12 months')),
        ]);

        $this->db->table('patients')->insert([
            'mrn' => 2001, 'name' => 'Donor One', 'blood_group' => 'A',
            'organs' => 'kidney', 'type' => 'donor', 'urgency' => 'low',
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
        $rows = $this->db->table('patients')->orderBy('urgency', 'DESC')->get()->getResultArray();

        $this->assertSame('critical', $rows[0]['urgency']);
        $this->assertSame('1', (string) $rows[0]['urgency_rank']);
        $this->assertSame('1', (string) $rows[0]['is_urgent']);

        $this->assertSame('low', $rows[2]['urgency']);
        $this->assertSame('4', (string) $rows[2]['urgency_rank']);
        $this->assertSame('0', (string) $rows[2]['is_urgent']);
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

        $this->assertSame(['kidney', 'liver'], $lists->get_enum_values('patients', 'organs'));
        $this->assertSame(['A', 'B', 'AB', 'O'], $lists->get_enum_values('patients', 'blood_group'));
        $this->assertSame(['M', 'F'], $lists->get_enum_values('patients', 'gender'));
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
        $this->db->table('patients')->where('mrn', 1001)->delete();
    }

    public function testAnUnknownMrpIsRejected(): void
    {
        $this->expectException(Throwable::class);
        $this->db->table('patients')->insert([
            'mrn' => 9999, 'name' => 'x', 'blood_group' => 'A', 'organs' => 'kidney',
            'type' => 'recipient', 'entry_date' => '2026-01-01', 'mrp_id' => 424242,
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
        $this->db->table('patients')->where('mrn', 1001)->update(['mrn' => 1010]);

        $this->assertSame('1010', (string) $this->db->table('pairs')->get()->getRowArray()['recipient_mrn']);
    }

    // ---- The platform's own fields have somewhere to live ----------------

    public function testEveryPlatformFieldPersists(): void
    {
        $this->seedPeople();

        $row = $this->db->table('patients')->where('mrn', 1001)->get()->getRowArray();

        // The columns added for the platform's screens.
        $this->assertSame('KAMC', $row['hospital']);
        $this->assertSame('ESRD', $row['diagnosis']);
        $this->assertSame('1', (string) $row['coordinator_id']);

        $donor = $this->db->table('patients')->where('mrn', 2001)->get()->getRowArray();
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
