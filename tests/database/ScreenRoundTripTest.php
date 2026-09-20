<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\UiStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Every field the screens collect has to reach a column and come back.
 *
 * This exists because of a specific failure: the forms posted `gender`,
 * `selectedMrp`, `firstDialysis`, the donor status and the pair's
 * relationship, and nothing read them, so a record saved from a filled-in
 * screen came back half empty. The controller silently dropping a field that
 * the view faithfully posts leaves no error behind, so the only way to catch
 * it is to post a form and then look in the table.
 *
 * MySQL/MariaDB only, same as SchemaTest.
 *
 * @internal
 */
final class ScreenRoundTripTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private int $mrpId;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db->DBDriver !== 'MySQLi') {
            $this->markTestSkipped('This schema is MySQL-specific; the tests group uses ' . $this->db->DBDriver . '.');
        }

        $this->db->table('mrp')->insert(['code' => 'MRP-001', 'name' => 'Dr. Test']);
        $this->mrpId = (int) $this->db->insertID();

        $this->withSession(['ui_signed_in' => true, 'ui_organ' => 'kidney']);
    }

    // ---- Add Recipient ---------------------------------------------------

    public function testAddRecipientStoresEveryFieldTheFormCollects(): void
    {
        $this->post('recipients/new', [
            'name'          => 'Ahmed Test',
            'age'           => '41',
            'bloodType'     => 'O',
            'gender'        => 'Male',
            'phone'         => '+966500000001',
            'address'       => 'Riyadh',
            'hospital'      => 'KAMC',
            'diagnosis'     => 'ESRD',
            'urgency'       => 'critical',
            'selectedMrp'   => (string) $this->mrpId,
            'firstDialysis' => '01/03/2024',
            'notes'         => 'clinical note',
        ]);

        $this->seeInDatabase('recipients', [
            'name'           => 'Ahmed Test',
            'age'            => 41,
            'blood_group'    => 'O',
            'gender'         => 'male',
            'phone'          => '+966500000001',
            'city'           => 'Riyadh',
            'hospital'       => 'KAMC',
            'diagnosis'      => 'ESRD',
            'urgency'        => 'critical',
            'mrp_id'         => $this->mrpId,
            'dialysis_start' => '2024-03-01',
            'notes'          => 'clinical note',
        ]);
    }

    /**
     * Reopening a saved record has to show what was saved. When these came
     * back as defaults, pressing Save reassigned the record's MRP to whoever
     * happened to be first in the list.
     */
    public function testReopeningARecipientShowsTheStoredValues(): void
    {
        $this->post('recipients/new', [
            'name'          => 'Ahmed Test',
            'age'           => '41',
            'bloodType'     => 'O',
            'gender'        => 'Female',
            'urgency'       => 'high',
            'selectedMrp'   => (string) $this->mrpId,
            'firstDialysis' => '01/03/2024',
        ]);

        $mrn  = (int) $this->db->table('recipients')->get()->getRowArray()['mrn'];
        $html = $this->get('recipients/' . $mrn)->getBody();

        $this->assertStringContainsString('value="01/03/2024"', $html);
        $this->assertStringContainsString('<option value="Female" selected>', $html);
        $this->assertStringContainsString('<option value="' . $this->mrpId . '" selected>', $html);
    }

    // ---- The score on the waiting list ------------------------------------

    /**
     * The Score column used to be a lookup table of made-up numbers keyed on
     * urgency. It is the real score now, and the list is ordered by it.
     */
    public function testTheWaitingListShowsTheComputedScoreAndOrdersByIt(): void
    {
        $this->addRecipient(1001, 'Waited Longer', 'high', 30, 30);
        $this->addRecipient(1002, 'Waited Less', 'high', 5, 5);
        $this->addRecipient(1003, 'Most Urgent', 'critical', 1, 1);

        $list = (new UiStore())->waitingList();

        // Most urgent first, then by score.
        $this->assertSame(['Most Urgent', 'Waited Longer', 'Waited Less'], array_column($list, 'name'));
        $this->assertEqualsWithDelta(6.0, $list[1]['score'], 0.001);
        $this->assertEqualsWithDelta(1.0, $list[2]['score'], 0.001);

        $html = $this->get('recipients')->getBody();
        $this->assertStringContainsString('6.0', $html);
        $this->assertStringNotContainsString('5.2', $html, 'the prototype\'s fake score should be gone');
    }

    /** No dialysis date means no score, shown as a dash rather than as zero. */
    public function testARecipientWithoutADialysisDateScoresNothing(): void
    {
        $this->addRecipient(1001, 'No Dialysis', 'medium', 12, null);

        $this->assertNull((new UiStore())->waitingList()[0]['score']);
        $this->assertStringContainsString('&mdash;', $this->get('recipients')->getBody());
    }

    // ---- Add Donor -------------------------------------------------------

    public function testAddDonorStoresEveryFieldTheFormCollects(): void
    {
        $this->post('donors/new', [
            'name'             => 'Noura Test',
            'age'              => '35',
            'bloodType'        => 'AB',
            'donorGender'      => 'Female',
            'phone'            => '+966500000002',
            'address'          => 'Jeddah',
            'hospital'         => 'KAMC',
            'donorMrp'         => (string) $this->mrpId,
            'donorStatus'      => 'Active',
            'donorCoordinator' => 'Coordinator One',
            'notes'            => 'donor note',
        ]);

        $this->seeInDatabase('donors', [
            'name'        => 'Noura Test',
            'age'         => 35,
            'blood_group' => 'AB',
            'gender'      => 'female',
            'city'        => 'Jeddah',
            'mrp_id'      => $this->mrpId,
            'status'      => 'active',
            'notes'       => 'donor note',
        ]);

        // The coordinator is free text on screen but a foreign key in the
        // table, so typing a name has to register one.
        $this->seeInDatabase('coordinators', ['name' => 'Coordinator One']);
        $donor = $this->db->table('donors')->get()->getRowArray();
        $this->assertNotNull($donor['coordinator_id']);
    }

    /** Typing the same coordinator twice must not register two of them. */
    public function testTheSameCoordinatorNameIsReusedNotDuplicated(): void
    {
        foreach (['Noura Test', 'Sara Test'] as $name) {
            $this->post('donors/new', [
                'name'             => $name,
                'age'              => '35',
                'bloodType'        => 'O',
                'donorCoordinator' => 'Coordinator One',
            ]);
        }

        $this->assertSame(1, $this->db->table('coordinators')->countAllResults());
    }

    /**
     * The donors list is the same table as the waiting list — same columns
     * where the two screens share a field, same style — because it used to
     * carry its own, which made it read as a different kind of screen.
     */
    public function testTheDonorsListShowsTheAgreedColumns(): void
    {
        $this->post('donors/new', [
            'name'        => 'Noura Test',
            'age'         => '35',
            'bloodType'   => 'AB',
            'donorGender' => 'Female',
            'hospital'    => 'KAMC',
        ]);

        $html = $this->get('donors')->getBody();

        foreach (['Name', 'MRN', 'Age', 'Gender', 'Blood Group', 'Type', 'Labs'] as $column) {
            $this->assertStringContainsString('<th>' . $column . '</th>', $html);
        }

        // Dropped: neither identifies a donor at a glance, and both are on the
        // record screen.
        $this->assertStringNotContainsString('<th>Relationship</th>', $html);
        $this->assertStringNotContainsString('<th>Hospital</th>', $html);

        // The shared style, not the one it used to have on its own.
        $this->assertStringContainsString('class="table list-table"', $html);

        $this->assertStringContainsString('Female', $html);
        $this->assertStringContainsString('AB', $html);
    }

    // ---- Add Pair --------------------------------------------------------

    public function testAddPairStoresBothPeopleAndTheLinkBetweenThem(): void
    {
        $this->post('pairs/new', [
            'rName'          => 'Recipient Pair',
            'rAge'           => '52',
            'rBloodType'     => 'A',
            'rGender'        => 'Female',
            'rCity'          => 'Dammam',
            'rHospital'      => 'KAMC',
            'rDiagnosis'     => 'ESRD',
            'rUrgency'       => 'high',
            'rMrp'           => (string) $this->mrpId,
            'rFirstDialysis' => '10/01/2023',
            'dName'          => 'Donor Pair',
            'dAge'           => '30',
            'dBloodType'     => 'A',
            'dGender'        => 'Male',
            'dCity'          => 'Dammam',
            'dMrp'           => (string) $this->mrpId,
            'dStatus'        => 'On Hold',
            'dCoordinator'   => 'Coordinator Two',
            'relationship'   => 'Sibling',
            'crossmatchDate' => '05/10/2026',
        ]);

        $this->seeInDatabase('recipients', [
            'name'           => 'Recipient Pair',
            'gender'         => 'female',
            'mrp_id'         => $this->mrpId,
            'dialysis_start' => '2023-01-10',
            'urgency'        => 'high',
        ]);
        $this->seeInDatabase('donors', [
            'name'   => 'Donor Pair',
            'gender' => 'male',
            'mrp_id' => $this->mrpId,
            'status' => 'on_hold',
        ]);
        $this->seeInDatabase('pairs', [
            'status'          => 'active',
            'relationship'    => 'Sibling',
            'crossmatch_date' => '2026-10-05',
        ]);
    }

    /**
     * Both sides of an open pair leave their own lists — the linking rule the
     * waiting list is built on.
     */
    public function testPairingTakesBothSidesOffTheirLists(): void
    {
        $this->post('pairs/new', [
            'rName'      => 'Recipient Pair',
            'rAge'       => '52',
            'rBloodType' => 'A',
            'dName'      => 'Donor Pair',
            'dAge'       => '30',
            'dBloodType' => 'A',
        ]);

        $store = new UiStore();
        $this->assertSame([], $store->waitingList(), 'the recipient should have left the waiting list');
        $this->assertSame([], $store->availableDonors(), 'the donor should have left the donors list');
        $this->assertCount(1, $store->pairs());

        // Both are still on the register — they left the lists, not the system.
        $this->assertCount(1, $store->recipients());
        $this->assertCount(1, $store->donors());
    }

    // ---- Pair profile ----------------------------------------------------

    /**
     * The pair screen's gender, MRP, coordinator, first dialysis and donor
     * status used to render without a `name`, so editing them there threw the
     * change away without saying so.
     */
    public function testEditingAPairSavesEveryFieldOnTheScreen(): void
    {
        $this->post('pairs/new', [
            'rName'      => 'Recipient Pair',
            'rAge'       => '52',
            'rBloodType' => 'A',
            'dName'      => 'Donor Pair',
            'dAge'       => '30',
            'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        $this->post('pairs/' . $pairId, [
            'relationship'   => 'Spouse',
            'pairStatus'     => 'scheduled',
            'crossmatchDate' => '06/10/2026',
            'rName'          => 'Recipient Edited',
            'rAge'           => '53',
            'rBloodType'     => 'A',
            'rUrgency'       => 'critical',
            'rGender'        => 'Male',
            'rMrp'           => (string) $this->mrpId,
            'rFirstDialysis' => '11/02/2023',
            'dName'          => 'Donor Edited',
            'dAge'           => '31',
            'dBloodType'     => 'A',
            'dGender'        => 'Female',
            'dMrp'           => (string) $this->mrpId,
            'dStatus'        => 'Completed',
            'dCoordinator'   => 'Coordinator Three',
        ]);

        $this->seeInDatabase('pairs', [
            'id'              => $pairId,
            'status'          => 'scheduled',
            'relationship'    => 'Spouse',
            'crossmatch_date' => '2026-10-06',
        ]);
        $this->seeInDatabase('recipients', [
            'name'           => 'Recipient Edited',
            'gender'         => 'male',
            'urgency'        => 'critical',
            'mrp_id'         => $this->mrpId,
            'dialysis_start' => '2023-02-11',
        ]);
        $this->seeInDatabase('donors', [
            'name'   => 'Donor Edited',
            'gender' => 'female',
            'status' => 'completed',
            'mrp_id' => $this->mrpId,
        ]);
        $this->seeInDatabase('coordinators', ['name' => 'Coordinator Three']);
    }

    // ---- Lab workup ------------------------------------------------------

    public function testALabResultEnteredOnARecordIsStored(): void
    {
        $this->post('recipients/new', ['name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $mrn = (int) $this->db->table('recipients')->get()->getRowArray()['mrn'];
        $lab = $this->db->table('labs')->where('organ_code', 'kidney')->get()->getRowArray();

        $this->post('recipients/' . $mrn, [
            'name'      => 'Ahmed Test',
            'age'       => '41',
            'bloodType' => 'O',
            'labs'      => [[
                'id'     => $lab['id'],
                'name'   => $lab['name'],
                'status' => 'completed',
                'result' => 'eGFR 12',
                'date'   => '17/09/2026',
                'notes'  => 'repeat in 3 months',
            ]],
        ]);

        $this->seeInDatabase('lab_results', [
            'person_mrn'  => $mrn,
            'person_type' => 'recipient',
            'lab_id'      => $lab['id'],
            'status'      => 'completed',
            'value'       => 'eGFR 12',
            'taken_on'    => '2026-09-17',
            'notes'       => 'repeat in 3 months',
        ]);
    }

    // ---- MRP -------------------------------------------------------------

    public function testAddingAnMrpStoresIt(): void
    {
        $this->post('mrp', ['id' => 'MRP-010', 'name' => 'Dr. Sara Nephro']);

        $this->seeInDatabase('mrp', ['code' => 'MRP-010', 'name' => 'Dr. Sara Nephro']);
    }

    // ---- Helpers ----------------------------------------------------------

    private function addRecipient(int $mrn, string $name, string $urgency, int $monthsWaiting, ?int $monthsOnDialysis): void
    {
        $this->db->table('recipients')->insert([
            'mrn'            => $mrn,
            'name'           => $name,
            'organ_code'     => 'kidney',
            'blood_group'    => 'O',
            'urgency'        => $urgency,
            'entry_date'     => date('Y-m-d', strtotime("-{$monthsWaiting} months")),
            'dialysis_start' => $monthsOnDialysis === null ? null : date('Y-m-d', strtotime("-{$monthsOnDialysis} months")),
        ]);
    }
}
