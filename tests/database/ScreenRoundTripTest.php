<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\UiStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

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

    /**
     * Posts the way a screen does, token and all.
     *
     * Every form emits `csrf_field()` and the filter checks it, so a post
     * without one is refused — as it should be. The browser sends the token
     * back from the cookie the last page set; there is no page here, so this
     * mints one and posts it the way the field would.
     *
     * @param array<string, mixed>|null $params
     */
    public function post($path, ?array $params = null): \CodeIgniter\Test\TestResponse
    {
        $security = service('security');
        $params   = ($params ?? []) + [$security->getTokenName() => $security->getHash()];

        return $this->carrySession($this->call('post', $path, $params));
    }

    /**
     * @param array<string, mixed>|null $params
     */
    public function get($path, ?array $params = null): \CodeIgniter\Test\TestResponse
    {
        return $this->carrySession($this->call('get', $path, $params));
    }

    /**
     * Carries the session into the next request, the way a cookie does.
     *
     * The feature-test client copies the seeded array over `$_SESSION` before
     * every call, so anything a request writes there is gone by the next one —
     * which no browser does, and which a screen that builds something up over
     * several posts cannot be tested through at all.
     */
    private function carrySession(\CodeIgniter\Test\TestResponse $response): \CodeIgniter\Test\TestResponse
    {
        if (isset($_SESSION) && is_array($_SESSION)) {
            $this->withSession($_SESSION);
        }

        return $response;
    }

    // ---- Add Recipient ---------------------------------------------------

    public function testAddRecipientStoresEveryFieldTheFormCollects(): void
    {
        $this->post('recipients/new', [
            'mrn'           => '4001',
            'name'          => 'Ahmed Test',
            'age'           => '41',
            'bloodType'     => 'O',
            'gender'        => 'Male',
            'phone'         => '+966500000001',
            'address'       => 'Riyadh',
            'urgent'        => '1',
            'coordinator'   => 'Coordinator Zero',
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
            'is_urgent'      => 1,
            'mrp_id'         => $this->mrpId,
            'dialysis_start' => '2024-03-01',
            'notes'          => 'clinical note',
        ]);
        $this->seeInDatabase('coordinators', ['name' => 'Coordinator Zero']);
    }

    /**
     * Reopening a saved record has to show what was saved. When these came
     * back as defaults, pressing Save reassigned the record's MRP to whoever
     * happened to be first in the list.
     */
    public function testReopeningARecipientShowsTheStoredValues(): void
    {
        $this->post('recipients/new', [
            'mrn'           => '4002',
            'name'          => 'Ahmed Test',
            'age'           => '41',
            'bloodType'     => 'O',
            'gender'        => 'Female',
            'urgent'        => '1',
            'selectedMrp'   => (string) $this->mrpId,
            'firstDialysis' => '01/03/2024',
        ]);

        $mrn  = (int) $this->db->table('recipients')->get()->getRowArray()['mrn'];
        $html = $this->get('recipients/' . $mrn)->getBody();

        $this->assertStringContainsString('value="01/03/2024"', $html);
        $this->assertStringContainsString('<option value="Female" selected>', $html);
        $this->assertStringContainsString('<option value="' . $this->mrpId . '" selected>', $html);
    }

    // ---- Linking: a new counterpart, or one already registered -------------

    /**
     * "Link with Donor" used to drop you on the donors list, which said
     * nothing about what to do there. It offers the two real choices now.
     */
    public function testTheLinkButtonOffersTheChoiceRatherThanLeavingForAList(): void
    {
        $this->post('recipients/new', ['mrn' => '9001', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);

        $html = $this->get('recipients/9001')->getBody();

        // A real link to the choice at its own URL, and the same choice on the
        // page as a dialog for when JavaScript is on.
        $this->assertStringContainsString('recipients/9001/link" data-dialog="link-choice"', $html);
        $this->assertStringContainsString('<dialog id="link-choice"', $html);
        $this->assertStringNotContainsString('btn-outline" href="' . site_url('donors') . '"', $html);
    }

    public function testTheChoicePageOffersBothWays(): void
    {
        $this->post('recipients/new', ['mrn' => '9002', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);

        $html = $this->get('recipients/9002/link')->getBody();

        $this->assertStringContainsString('Link with a new donor', $html);
        $this->assertStringContainsString('Link with an existing donor', $html);
        $this->assertStringContainsString(site_url('pairs/new') . '?recipient=9002', $html);
        $this->assertStringContainsString(site_url('recipients/9002/link/existing'), $html);
    }

    /** A donor record offers the mirror image of it. */
    public function testADonorIsOfferedARecipient(): void
    {
        $this->post('donors/new', ['mrn' => '9003', 'name' => 'Fahad Test', 'age' => '29', 'bloodType' => 'A']);

        $html = $this->get('donors/9003/link')->getBody();

        $this->assertStringContainsString('Link with a new recipient', $html);
        $this->assertStringContainsString('Link with an existing recipient', $html);
        $this->assertStringContainsString(site_url('pairs/new') . '?donor=9003', $html);
    }

    /** Somebody already paired has nothing to choose, so they see the pair. */
    public function testAPairedPersonIsSentToTheirPair(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9004', 'dMrn' => '9005',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        $this->get('recipients/9004/link')->assertRedirectTo(site_url('pairs/' . $pairId));
    }

    // ---- "a new one": Add Pair with this record already filled in ----------

    public function testAddPairArrivesFilledInAndFixedOnTheKnownSide(): void
    {
        $this->post('recipients/new', [
            'mrn'       => '9006',
            'name'      => 'Layla Test',
            'age'       => '38',
            'bloodType'   => 'B',
            'address'     => 'Taif',
            'coordinator' => 'Coordinator Test',
        ]);

        $html = $this->get('pairs/new?recipient=9006')->getBody();

        $this->assertStringContainsString('Link Layla Test', $html);
        $this->assertStringContainsString('name="fixedSide" value="recipient"', $html);
        // The known half is filled in and shut; its MRN rides in a hidden
        // input, since a disabled fieldset posts nothing.
        $this->assertStringContainsString('name="rMrn" value="9006"', $html);
        $this->assertStringContainsString('value="Layla Test"', $html);
        $this->assertStringContainsString('value="Taif"', $html);
        // The other half is still the form to fill in.
        $this->assertStringContainsString('name="dMrn"', $html);
    }

    /**
     * Saving writes only the new person. The known one is already on the
     * system, so re-validating their MRN as new would refuse the save.
     */
    public function testSavingCreatesOnlyTheNewHalfOfThePair(): void
    {
        $this->post('recipients/new', [
            'mrn'       => '9007',
            'name'      => 'Layla Test',
            'age'       => '38',
            'bloodType' => 'B',
            'address'   => 'Taif',
        ]);

        $this->post('pairs/new', [
            'fixedSide'      => 'recipient',
            'rMrn'           => '9007',
            'dMrn'           => '9008',
            'dName'          => 'Nasser Test',
            'dAge'           => '44',
            'dBloodType'     => 'B',
            'relationship'   => 'Brother',
            'crossmatchDate' => '15/10/2026',
        ]);

        $this->assertSame(1, $this->db->table('recipients')->countAllResults(), 'the known recipient is not written again');
        $this->seeInDatabase('recipients', ['mrn' => 9007, 'name' => 'Layla Test', 'city' => 'Taif']);
        $this->seeInDatabase('donors', ['mrn' => 9008, 'name' => 'Nasser Test']);
        $this->seeInDatabase('pairs', [
            'recipient_mrn'   => 9007,
            'donor_mrn'       => 9008,
            'relationship'    => 'Brother',
            'crossmatch_date' => '2026-10-15',
        ]);
    }

    public function testTheKnownSideIsRefusedIfSomethingPairedThemMeanwhile(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9009', 'dMrn' => '9010',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        // 9009 is paired now; a form opened before that still posts.
        $this->post('pairs/new', [
            'fixedSide'  => 'recipient',
            'rMrn'       => '9009',
            'dMrn'       => '9011',
            'dName'      => 'Someone Else',
            'dAge'       => '30',
            'dBloodType' => 'A',
        ]);

        $this->assertSame(1, $this->db->table('pairs')->countAllResults());
        $this->assertSame(0, $this->db->table('donors')->where('mrn', 9011)->countAllResults());
        $this->assertStringContainsString('already in an open pair', (string) session('ui_error'));
    }

    // ---- "an existing one": pick from the list -----------------------------

    public function testThePickerListsUnpairedCounterpartsOnly(): void
    {
        $this->post('recipients/new', ['mrn' => '9012', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);
        $this->post('donors/new', ['mrn' => '9013', 'name' => 'Free Donor', 'age' => '33', 'bloodType' => 'B']);
        $this->post('pairs/new', [
            'rMrn' => '9014', 'dMrn' => '9015',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Paired Donor', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $html = $this->get('recipients/9012/link/existing')->getBody();

        $this->assertStringContainsString('Free Donor', $html);
        $this->assertStringNotContainsString('Paired Donor', $html);
        $this->assertStringContainsString('name="mrn" value="9013"', $html);
    }

    /**
     * One person may hold a row in both registers under their one hospital
     * number, so they could otherwise be offered as their own donor.
     */
    public function testThePickerDoesNotOfferThePersonThemselves(): void
    {
        $this->post('recipients/new', ['mrn' => '9016', 'name' => 'Both Test', 'age' => '38', 'bloodType' => 'B']);
        $this->post('donors/new', ['mrn' => '9016', 'name' => 'Both Test', 'age' => '38', 'bloodType' => 'B']);

        $html = $this->get('recipients/9016/link/existing')->getBody();

        $this->assertStringNotContainsString('name="mrn" value="9016"', $html);
    }

    public function testChoosingFromThePickerCreatesThePair(): void
    {
        $this->post('recipients/new', ['mrn' => '9017', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);
        $this->post('donors/new', ['mrn' => '9018', 'name' => 'Free Donor', 'age' => '33', 'bloodType' => 'B']);

        $this->post('recipients/9017/link/existing', [
            'mrn'            => '9018',
            'relationship'   => 'Brother',
            'crossmatchDate' => '01/10/2026',
        ]);

        $this->seeInDatabase('pairs', [
            'recipient_mrn'   => 9017,
            'donor_mrn'       => 9018,
            'status'          => 'active',
            'relationship'    => 'Brother',
            'crossmatch_date' => '2026-10-01',
        ]);
        // The donors list shows the relationship, so it lands there too.
        $this->seeInDatabase('donors', ['mrn' => 9018, 'relationship' => 'Brother']);
        // Neither person is duplicated: the pair links what was already there.
        $this->assertSame(1, $this->db->table('recipients')->countAllResults());
        $this->assertSame(1, $this->db->table('donors')->countAllResults());
    }

    public function testChoosingSomebodyAlreadyPairedIsRefused(): void
    {
        $this->post('recipients/new', ['mrn' => '9019', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);
        $this->post('pairs/new', [
            'rMrn' => '9020', 'dMrn' => '9021',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Paired Donor', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $this->post('recipients/9019/link/existing', ['mrn' => '9021']);

        $this->assertSame(1, $this->db->table('pairs')->countAllResults());
        $this->assertStringContainsString('already in an open pair', (string) session('ui_error'));
    }

    // ---- A saved record opens read-only ------------------------------------

    /**
     * A record is for reading; editing is deliberate. Every card renders
     * inside a disabled fieldset with its own Edit, so nothing is a live
     * control until one is opened.
     */
    public function testASavedRecordOpensWithEveryCardReadOnly(): void
    {
        $this->post('recipients/new', ['mrn' => '8001', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $html = $this->get('recipients/8001')->getBody();

        $this->assertSame(3, substr_count($html, 'class="card-fields" disabled'), 'personal, labs and notes');
        $this->assertSame(3, substr_count($html, 'class="btn-edit"'), 'one Edit per card');
        $this->assertStringNotContainsString('btn-save', $html, 'nothing to save until a card is opened');
    }

    public function testEditOpensOnlyTheCardItNames(): void
    {
        $this->post('recipients/new', ['mrn' => '8002', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $html = $this->get('recipients/8002?edit=personal')->getBody();

        $this->assertSame(2, substr_count($html, 'class="card-fields" disabled'), 'the other two stay shut');
        $this->assertSame(1, substr_count($html, 'name="section" value="personal"'));
        $this->assertSame(1, substr_count($html, 'btn-save'), 'the open card saves itself');
    }

    /** A mistyped link opens the record rather than an error. */
    public function testAnUnknownCardNameJustOpensTheRecord(): void
    {
        $this->post('recipients/new', ['mrn' => '8003', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $html = $this->get('recipients/8003?edit=nonsense')->getBody();

        $this->assertSame(3, substr_count($html, 'class="card-fields" disabled'));
        $this->assertStringNotContainsString('btn-save', $html);
    }

    /** An add screen has nothing to read yet, so it stays one open form. */
    public function testTheAddScreenIsUnaffected(): void
    {
        $html = $this->get('recipients/new')->getBody();

        $this->assertStringNotContainsString('card-fields" disabled', $html);
        $this->assertStringNotContainsString('btn-edit', $html);
        $this->assertSame(1, substr_count($html, 'btn-save'));
    }

    /**
     * The point of the whole arrangement: the cards that are shut post
     * nothing, so saving one cannot disturb another.
     */
    public function testSavingOneCardLeavesTheOthersAlone(): void
    {
        $this->post('recipients/new', [
            'mrn'       => '8004',
            'name'      => 'Ahmed Test',
            'age'       => '41',
            'bloodType' => 'O',
            'phone'     => '+966500000001',
            'address'   => 'Riyadh',
            'notes'     => 'original note',
        ]);

        $this->post('recipients/8004', ['section' => 'notes', 'notes' => 'replaced']);

        $this->seeInDatabase('recipients', [
            'mrn'   => 8004,
            'name'  => 'Ahmed Test',
            'phone' => '+966500000001',
            'city'  => 'Riyadh',
            'notes' => 'replaced',
        ]);
    }

    /**
     * And the server keeps to the named card on its own, so a stale tab or a
     * hand-made post cannot reach past the card it claims to be.
     */
    public function testAPostCannotReachPastTheCardItNames(): void
    {
        $this->post('recipients/new', [
            'mrn'       => '8005',
            'name'      => 'Ahmed Test',
            'age'       => '41',
            'bloodType' => 'O',
            'address'   => 'Riyadh',
        ]);

        $this->post('recipients/8005', [
            'section' => 'notes',
            'notes'   => 'a note',
            'name'    => 'Should Not Land',
            'address' => 'Should Not Land',
        ]);

        $this->seeInDatabase('recipients', [
            'mrn'   => 8005,
            'name'  => 'Ahmed Test',
            'city'  => 'Riyadh',
            'notes' => 'a note',
        ]);
    }

    /**
     * An emptied box on an open card means the value was removed. It used to
     * be indistinguishable from a field the form had not sent, so a wrong
     * phone number could never be taken off a record.
     */
    public function testClearingAFieldOnAnOpenCardClearsTheColumn(): void
    {
        $this->post('recipients/new', [
            'mrn'       => '8006',
            'name'      => 'Ahmed Test',
            'age'       => '41',
            'bloodType' => 'O',
            'phone'     => '+966500000001',
        ]);

        $this->post('recipients/8006', [
            'section'   => 'personal',
            'name'      => 'Ahmed Test',
            'age'       => '41',
            'bloodType' => 'O',
            'phone'     => '',
        ]);

        $this->seeInDatabase('recipients', ['mrn' => 8006, 'phone' => null]);
        // But a column that cannot be NULL keeps what it had: blanking a name
        // is a slip, not an instruction.
        $this->seeInDatabase('recipients', ['mrn' => 8006, 'name' => 'Ahmed Test']);
    }

    public function testAPairOpensWithEveryCardReadOnly(): void
    {
        $this->post('pairs/new', [
            'rMrn'       => '8010',
            'dMrn'       => '8011',
            'rName'      => 'Recipient Pair',
            'rAge'       => '52',
            'rBloodType' => 'A',
            'dName'      => 'Donor Pair',
            'dAge'       => '30',
            'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];
        $html   = $this->get('pairs/' . $pairId)->getBody();

        // Pair details, and each person's information, workup and notes.
        $this->assertSame(7, substr_count($html, 'class="card-fields" disabled'));
        $this->assertSame(7, substr_count($html, 'class="btn-edit"'));
        $this->assertStringNotContainsString('btn-save', $html);
    }

    /** Saving the pair's own card must not touch either person's record. */
    public function testSavingThePairCardLeavesBothPeopleAlone(): void
    {
        $this->post('pairs/new', [
            'rMrn'       => '8012',
            'dMrn'       => '8013',
            'rName'      => 'Recipient Pair',
            'rAge'       => '52',
            'rBloodType' => 'A',
            'rCity'      => 'Dammam',
            'dName'      => 'Donor Pair',
            'dAge'       => '30',
            'dBloodType' => 'A',
            'dCity'      => 'Dammam',
        ]);

        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        $this->post('pairs/' . $pairId, [
            'section'        => 'pair',
            'relationship'   => 'Cousin',
            'pairStatus'     => 'confirmed',
            'crossmatchDate' => '11/11/2026',
        ]);

        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'confirmed', 'relationship' => 'Cousin']);
        $this->seeInDatabase('recipients', ['mrn' => 8012, 'name' => 'Recipient Pair', 'city' => 'Dammam', 'age' => 52]);
        // The donor keeps its own fields, but the relationship is the pair's,
        // so the donors list stays in step with it.
        $this->seeInDatabase('donors', ['mrn' => 8013, 'name' => 'Donor Pair', 'city' => 'Dammam', 'relationship' => 'Cousin']);
    }

    // ---- The MRN is entered, never generated -------------------------------

    /**
     * The MRN is the hospital's own number and comes with the patient, so the
     * form collects it. It used to be generated — max + 1 — which would have
     * filed everyone under numbers that mean nothing to the hospital.
     */
    public function testTheEnteredMrnIsTheOneStored(): void
    {
        $this->post('recipients/new', ['mrn' => '7654321', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $this->seeInDatabase('recipients', ['mrn' => 7654321, 'name' => 'Ahmed Test']);
    }

    public function testTheAddFormAsksForTheMrnRatherThanShowingAuto(): void
    {
        $html = $this->get('recipients/new')->getBody();

        $this->assertStringContainsString('name="mrn"', $html);
        $this->assertStringNotContainsString('value="Auto"', $html);
    }

    /** On an existing record the MRN identifies it, so it is not editable. */
    public function testTheMrnIsReadOnlyOnASavedRecord(): void
    {
        $this->post('recipients/new', ['mrn' => '7001', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $html = $this->get('recipients/7001')->getBody();

        $this->assertStringNotContainsString('name="mrn"', $html);
        $this->assertStringContainsString('value="7001" readonly', $html);
    }

    #[DataProvider('badMrns')]
    public function testARecordWithoutAUsableMrnIsRefused(string $mrn, string $expected): void
    {
        $this->post('recipients/new', [
            'mrn'         => $mrn,
            'name'        => 'Ahmed Test',
            'age'         => '41',
            'bloodType'   => 'O',
            'coordinator' => 'Coordinator Test',
        ]);

        $this->assertSame(0, $this->db->table('recipients')->countAllResults(), 'nothing should have been stored');
        $this->assertStringContainsString($expected, (string) session('ui_error'));
    }

    /**
     * A refused save sends the whole form back with it, so only the number has
     * to be retyped. This checks the controller's half — the redirect carries
     * the input — since the view's half is `old()`, and the mock session these
     * tests run on does not age flashdata the way a real request does.
     */
    public function testARefusedSaveCarriesTheRestOfTheFormBack(): void
    {
        $this->post('recipients/new', [
            'mrn'         => '',
            'name'        => 'Ahmed Test',
            'age'         => '41',
            'bloodType'   => 'AB',
            'coordinator' => 'Coordinator Test',
        ]);

        $posted = session('_ci_old_input')['post'] ?? [];

        $this->assertSame('Ahmed Test', $posted['name'] ?? null);
        $this->assertSame('Coordinator Test', $posted['coordinator'] ?? null);
        $this->assertSame('AB', $posted['bloodType'] ?? null);
    }

    /** @return array<string, array{string, string}> */
    public static function badMrns(): array
    {
        return [
            'blank'       => ['', 'required'],
            'letters'     => ['AB-12', 'must be a number'],
            'zero'        => ['0', 'must be a number'],
            'with spaces' => ['12 34', 'must be a number'],
        ];
    }

    public function testAnMrnAlreadyOnTheRegisterIsRefused(): void
    {
        $this->post('recipients/new', ['mrn' => '7002', 'name' => 'First', 'age' => '41', 'bloodType' => 'O']);
        $this->post('recipients/new', ['mrn' => '7002', 'name' => 'Second', 'age' => '50', 'bloodType' => 'A']);

        $this->assertSame(1, $this->db->table('recipients')->countAllResults());
        $this->seeInDatabase('recipients', ['mrn' => 7002, 'name' => 'First']);
        $this->assertStringContainsString('already registered', (string) session('ui_error'));
    }

    /**
     * The registers are separate tables, so one person can be a recipient in
     * one programme and a donor in another under their single hospital number.
     */
    public function testTheSameMrnMayAppearOnBothRegisters(): void
    {
        $this->post('recipients/new', ['mrn' => '7003', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);
        $this->post('donors/new', ['mrn' => '7003', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $this->seeInDatabase('recipients', ['mrn' => 7003]);
        $this->seeInDatabase('donors', ['mrn' => 7003]);
    }

    /** On a pair it would mean donating to oneself. */
    public function testAPairCannotHaveTheSameMrnOnBothSides(): void
    {
        $this->post('pairs/new', [
            'rMrn'       => '7004',
            'dMrn'       => '7004',
            'rName'      => 'Recipient Pair',
            'rAge'       => '52',
            'rBloodType' => 'A',
            'dName'      => 'Donor Pair',
            'dAge'       => '30',
            'dBloodType' => 'A',
        ]);

        $this->assertSame(0, $this->db->table('recipients')->countAllResults());
        $this->assertSame(0, $this->db->table('donors')->countAllResults());
        $this->assertStringContainsString('cannot share an MRN', (string) session('ui_error'));
    }

    /**
     * Both numbers are checked before either person is stored: a pair half
     * written is worse than one not written at all.
     */
    public function testABadDonorMrnStoresNeitherSideOfThePair(): void
    {
        $this->post('pairs/new', [
            'rMrn'       => '7005',
            'dMrn'       => '',
            'rName'      => 'Recipient Pair',
            'rAge'       => '52',
            'rBloodType' => 'A',
            'dName'      => 'Donor Pair',
            'dAge'       => '30',
            'dBloodType' => 'A',
        ]);

        $this->assertSame(0, $this->db->table('recipients')->countAllResults(), 'the recipient should not have been stored either');
        $this->assertSame(0, $this->db->table('donors')->countAllResults());
        $this->assertSame(0, $this->db->table('pairs')->countAllResults());
    }

    // ---- What a recipient record holds now ---------------------------------

    /**
     * Diagnosis, Hospital and the four-level Urgency scale came off the
     * recipient; Urgent is the one question that is left, and Recipient
     * Coordinator was added.
     */
    public function testTheRecipientFormAsksTheAgreedQuestions(): void
    {
        $html = $this->get('recipients/new')->getBody();

        foreach (['diagnosis', 'urgency', 'hospital'] as $gone) {
            $this->assertStringNotContainsString('name="' . $gone . '"', $html);
        }

        $this->assertStringContainsString('name="coordinator"', $html);
        $this->assertStringContainsString('type="checkbox" id="f-urgent" name="urgent"', $html);
    }

    /**
     * A checkbox posts nothing when it is unticked, so a hidden 0 goes first
     * and the box overrides it — otherwise an urgent case could never be made
     * un-urgent again.
     */
    public function testTheUrgentCheckboxStoresBothAnswers(): void
    {
        $this->post('recipients/new', [
            'mrn'       => '3001',
            'name'      => 'Urgent Test',
            'age'       => '41',
            'bloodType' => 'O',
            'urgent'    => '1',
        ]);
        $this->seeInDatabase('recipients', ['mrn' => 3001, 'is_urgent' => 1]);

        // Unticked: the browser sends only the hidden field.
        $this->post('recipients/3001', [
            'section'   => 'personal',
            'name'      => 'Urgent Test',
            'age'       => '41',
            'bloodType' => 'O',
            'urgent'    => '0',
        ]);
        $this->seeInDatabase('recipients', ['mrn' => 3001, 'is_urgent' => 0]);
    }

    public function testTheRecipientCoordinatorIsRegisteredByBeingTyped(): void
    {
        $this->post('recipients/new', [
            'mrn'         => '3002',
            'name'        => 'Ahmed Test',
            'age'         => '41',
            'bloodType'   => 'O',
            'coordinator' => 'Noura Al-Harbi',
        ]);

        $this->seeInDatabase('coordinators', ['name' => 'Noura Al-Harbi']);
        $row = $this->db->table('recipients')->where('mrn', 3002)->get()->getRowArray();
        $this->assertNotNull($row['coordinator_id']);
    }

    // ---- Donor type --------------------------------------------------------

    /**
     * Registering a donor alone can only ask living or deceased: whether a
     * living donor is related is a question about them and a recipient, and
     * there is no recipient on this screen.
     */
    public function testAddDonorAsksLivingOrDeceased(): void
    {
        $html = $this->get('donors/new')->getBody();

        $this->assertStringContainsString('name="donationType"', $html);
        // Whichever is selected carries an extra attribute, so match the
        // option's opening and its label rather than the whole tag.
        $this->assertMatchesRegularExpression('/<option value="living"[^>]*>Living</', $html);
        $this->assertMatchesRegularExpression('/<option value="deceased"[^>]*>Deceased</', $html);
        $this->assertStringNotContainsString('value="living_related"', $html);
        $this->assertStringNotContainsString('value="living_unrelated"', $html);
    }

    /** On a pair the recipient is known, so the finer question can be asked. */
    public function testAddPairAsksRelatedOrUnrelated(): void
    {
        $html = $this->get('pairs/new')->getBody();

        $this->assertStringContainsString('name="dType"', $html);
        $this->assertMatchesRegularExpression('/<option value="living_related"[^>]*>Living Related</', $html);
        $this->assertMatchesRegularExpression('/<option value="living_unrelated"[^>]*>Living Unrelated</', $html);
        $this->assertMatchesRegularExpression('/<option value="deceased"[^>]*>Deceased</', $html);
        // "Living" on its own is what a donor registered alone holds; it is not
        // one of the answers here.
        $this->assertDoesNotMatchRegularExpression('/<option value="living"[^>]*>/', $html);
    }

    public function testTheTypeChosenOnEitherScreenIsStored(): void
    {
        $this->post('donors/new', [
            'mrn'          => '6001',
            'name'         => 'Deceased Donor',
            'age'          => '40',
            'bloodType'    => 'O',
            'donationType' => 'deceased',
        ]);
        $this->seeInDatabase('donors', ['mrn' => 6001, 'donation_type' => 'deceased']);

        $this->post('pairs/new', [
            'rMrn' => '6100', 'dMrn' => '6101',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
            'dType' => 'living_unrelated',
        ]);
        $this->seeInDatabase('donors', ['mrn' => 6101, 'donation_type' => 'living_unrelated']);
    }

    /**
     * A donor registered from a pair holds a value the register screen does
     * not offer, so that screen shows the full list once there is a record —
     * otherwise opening it would silently downgrade them to Living.
     */
    public function testASavedRecordKeepsATypeTheAddScreenCannotOffer(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '6102', 'dMrn' => '6103',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
            'dType' => 'living_related',
        ]);

        $html = $this->get('donors/6103?edit=personal')->getBody();
        $this->assertStringContainsString('<option value="living_related" selected>', $html);

        // And saving that card back leaves it where it was.
        $this->post('donors/6103', [
            'section'      => 'personal',
            'name'         => 'D',
            'age'          => '30',
            'bloodType'    => 'A',
            'donationType' => 'living_related',
        ]);
        $this->seeInDatabase('donors', ['mrn' => 6103, 'donation_type' => 'living_related']);
    }

    public function testATypeOutsideTheListIsIgnored(): void
    {
        $this->post('donors/new', [
            'mrn'          => '6004',
            'name'         => 'Donor',
            'age'          => '40',
            'bloodType'    => 'O',
            'donationType' => 'nonsense',
        ]);

        $this->seeInDatabase('donors', ['mrn' => 6004, 'donation_type' => 'living']);
    }

    /** The lists show the label, not the key. */
    public function testTheDonorsListShowsTheTypeLabel(): void
    {
        $this->post('donors/new', [
            'mrn'          => '6005',
            'name'         => 'Donor',
            'age'          => '40',
            'bloodType'    => 'O',
            'donationType' => 'deceased',
        ]);

        $this->assertStringContainsString('>Deceased</span>', $this->get('donors')->getBody());
    }

    // ---- Status: one value, two screens ------------------------------------

    public function testTheRecipientStatusOffersTheAgreedList(): void
    {
        $html = $this->get('recipients/new')->getBody();

        foreach (UiStore::STATUS_OPTIONS as $value => $label) {
            $this->assertStringContainsString('value="' . $value . '"', $html);
            $this->assertStringContainsString('>' . $label . '</option>', $html);
        }
    }

    /** The pair's Match Status is the same list, not a second one. */
    public function testThePairOffersTheSameListAsTheRecipient(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '2001', 'dMrn' => '2002',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];
        $html   = $this->get('pairs/' . $pairId)->getBody();

        foreach (array_keys(UiStore::STATUS_OPTIONS) as $value) {
            $this->assertStringContainsString('<option value="' . $value . '"', $html);
        }
    }

    /** They agree from the moment the pair exists. */
    public function testANewPairSetsTheRecipientsStatusToo(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '2003', 'dMrn' => '2004',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $this->seeInDatabase('recipients', ['mrn' => 2003, 'status' => 'active']);
        $this->seeInDatabase('pairs', ['recipient_mrn' => 2003, 'status' => 'active']);
    }

    public function testSettingTheStatusOnTheRecipientSetsThePairs(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '2005', 'dMrn' => '2006',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $this->post('recipients/2005', [
            'section'   => 'personal',
            'name'      => 'R',
            'age'       => '40',
            'bloodType' => 'A',
            'status'    => 'paired_exchange',
        ]);

        $this->seeInDatabase('recipients', ['mrn' => 2005, 'status' => 'paired_exchange']);
        $this->seeInDatabase('pairs', ['recipient_mrn' => 2005, 'status' => 'paired_exchange']);
    }

    public function testSettingTheMatchStatusOnThePairSetsTheRecipients(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '2007', 'dMrn' => '2008',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];
        $this->post('pairs/' . $pairId, ['section' => 'pair', 'pairStatus' => 'completed']);

        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'completed']);
        $this->seeInDatabase('recipients', ['mrn' => 2007, 'status' => 'completed']);
    }

    /**
     * `closed` is the one status with meaning beyond its label: it is what an
     * open pair is defined against, so it still frees both sides.
     */
    public function testClosingFromEitherScreenPutsBothSidesBackOnTheirLists(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '2009', 'dMrn' => '2010',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $this->post('recipients/2009', [
            'section'   => 'personal',
            'name'      => 'R',
            'age'       => '40',
            'bloodType' => 'A',
            'status'    => 'closed',
        ]);

        $store = new UiStore();
        $this->assertCount(1, $store->waitingList());
        $this->assertCount(1, $store->availableDonors());
        $this->seeInDatabase('pairs', ['recipient_mrn' => 2009, 'status' => 'closed']);
    }

    /** A recipient with no pair simply keeps their own status. */
    public function testAnUnpairedRecipientKeepsTheirOwnStatus(): void
    {
        $this->post('recipients/new', [
            'mrn'       => '2011',
            'name'      => 'Solo',
            'age'       => '40',
            'bloodType' => 'A',
            'status'    => 'on_hold',
        ]);

        $this->seeInDatabase('recipients', ['mrn' => 2011, 'status' => 'on_hold']);
        $this->assertSame(0, $this->db->table('pairs')->countAllResults());
    }

    /** Anything outside the list is refused rather than reaching the ENUM. */
    public function testAStatusOutsideTheListIsIgnored(): void
    {
        $this->post('recipients/new', [
            'mrn'       => '2012',
            'name'      => 'Solo',
            'age'       => '40',
            'bloodType' => 'A',
            'status'    => 'nonsense',
        ]);

        $this->seeInDatabase('recipients', ['mrn' => 2012, 'status' => 'pending']);
    }

    // ---- Dates -------------------------------------------------------------

    /**
     * Each date is a text box holding DD/MM/YYYY plus a picker that posts
     * nothing. The picker used to inherit the previous field's name, because
     * CodeIgniter carries view data between `view()` calls — which made the
     * read-only Entry Date post as First Dialysis and move it on every save.
     */
    public function testEachDateFieldCarriesItsOwnNameAndAPickerThatPostsNothing(): void
    {
        $this->post('recipients/new', [
            'mrn'           => '3003',
            'name'          => 'Ahmed Test',
            'age'           => '41',
            'bloodType'     => 'O',
            'firstDialysis' => '01/03/2024',
        ]);

        $html = $this->get('recipients/3003?edit=personal')->getBody();

        $this->assertSame(1, substr_count($html, 'name="firstDialysis"'), 'only the dialysis box carries that name');
        // Entry Date renders read-only, with no name and no picker.
        $this->assertMatchesRegularExpression('/id="f-entry"(?![^>]*name=)/', $html);
        // The picker is unnamed, so it posts nothing.
        $this->assertStringNotContainsString('<input type="date" name', $html);
    }

    public function testSavingTheCardDoesNotMoveTheDialysisDate(): void
    {
        $this->post('recipients/new', [
            'mrn'           => '3004',
            'name'          => 'Ahmed Test',
            'age'           => '41',
            'bloodType'     => 'O',
            'firstDialysis' => '01/03/2024',
        ]);

        $this->post('recipients/3004', [
            'section'       => 'personal',
            'name'          => 'Ahmed Test',
            'age'           => '42',
            'bloodType'     => 'O',
            'firstDialysis' => '01/03/2024',
        ]);

        $this->seeInDatabase('recipients', ['mrn' => 3004, 'age' => 42, 'dialysis_start' => '2024-03-01']);
    }

    // ---- The score on the waiting list ------------------------------------

    /**
     * The Score column used to be a lookup table of made-up numbers keyed on
     * urgency. It is the real score now, and the list is ordered by it.
     */
    public function testTheWaitingListShowsTheComputedScoreAndOrdersByIt(): void
    {
        $this->addRecipient(1001, 'Urgent, Waited Longer', true, 30, 30);
        $this->addRecipient(1002, 'Urgent, Waited Less', true, 5, 5);
        $this->addRecipient(1003, 'Not Urgent, Waited Longest', false, 60, 60);

        $list = (new UiStore())->waitingList();

        // Urgent first, whatever the score; score orders within each group, so
        // the longest wait of all still comes last for not being urgent.
        $this->assertSame(
            ['Urgent, Waited Longer', 'Urgent, Waited Less', 'Not Urgent, Waited Longest'],
            array_column($list, 'name')
        );
        $this->assertEqualsWithDelta(6.0, $list[0]['score'], 0.001);
        $this->assertEqualsWithDelta(1.0, $list[1]['score'], 0.001);
        $this->assertEqualsWithDelta(12.0, $list[2]['score'], 0.001);

        $html = $this->get('recipients')->getBody();
        $this->assertStringContainsString('6.0', $html);
        $this->assertStringNotContainsString('5.2', $html, 'the prototype\'s fake score should be gone');
    }

    /** No dialysis date means no score, shown as a dash rather than as zero. */
    public function testARecipientWithoutADialysisDateScoresNothing(): void
    {
        $this->addRecipient(1001, 'No Dialysis', false, 12, null);

        $this->assertNull((new UiStore())->waitingList()[0]['score']);
        $this->assertStringContainsString('&mdash;', $this->get('recipients')->getBody());
    }

    // ---- Add Donor -------------------------------------------------------

    public function testAddDonorStoresEveryFieldTheFormCollects(): void
    {
        $this->post('donors/new', [
            'mrn'              => '4003',
            'name'             => 'Noura Test',
            'age'              => '35',
            'bloodType'        => 'AB',
            'donorGender'      => 'Female',
            'phone'            => '+966500000002',
            'address'          => 'Jeddah',
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
        foreach (['4004' => 'Noura Test', '4005' => 'Sara Test'] as $mrn => $name) {
            $this->post('donors/new', [
                'mrn'              => (string) $mrn,
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
            'mrn'         => '4030',
            'name'        => 'Noura Test',
            'age'         => '35',
            'bloodType'   => 'AB',
            'donorGender' => 'Female',
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
            'rMrn'           => '4006',
            'dMrn'           => '4007',
            'rName'          => 'Recipient Pair',
            'rAge'           => '52',
            'rBloodType'     => 'A',
            'rGender'        => 'Female',
            'rCity'          => 'Dammam',
            'rUrgent'        => '1',
            'rCoordinator'   => 'Coordinator Pair',
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
            'is_urgent'      => 1,
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
            'rMrn'       => '4010',
            'dMrn'       => '4011',
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
            'rMrn'       => '4010',
            'dMrn'       => '4011',
            'rName'      => 'Recipient Pair',
            'rAge'       => '52',
            'rBloodType' => 'A',
            'dName'      => 'Donor Pair',
            'dAge'       => '30',
            'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        // One card at a time, which is how the screen now submits.
        $this->post('pairs/' . $pairId, [
            'section'        => 'pair',
            'relationship'   => 'Spouse',
            'pairStatus'     => 'confirmed',
            'crossmatchDate' => '06/10/2026',
        ]);

        $this->post('pairs/' . $pairId, [
            'section'        => 'recipient',
            'rName'          => 'Recipient Edited',
            'rAge'           => '53',
            'rBloodType'     => 'A',
            'rUrgent'        => '1',
            'rGender'        => 'Male',
            'rMrp'           => (string) $this->mrpId,
            'rFirstDialysis' => '11/02/2023',
        ]);

        $this->post('pairs/' . $pairId, [
            'section'      => 'donor',
            'dName'        => 'Donor Edited',
            'dAge'         => '31',
            'dBloodType'   => 'A',
            'dGender'      => 'Female',
            'dMrp'         => (string) $this->mrpId,
            'dStatus'      => 'Completed',
            'dCoordinator' => 'Coordinator Three',
        ]);

        $this->seeInDatabase('pairs', [
            'id'              => $pairId,
            'status'          => 'confirmed',
            'relationship'    => 'Spouse',
            'crossmatch_date' => '2026-10-06',
        ]);
        $this->seeInDatabase('recipients', [
            'name'           => 'Recipient Edited',
            'gender'         => 'male',
            'is_urgent'      => 1,
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

    // ---- Paired exchange ---------------------------------------------------

    /**
     * The whole swap: two pairs in, two pairs out, nobody left over.
     *
     * Pair A is Recipient A with Donor A, pair B likewise. The exchange puts
     * Recipient A with Donor B — which takes Donor B out of pair B and leaves
     * Recipient B without one. Pairing Recipient B with Donor A closes the
     * loop, and then both old pairs give way to both new ones.
     */
    public function testATwoWaySwapReplacesBothPairs(): void
    {
        [$pairA, $pairB] = $this->twoPairsToExchange();

        $this->post('exchange/start/' . $pairA)->assertRedirectTo(site_url('exchange/build'));

        // Recipient A with Donor B. That breaks pair B.
        $this->post('exchange/build', ['action' => 'link', 'recipientMrn' => '8101', 'donorMrn' => '8202']);

        $html = $this->get('exchange/build')->getBody();
        $this->assertStringContainsString('1 of 2 pairs complete', $html);
        // Both leftovers are named, and what would happen to them is said.
        $this->assertStringContainsString('Still to pair:', $html);
        $this->assertStringContainsString('Recipient B', $html);
        $this->assertStringContainsString('Donor A', $html);
        $this->assertStringContainsString('go back to their list unpaired', $html);

        // Recipient B with Donor A closes the loop.
        $this->post('exchange/build', ['action' => 'link', 'recipientMrn' => '8201', 'donorMrn' => '8102']);

        $html = $this->get('exchange/build')->getBody();
        $this->assertStringContainsString('2 of 2 pairs complete', $html);
        $this->assertStringContainsString('Nobody is left without a pair', $html);

        $this->post('exchange/build', ['action' => 'confirm'])->assertRedirectTo(site_url('pairs'));

        // The two old pairs are closed — history, not deleted — and the two
        // new ones stand in their place.
        $this->seeInDatabase('pairs', ['id' => $pairA, 'status' => 'closed', 'closed_reason' => 'Paired exchange']);
        $this->seeInDatabase('pairs', ['id' => $pairB, 'status' => 'closed']);
        $this->seeInDatabase('pairs', ['recipient_mrn' => 8101, 'donor_mrn' => 8202, 'status' => 'paired_exchange']);
        $this->seeInDatabase('pairs', ['recipient_mrn' => 8201, 'donor_mrn' => 8102, 'status' => 'paired_exchange']);
        // The recipient's own status follows their pair, as everywhere else.
        $this->seeInDatabase('recipients', ['mrn' => 8101, 'status' => 'paired_exchange']);

        // And nobody is on a list they should not be: both are paired again.
        $store = new UiStore();
        $this->assertSame([], $store->availableDonors());
        $this->assertSame([], $store->waitingList());
    }

    /**
     * An exchange may be confirmed with somebody left over.
     *
     * A swap that only half works is a real outcome: the person nobody was
     * found for goes back to their list, records and workup intact, free to
     * be matched another day. Refusing to record it did not make it less true.
     */
    public function testAnExchangeMayLeaveSomebodyUnpaired(): void
    {
        [$pairA, $pairB] = $this->twoPairsToExchange();

        // Someone unpaired, who owes nothing to anyone.
        $this->post('recipients/new', ['mrn' => '8301', 'name' => 'Free Recipient', 'age' => '39', 'bloodType' => 'A']);

        $this->post('exchange/start/' . $pairA);
        // The free recipient takes Donor A, so Recipient A is left over.
        $this->post('exchange/build', ['action' => 'link', 'recipientMrn' => '8301', 'donorMrn' => '8102']);

        $html = $this->get('exchange/build')->getBody();
        $this->assertStringContainsString('Recipient A', $html, 'the partner they displaced is named');
        $this->assertStringContainsString('Confirm, leaving 1 person unpaired', $html);

        $this->post('exchange/build', ['action' => 'confirm'])->assertRedirectTo(site_url('pairs'));

        $this->seeInDatabase('pairs', ['id' => $pairA, 'status' => 'closed']);
        $this->seeInDatabase('pairs', ['recipient_mrn' => 8301, 'donor_mrn' => 8102, 'status' => 'paired_exchange']);
        // Recipient A is back on the waiting list rather than gone.
        $this->seeInDatabase('recipients', ['mrn' => 8101]);
        $waiting = array_column((new UiStore())->waitingList(), 'id');
        $this->assertContains('8101', $waiting);
        // And pair B, which this never touched, is untouched.
        $this->seeInDatabase('pairs', ['id' => $pairB, 'status' => 'active']);
    }

    /** There still has to be an exchange: confirming nothing is refused. */
    public function testConfirmingWithNoPairsMadeIsRefused(): void
    {
        [$pairA] = $this->twoPairsToExchange();

        $this->post('exchange/start/' . $pairA);
        $this->post('exchange/build', ['action' => 'confirm'])
            ->assertRedirectTo(site_url('exchange/build'));

        $this->seeInDatabase('pairs', ['id' => $pairA, 'status' => 'active']);
        $this->assertStringContainsString('at least two people', (string) session()->getFlashdata('ui_error'));
    }

    /** The list offers only pairs an exchange can move, and can be searched. */
    public function testTheExchangeListIsFilteredAndSearchable(): void
    {
        [$pairA, $pairB] = $this->twoPairsToExchange();
        model(\App\Models\PairModel::class)->update($pairB, ['status' => 'completed']);

        $html = $this->get('exchange')->getBody();
        $this->assertStringContainsString('Recipient A', $html);
        $this->assertStringNotContainsString('Recipient B', $html, 'a completed transplant is not exchangeable');

        // Search takes either side's file number.
        $this->assertStringContainsString('Recipient A', $this->get('exchange?q=8102')->getBody());
        $this->assertStringNotContainsString('Recipient A', $this->get('exchange?q=9999')->getBody());
    }

    /** Two pairs on this programme, both open. @return array{int, int} */
    private function twoPairsToExchange(): array
    {
        $this->post('pairs/new', [
            'rMrn' => '8101', 'dMrn' => '8102',
            'rName' => 'Recipient A', 'rAge' => '44', 'rBloodType' => 'A',
            'dName' => 'Donor A', 'dAge' => '33', 'dBloodType' => 'B',
        ]);
        $this->post('pairs/new', [
            'rMrn' => '8201', 'dMrn' => '8202',
            'rName' => 'Recipient B', 'rAge' => '51', 'rBloodType' => 'B',
            'dName' => 'Donor B', 'dAge' => '36', 'dBloodType' => 'A',
        ]);

        $ids = array_column($this->db->table('pairs')->orderBy('id')->get()->getResultArray(), 'id');

        return [(int) $ids[0], (int) $ids[1]];
    }

    // ---- The dashboard's per-doctor statistic ------------------------------

    /**
     * The last statistic counts one physician's recipients, not the programme's.
     *
     * Which physician is a dropdown, and choosing one is a GET — so the count
     * follows the choice with JavaScript off, the same as with it on.
     */
    public function testTheDashboardCountsRecipientsForTheChosenDoctor(): void
    {
        $this->db->table('mrp')->insert(['code' => 'MRP-002', 'name' => 'Dr. Second']);
        $second = (int) $this->db->insertID();

        // Two under the first physician, one under the second.
        foreach ([['5001', (string) $this->mrpId], ['5002', (string) $this->mrpId], ['5003', (string) $second]] as [$mrn, $mrp]) {
            $this->post('recipients/new', [
                'mrn' => $mrn, 'name' => 'Recipient ' . $mrn, 'age' => '40',
                'bloodType' => 'A', 'selectedMrp' => $mrp,
            ]);
        }

        $html = $this->get('dashboard?mrp=' . $this->mrpId)->getBody();
        $this->assertStringContainsString('Number of Recipients for', $html);
        $this->assertStringContainsString('>Dr. Test</option>', $html);
        $this->assertSame(2, $this->countFor($html));

        $this->assertSame(1, $this->countFor($this->get('dashboard?mrp=' . $second)->getBody()));

        // Active / Scheduled was the programme-wide row this replaced.
        $this->assertStringNotContainsString('Active / Scheduled', $html);
    }

    /** Nothing chosen, or something chosen that is gone, falls back sensibly. */
    public function testTheDoctorStatisticFallsBackToTheFirstOnTheList(): void
    {
        $this->post('recipients/new', [
            'mrn' => '5004', 'name' => 'Recipient', 'age' => '40',
            'bloodType' => 'A', 'selectedMrp' => (string) $this->mrpId,
        ]);

        // No `?mrp=` at all, and an id belonging to nobody, both land on the
        // first physician rather than on a count belonging to no one.
        foreach (['dashboard', 'dashboard?mrp=999999', 'dashboard?mrp=nonsense'] as $url) {
            $html = $this->get($url)->getBody();
            $this->assertStringContainsString('value="' . $this->mrpId . '" selected', $html, $url);
            $this->assertSame(1, $this->countFor($html), $url);
        }
    }

    /** The number printed beside the doctor's name. */
    private function countFor(string $html): int
    {
        $tail = substr($html, strrpos($html, 'Number of Recipients for') ?: 0);
        preg_match('~<span class="stat-value">(\d+)</span>~', $tail, $m);

        return (int) ($m[1] ?? -1);
    }

    // ---- Deleting from a list --------------------------------------------

    /** Every list offers it, at the end of the row. */
    public function testEachListOffersADeleteButton(): void
    {
        $this->post('recipients/new', ['mrn' => '9201', 'name' => 'Recipient One', 'age' => '40', 'bloodType' => 'A']);
        $this->post('donors/new', ['mrn' => '9202', 'name' => 'Donor One', 'age' => '30', 'bloodType' => 'A']);
        $this->post('pairs/new', [
            'rMrn' => '9203', 'dMrn' => '9204',
            'rName' => 'Recipient Two', 'rAge' => '50', 'rBloodType' => 'O',
            'dName' => 'Donor Two', 'dAge' => '35', 'dBloodType' => 'O',
        ]);
        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        foreach ([
            'recipients' => 'recipients/9201/delete',
            'donors'     => 'donors/9202/delete',
            'pairs'      => 'pairs/' . $pairId . '/delete',
        ] as $list => $deleteUrl) {
            $html = $this->get($list)->getBody();

            $this->assertStringContainsString(site_url($deleteUrl), $html, "{$list} should offer delete");
            // One dialog for the whole list, filled in by whichever row asked.
            $this->assertSame(1, substr_count($html, 'id="confirm-delete"'));
        }
    }

    /**
     * The button asks first, and asks on a page of its own.
     *
     * A GET that deletes goes off when a browser prefetches the link, which on
     * a patient register is not recoverable — so GET only ever renders the
     * question.
     */
    public function testTheDeleteLinkAsksRatherThanDeletes(): void
    {
        $this->post('recipients/new', ['mrn' => '9205', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);

        $html = $this->get('recipients/9205/delete')->getBody();

        $this->assertStringContainsString('Delete Layla Test?', $html);
        $this->assertStringContainsString('cannot be undone', $html);
        $this->seeInDatabase('recipients', ['mrn' => 9205]);
    }

    /** Posting it removes the record, and the workup with it. */
    public function testDeletingARecipientTakesTheirWorkupWithThem(): void
    {
        $this->post('recipients/new', ['mrn' => '9206', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);

        $lab = $this->db->table('labs')
            ->where(['organ_code' => 'kidney', 'person_type' => 'recipient', 'name' => 'HIV'])
            ->get()->getRowArray();

        $this->post('recipients/9206', [
            'section' => 'labs',
            'labs'    => [['id' => $lab['id'], 'name' => 'HIV', 'status' => 'negative']],
        ]);
        $this->seeInDatabase('lab_results', ['person_mrn' => 9206]);

        $this->post('recipients/9206/delete')->assertRedirectTo(site_url('recipients'));

        $this->dontSeeInDatabase('recipients', ['mrn' => 9206]);
        $this->dontSeeInDatabase('lab_results', ['person_mrn' => 9206, 'person_type' => 'recipient']);
    }

    /**
     * Deleting a pair unmakes the link. It does not delete the two it joined.
     *
     * They go back to their lists with their records and workups intact, free
     * to be matched again.
     */
    public function testDeletingAPairKeepsBothPeople(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9207', 'dMrn' => '9208',
            'rName' => 'Recipient Three', 'rAge' => '44', 'rBloodType' => 'A',
            'dName' => 'Donor Three', 'dAge' => '33', 'dBloodType' => 'A',
        ]);
        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        $this->post('pairs/' . $pairId . '/delete')->assertRedirectTo(site_url('pairs'));

        $this->dontSeeInDatabase('pairs', ['id' => $pairId]);
        $this->seeInDatabase('recipients', ['mrn' => 9207]);
        $this->seeInDatabase('donors', ['mrn' => 9208]);

        // And both are free again, which is what the lists are for.
        $store = new UiStore();
        $this->assertCount(1, $store->waitingList());
        $this->assertCount(1, $store->availableDonors());
    }

    /** Somebody a pair names cannot just vanish from under it. */
    public function testAPairedPersonIsNotDeletedButExplained(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9209', 'dMrn' => '9210',
            'rName' => 'Recipient Four', 'rAge' => '44', 'rBloodType' => 'A',
            'dName' => 'Donor Four', 'dAge' => '33', 'dBloodType' => 'A',
        ]);

        $this->post('recipients/9209/delete')->assertRedirectTo(site_url('recipients'));

        $this->seeInDatabase('recipients', ['mrn' => 9209]);
        $this->assertStringContainsString(
            'is in pair #',
            (string) session()->getFlashdata('ui_error')
        );
    }

    /** A record on the other programme is not this programme's to delete. */
    public function testARecordFromAnotherProgrammeIsNotDeleted(): void
    {
        $this->post('recipients/new', ['mrn' => '9211', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);

        // Switched to the liver programme; the kidney register is not its
        // to delete from, even though an MRN finds a record either way.
        $this->withSession(['ui_signed_in' => true, 'ui_organ' => 'liver']);
        $this->post('recipients/9211/delete');

        $this->seeInDatabase('recipients', ['mrn' => 9211]);
    }

    // ---- The printed sheet -----------------------------------------------

    /**
     * Export is a sheet to print, not a file to open in a spreadsheet.
     *
     * It shows the same pairs the table does, narrowed the same way, and it
     * carries nothing that only means something on screen.
     */
    public function testTheExportIsAPrintableSheetOfTheFilteredPairs(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9101', 'dMrn' => '9102',
            'rName' => 'Recipient One', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Donor One', 'dAge' => '30', 'dBloodType' => 'A',
        ]);
        $this->post('pairs/new', [
            'rMrn' => '9103', 'dMrn' => '9104',
            'rName' => 'Recipient Two', 'rAge' => '50', 'rBloodType' => 'O',
            'dName' => 'Donor Two', 'dAge' => '35', 'dBloodType' => 'O',
        ]);

        $html = $this->get('pairs/print')->getBody();

        $this->assertStringContainsString('Pairs List &mdash; Kidney Programme', $html);
        $this->assertStringContainsString('All pairs', $html);
        $this->assertStringContainsString('Recipient One', $html);
        $this->assertStringContainsString('Donor Two', $html);

        // Its own document: no sidebar, no chips, and a stylesheet for paper.
        $this->assertStringContainsString('assets/ui/css/print.css', $html);
        $this->assertStringNotContainsString('class="sidebar', $html);
        $this->assertStringNotContainsString('class="chip', $html);

        // A pair is one block, so a page break cannot land between a
        // recipient and the donor they are matched to.
        $this->assertSame(2, substr_count($html, '<tbody class="pair">'));

        // The filters narrow the sheet exactly as they narrow the table.
        $filtered = $this->get('pairs/print?bt=A')->getBody();
        $this->assertStringContainsString('Blood type A', $filtered);
        $this->assertStringContainsString('Recipient One', $filtered);
        $this->assertStringNotContainsString('Recipient Two', $filtered);
    }

    /** The list links to the sheet, and no longer to a CSV. */
    public function testThePairsListOffersThePdfExport(): void
    {
        $html = $this->get('pairs')->getBody();

        $this->assertStringContainsString('Export PDF', $html);
        $this->assertStringContainsString(site_url('pairs/print'), $html);
        $this->assertStringNotContainsString('Export CSV', $html);
    }

    // ---- Lab workup ------------------------------------------------------

    public function testALabResultEnteredOnARecordIsStored(): void
    {
        $this->post('recipients/new', ['mrn' => '4020', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $mrn = (int) $this->db->table('recipients')->get()->getRowArray()['mrn'];
        // A serology, which answers Positive or Negative.
        $lab = $this->db->table('labs')
            ->where(['organ_code' => 'kidney', 'person_type' => 'recipient', 'name' => 'HIV'])
            ->get()->getRowArray();

        $this->post('recipients/' . $mrn, [
            'section'   => 'labs',
            'name'      => 'Ahmed Test',
            'age'       => '41',
            'bloodType' => 'O',
            'labs'      => [[
                'id'     => $lab['id'],
                'name'   => $lab['name'],
                'status' => 'negative',
                'result' => 'Non-reactive',
                'date'   => '17/09/2026',
                'notes'  => 'repeat in 3 months',
            ]],
        ]);

        $this->seeInDatabase('lab_results', [
            'person_mrn'  => $mrn,
            'person_type' => 'recipient',
            'lab_id'      => $lab['id'],
            'status'      => 'negative',
            'value'       => 'Non-reactive',
            'taken_on'    => '2026-09-17',
            'notes'       => 'repeat in 3 months',
        ]);
    }

    /**
     * Each card offers its own test's answers, and only those. A serology is
     * Positive or Negative; a referral is Cleared or not; nothing is offered
     * the generic Pending / Done / Flagged the cards used to show.
     */
    public function testEachCardOffersItsOwnTestsAnswers(): void
    {
        $this->post('recipients/new', ['mrn' => '4021', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $html = $this->get('recipients/4021?edit=labs')->getBody();

        foreach ([
            'HIV'         => ['Positive', 'Negative'],
            'Dental'      => ['Cleared', 'Not cleared'],
            'MMR'         => ['Given', 'Not required', 'Not given'],
            'CBC'         => ['Acceptable', 'Abnormal'],
            'Blood group' => ['A', 'B', 'AB', 'O'],
        ] as $test => $answers) {
            $card = $this->cardFor($html, $test);

            foreach ($answers as $answer) {
                $this->assertStringContainsString('>' . $answer . '</button>', $card, "{$test} should offer {$answer}");
            }
        }

        // The serology card offers no Cleared, and the referral no Positive.
        $this->assertStringNotContainsString('>Cleared</button>', $this->cardFor($html, 'HIV'));
        $this->assertStringNotContainsString('>Positive</button>', $this->cardFor($html, 'Dental'));
    }

    /** An answer the test does not offer is refused rather than stored. */
    public function testAnAnswerFromAnotherTestsVocabularyIsRefused(): void
    {
        $this->post('recipients/new', ['mrn' => '4022', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $lab = $this->db->table('labs')
            ->where(['organ_code' => 'kidney', 'person_type' => 'recipient', 'name' => 'Dental'])
            ->get()->getRowArray();

        $this->post('recipients/4022', [
            'section' => 'labs',
            'labs'    => [[
                'id'     => $lab['id'],
                'name'   => $lab['name'],
                'status' => 'negative',
                'notes'  => 'a referral is not a serology',
            ]],
        ]);

        // The note is kept; the answer is not one this test offers.
        $this->seeInDatabase('lab_results', ['lab_id' => $lab['id'], 'status' => 'not_done']);
    }

    /**
     * The comment is on the face of every card, not behind the pencil.
     *
     * The check list prints a comment line under each test, so a workup that
     * only carries comments — no answers, no dates — is a real one and has to
     * survive the round trip.
     */
    public function testACommentIsOfferedOnEveryTestAndKeptOnItsOwn(): void
    {
        $this->post('recipients/new', ['mrn' => '4024', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $html = $this->get('recipients/4024?edit=labs')->getBody();
        $this->assertSame(71, substr_count($html, 'class="lab-comment"'), 'one per test');

        // HLA typing asks for the loci the sheet prints under its comment.
        $this->assertStringContainsString('DRB1', $this->cardFor($html, 'HLA Typing'));
        $this->assertStringNotContainsString('DRB1', $this->cardFor($html, 'CBC'));

        $cbc = $this->db->table('labs')
            ->where(['organ_code' => 'kidney', 'person_type' => 'recipient', 'name' => 'CBC'])
            ->get()->getRowArray();

        $this->post('recipients/4024', [
            'section' => 'labs',
            'labs'    => [['id' => $cbc['id'], 'name' => 'CBC', 'status' => 'not_done', 'notes' => 'Hb 13.4']],
        ]);

        $this->seeInDatabase('lab_results', ['lab_id' => $cbc['id'], 'status' => 'not_done', 'notes' => 'Hb 13.4']);
        $this->assertStringContainsString('Hb 13.4', $this->get('recipients/4024')->getBody());
    }

    /** A result belongs to the side that entered it, not to a test's name. */
    public function testATestFromTheOtherSidesSheetIsNotStored(): void
    {
        $this->post('recipients/new', ['mrn' => '4025', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $donorCbc = $this->db->table('labs')
            ->where(['organ_code' => 'kidney', 'person_type' => 'donor', 'name' => 'CBC'])
            ->get()->getRowArray();

        $this->post('recipients/4025', [
            'section' => 'labs',
            'labs'    => [['id' => $donorCbc['id'], 'name' => 'CBC', 'status' => 'acceptable']],
        ]);

        $this->dontSeeInDatabase('lab_results', ['lab_id' => $donorCbc['id']]);
    }

    /** The bar counts answered tests, whatever the answer was. */
    public function testTheProgressBarCountsAnsweredTests(): void
    {
        $this->post('recipients/new', ['mrn' => '4023', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $hiv    = $this->db->table('labs')->where(['organ_code' => 'kidney', 'person_type' => 'recipient', 'name' => 'HIV'])->get()->getRowArray();
        $dental = $this->db->table('labs')->where(['organ_code' => 'kidney', 'person_type' => 'recipient', 'name' => 'Dental'])->get()->getRowArray();

        $this->post('recipients/4023', [
            'section' => 'labs',
            'labs'    => [
                ['id' => $hiv['id'], 'name' => 'HIV', 'status' => 'negative'],
                ['id' => $dental['id'], 'name' => 'Dental', 'status' => 'not_applicable'],
            ],
        ]);

        $html = $this->get('recipients/4023')->getBody();
        $this->assertStringContainsString('2 of 71 completed', $html);
    }

    /** @return string The markup of one test's card. */
    private function cardFor(string $html, string $test): string
    {
        $cards = explode('class="lab-card', $html);

        foreach ($cards as $card) {
            if (str_contains($card, '>' . $test . '</div>')) {
                return $card;
            }
        }

        $this->fail("no card for {$test}");
    }

    // ---- MRP -------------------------------------------------------------

    public function testAddingAnMrpStoresIt(): void
    {
        $this->post('mrp', ['id' => 'MRP-010', 'name' => 'Dr. Sara Nephro']);

        $this->seeInDatabase('mrp', ['code' => 'MRP-010', 'name' => 'Dr. Sara Nephro']);
    }

    // ---- Helpers ----------------------------------------------------------

    private function addRecipient(int $mrn, string $name, bool $urgent, int $monthsWaiting, ?int $monthsOnDialysis): void
    {
        $this->db->table('recipients')->insert([
            'mrn'            => $mrn,
            'name'           => $name,
            'organ_code'     => 'kidney',
            'blood_group'    => 'O',
            'is_urgent'      => $urgent ? 1 : 0,
            'entry_date'     => date('Y-m-d', strtotime("-{$monthsWaiting} months")),
            'dialysis_start' => $monthsOnDialysis === null ? null : date('Y-m-d', strtotime("-{$monthsOnDialysis} months")),
        ]);
    }
}
