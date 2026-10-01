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
     * A recipient collects potential donors rather than linking to one, so the
     * button adds to that list and the choice is on the record itself.
     */
    public function testTheRecipientsButtonAddsAPotentialDonor(): void
    {
        $this->post('recipients/new', ['mrn' => '9001', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);
        $this->post('donors/new', ['mrn' => '9009', 'name' => 'Free Donor', 'age' => '33', 'bloodType' => 'B']);

        $html = $this->get('recipients/9001')->getBody();

        $this->assertStringContainsString('Add Potential Donor', $html);
        // With scripting off it goes to Add Donor, entered for this recipient.
        $this->assertStringContainsString(site_url('donors/new') . '?for=9001', $html);
        // And the same choice is on the page as a dialog, the second half of
        // it a select rather than a page of its own.
        $this->assertStringContainsString('<dialog id="link-choice"', $html);
        $this->assertStringContainsString('A new donor', $html);
        $this->assertStringContainsString('A donor already registered', $html);
        $this->assertStringContainsString('name="donorMrn"', $html);
        $this->assertStringContainsString('Free Donor', $html);
        // Nothing of the old flow: no pair is made from this screen.
        $this->assertStringNotContainsString(site_url('pairs/new') . '?recipient=9001', $html);
    }

    /** And the two pages it used to lead to are gone with it. */
    public function testTheRecipientsLinkPagesAreGone(): void
    {
        $this->post('recipients/new', ['mrn' => '9002', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);

        $html = $this->get('recipients/9002')->getBody();
        $this->assertStringNotContainsString('recipients/9002/link', $html);

        $routes = service('routes')->getRoutes('get');
        $this->assertArrayNotHasKey('recipients/([^/]+)/link', $routes);
        $this->assertArrayNotHasKey('recipients/([^/]+)/link/existing', $routes);
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

 
    // ---- "an existing one": pick from the list -----------------------------

    public function testThePickerListsUnpairedCounterpartsOnly(): void
    {
        $this->post('donors/new', ['mrn' => '9012', 'name' => 'Free Donor', 'age' => '33', 'bloodType' => 'B']);
        $this->post('recipients/new', ['mrn' => '9013', 'name' => 'Free Recipient', 'age' => '38', 'bloodType' => 'B']);
        $this->post('pairs/new', [
            'rMrn' => '9014', 'dMrn' => '9015',
            'rName' => 'Paired Recipient', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $html = $this->get('donors/9012/link/existing')->getBody();

        $this->assertStringContainsString('Free Recipient', $html);
        $this->assertStringNotContainsString('Paired Recipient', $html);
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

        $html = $this->get('donors/9016/link/existing')->getBody();

        $this->assertStringNotContainsString('name="mrn" value="9016"', $html);
    }

    public function testChoosingFromThePickerCreatesThePair(): void
    {
        $this->post('recipients/new', ['mrn' => '9017', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);
        $this->post('donors/new', ['mrn' => '9018', 'name' => 'Free Donor', 'age' => '33', 'bloodType' => 'B']);

        $this->post('donors/9018/link/existing', [
            'mrn'            => '9017',
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

    public function testConsideringSomebodyAlreadyPairedIsRefused(): void
    {
        $this->post('recipients/new', ['mrn' => '9019', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);
        $this->post('pairs/new', [
            'rMrn' => '9020', 'dMrn' => '9021',
            'rName' => 'Paired Recipient', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        // A donor in a pair is spoken for; considering them for somebody else
        // would be offering a decision that has already been taken.
        $this->post('recipients/9019/donors', ['donorMrn' => '9021']);

        $this->dontSeeInDatabase('potential_donors', ['recipient_mrn' => 9019, 'donor_mrn' => 9021]);
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

        foreach (UiStore::PERSON_STATUS_OPTIONS as $value => $label) {
            $this->assertStringContainsString('value="' . $value . '"', $html);
            $this->assertStringContainsString('>' . $label . '</option>', $html);
        }

        // The three a pair alone can be are not on a person's own record:
        // somebody is not "transplanted", their case is.
        foreach (['transplanted', 'paired_exchange', 'closed'] as $pairOnly) {
            $this->assertStringNotContainsString('value="' . $pairOnly . '"', $html);
        }
    }

    /** The pair's Match Status is the same list, not a second one. */
    /** The pair offers six; a person's three are exactly the first three. */
    public function testThePairOffersItsOwnLongerList(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '2001', 'dMrn' => '2002',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];
        $html   = $this->get('pairs/' . $pairId)->getBody();

        foreach (array_keys(UiStore::PAIR_STATUS_OPTIONS) as $value) {
            $this->assertStringContainsString('<option value="' . $value . '"', $html);
        }

        // Retired words are still named for old records, never offered again.
        foreach (['pending', 'confirmed', 'completed'] as $retired) {
            $this->assertStringNotContainsString('<option value="' . $retired . '"', $html);
            $this->assertArrayHasKey($retired, UiStore::STATUS_OPTIONS);
        }

        $this->assertSame(
            array_keys(UiStore::PERSON_STATUS_OPTIONS),
            array_slice(array_keys(UiStore::PAIR_STATUS_OPTIONS), 0, 3)
        );
    }

    /** They agree from the moment the pair exists. */
 
 
    /**
     * The pair's status reaches the recipient only where it can.
     *
     * The three they share carry across as they always did. The three only a
     * pair can be — Transplanted, Paired Exchange, Closed — describe the case
     * and not the person, so the recipient's own status is left alone rather
     * than forced into a word their record does not have.
     */
 
    /**
     * `closed` is the one status with meaning beyond its label: it is what an
     * open pair is defined against, so it still frees both sides.
     */
    /**
     * Closing is the pair's to do, and the pair asks why.
     *
     * It is the one status that means something beyond its label — both sides
     * go back on their lists — so it is the one the card asks a reason for,
     * and the reason is kept only while the pair is closed.
     */
    public function testClosingAPairAsksWhyAndReleasesBothSides(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '2009', 'dMrn' => '2010',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        // The box is on the card, and says what it is for.
        $this->assertStringContainsString('Why was it closed?', $this->get('pairs/' . $pairId)->getBody());

        $this->post('pairs/' . $pairId, [
            'section'      => 'pair',
            'pairStatus'   => 'closed',
            'closedReason' => 'Crossmatch positive on repeat',
        ]);

        $this->seeInDatabase('pairs', [
            'id'            => $pairId,
            'status'        => 'closed',
            'closed_reason' => 'Crossmatch positive on repeat',
        ]);

        $store = new UiStore();
        $this->assertCount(1, $store->waitingList());
        $this->assertCount(1, $store->availableDonors());

        // Reopening it drops the reason: it is about an ending that is undone.
        $this->post('pairs/' . $pairId, ['section' => 'pair', 'pairStatus' => 'active', 'closedReason' => '']);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'active', 'closed_reason' => null]);
    }

    /** A donor's own record offers the same three a recipient's does. */
    public function testTheDonorStatusOffersTheSameThree(): void
    {
        $html = $this->get('donors/new')->getBody();

        foreach (['On Hold', 'Active', 'Declined'] as $label) {
            $this->assertStringContainsString('>' . $label . '</option>', $html);
        }

        foreach (['Completed', 'Cancelled'] as $retired) {
            $this->assertStringNotContainsString('>' . $retired . '</option>', $html);
        }

        $this->post('donors/new', ['mrn' => '2011', 'name' => 'D', 'age' => '30', 'bloodType' => 'A', 'donorStatus' => 'Declined']);
        $this->seeInDatabase('donors', ['mrn' => 2011, 'status' => 'declined']);
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
     * Entry Date post as First Dialysis and move it on every save.
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

        foreach (['birthDate', 'firstDialysis', 'dateRegistered'] as $field) {
            $this->assertSame(1, substr_count($html, 'name="' . $field . '"'), 'exactly one box carries ' . $field);
        }

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
     * The chain closes on itself: two pairs in, two pairs out.
     *
     * Pair A is an A recipient with a B donor, pair B a B recipient with an A
     * donor — neither can use their own. Crossing them works, and the chain
     * comes back round to where it started.
     */
    public function testAChainThatClosesOnItselfIsSaved(): void
    {
        [$pairA, $pairB] = $this->twoPairsToExchange();

        $this->post('exchange/start/' . $pairA)->assertRedirectTo(site_url('exchange/build'));

        // The A recipient takes the A donor out of pair B.
        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8101', 'donorMrn' => '8202']);

        $html = $this->get('exchange/build')->getBody();
        $this->assertStringContainsString('1 recipient still without a donor', $html);
        $this->assertStringContainsString('Recipient B', $html);

        // And the B recipient takes the B donor, closing the circle.
        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8201', 'donorMrn' => '8102']);

        $html = $this->get('exchange/build')->getBody();
        $this->assertStringContainsString('The chain is complete', $html);

        $this->post('exchange/build', ['action' => 'confirm'])->assertRedirectTo(site_url('pairs'));

        $this->seeInDatabase('pairs', ['id' => $pairA, 'status' => 'closed', 'closed_reason' => 'Paired exchange']);
        $this->seeInDatabase('pairs', ['id' => $pairB, 'status' => 'closed']);
        $this->seeInDatabase('pairs', ['recipient_mrn' => 8101, 'donor_mrn' => 8202, 'status' => 'paired_exchange']);
        $this->seeInDatabase('pairs', ['recipient_mrn' => 8201, 'donor_mrn' => 8102, 'status' => 'paired_exchange']);
        $this->seeInDatabase('recipients', ['mrn' => 8101, 'status' => 'paired_exchange']);
    }

    /** Only a blood-group match is ever offered, from either side. */
    public function testTheListsOfferCompatibleMatchesOnly(): void
    {
        [$pairA] = $this->twoPairsToExchange();
        // An AB donor, who can only give to AB — so not to either recipient.
        $this->post('donors/new', ['mrn' => '8601', 'name' => 'AB Donor', 'age' => '40', 'bloodType' => 'AB']);
        // An O donor, who can give to anyone.
        $this->post('donors/new', ['mrn' => '8602', 'name' => 'Universal Donor', 'age' => '41', 'bloodType' => 'O']);

        $this->post('exchange/start/' . $pairA);
        $html = $this->get('exchange/build')->getBody();

        $this->assertStringContainsString('Universal Donor', $html, 'O gives to everyone');
        $this->assertStringNotContainsString('AB Donor', $html, 'AB gives only to AB');

        // Posting the incompatible one anyway is refused, not merely hidden.
        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8101', 'donorMrn' => '8601']);
        $this->assertStringContainsString('cannot give to', (string) session()->getFlashdata('ui_error'));
    }

    /** A donor already spoken for is gone from the other lists. */
    public function testADonorCannotBeMatchedTwice(): void
    {
        [$pairA] = $this->twoPairsToExchange();
        $this->post('recipients/new', ['mrn' => '8701', 'name' => 'Second Recipient', 'age' => '39', 'bloodType' => 'AB']);

        $this->post('exchange/start/' . $pairA);
        // The A recipient takes the A donor from pair B.
        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8101', 'donorMrn' => '8202']);

        // The same donor offered to somebody else is refused.
        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8701', 'donorMrn' => '8202']);
        $this->assertStringContainsString('already matched', (string) session()->getFlashdata('ui_error'));
    }

    /**
     * A recipient without a donor stops the save; a donor without a recipient
     * only has to be decided about.
     */
    public function testARecipientWithoutADonorStopsTheSave(): void
    {
        [$pairA] = $this->twoPairsToExchange();
        $this->post('donors/new', ['mrn' => '8801', 'name' => 'Spare Donor', 'age' => '44', 'bloodType' => 'O']);

        $this->post('exchange/start/' . $pairA);
        // Pair A's recipient takes the free O donor. Pair A's own donor is now
        // spare, and nobody is left without one — but the donor needs a fate.
        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8101', 'donorMrn' => '8801']);

        $html = $this->get('exchange/build')->getBody();
        $this->assertStringContainsString('1 donor without a recipient', $html);

        // Saving is refused while that is undecided.
        $this->post('exchange/build', ['action' => 'confirm'])->assertRedirectTo(site_url('exchange/build'));
        $this->assertStringContainsString('what becomes of each donor', (string) session()->getFlashdata('ui_error'));

        // Decide, and it saves.
        $this->post('exchange/build', ['action' => 'fate', 'donorMrn' => '8102', 'fate' => 'available']);
        $this->post('exchange/build', ['action' => 'confirm'])->assertRedirectTo(site_url('pairs'));

        $this->seeInDatabase('pairs', ['recipient_mrn' => 8101, 'donor_mrn' => 8801]);
        // Sent back to the register rather than deleted.
        $this->seeInDatabase('donors', ['mrn' => 8102]);
        $this->assertContains('8102', array_column((new UiStore())->availableDonors(), 'id'));
    }

    /**
     * The two fates only appear once the spare donor is the one left.
     *
     * While a recipient is still without a donor the chain has somewhere to
     * go, so ending it here is not yet a choice anybody has to make — and
     * offering it then invites a decision nobody needs.
     */
    public function testTheSpareDonorsFatesWaitUntilNobodyElseIs(): void
    {
        [$pairA] = $this->twoPairsToExchange();
        $this->post('donors/new', ['mrn' => '8803', 'name' => 'Spare Donor', 'age' => '44', 'bloodType' => 'O']);

        // Straight after starting, both sides of pair A are open: a recipient
        // without a donor, and a donor without a recipient.
        $this->post('exchange/start/' . $pairA);
        $html = $this->get('exchange/build')->getBody();
        $this->assertStringContainsString('Donor without a recipient', $html);
        $this->assertStringNotContainsString('btn-fate', $html, 'a recipient is still waiting');

        // Match the recipient, and now the donor really is the only one left.
        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8101', 'donorMrn' => '8803']);
        $html = $this->get('exchange/build')->getBody();
        $this->assertStringContainsString('btn-fate', $html);
        $this->assertStringContainsString('Everyone else is matched', $html);
        $this->assertStringContainsString('Move to the available donors list', $html);
        $this->assertStringContainsString('Delete from the system', $html);
    }

    /** The screen is the chain: the duplicate card above it is gone. */
    public function testTheBuilderShowsTheChainAndNothingElse(): void
    {
        [$pairA] = $this->twoPairsToExchange();
        $this->post('exchange/start/' . $pairA);

        $html = $this->get('exchange/build')->getBody();

        $this->assertStringContainsString('>The chain</h2>', $html);
        $this->assertStringNotContainsString('The pair being exchanged', $html);
        $this->assertSame(1, substr_count($html, 'class="card-title card-title--mb4"'));
    }

    /** The other fate: the spare donor is removed from the system. */
    public function testASpareDonorCanBeDeletedInstead(): void
    {
        [$pairA] = $this->twoPairsToExchange();
        $this->post('donors/new', ['mrn' => '8802', 'name' => 'Spare Donor', 'age' => '44', 'bloodType' => 'O']);

        $this->post('exchange/start/' . $pairA);
        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8101', 'donorMrn' => '8802']);
        $this->post('exchange/build', ['action' => 'fate', 'donorMrn' => '8102', 'fate' => 'delete']);

        // The review says so before it happens.
        $review = $this->get('exchange/review')->getBody();
        $this->assertStringContainsString('Deleted from the system', $review);
        $this->assertStringContainsString('cannot be undone', $review);

        $this->post('exchange/build', ['action' => 'confirm'])->assertRedirectTo(site_url('pairs'));
        $this->dontSeeInDatabase('donors', ['mrn' => 8102]);
        // The pair naming them goes too — a foreign key would hold it there
        // otherwise, and half a deletion is not one.
        $this->dontSeeInDatabase('pairs', ['id' => $pairA]);
    }

    /** Undo steps back, and cancelling puts everything back as it was. */
    public function testUndoAndCancelLeaveNothingBehind(): void
    {
        [$pairA, $pairB] = $this->twoPairsToExchange();

        $this->post('exchange/start/' . $pairA);
        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8101', 'donorMrn' => '8202']);
        $this->assertStringContainsString('Recipient A', $this->get('exchange/build')->getBody());

        $this->post('exchange/build', ['action' => 'undo']);
        $html = $this->get('exchange/build')->getBody();
        // Back to the starting pair alone: its recipient, and nobody else's.
        // Recipient B is still named on the screen — they are a compatible
        // choice for the spare donor — but they are no longer a link in the
        // chain, which is one node again.
        $this->assertStringContainsString('1 recipient still without a donor', $html, 'the link is gone again');
        // Two nodes, both from the starting pair: its recipient needing a
        // donor, and its donor spare. Nothing of pair B is in the chain.
        $this->assertSame(2, substr_count($html, 'class="chain-index"'));

        $this->post('exchange/build', ['action' => 'discard'])->assertRedirectTo(site_url('exchange'));

        // Nothing was ever written, so both pairs stand exactly as they were.
        $this->seeInDatabase('pairs', ['id' => $pairA, 'status' => 'active']);
        $this->seeInDatabase('pairs', ['id' => $pairB, 'status' => 'active']);
        $this->assertSame(2, $this->db->table('pairs')->countAllResults());
    }

    /**
     * A pair is on the exchange list only once its own screen offers it.
     *
     * That is the consent: without it a pair could be swapped apart by
     * somebody who never proposed the swap.
     */
    public function testAPairIsOnlyOfferedAfterPairExchangeIsPressed(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '8401', 'dMrn' => '8402',
            'rName' => 'Recipient C', 'rAge' => '44', 'rBloodType' => 'A',
            'dName' => 'Donor C', 'dAge' => '33', 'dBloodType' => 'B',
        ]);
        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        // Not offered yet, so not on the list — and not startable either.
        $this->assertStringNotContainsString('Recipient C', $this->get('exchange')->getBody());
        $this->post('exchange/start/' . $pairId)->assertRedirectTo(site_url('exchange'));

        // The pair's own screen offers the button, and pressing it is a post.
        $this->assertStringContainsString('Pair Exchange', $this->get('pairs/' . $pairId)->getBody());
        $this->post('pairs/' . $pairId, ['section' => 'exchange', 'forExchange' => '1']);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'for_exchange' => 1]);

        $this->assertStringContainsString('Recipient C', $this->get('exchange')->getBody());
        $this->post('exchange/start/' . $pairId)->assertRedirectTo(site_url('exchange/build'));

        // And it can be taken back.
        $this->post('pairs/' . $pairId, ['section' => 'exchange', 'forExchange' => '0']);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'for_exchange' => 0]);
        $this->assertStringNotContainsString('Recipient C', $this->get('exchange')->getBody());
    }

    /** Somebody in a pair nobody offered is not on the table to displace. */
    public function testAPairNotOfferedCannotBeBrokenByAnExchange(): void
    {
        [$pairA, $pairB] = $this->twoPairsToExchange();
        $this->post('pairs/' . $pairB, ['section' => 'exchange', 'forExchange' => '0']);

        $this->post('exchange/start/' . $pairA);

        $html = $this->get('exchange/build')->getBody();
        $this->assertStringNotContainsString('Donor B', $html, 'pair B was withdrawn, so its donor is not on offer');

        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8101', 'donorMrn' => '8202']);
        $this->assertStringContainsString('not been put forward', (string) session()->getFlashdata('ui_error'));
        $this->seeInDatabase('pairs', ['id' => $pairB, 'status' => 'active']);
    }

    /** The list offers only pairs an exchange can move, and can be searched. */
    public function testTheExchangeListIsFilteredAndSearchable(): void
    {
        [$pairA, $pairB] = $this->twoPairsToExchange();
        model(\App\Models\PairModel::class)->update($pairB, ['status' => 'completed']);
        $this->assertNotSame(0, $pairA);

        $html = $this->get('exchange')->getBody();
        $this->assertStringContainsString('Recipient A', $html);
        $this->assertStringNotContainsString('Recipient B', $html, 'a completed transplant is not exchangeable');

        $this->assertStringContainsString('Recipient A', $this->get('exchange?q=8102')->getBody());
        $this->assertStringNotContainsString('Recipient A', $this->get('exchange?q=9999')->getBody());
    }

    /** The compatibility rule itself, spelled out. */
    public function testBloodGroupCompatibilityIsDonorToRecipient(): void
    {
        foreach (['O' => ['O', 'A', 'B', 'AB'], 'A' => ['A', 'AB'], 'B' => ['B', 'AB'], 'AB' => ['AB']] as $donor => $canTake) {
            foreach (['O', 'A', 'B', 'AB'] as $recipient) {
                $this->assertSame(
                    in_array($recipient, $canTake, true),
                    \App\Libraries\ExchangeDraft::canGive($donor, $recipient),
                    "{$donor} to {$recipient}"
                );
            }
        }
    }

    /**
     * Two pairs on this programme, crossed so neither can use their own donor.
     *
     * Put forward through the button, not by writing the column: nothing
     * reaches the exchange screen any other way, and the tests should not
     * either.
     *
     * @return array{int, int}
     */
    private function twoPairsToExchange(): array
    {
        // A recipient with a B donor, and a B recipient with an A donor.
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

        foreach ($ids as $id) {
            $this->post('pairs/' . $id, ['section' => 'exchange', 'forExchange' => '1']);
        }

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
        // The sheet follows the screen, and the screen opens on Active.
        $this->assertStringContainsString('Active', $html);
        $this->assertStringContainsString('All pairs', $this->get('pairs/print?status=all')->getBody());
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

    /**
     * Add Pair sets the pair's status, rather than always creating it Active
     * and leaving somebody to change it on the next screen.
     */
    public function testAddPairSetsThePairsOwnStatus(): void
    {
        $html = $this->get('pairs/new')->getBody();
        $this->assertStringContainsString('name="pairStatus"', $html);
        $this->assertStringContainsString('Paired Exchange', $html);
        $this->assertStringContainsString('name="closedReason"', $html);

        $this->post('pairs/new', [
            'rMrn' => '9501', 'dMrn' => '9502',
            'rName' => 'On Hold Recipient', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'On Hold Donor', 'dAge' => '30', 'dBloodType' => 'A',
            'pairStatus' => 'on_hold', 'rStatus' => 'on_hold',
        ]);

        $pair = $this->db->table('pairs')->where('recipient_mrn', 9501)->get()->getRowArray();
        $this->assertSame('on_hold', $pair['status']);

        // A status the menu does not offer is not a status.
        $this->post('pairs/new', [
            'rMrn' => '9503', 'dMrn' => '9504',
            'rName' => 'Bad Status', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Bad Status Donor', 'dAge' => '30', 'dBloodType' => 'A',
            'pairStatus' => 'nonsense',
        ]);
        $this->assertSame('active', $this->db->table('pairs')->where('recipient_mrn', 9503)->get()->getRowArray()['status']);
    }

    /** Closed on Add Pair keeps its reason, the same as closing one later does. */
    public function testAddPairKeepsTheReasonAPairWasClosedFor(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9505', 'dMrn' => '9506',
            'rName' => 'Closed Recipient', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Closed Donor', 'dAge' => '30', 'dBloodType' => 'A',
            'pairStatus' => 'closed', 'closedReason' => 'Donor withdrew before workup.',
        ]);

        $pair = $this->db->table('pairs')->where('recipient_mrn', 9505)->get()->getRowArray();
        $this->assertSame('closed', $pair['status']);
        $this->assertSame('Donor withdrew before workup.', $pair['closed_reason']);
    }

    /**
     * The recipient's own status is on the pair's screens too, and saving it
     * there sets the person — not the link. The two were one fact while a
     * recipient had one donor; they are separate now.
     */
    public function testTheRecipientStatusOnAPairScreenMovesThePairWithIt(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9507', 'dMrn' => '9508',
            'rName' => 'Status Recipient', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Status Donor', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->where('recipient_mrn', 9507)->get()->getRowArray()['id'];

        $html = $this->get('pairs/' . $pairId)->getBody();
        $this->assertStringContainsString('name="rStatus"', $html);
        $this->assertStringContainsString('Recipient Status', $html);

        // The recipient's card sets the recipient, wherever it is opened from.
        $this->post('pairs/' . $pairId, [
            'section' => 'recipient',
            'rName' => 'Status Recipient', 'rAge' => '40', 'rBloodType' => 'A',
            'rStatus' => 'declined',
        ]);

        $this->assertSame('declined', $this->db->table('recipients')->where('mrn', 9507)->get()->getRowArray()['status']);
        // And leaves the link where it was: a recipient may hold several, so
        // there is no saying which of them this would have meant.
        $this->assertSame('active', $this->db->table('pairs')->where('id', $pairId)->get()->getRowArray()['status']);

        // The pair's card sets the pair, and only the pair.
        $this->post('pairs/' . $pairId, ['section' => 'pair', 'pairStatus' => 'transplanted']);
        $this->assertSame('transplanted', $this->db->table('pairs')->where('id', $pairId)->get()->getRowArray()['status']);
        $this->assertSame('declined', $this->db->table('recipients')->where('mrn', 9507)->get()->getRowArray()['status']);
    }

    /** The Donors List narrows by blood type, the same way the waitlist does. */
    public function testTheDonorsListFiltersByBloodType(): void
    {
        $this->post('donors/new', ['mrn' => '9601', 'name' => 'Type A Donor', 'age' => '30', 'bloodType' => 'A']);
        $this->post('donors/new', ['mrn' => '9602', 'name' => 'Type O Donor', 'age' => '35', 'bloodType' => 'O']);

        $all = $this->get('donors')->getBody();
        $this->assertStringContainsString('Type A Donor', $all);
        $this->assertStringContainsString('Type O Donor', $all);
        $this->assertStringContainsString(site_url('donors') . '?bt=A', $all);

        $onlyA = $this->get('donors?bt=A')->getBody();
        $this->assertStringContainsString('Type A Donor', $onlyA);
        $this->assertStringNotContainsString('Type O Donor', $onlyA);

        // Nothing of that type is not the same as an empty register.
        $none = $this->get('donors?bt=AB')->getBody();
        $this->assertStringContainsString('No unmatched donors with blood type AB', $none);
        $this->assertStringContainsString('Show all blood types', $none);
    }

    /**
     * Paired Exchange asks for a file number and nothing else: being on the
     * list is already a status, and an exchange matches blood groups to each
     * other rather than reading one at a time.
     */
    public function testPairedExchangeHasNoBloodTypeOrStatusChips(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9603', 'dMrn' => '9604',
            'rName' => 'Offered Recipient', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Offered Donor', 'dAge' => '30', 'dBloodType' => 'B',
        ]);

        $pairId = (int) $this->db->table('pairs')->where('recipient_mrn', 9603)->get()->getRowArray()['id'];
        $this->post('pairs/' . $pairId, ['section' => 'exchange', 'forExchange' => '1']);

        $html = $this->get('exchange')->getBody();

        $this->assertStringContainsString('Offered Recipient', $html);
        $this->assertStringContainsString('File number:', $html);

        // The row opens the pair, and carries a real link for it as well.
        $this->assertStringContainsString('data-href="' . site_url('pairs/' . $pairId) . '"', $html);
        $this->assertStringContainsString('<a href="' . site_url('pairs/' . $pairId) . '">' . $pairId . '</a>', $html);
        $this->assertStringNotContainsString('Blood type:', $html);
        $this->assertStringNotContainsString('Status:', $html);

        // The search still works, and a blood-type parameter is simply ignored
        // rather than hiding a pair the screen is meant to show.
        $this->assertStringContainsString('Offered Recipient', $this->get('exchange?q=9603')->getBody());
        $this->assertStringContainsString('Offered Recipient', $this->get('exchange?bt=AB')->getBody());
        $this->assertStringNotContainsString('Offered Recipient', $this->get('exchange?q=1234')->getBody());
    }

    /**
     * A donor is refused a second recipient; a recipient is not refused a
     * second donor.
     *
     * Being promised to two recipients is not a thing the register should be
     * able to say. Looking at two donors for one patient is ordinary.
     */
    public function testADonorIsRefusedASecondRecipientButARecipientIsNot(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9009', 'dMrn' => '9010',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        // A second donor for the same recipient: allowed.
        $this->post('pairs/new', [
            'fixedSide'  => 'recipient',
            'rMrn'       => '9009',
            'dMrn'       => '9011',
            'dName'      => 'Someone Else',
            'dAge'       => '30',
            'dBloodType' => 'A',
        ]);

        $this->assertSame(2, $this->db->table('pairs')->where('recipient_mrn', 9009)->countAllResults());
        $this->assertSame(1, $this->db->table('donors')->where('mrn', 9011)->countAllResults());

        // The same donor for a second recipient: refused. Reached the way the
        // screens reach it — from the donor's own record — so the MRN check
        // does not answer first.
        $this->post('pairs/new', [
            'fixedSide'  => 'donor',
            'dMrn'       => '9010',
            'rMrn'       => '9012',
            'rName'      => 'Another R',
            'rAge'       => '41',
            'rBloodType' => 'A',
        ]);

        $this->assertSame(1, $this->db->table('pairs')->where('donor_mrn', 9010)->countAllResults());
        $this->assertStringContainsString('already in an open pair', (string) session('ui_error'));
    }

    /**
     * A donor with a pair has nothing to choose, so Link shows it. A recipient
     * always has something to choose: another donor.
     */
    public function testOnlyADonorIsSentStraightToTheirPair(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9004', 'dMrn' => '9005',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        $this->get('donors/9005/link')->assertRedirectTo(site_url('pairs/' . $pairId));
        // The recipient has no such page: their choice is on their record.
        $this->assertStringContainsString('Add Potential Donor', $this->get('recipients/9004')->getBody());
    }

    /**
     * A recipient's status and their links' are separate facts now.
     *
     * They were one while a recipient had one donor. With several there is no
     * saying which of them a recipient set to Declined would mean, so the
     * person's status is the person's — are they on the programme — and each
     * link carries its own.
     */
    public function testTheRecipientsStatusAndTheirLinksAreSeparate(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '2005', 'dMrn' => '2006',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
            'pairStatus' => 'active', 'rStatus' => 'on_hold',
        ]);
        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        $this->seeInDatabase('recipients', ['mrn' => 2005, 'status' => 'on_hold']);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'active']);

        // The person's card moves the person, and only the person.
        $this->post('recipients/2005', [
            'section' => 'personal', 'name' => 'R', 'age' => '40', 'bloodType' => 'A',
            'status'  => 'declined',
        ]);
        $this->seeInDatabase('recipients', ['mrn' => 2005, 'status' => 'declined']);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'active']);

        // The pair's card moves the pair, and only the pair.
        $this->post('pairs/' . $pairId, ['section' => 'pair', 'pairStatus' => 'on_hold']);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'on_hold']);
        $this->seeInDatabase('recipients', ['mrn' => 2005, 'status' => 'declined']);
    }

    /**
     * A recipient's potential donors are tabs, and setting one aside greys it
     * rather than taking it off the record.
     */
    public function testARecipientsPotentialDonorsAreTabs(): void
    {
        $this->post('recipients/new', ['mrn' => '8800', 'name' => 'Tabs Test', 'age' => '44', 'bloodType' => 'A']);

        foreach ([['8801', 'Donor One'], ['8802', 'Donor Two']] as [$dMrn, $dName]) {
            $this->post('donors/new?for=8800', [
                'for' => '8800', 'mrn' => $dMrn, 'name' => $dName, 'age' => '33', 'bloodType' => 'A',
            ]);
        }

        // Candidates, not pairs: nothing has been decided yet.
        $this->assertSame(0, $this->db->table('pairs')->countAllResults());
        $this->assertSame(2, $this->db->table('potential_donors')->where('recipient_mrn', 8800)->countAllResults());

        $html = $this->get('recipients/8800')->getBody();
        $this->assertStringContainsString('2 potential donors', $html);
        $this->assertStringContainsString('donor-1', $html);
        $this->assertStringContainsString('donor-2', $html);
        $this->assertStringContainsString('Donor One', $html);

        // The second tab shows the second donor, in full: their details and
        // their whole workup, without leaving the recipient's record.
        $second = $this->get('recipients/8800?donor=2')->getBody();
        $this->assertStringContainsString('Donor Two', $second);
        $this->assertStringContainsString('Required Lab Tests', $second);

        $first = $this->db->table('potential_donors')->where('recipient_mrn', 8800)->orderBy('id')->get()->getRowArray();

        // It asks before setting anybody aside.
        $this->assertStringContainsString(
            'set to Declined',
            $this->get('recipients/8800/donors/' . $first['id'] . '/delink')->getBody()
        );
        $this->seeInDatabase('potential_donors', ['id' => $first['id'], 'status' => 'active']);

        $this->post('recipients/8800/donors/' . $first['id'] . '/delink');

        $this->seeInDatabase('potential_donors', ['id' => $first['id'], 'status' => 'declined']);

        $html = $this->get('recipients/8800?donor=1')->getBody();
        $this->assertStringContainsString('tab--delinked', $html);
        $this->assertStringContainsString('cannot be changed', $html);
        // Read-only: the tab that was set aside has nothing left to press.
        $this->assertStringNotContainsString('/delink"', $html);
        $this->assertStringNotContainsString('Pair up', $html);
    }

    /** The status beside a tab's name is a word, and only a word. */
    public function testATabsStatusIsPlainText(): void
    {
        $this->post('recipients/new', ['mrn' => '8810', 'name' => 'Plain', 'age' => '44', 'bloodType' => 'A']);
        $this->post('donors/new?for=8810', ['for' => '8810', 'mrn' => '8811', 'name' => 'D', 'age' => '33', 'bloodType' => 'A']);

        $html = $this->get('recipients/8810')->getBody();

        $this->assertStringContainsString('<span class="tab-status">Active</span>', $html);

        $id = (int) $this->db->table('potential_donors')->get()->getRowArray()['id'];
        $this->post('recipients/8810/donors/' . $id . '/status', ['status' => 'on_hold']);

        $this->seeInDatabase('potential_donors', ['id' => $id, 'status' => 'on_hold']);
        $this->assertStringContainsString('<span class="tab-status">On Hold</span>', $this->get('recipients/8810')->getBody());
    }

    /**
     * Adding a potential donor adds nobody to the Donors List and makes no
     * pair. That is the whole point of the middle state.
     */
    public function testAPotentialDonorIsNotOnTheRegisterUntilThePairIsMade(): void
    {
        $this->post('recipients/new', ['mrn' => '8820', 'name' => 'Waiting', 'age' => '44', 'bloodType' => 'A']);
        $this->post('donors/new?for=8820', [
            'for' => '8820', 'mrn' => '8821', 'name' => 'Candidate', 'age' => '33', 'bloodType' => 'A',
        ])->assertRedirectTo(site_url('recipients/8820') . '?donor=1');

        $this->seeInDatabase('donors', ['mrn' => 8821, 'is_listed' => 0]);
        $this->assertSame(0, $this->db->table('pairs')->countAllResults());
        // Not in the table the Donors List is built from.
        $this->assertStringNotContainsString(
            'donors/8821',
            $this->get('donors')->getBody()
        );

        // Pair up is what puts them on it. The Donors List itself shows only
        // donors who are free, so a paired one is off it again for that other
        // reason — what changed here is that they are on the register at all.
        $id = (int) $this->db->table('potential_donors')->get()->getRowArray()['id'];
        $this->post('recipients/8820/donors/' . $id . '/pair');

        $this->seeInDatabase('donors', ['mrn' => 8821, 'is_listed' => 1]);
    }

    /**
     * Pair up is the decision the list was leading to: one pair, every other
     * candidate set aside, and the pair's own screen.
     */
    public function testPairUpMakesThePairAndSetsTheRestAside(): void
    {
        $this->post('recipients/new', ['mrn' => '8830', 'name' => 'Decider', 'age' => '44', 'bloodType' => 'A']);

        foreach (['8831', '8832', '8833'] as $dMrn) {
            $this->post('donors/new?for=8830', [
                'for' => '8830', 'mrn' => $dMrn, 'name' => 'Donor ' . $dMrn, 'age' => '33', 'bloodType' => 'A',
            ]);
        }

        $rows   = $this->db->table('potential_donors')->where('recipient_mrn', 8830)->orderBy('id')->get()->getResultArray();
        $chosen = $rows[1];

        $this->post('recipients/8830/donors/' . $chosen['id'] . '/pair');

        $pair = $this->db->table('pairs')->where('recipient_mrn', 8830)->get()->getRowArray();
        $this->assertNotNull($pair);
        $this->assertSame('8832', (string) $pair['donor_mrn']);
        $this->assertSame(1, $this->db->table('pairs')->countAllResults());

        $this->seeInDatabase('potential_donors', ['id' => $chosen['id'], 'status' => 'active']);
        $this->seeInDatabase('potential_donors', ['id' => $rows[0]['id'], 'status' => 'declined']);
        $this->seeInDatabase('potential_donors', ['id' => $rows[2]['id'], 'status' => 'declined']);
    }

    /** And it is the pair's screen that opens, not another step. */
    public function testPairUpGoesStraightToThePair(): void
    {
        $this->post('recipients/new', ['mrn' => '8840', 'name' => 'Decider', 'age' => '44', 'bloodType' => 'A']);
        $this->post('donors/new?for=8840', ['for' => '8840', 'mrn' => '8841', 'name' => 'D', 'age' => '33', 'bloodType' => 'A']);

        $id = (int) $this->db->table('potential_donors')->get()->getRowArray()['id'];
        $pairId = null;

        $this->post('recipients/8840/donors/' . $id . '/pair');
        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        $this->post('recipients/8840/donors/' . $id . '/pair')
            ->assertRedirectTo(site_url('pairs/' . $pairId));
    }

    /** Somebody already on the register can be considered without re-entering them. */
    public function testAnExistingDonorCanBeAddedAsACandidate(): void
    {
        $this->post('recipients/new', ['mrn' => '8850', 'name' => 'Collector', 'age' => '44', 'bloodType' => 'A']);
        $this->post('donors/new', ['mrn' => '8851', 'name' => 'On The Register', 'age' => '30', 'bloodType' => 'A']);

        $this->post('recipients/8850/donors', ['donorMrn' => '8851'])
            ->assertRedirectTo(site_url('recipients/8850') . '?donor=1');

        $this->seeInDatabase('potential_donors', ['recipient_mrn' => 8850, 'donor_mrn' => 8851, 'status' => 'active']);
        // Already listed, and they stay listed: nothing about them changed.
        $this->seeInDatabase('donors', ['mrn' => 8851, 'is_listed' => 1]);
        $this->assertSame(0, $this->db->table('pairs')->countAllResults());
    }

    /**
     * A pair made by any other door still shows as a tab, because the two were
     * considered together however the pair came about.
     */
    public function testAPairMadeElsewhereStillShowsAsATab(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '8860', 'dMrn' => '8861',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Through Add Pair', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $this->seeInDatabase('potential_donors', ['recipient_mrn' => 8860, 'donor_mrn' => 8861]);
        $this->assertStringContainsString('Through Add Pair', $this->get('recipients/8860')->getBody());
    }

    /** Every record screen offers its own sheet, and the sheet has the record on it. */
    public function testARecordPrintsAsItsOwnSheet(): void
    {
        $this->post('recipients/new', [
            'mrn' => '9401', 'name' => 'Noura Printed', 'age' => '39', 'bloodType' => 'B',
            'phone' => '+966 50 111 2222', 'address' => 'Makkah', 'gender' => 'Female',
            'notes' => 'Seen in clinic on Tuesday.',
        ]);

        // The screen carries the link, and it opens the sheet.
        $screen = $this->get('recipients/9401')->getBody();
        $this->assertStringContainsString(site_url('recipients/9401') . '/print', $screen);

        $sheet = $this->get('recipients/9401/print')->getBody();

        $this->assertStringContainsString('Recipient Record &mdash; Noura Printed', $sheet);
        $this->assertStringContainsString('MRN 9401', $sheet);
        $this->assertStringContainsString('Makkah', $sheet);
        $this->assertStringContainsString('+966 50 111 2222', $sheet);
        $this->assertStringContainsString('Seen in clinic on Tuesday.', $sheet);

        // The whole workup, group headings and all — it is most of what a
        // record is.
        $this->assertStringContainsString('Immunology tests', $sheet);
        $this->assertStringContainsString('Cross match', $sheet);
        $this->assertStringContainsString('Transplant Nephrology Clinic', $sheet);

        // A document of its own: no shell, and the two stylesheets for paper.
        $this->assertStringContainsString('assets/ui/css/sheet.css', $sheet);
        $this->assertStringContainsString('assets/ui/css/record-print.css', $sheet);
        $this->assertStringNotContainsString('class="sidebar', $sheet);
    }

    /** A donor's sheet is the donor's, down to the fields only they have. */
    public function testADonorSheetCarriesTheDonorsOwnFields(): void
    {
        $this->post('donors/new', [
            'mrn' => '9402', 'name' => 'Khalid Printed', 'age' => '44', 'bloodType' => 'A',
            'donorGender' => 'Male', 'donationType' => 'living',
        ]);

        $sheet = $this->get('donors/9402/print')->getBody();

        $this->assertStringContainsString('Donor Record &mdash; Khalid Printed', $sheet);
        $this->assertStringContainsString('Donor Type', $sheet);
        $this->assertStringContainsString('Living', $sheet);
        $this->assertStringContainsString('Not linked', $sheet);
        // The donor's own check list, which is not the recipient's.
        $this->assertStringContainsString('Renal panel/Cr', $sheet);
    }

    /** A pair's sheet is the pair, then both people in full. */
    public function testAPairSheetCarriesBothRecords(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9403', 'dMrn' => '9404',
            'rName' => 'Recipient Sheet', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Donor Sheet', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->where('recipient_mrn', 9403)->get()->getRowArray()['id'];

        $screen = $this->get('pairs/' . $pairId)->getBody();
        $this->assertStringContainsString(site_url('pairs/' . $pairId) . '/print', $screen);

        $sheet = $this->get('pairs/' . $pairId . '/print')->getBody();

        $this->assertStringContainsString('Pair Record', $sheet);
        $this->assertStringContainsString('Recipient Sheet', $sheet);
        $this->assertStringContainsString('Donor Sheet', $sheet);
        $this->assertStringContainsString('Pair Details', $sheet);

        // Two workups and two sets of notes, told apart by whose they are.
        $this->assertStringContainsString('Recipient &mdash; Required Lab Tests', $sheet);
        $this->assertStringContainsString('Donor &mdash; Required Lab Tests', $sheet);
        $this->assertStringContainsString('Recipient &mdash; Clinical Notes', $sheet);
        $this->assertStringContainsString('Donor &mdash; Clinical Notes', $sheet);
    }

    /** A sheet for a record that is not there is not a blank sheet. */
    public function testPrintingAMissingRecordGoesBackToTheList(): void
    {
        $this->get('recipients/9999/print')->assertRedirectTo(site_url('recipients'));
        $this->get('donors/9999/print')->assertRedirectTo(site_url('donors'));
        $this->get('pairs/9999/print')->assertRedirectTo(site_url('pairs'));
    }

    /**
     * The register keeps every pair it has ever held, so the screen opens on
     * the ones still being worked rather than on all of them.
     */
    public function testThePairsListOpensOnActive(): void
    {
        // Nothing narrowed and nothing to show: the register really is empty.
        $this->assertStringContainsString('No pairs found.', $this->get('pairs?status=all')->getBody());

        $this->post('pairs/new', [
            'rMrn' => '9301', 'dMrn' => '9302',
            'rName' => 'Still Going', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Donor Going', 'dAge' => '30', 'dBloodType' => 'A',
        ]);
        $this->post('pairs/new', [
            'rMrn' => '9303', 'dMrn' => '9304',
            'rName' => 'Long Finished', 'rAge' => '50', 'rBloodType' => 'O',
            'dName' => 'Donor Finished', 'dAge' => '35', 'dBloodType' => 'O',
        ]);

        $finished = $this->db->table('pairs')->where('recipient_mrn', 9303)->get()->getRowArray();
        $this->db->table('pairs')->where('id', $finished['id'])->update(['status' => 'transplanted']);

        // No query string at all: the transplanted pair is not in the table.
        $html = $this->get('pairs')->getBody();
        $this->assertStringContainsString('Still Going', $html);
        $this->assertStringNotContainsString('Long Finished', $html);

        // The Active chip is the one lit, and All is reachable — its link has
        // to spell the filter out, since an empty query string means Active.
        $this->assertStringContainsString('href="' . site_url('pairs') . '?status=all"', $html);

        $all = $this->get('pairs?status=all')->getBody();
        $this->assertStringContainsString('Still Going', $all);
        $this->assertStringContainsString('Long Finished', $all);

        // And the default drops back out of the URL rather than piling up.
        $this->assertStringContainsString('href="' . site_url('pairs') . '"', $all);

        // An empty table says so in one sentence, whatever emptied it. The
        // chips sit above it and are the way back.
        $none = $this->get('pairs?status=closed')->getBody();
        $this->assertStringContainsString('No pairs found.', $none);
        $this->assertStringNotContainsString('Show all pairs', $none);
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

        // An answer and a comment: all a card collects now.
        $this->post('recipients/' . $mrn, [
            'section'   => 'labs',
            'name'      => 'Ahmed Test',
            'age'       => '41',
            'bloodType' => 'O',
            'labs'      => [[
                'id'     => $lab['id'],
                'name'   => $lab['name'],
                'status' => 'negative',
                'notes'  => 'repeat in 3 months',
            ]],
        ]);

        $this->seeInDatabase('lab_results', [
            'person_mrn'  => $mrn,
            'person_type' => 'recipient',
            'lab_id'      => $lab['id'],
            'status'      => 'negative',
            'notes'       => 'repeat in 3 months',
        ]);
    }

    /**
     * A value recorded before the cards stopped asking for one is not erased
     * by saving the card that no longer shows it.
     *
     * The screens write `status` and `notes` and nothing else, so the two
     * columns keep whatever they hold rather than being nulled by a form that
     * has no field to null them from.
     */
    public function testSavingACardLeavesAValueRecordedEarlierAlone(): void
    {
        $this->post('recipients/new', ['mrn' => '4021', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $lab = $this->db->table('labs')
            ->where(['organ_code' => 'kidney', 'person_type' => 'recipient', 'name' => 'HIV'])
            ->get()->getRowArray();

        // As an older version of the screens would have left it.
        $this->db->table('lab_results')->insert([
            'person_mrn'  => 4021,
            'person_type' => 'recipient',
            'lab_id'      => $lab['id'],
            'status'      => 'pending',
            'value'       => 'Non-reactive',
            'taken_on'    => '2026-09-17',
        ]);

        $this->post('recipients/4021', [
            'section'   => 'labs',
            'name'      => 'Ahmed Test',
            'age'       => '41',
            'bloodType' => 'O',
            'labs'      => [[
                'id'     => $lab['id'],
                'name'   => $lab['name'],
                'status' => 'negative',
                'notes'  => 'repeat in 3 months',
            ]],
        ]);

        $this->seeInDatabase('lab_results', [
            'person_mrn' => 4021,
            'lab_id'     => $lab['id'],
            'status'     => 'negative',
            'notes'      => 'repeat in 3 months',
            'value'      => 'Non-reactive',
            'taken_on'   => '2026-09-17',
        ]);
    }

    // ---- Tests a record adds for itself ----------------------------------

    /**
     * Other is a heading with a button under it, not a test.
     *
     * The check list seeds nothing there, so the group is empty until
     * somebody adds something — and the button has to be on the screen for
     * them to add the first one.
     */
    public function testTheOtherGroupOffersAddLabWhenItIsEmpty(): void
    {
        $this->post('recipients/new', ['mrn' => '4030', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $html = $this->get('recipients/4030?edit=labs')->getBody();

        $this->assertStringContainsString('Add lab', $html);
        $this->assertStringContainsString(site_url('recipients/4030') . '/labs', $html);
        $this->assertStringContainsString('No tests added.', $html);
    }

    /** Added, named, answered and commented on — then read back. */
    public function testATestARecordAddsIsItsOwnToNameAndAnswer(): void
    {
        $this->post('recipients/new', ['mrn' => '4031', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);
        $this->post('recipients/4031/labs');

        $lab = $this->db->table('labs')->where('person_mrn', 4031)->get()->getRowArray();
        $this->assertNotNull($lab, 'the test belongs to the record that added it');
        $this->assertSame('custom', $lab['result_type']);
        $this->assertSame('recipient', $lab['person_type']);

        // Its name is typed on the card and saved with the answer.
        $this->post('recipients/4031', [
            'section'   => 'labs',
            'name'      => 'Ahmed Test',
            'age'       => '41',
            'bloodType' => 'O',
            'labs'      => [[
                'id'     => $lab['id'],
                'name'   => 'Ultrasound Doppler Hepatic Vein',
                'status' => 'acceptable',
                'notes'  => 'Requested.',
            ]],
        ]);

        $this->seeInDatabase('labs', ['id' => $lab['id'], 'name' => 'Ultrasound Doppler Hepatic Vein']);
        $this->seeInDatabase('lab_results', [
            'person_mrn' => 4031,
            'lab_id'     => $lab['id'],
            'status'     => 'acceptable',
            'notes'      => 'Requested.',
        ]);

        $html = $this->get('recipients/4031?edit=labs')->getBody();
        $this->assertStringContainsString('Ultrasound Doppler Hepatic Vein', $html);
        $this->assertStringContainsString('lab-name-field', $html);

        // Every answer the platform has, and nothing a catalogue test offers
        // is missing from it.
        foreach (UiStore::RESULT_OPTIONS['custom'] as $status) {
            $this->assertStringContainsString('data-lab-status="' . $status . '"', $html);
        }
    }

    /** One record's test is not on anybody else's sheet. */
    public function testATestOneRecordAddedIsNotOnAnothers(): void
    {
        $this->post('recipients/new', ['mrn' => '4032', 'name' => 'First', 'age' => '41', 'bloodType' => 'O']);
        $this->post('recipients/new', ['mrn' => '4033', 'name' => 'Second', 'age' => '42', 'bloodType' => 'A']);
        $this->post('recipients/4032/labs');

        $lab = $this->db->table('labs')->where('person_mrn', 4032)->get()->getRowArray();
        $this->db->table('labs')->where('id', $lab['id'])->update(['name' => 'Only Ones Own Test']);

        $this->assertStringContainsString('Only Ones Own Test', $this->get('recipients/4032?edit=labs')->getBody());
        $this->assertStringNotContainsString('Only Ones Own Test', $this->get('recipients/4033?edit=labs')->getBody());

        // Nor may the other record answer it by posting its id.
        $this->post('recipients/4033', [
            'section' => 'labs', 'name' => 'Second', 'age' => '42', 'bloodType' => 'A',
            'labs'    => [['id' => $lab['id'], 'name' => 'Stolen', 'status' => 'acceptable', 'notes' => 'no']],
        ]);

        $this->dontSeeInDatabase('lab_results', ['person_mrn' => 4033, 'lab_id' => $lab['id']]);
        $this->seeInDatabase('labs', ['id' => $lab['id'], 'name' => 'Only Ones Own Test']);
    }

    /** Removing it takes the answer recorded against it too. */
    public function testRemovingATestARecordAddedTakesItsResult(): void
    {
        $this->post('recipients/new', ['mrn' => '4034', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);
        $this->post('recipients/4034/labs');

        $lab = $this->db->table('labs')->where('person_mrn', 4034)->get()->getRowArray();
        $this->post('recipients/4034', [
            'section' => 'labs', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O',
            'labs'    => [['id' => $lab['id'], 'name' => 'Going away', 'status' => 'acceptable', 'notes' => 'x']],
        ]);
        $this->seeInDatabase('lab_results', ['lab_id' => $lab['id']]);

        // It asks first.
        $this->assertStringContainsString(
            'This cannot be undone.',
            $this->get('recipients/4034/labs/' . $lab['id'] . '/delete')->getBody()
        );
        $this->seeInDatabase('labs', ['id' => $lab['id']]);

        $this->post('recipients/4034/labs/' . $lab['id'] . '/delete');

        $this->dontSeeInDatabase('labs', ['id' => $lab['id']]);
        $this->dontSeeInDatabase('lab_results', ['lab_id' => $lab['id']]);
    }

    /** A catalogue test is the programme's and cannot be taken off a record. */
    public function testACatalogueTestCannotBeRemovedFromOneRecord(): void
    {
        $this->post('recipients/new', ['mrn' => '4035', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $lab = $this->db->table('labs')
            ->where(['organ_code' => 'kidney', 'person_type' => 'recipient', 'name' => 'HIV'])
            ->get()->getRowArray();

        $this->post('recipients/4035/labs/' . $lab['id'] . '/delete');

        $this->seeInDatabase('labs', ['id' => $lab['id']]);
    }

    /** Pressing Add lab does not throw away what the card was holding. */
    public function testAddLabKeepsTheAnswersAlreadyOnTheCard(): void
    {
        $this->post('recipients/new', ['mrn' => '4036', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $hiv = $this->db->table('labs')
            ->where(['organ_code' => 'kidney', 'person_type' => 'recipient', 'name' => 'HIV'])
            ->get()->getRowArray();

        // The button is inside the card's form, so the card comes with it.
        $this->post('recipients/4036/labs', [
            'labs' => [['id' => $hiv['id'], 'name' => 'HIV', 'status' => 'negative', 'notes' => 'typed just now']],
        ]);

        $this->seeInDatabase('lab_results', [
            'person_mrn' => 4036,
            'lab_id'     => $hiv['id'],
            'status'     => 'negative',
            'notes'      => 'typed just now',
        ]);
        $this->assertSame(1, $this->db->table('labs')->where('person_mrn', 4036)->countAllResults());
    }

    /** The cards ask for an answer and a comment, and nothing else. */
    public function testTheCardsNoLongerOfferAValueOrADate(): void
    {
        $this->post('recipients/new', ['mrn' => '4022', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $html = $this->get('recipients/4022?edit=labs')->getBody();

        $this->assertStringNotContainsString('Value / finding', $html);
        $this->assertStringNotContainsString('Date (DD/MM/YYYY)', $html);
        $this->assertStringNotContainsString('data-lab-edit', $html);
        $this->assertStringNotContainsString('data-lab-editor', $html);
        $this->assertStringNotContainsString('[result]', $html);
        $this->assertStringNotContainsString('[date]', $html);

        // What is left: the answers, and one comment box per test.
        $this->assertStringContainsString('data-lab-status', $html);
        $this->assertStringContainsString('class="lab-comment"', $html);

        // And the printed sheet has no column for them either.
        $sheet = $this->get('recipients/4022/print')->getBody();
        $this->assertStringNotContainsString('Value / finding', $sheet);
        $this->assertStringContainsString('Comment', $sheet);
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
        $this->assertSame(74, substr_count($html, 'class="lab-comment"'), 'one per test');

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
        // Every card can be completed now: the one that could not — the
        // free-text Other box — is not on the sheet any more.
        $this->assertStringContainsString('2 of 74 completed', $html);
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
