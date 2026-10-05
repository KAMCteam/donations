<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\UiStore;
use CodeIgniter\Exceptions\PageNotFoundException;
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
     * A recipient with no pair is offered one, in one step: the two ways in,
     * and the pair exists as soon as either is answered.
     */
    public function testTheRecipientsButtonLinksWithADonor(): void
    {
        $this->post('recipients/new', ['mrn' => '9001', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);

        $html = $this->get('recipients/9001')->getBody();

        $this->assertStringContainsString('Link with Donor', $html);
        $this->assertStringContainsString('<dialog id="link-choice"', $html);
        $this->assertStringContainsString('Link with a new donor', $html);
        $this->assertStringContainsString('Link with an existing donor', $html);
        $this->assertStringContainsString(site_url('pairs/new') . '?recipient=9001', $html);
        // Nothing of the middle state: there are no potential donors.
        $this->assertStringNotContainsString('Potential Donor', $html);
    }

    /**
     * The existing one is chosen in the dialog, from a select, and nothing
     * else is asked there.
     *
     * The status belongs to the pair's donors — a pair decides which of them
     * it is going ahead with on its own screen — so the dialog that makes the
     * pair does not offer it.
     */
    public function testTheLinkDialogChoosesFromASelectAndAsksNothingElse(): void
    {
        $this->post('recipients/new', ['mrn' => '9002', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);
        $this->post('donors/new', ['mrn' => '9023', 'name' => 'Free Donor', 'age' => '33', 'bloodType' => 'B']);

        $html   = $this->get('recipients/9002')->getBody();
        $dialog = substr($html, (int) strpos($html, '<dialog id="link-choice"'));

        $this->assertStringContainsString('name="mrn"', $dialog);
        $this->assertStringContainsString('<option value="9023"', $dialog);
        $this->assertStringContainsString(site_url('recipients/9002/link/existing'), $dialog);
        $this->assertStringNotContainsString('name="status"', $dialog, 'the dialog does not set a status');
    }

    /** The same choice is a page of its own, for when scripting is off. */
    public function testTheLinkChoiceIsStillAPageOfItsOwn(): void
    {
        $this->post('recipients/new', ['mrn' => '9024', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);
        $this->post('donors/new', ['mrn' => '9025', 'name' => 'Free Donor', 'age' => '33', 'bloodType' => 'B']);

        $this->assertStringContainsString('recipients/9024/link', $this->get('recipients/9024')->getBody());

        $html = $this->get('recipients/9024/link')->getBody();

        $this->assertStringContainsString('Link with a new donor', $html);
        $this->assertStringContainsString('Link with an existing donor', $html);
        $this->assertStringContainsString('<option value="9025"', $html);
        // The screen of names it used to lead to is gone.
        $this->assertStringNotContainsString('Choose a donor', $html);
    }

    /** A donor record offers the mirror image of it. */
    public function testADonorIsOfferedARecipient(): void
    {
        $this->post('donors/new', ['mrn' => '9003', 'name' => 'Fahad Test', 'age' => '29', 'bloodType' => 'A']);

        $html = $this->get('donors/9003/link')->getBody();

        $this->assertStringContainsString('Link with a new recipient', $html);
        $this->assertStringContainsString('Link with an existing recipient', $html);
        $this->assertStringContainsString(site_url('pairs/new') . '?donor=9003', $html);
        $this->assertStringNotContainsString('Choose a recipient', $html);
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

        $html = $this->get('donors/9012')->getBody();

        $this->assertStringContainsString('Free Recipient', $html);
        $this->assertStringNotContainsString('Paired Recipient', $html);
        $this->assertStringContainsString('<option value="9013"', $html);
    }

    /**
     * One person may hold a row in both registers under their one hospital
     * number, so they could otherwise be offered as their own donor.
     */
    public function testThePickerDoesNotOfferThePersonThemselves(): void
    {
        $this->post('recipients/new', ['mrn' => '9016', 'name' => 'Both Test', 'age' => '38', 'bloodType' => 'B']);
        $this->post('donors/new', ['mrn' => '9016', 'name' => 'Both Test', 'age' => '38', 'bloodType' => 'B']);

        $html = $this->get('donors/9016')->getBody();

        $this->assertStringNotContainsString('<option value="9016"', $html);
    }

    public function testChoosingFromThePickerCreatesThePair(): void
    {
        $this->post('recipients/new', ['mrn' => '9017', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);
        $this->post('donors/new', ['mrn' => '9018', 'name' => 'Free Donor', 'age' => '33', 'bloodType' => 'B']);

        // Only the MRN: the relationship and the crossmatch date are the
        // pair's, entered on the pair's own screen, which is where this lands.
        $this->post('donors/9018/link/existing', ['mrn' => '9017']);

        $this->seeInDatabase('pairs', [
            'recipient_mrn' => 9017,
            'donor_mrn'     => 9018,
            'status'        => 'active',
        ]);
        // Neither person is duplicated: the pair links what was already there.
        $this->assertSame(1, $this->db->table('recipients')->countAllResults());
        $this->assertSame(1, $this->db->table('donors')->countAllResults());
    }

    public function testAddingSomebodyAlreadyPairedIsRefused(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9019', 'dMrn' => '9022',
            'rName' => 'Layla Test', 'rAge' => '38', 'rBloodType' => 'B',
            'dName' => 'Their Donor', 'dAge' => '30', 'dBloodType' => 'B',
        ]);
        $this->post('pairs/new', [
            'rMrn' => '9020', 'dMrn' => '9021',
            'rName' => 'Paired Recipient', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->where('recipient_mrn', 9019)->get()->getRowArray()['id'];

        // A donor in a pair is spoken for; putting them on a second one would
        // be promising them twice.
        $this->post('pairs/' . $pairId . '/donors', ['donorMrn' => '9021', 'status' => 'on_hold']);

        $this->dontSeeInDatabase('pairs', ['recipient_mrn' => 9019, 'donor_mrn' => 9021]);
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

        // Four, not three: the workup's fields are in two, with the Other
        // group's Add lab between them — a button that waited on the pencil
        // would be a button for editing this card rather than adding another.
        $this->assertSame(4, substr_count($html, 'class="card-fields" disabled'), 'personal, labs and notes');
        $this->assertSame(3, substr_count($html, 'class="btn-edit"'), 'one Edit per card');
        $this->assertStringNotContainsString('btn-save', $html, 'nothing to save until a card is opened');
    }

    public function testEditOpensOnlyTheCardItNames(): void
    {
        $this->post('recipients/new', ['mrn' => '8002', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $html = $this->get('recipients/8002?edit=personal')->getBody();

        $this->assertSame(3, substr_count($html, 'class="card-fields" disabled'), 'the other two stay shut');
        $this->assertSame(1, substr_count($html, 'name="section" value="personal"'));
        $this->assertSame(1, substr_count($html, 'btn-save'), 'the open card saves itself');
    }

    /** A mistyped link opens the record rather than an error. */
    public function testAnUnknownCardNameJustOpensTheRecord(): void
    {
        $this->post('recipients/new', ['mrn' => '8003', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $html = $this->get('recipients/8003?edit=nonsense')->getBody();

        $this->assertSame(4, substr_count($html, 'class="card-fields" disabled'));
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
        // Nine, not seven: each of the two workups has its fields in two,
        // with the Other group's Add lab standing between them.
        $this->assertSame(9, substr_count($html, 'class="card-fields" disabled'));
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

        // Transplanted is a person's word too — a transplant is a thing that
        // happens to somebody — but the two that only describe a case are not
        // on a person's record.
        $this->assertStringContainsString('value="transplanted"', $html);

        foreach (['paired_exchange', 'closed'] as $pairOnly) {
            $this->assertStringNotContainsString('value="' . $pairOnly . '"', $html);
        }
    }

    /** The pair's Match Status is the same list, not a second one. */
    /** The pair offers six; a person's four are exactly the first four. */
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
            array_slice(array_keys(UiStore::PAIR_STATUS_OPTIONS), 0, 4)
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

        // The donor's card belongs to their tab, because a pair holds more
        // than one of them and a save has to say which.
        $this->post('pairs/' . $pairId, [
            'section'      => 'pd' . $pairId . '-personal',
            'dName'        => 'Donor Edited',
            'dAge'         => '31',
            'dBloodType'   => 'A',
            'dGender'      => 'Female',
            'dType'        => 'living_related',
            'dMrp'         => (string) $this->mrpId,
            'dStatus'      => 'Declined',
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
            'status' => 'declined',
            'mrp_id' => $this->mrpId,
        ]);
        $this->seeInDatabase('coordinators', ['name' => 'Coordinator Three']);
    }

    /**
     * Transplanted is the one status with a day attached to it.
     *
     * The field is asked for beside the word rather than sitting empty on
     * every pair, and a pair moved off Transplanted loses it — a date for
     * something that has been taken back is worse than no date.
     */
    public function testATransplantedPairCarriesTheDayItHappened(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '4210', 'dMrn' => '4211',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);
        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        // The question is on the card, hidden until the word asks for it.
        $card = $this->get('pairs/' . $pairId . '?edit=pair')->getBody();
        $this->assertStringContainsString('Date of Transplant', $card);
        $this->assertStringContainsString('name="transplantDate"', $card);
        $this->assertStringContainsString('data-reveal-when="closed,transplanted"', $card);
        $this->assertMatchesRegularExpression('/id="transplant-date" hidden/', $card);

        $this->post('pairs/' . $pairId, [
            'section'        => 'pair',
            'pairStatus'     => 'transplanted',
            'transplantDate' => '14/09/2026',
        ]);

        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'transplanted', 'surgery_date' => '2026-09-14']);

        // And it is read on the card without opening it.
        $view = $this->get('pairs/' . $pairId)->getBody();
        $this->assertStringContainsString('value="14/09/2026"', $view);
        $this->assertStringNotContainsString('id="transplant-date" hidden', $view);

        // A date belonging to no transplant is not kept.
        $this->post('pairs/' . $pairId, [
            'section'        => 'pair',
            'pairStatus'     => 'active',
            'transplantDate' => '14/09/2026',
        ]);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'active', 'surgery_date' => null]);
    }

    /** A pair created as Transplanted keeps its day too. */
    public function testAPairMadeTransplantedKeepsItsDay(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '4212', 'dMrn' => '4213',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
            'pairStatus' => 'transplanted', 'transplantDate' => '01/03/2026',
        ]);

        $this->seeInDatabase('pairs', [
            'recipient_mrn' => 4212, 'status' => 'transplanted', 'surgery_date' => '2026-03-01',
        ]);
    }

    /**
     * The coordinator is chosen from the people registered as one.
     *
     * It was a text box, which asked somebody to remember a colleague's name
     * and spell it the way the last person did — and registered a second
     * coordinator quietly when they did not.
     */
    public function testTheCoordinatorIsChosenFromTheRegistered(): void
    {
        $this->post('mrp', ['id' => 'C-7001', 'kind' => 'coordinator', 'name' => 'First Coordinator']);
        $this->post('mrp', ['id' => 'C-7002', 'kind' => 'coordinator', 'name' => 'Second Coordinator']);
        // A doctor is not one of them: the list is coordinators.
        $this->post('mrp', ['id' => 'D-7003', 'kind' => 'doctor', 'name' => 'A Doctor']);

        $this->post('recipients/new', ['mrn' => '4220', 'name' => 'R', 'age' => '40', 'bloodType' => 'A']);

        $html = $this->get('recipients/4220?edit=personal')->getBody();

        $this->assertStringContainsString('<select id="f-coordinator" name="coordinator"', $html);
        $this->assertStringNotContainsString('<input type="text" id="f-coordinator"', $html);

        // The list is coordinators. A doctor is on the MRP select beside it,
        // which is why this reads the one control rather than the page.
        $picker = substr($html, (int) strpos($html, 'id="f-coordinator"'));
        $picker = substr($picker, 0, (int) strpos($picker, '</select>'));

        $this->assertStringContainsString('>First Coordinator</option>', $picker);
        $this->assertStringContainsString('>Second Coordinator</option>', $picker);
        $this->assertStringNotContainsString('A Doctor', $picker);

        // And choosing one stores the row that already exists rather than a
        // second one under the same name.
        $this->post('recipients/4220', [
            'section' => 'personal', 'name' => 'R', 'age' => '40', 'bloodType' => 'A',
            'coordinator' => 'Second Coordinator',
        ]);

        $this->assertSame(2, $this->db->table('coordinators')->countAllResults());
        $id = (int) $this->db->table('coordinators')->where('name', 'Second Coordinator')->get()->getRowArray()['id'];
        $this->seeInDatabase('recipients', ['mrn' => 4220, 'coordinator_id' => $id]);
    }

    /**
     * Deactivating a coordinator stops them being offered, and leaves the
     * records that already name them alone.
     */
    public function testADeactivatedCoordinatorIsKeptButNotOffered(): void
    {
        $this->post('mrp', ['id' => 'C-7004', 'kind' => 'coordinator', 'name' => 'Was A Coordinator']);
        $this->post('recipients/new', ['mrn' => '4221', 'name' => 'R', 'age' => '40', 'bloodType' => 'A']);
        $this->post('recipients/4221', [
            'section' => 'personal', 'name' => 'R', 'age' => '40', 'bloodType' => 'A',
            'coordinator' => 'Was A Coordinator',
        ]);

        $mrpId = (int) $this->db->table('mrp')->where('code', 'C-7004')->get()->getRowArray()['id'];
        $this->post('mrp/' . $mrpId . '/active', ['active' => '0']);

        // Both rows, because a coordinator is two of them.
        $this->seeInDatabase('coordinators', ['name' => 'Was A Coordinator', 'is_active' => 0]);

        // Gone from a record that has nobody…
        $this->post('recipients/new', ['mrn' => '4222', 'name' => 'Other', 'age' => '40', 'bloodType' => 'A']);
        $this->assertStringNotContainsString(
            'Was A Coordinator',
            $this->get('recipients/4222?edit=personal')->getBody()
        );

        // …and still on the record that named them, said in so many words.
        $this->assertStringContainsString(
            'Was A Coordinator &mdash; no longer registered',
            $this->get('recipients/4221?edit=personal')->getBody()
        );
    }

    /**
     * The pair's workups fold, because what is under them is the point of the
     * screen.
     *
     * Seventy-odd cards is a screenful and a half between whoever is reading
     * and the pair's donors. Shut, the line that says how the workup is going
     * is still on the screen; the card it is being edited on is open.
     */
    public function testThePairsWorkupsFoldAway(): void
    {
        [$pairId] = $this->pairWith('8950', '8951', 'The Donor');

        $html = $this->get('pairs/' . $pairId)->getBody();

        // Both of them — the recipient's and the open donor tab's.
        $this->assertSame(2, substr_count($html, '<details class="card card--pad card-fold" data-lab-section>'));
        // Shut, and still saying where the workup stands.
        $this->assertStringContainsString('of 74 completed', $html);

        // The card being edited arrives open.
        $open = $this->get('pairs/' . $pairId . '?edit=rlabs')->getBody();
        $this->assertStringContainsString('data-lab-section open', $open);
    }

    /** The record screens fold too, and arrive open: the workup is the screen. */
    public function testTheRecordsWorkupFoldsButArrivesOpen(): void
    {
        $this->post('recipients/new', ['mrn' => '8952', 'name' => 'R', 'age' => '40', 'bloodType' => 'A']);
        $this->post('donors/new', ['mrn' => '8953', 'name' => 'D', 'age' => '30', 'bloodType' => 'A']);

        foreach (['recipients/8952', 'donors/8953'] as $screen) {
            $html = $this->get($screen)->getBody();

            $this->assertStringContainsString('data-lab-section open', $html, $screen);
            $this->assertStringNotContainsString('card-fold" data-lab-section>', $html, $screen);
        }
    }

    /**
     * Each group says how far it has got, and the shut card says it for all
     * of them.
     *
     * A workup is read group by group — immunology is somebody's morning and
     * serology is somebody else's — so one percentage against seventy-odd
     * tests is the one number nobody is working from.
     */
    public function testEachLabGroupCarriesItsOwnPercentage(): void
    {
        $this->post('recipients/new', ['mrn' => '8954', 'name' => 'R', 'age' => '40', 'bloodType' => 'A']);
        [$pairId] = $this->pairWith('8955', '8956', 'The Donor');

        foreach (['recipients/8954', 'donors/8956', 'pairs/' . $pairId] as $screen) {
            $html = $this->get($screen)->getBody();

            // A bar and a percentage over each group's own tests...
            $this->assertStringContainsString('data-lab-group-head="0"', $html, $screen);
            $this->assertStringContainsString('data-lab-group-fill', $html, $screen);
            $this->assertStringContainsString('data-lab-group="0"', $html, $screen);

            // ...and the same groups, by name, on the line the card shows
            // while it is shut.
            $this->assertStringContainsString('class="lab-fold-groups"', $html, $screen);
            $this->assertStringContainsString('data-lab-sum="0"', $html, $screen);
            $this->assertStringContainsString('<span class="lab-fold-group-name">Immunology', $html, $screen);
        }
    }

    /** Answering moves the group's own number, not only the card's. */
    public function testAGroupsPercentageCountsOnlyItsOwnTests(): void
    {
        $this->post('recipients/new', ['mrn' => '8957', 'name' => 'R', 'age' => '40', 'bloodType' => 'A']);

        $before = $this->get('recipients/8957')->getBody();
        $this->assertSame(0, $this->groupPct($before, 0), 'nothing answered yet');

        // Answer one test in the first group.
        $tests = (new UiStore(session()))->findRecipient('8957')['labTests'];
        $posted = [];

        foreach ($tests as $i => $test) {
            $posted[$i] = ['id' => $test['id'], 'name' => $test['name'], 'status' => $test['status']];
        }

        // Whatever the first test's own answers are — every group asks a
        // different question — as long as it is one that counts as answered.
        $first   = array_key_first($posted);
        $answers = array_column($tests[$first]['answers'] ?? [], 'key');
        $answer  = array_values(array_diff($answers, UiStore::RESULT_UNANSWERED))[0] ?? 'done';

        $posted[$first]['status'] = $answer;

        $this->post('recipients/8957', ['section' => 'labs', 'labs' => $posted]);

        $after = $this->get('recipients/8957')->getBody();
        $this->assertGreaterThan(0, $this->groupPct($after, 0), 'the first group moved');
        $this->assertSame(0, $this->groupPct($after, 1), 'and nobody else did');
    }

    /** The percentage one group's bar is showing. */
    private function groupPct(string $html, int $group): int
    {
        $head = substr($html, (int) strpos($html, 'data-lab-group-head="' . $group . '"'));
        $head = substr($head, 0, (int) strpos($head, '</div>'));

        return preg_match('/data-lab-group-pct>(\d+)%/', $head, $m) === 1 ? (int) $m[1] : -1;
    }

    // ---- Saying what is wrong, once, and before Save -----------------------

    /**
     * A refusal is printed once.
     *
     * The layout prints what an action that redirects left behind, and a
     * screen with a slot of its own for the refusal reads the same flash — so
     * both printed it, and every rejected save said everything twice.
     */
    public function testARefusedSaveIsSaidOnce(): void
    {
        $this->post('recipients/new', ['mrn' => '4230', 'name' => 'First', 'age' => '40', 'bloodType' => 'A']);
        $this->post('recipients/new', ['mrn' => '4230', 'name' => 'Second', 'age' => '41', 'bloodType' => 'B']);

        $html    = $this->get('recipients/new')->getBody();
        $message = 'A recipient with MRN 4230 is already registered.';

        $this->assertSame(1, substr_count($html, $message), 'the reason is given once');
        $this->assertStringContainsString('<div class="form-error" role="alert">' . $message, $html);
    }

    /**
     * Whether a file number is free is the one thing a form cannot work out
     * for itself, so it can ask.
     */
    public function testAFileNumberCanBeCheckedBeforeTheFormIsSent(): void
    {
        $this->post('recipients/new', ['mrn' => '4231', 'name' => 'Taken', 'age' => '40', 'bloodType' => 'A']);

        // The feature client wraps a JSON body in a page of its own, so these
        // read the answer rather than decoding it.
        $taken = $this->get('mrn-taken/recipient/4231')->getBody();
        $this->assertStringContainsString('"taken": true', $taken);
        // The same sentence the save would have given, from the same method.
        $this->assertStringContainsString('A recipient with MRN 4231 is already registered.', $taken);

        $free = $this->get('mrn-taken/recipient/4232')->getBody();
        $this->assertStringContainsString('"taken": false', $free);
        $this->assertStringContainsString('"message": ""', $free);

        // The registers are separate, so the same number is free on the other.
        $this->assertStringContainsString('"taken": false', $this->get('mrn-taken/donor/4231')->getBody());
    }

    /** And every box that is checked says which register it belongs to. */
    public function testTheFormsMarkTheirFileNumberBoxes(): void
    {
        $this->assertStringContainsString('data-mrn="recipient"', $this->get('recipients/new')->getBody());
        $this->assertStringContainsString('data-mrn="donor"', $this->get('donors/new')->getBody());

        $pair = $this->get('pairs/new')->getBody();
        $this->assertStringContainsString('data-mrn="recipient"', $pair);
        $this->assertStringContainsString('data-mrn="donor"', $pair);

        // A saved record's number is not editable, so there is nothing to ask.
        $this->post('recipients/new', ['mrn' => '4233', 'name' => 'R', 'age' => '40', 'bloodType' => 'A']);
        $this->assertStringNotContainsString('data-mrn', $this->get('recipients/4233?edit=personal')->getBody());
    }

    /**
     * The personal details fold too, and keep the two things worth scanning.
     *
     * They start open, because the details are what a record is; shut, the
     * summary still says who it is about and where they stand.
     */
    public function testThePersonalDetailsFoldAndKeepTheNameBloodGroupAndStatus(): void
    {
        $this->post('recipients/new', [
            'mrn' => '8960', 'name' => 'Folded Recipient', 'age' => '40',
            'bloodType' => 'A', 'status' => 'on_hold',
        ]);
        $this->post('donors/new', [
            'mrn' => '8961', 'name' => 'Folded Donor', 'age' => '30',
            'bloodType' => 'A', 'donorStatus' => 'Declined',
        ]);
        [$pairId] = $this->pairWith('8962', '8963', 'Paired Donor');

        foreach (['recipients/8960', 'donors/8961', 'pairs/' . $pairId] as $screen) {
            $html = $this->get($screen)->getBody();

            // Open when the screen arrives, every one of them.
            $this->assertStringContainsString('<details class="card card--pad card-fold" open>', $html, $screen);
            $this->assertStringNotContainsString('class="card card--pad card-fold">', $html, $screen);
            $this->assertStringContainsString('class="card-head card-fold-head"', $html, $screen);
        }

        // What a shut card still says, and in the shape the card's own fields
        // say it in: the label above, the value in the read-only box.
        $recipient = $this->get('recipients/8960')->getBody();
        $this->assertStringContainsString('<span class="field-label">Recipient Name</span>', $recipient);
        $this->assertStringContainsString('>Folded Recipient</span>', $recipient);
        $this->assertStringContainsString('<span class="field-label">Blood Group</span>', $recipient);
        $this->assertStringContainsString('<span class="field-label">Recipient Status</span>', $recipient);
        $this->assertStringContainsString('>On Hold</span>', $recipient);

        $donor = $this->get('donors/8961')->getBody();
        $this->assertStringContainsString('<span class="field-label">Donor Name</span>', $donor);
        $this->assertStringContainsString('>Folded Donor</span>', $donor);
        $this->assertStringContainsString('<span class="field-label">Donor Status</span>', $donor);
        $this->assertStringContainsString('>Declined</span>', $donor);

        // The three are shown, not asked: a second set of controls carrying
        // the card's own field names would post every answer twice.
        $facts = substr($recipient, (int) strpos($recipient, 'class="card-fold-facts"'));
        $facts = substr($facts, 0, (int) strpos($facts, '</summary>'));
        $this->assertStringNotContainsString('<input', $facts);
        $this->assertStringNotContainsString('<select', $facts);

        // The pair says it for both of them, on each card.
        $pair = $this->get('pairs/' . $pairId)->getBody();
        $this->assertSame(2, substr_count($pair, 'card-fold-facts'), 'the recipient and the open donor tab');
    }

    /**
     * Both registers show the status they are already filtered by.
     *
     * The chips above the table narrowed the list by it; the table itself did
     * not say it, so a list under "All" could not be read for it at all.
     */
    public function testBothRegistersShowTheStatusColumn(): void
    {
        $this->post('recipients/new', [
            'mrn' => '8970', 'name' => 'Listed Recipient', 'age' => '40',
            'bloodType' => 'A', 'status' => 'on_hold',
        ]);
        $this->post('donors/new', [
            'mrn' => '8971', 'name' => 'Listed Donor', 'age' => '30',
            'bloodType' => 'A', 'donorStatus' => 'Declined',
        ]);

        $waitlist = $this->get('recipients')->getBody();
        $this->assertStringContainsString('<th>Status</th>', $waitlist);
        $this->assertStringContainsString('>On Hold</span>', $waitlist);

        $donors = $this->get('donors')->getBody();
        $this->assertStringContainsString('<th>Status</th>', $donors);
        $this->assertStringContainsString('>Declined</span>', $donors);

        // And the sheet shows the columns the screen shows.
        $this->assertStringContainsString('Status', $this->get('recipients/print')->getBody());
        $this->assertStringContainsString('Declined', $this->get('donors/print')->getBody());
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
        // The people keep their own word: a person is not "in a paired
        // exchange", their case is — and a recipient left holding it would be
        // missing from the next chain's lists, which ask for Active.
        $this->seeInDatabase('recipients', ['mrn' => 8101, 'status' => 'active']);
    }

    /**
     * The review asks what each new pair is, and saving writes it.
     *
     * An exchange makes several pairs at once, and the screen that usually
     * asks for a pair's details does not exist for them yet — so the review
     * asks there, with the Pair Details card's own three fields.
     */
    public function testTheReviewAsksForEachNewPairsDetails(): void
    {
        [$pairA] = $this->twoPairsToExchange();

        $this->post('exchange/start/' . $pairA);
        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8101', 'donorMrn' => '8202']);
        $this->post('exchange/build', ['action' => 'chooseRecipient', 'donorMrn' => '8102', 'recipientMrn' => '8201']);

        // The fields are on the review, named for the recipient they belong to.
        $review = $this->get('exchange/review')->getBody();
        $this->assertStringContainsString('name="pairDetails[8101][relationship]"', $review);
        $this->assertStringContainsString('name="pairDetails[8101][crossmatchDate]"', $review);
        $this->assertStringContainsString('name="pairDetails[8101][status]"', $review);
        // Paired Exchange is what these pairs are, so it is already chosen —
        // and Closed is not a word a pair can be made on.
        $this->assertStringContainsString('<option value="paired_exchange" selected>', $review);
        $this->assertStringNotContainsString('<option value="closed"', $review);

        $this->post('exchange/build', [
            'action'      => 'confirm',
            'pairDetails' => [
                '8101' => ['relationship' => 'Sibling', 'crossmatchDate' => '01/10/2026', 'status' => 'active'],
                '8201' => ['relationship' => 'Spouse', 'crossmatchDate' => '', 'status' => ''],
            ],
        ])->assertRedirectTo(site_url('pairs'));

        $this->seeInDatabase('pairs', [
            'recipient_mrn'   => 8101,
            'donor_mrn'       => 8202,
            'status'          => 'active',
            'relationship'    => 'Sibling',
            'crossmatch_date' => '2026-10-01',
        ]);
        // The donors list shows the relationship, so it lands there too.
        $this->seeInDatabase('donors', ['mrn' => 8202, 'relationship' => 'Sibling']);
        // Nothing said falls back to what the pair is: a paired exchange.
        $this->seeInDatabase('pairs', [
            'recipient_mrn' => 8201,
            'donor_mrn'     => 8102,
            'status'        => 'paired_exchange',
            'relationship'  => 'Spouse',
        ]);
        // And the recipient takes the word the pair was given.
        $this->seeInDatabase('recipients', ['mrn' => 8101, 'status' => 'active']);
    }

    /** Only a blood-group match is ever offered, from either side. */
    public function testTheListsOfferCompatibleMatchesOnly(): void
    {
        [$pairA] = $this->twoPairsToExchange();
        // An AB donor, who can only give to AB — so not to either recipient.
        $this->post('donors/new', ['mrn' => '8601', 'name' => 'AB Donor', 'age' => '40', 'bloodType' => 'AB', 'donorStatus' => 'Active']);
        // An O donor, who can give to anyone.
        $this->post('donors/new', ['mrn' => '8602', 'name' => 'Universal Donor', 'age' => '41', 'bloodType' => 'O', 'donorStatus' => 'Active']);

        $this->post('exchange/start/' . $pairA);
        $html = $this->get('exchange/build')->getBody();

        $this->assertStringContainsString('Universal Donor', $html, 'O gives to everyone');
        $this->assertStringNotContainsString('AB Donor', $html, 'AB gives only to AB');

        // Posting the incompatible one anyway is refused, not merely hidden.
        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8101', 'donorMrn' => '8601']);
        $this->assertStringContainsString('cannot give to', (string) session()->getFlashdata('ui_error'));
    }

    /**
     * The four lists the chain draws on, and the condition on each.
     *
     * A donor from a pair whose status is Paired Exchange, and Active there,
     * which is what tells the pair's own donor from its reserves. A donor from
     * the donors list who is Active. A recipient from a pair whose status is
     * Paired Exchange. A recipient from the waiting list who is Active.
     */
    public function testTheChainOffersOnlyActivePeople(): void
    {
        [$pairA] = $this->twoPairsToExchange();

        // Two free donors of the right group; one of them is on hold.
        $this->post('donors/new', [
            'mrn' => '8303', 'name' => 'Held Donor', 'age' => '30',
            'bloodType' => 'A', 'donorStatus' => 'On Hold',
        ]);
        $this->post('donors/new', [
            'mrn' => '8304', 'name' => 'Free Donor', 'age' => '30',
            'bloodType' => 'A', 'donorStatus' => 'Active',
        ]);
        // And two recipients on the waiting list, the same way.
        $this->post('recipients/new', [
            'mrn' => '8305', 'name' => 'Held Recipient', 'age' => '40',
            'bloodType' => 'B', 'status' => 'on_hold',
        ]);
        $this->post('recipients/new', [
            'mrn' => '8306', 'name' => 'Free Recipient', 'age' => '40',
            'bloodType' => 'B', 'status' => 'active',
        ]);

        $this->post('exchange/start/' . $pairA);

        $offered = $this->exchangeChoices($this->get('exchange/build')->getBody());

        // From the donors list, and from the waiting list.
        $this->assertStringContainsString('Free Donor', $offered);
        $this->assertStringNotContainsString('Held Donor', $offered);
        $this->assertStringContainsString('Free Recipient', $offered);
        $this->assertStringNotContainsString('Held Recipient', $offered);

        // And from a pair whose status is Paired Exchange: its own donor,
        // and its recipient.
        $this->assertStringContainsString('Donor B', $offered);
        $this->assertStringContainsString('Recipient B', $offered);

        // A reserve on that pair is not the pair's to give away, so the chain
        // is not offered them.
        $this->post('donors/new?pair=8201', [
            'pair' => '8201', 'mrn' => '8307', 'name' => 'Reserve Donor',
            'age' => '30', 'bloodType' => 'A', 'donorStatus' => 'On Hold',
        ]);

        $offered = $this->exchangeChoices($this->get('exchange/build')->getBody());
        $this->assertStringNotContainsString('Reserve Donor', $offered);
    }

    /** Every name the builder's choice lists offer, as one string. */
    private function exchangeChoices(string $html): string
    {
        preg_match_all('/<option\b[^>]*>(.*?)<\/option>/s', $html, $found);

        return implode(' | ', array_map('trim', $found[1]));
    }

    /** A donor already spoken for is gone from the other lists. */
    public function testADonorCannotBeMatchedTwice(): void
    {
        [$pairA] = $this->twoPairsToExchange();
        $this->post('recipients/new', ['mrn' => '8701', 'name' => 'Second Recipient', 'age' => '39', 'bloodType' => 'AB', 'status' => 'active']);

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
        $this->post('donors/new', ['mrn' => '8801', 'name' => 'Spare Donor', 'age' => '44', 'bloodType' => 'O', 'donorStatus' => 'Active']);

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
        $this->post('donors/new', ['mrn' => '8803', 'name' => 'Spare Donor', 'age' => '44', 'bloodType' => 'O', 'donorStatus' => 'Active']);

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
        $this->post('donors/new', ['mrn' => '8802', 'name' => 'Spare Donor', 'age' => '44', 'bloodType' => 'O', 'donorStatus' => 'Active']);

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
        $this->seeInDatabase('pairs', ['id' => $pairA, 'status' => 'paired_exchange']);
        $this->seeInDatabase('pairs', ['id' => $pairB, 'status' => 'paired_exchange']);
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

    /** Somebody in a pair that is not a paired exchange is not on the table. */
    public function testAPairNotOfferedCannotBeBrokenByAnExchange(): void
    {
        [$pairA] = $this->twoPairsToExchange();

        // An ordinary pair: its status is Active, so it is on nobody's
        // exchange list — and its donor could otherwise give to Recipient A.
        $this->post('pairs/new', [
            'rMrn' => '8501', 'dMrn' => '8502',
            'rName' => 'Recipient D', 'rAge' => '44', 'rBloodType' => 'AB', 'rStatus' => 'active',
            'dName' => 'Donor D', 'dAge' => '33', 'dBloodType' => 'O', 'dStatus' => 'Active',
        ]);
        $pairD = (int) $this->db->table('pairs')->where('recipient_mrn', 8501)->get()->getRowArray()['id'];

        $this->post('exchange/start/' . $pairA);

        $offered = $this->exchangeChoices($this->get('exchange/build')->getBody());
        $this->assertStringNotContainsString('Donor D', $offered, 'their pair is not a paired exchange');

        $this->post('exchange/build', ['action' => 'chooseDonor', 'recipientMrn' => '8101', 'donorMrn' => '8502']);
        $this->assertStringContainsString('not been put forward', (string) session()->getFlashdata('ui_error'));
        $this->seeInDatabase('pairs', ['id' => $pairD, 'status' => 'active']);
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
        // A recipient with a B donor, and a B recipient with an A donor. Both
        // sides Active, as a pair being worked up is: the chain offers nobody
        // else, because a chain is an agreement between people who are on the
        // programme now.
        $this->post('pairs/new', [
            'rMrn' => '8101', 'dMrn' => '8102',
            'rName' => 'Recipient A', 'rAge' => '44', 'rBloodType' => 'A', 'rStatus' => 'active',
            'dName' => 'Donor A', 'dAge' => '33', 'dBloodType' => 'B', 'dStatus' => 'Active',
        ]);
        $this->post('pairs/new', [
            'rMrn' => '8201', 'dMrn' => '8202',
            'rName' => 'Recipient B', 'rAge' => '51', 'rBloodType' => 'B', 'rStatus' => 'active',
            'dName' => 'Donor B', 'dAge' => '36', 'dBloodType' => 'A', 'dStatus' => 'Active',
        ]);

        $ids = array_column($this->db->table('pairs')->orderBy('id')->get()->getResultArray(), 'id');

        // Their status says it, which is what puts them on the exchange list
        // and what the chain's own lists look for.
        foreach ($ids as $id) {
            $this->post('pairs/' . $id, ['section' => 'pair', 'pairStatus' => 'paired_exchange']);
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

            // The question is beside the button that asks it, one per row,
            // and it posts to the address the button used to lead to.
            $this->assertStringContainsString('formaction="' . site_url($deleteUrl) . '"', $html, "{$list} should offer delete");
            $this->assertStringContainsString('class="confirm-title"', $html, "{$list} should ask first");
        }
    }

    /**
     * The button asks first, over the list it was pressed on.
     *
     * It used to ask on a page of its own, which meant pressing a bin by
     * accident lost the list. And the address only answers to a post: a GET
     * that deletes goes off when a browser prefetches the link, which on a
     * patient register is not recoverable.
     */
    public function testTheDeleteButtonAsksOverTheListItWasPressedOn(): void
    {
        $this->post('recipients/new', ['mrn' => '9205', 'name' => 'Layla Test', 'age' => '38', 'bloodType' => 'B']);

        $html = $this->get('recipients')->getBody();

        $this->assertStringContainsString('Delete Layla Test?', $html);
        $this->assertStringContainsString('cannot be undone', $html);
        // A dialog, not a screen of its own, and the opener links to it.
        $this->assertMatchesRegularExpression('/<dialog class="dialog" id="(confirm-[0-9a-f]+)"/', $html);
        $this->assertStringContainsString('data-dialog="confirm-', $html);
        $this->seeInDatabase('recipients', ['mrn' => 9205]);

        // There is no page behind it any more: the address answers to a post
        // and to nothing else.
        try {
            $this->get('recipients/9205/delete');
            $this->fail('the delete address should not answer a GET');
        } catch (PageNotFoundException) {
        }
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

        // The sheet is the list on paper, so it carries the list's own
        // columns, in its own words — and nothing the list does not have.
        $screen = $this->get('pairs')->getBody();

        foreach (['Type Dialysis', 'First Dialysis', 'Entry Date', 'Date of Crossmatch'] as $column) {
            $this->assertStringContainsString('<th>' . $column . '</th>', $html, $column);
            $this->assertStringContainsString('<th>' . $column . '</th>', $screen, $column);
        }

        $this->assertStringNotContainsString('<th>Note</th>', $html);
        $this->assertStringNotContainsString('<th>Dialysis</th>', $html, 'the list says First Dialysis');

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

        // The pair's card sets the pair — and hands the word on, because
        // Transplanted is the recipient's own as much as the pair's.
        $this->post('pairs/' . $pairId, ['section' => 'pair', 'pairStatus' => 'transplanted']);
        $this->assertSame('transplanted', $this->db->table('pairs')->where('id', $pairId)->get()->getRowArray()['status']);
        $this->assertSame('transplanted', $this->db->table('recipients')->where('mrn', 9507)->get()->getRowArray()['status']);
    }

    /** The Status field says what it will do beyond itself, before it does it. */
    public function testThePairsStatusSaysItSetsThePeoplesToo(): void
    {
        [$pairId] = $this->pairWith('2010', '2011', 'The Donor');

        $html = $this->get('pairs/' . $pairId . '?edit=pair')->getBody();

        $this->assertStringContainsString('class="field-hint"', $html);
        $this->assertStringContainsString('On Hold, Active, Declined', $html);
        $this->assertStringContainsString('and Transplanted', $html);
        $this->assertStringContainsString('saving this card sets theirs to the same word', $html);
        $this->assertStringContainsString('Paired Exchange and Closed', $html);
    }

    /** A transplant happens to a person, so a person's record says so. */
    public function testARecordCanBeTransplantedOnItsOwn(): void
    {
        $this->post('recipients/new', ['mrn' => '2012', 'name' => 'R', 'age' => '40', 'bloodType' => 'A']);
        $this->post('donors/new', ['mrn' => '2013', 'name' => 'D', 'age' => '30', 'bloodType' => 'A']);

        $this->assertStringContainsString('value="transplanted"', $this->get('recipients/2012?edit=personal')->getBody());
        $this->assertStringContainsString('value="Transplanted"', $this->get('donors/2013?edit=personal')->getBody());

        $this->post('recipients/2012', [
            'section' => 'personal', 'name' => 'R', 'bloodType' => 'A', 'status' => 'transplanted',
        ]);
        $this->post('donors/2013', [
            'section' => 'personal', 'name' => 'D', 'bloodType' => 'A',
            'donationType' => 'living', 'donorStatus' => 'Transplanted',
        ]);

        $this->seeInDatabase('recipients', ['mrn' => 2012, 'status' => 'transplanted']);
        $this->seeInDatabase('donors', ['mrn' => 2013, 'status' => 'transplanted']);
    }

    /**
     * A collection nobody could make is not "not done", it does not apply.
     *
     * 24h-urine for protein and Cr clearance are collections rather than bench
     * tests, so an anuric patient has no answer to give — and both sheets now
     * offer the word for that, as the cancer screening tests already did.
     */
    public function testTheUrineCollectionsOfferNotApplicable(): void
    {
        $this->post('recipients/new', ['mrn' => '2014', 'name' => 'R', 'age' => '40', 'bloodType' => 'A']);
        $this->post('donors/new', ['mrn' => '2015', 'name' => 'D', 'age' => '30', 'bloodType' => 'A']);

        foreach (['24h-urine for protein', 'Cr clearance', 'Creatinine Clearance'] as $name) {
            $this->seeInDatabase('labs', [
                'name'        => $name,
                'person_mrn'  => null,
                'result_type' => 'acceptable_abnormal_na',
            ]);
        }

        // And the card offers it, beside the three it always had.
        $lab = $this->db->table('labs')
            ->where(['organ_code' => 'kidney', 'person_type' => 'recipient', 'name' => 'Cr clearance'])
            ->get()->getRowArray();

        $html = $this->get('recipients/2014?edit=labs')->getBody();
        $card = substr($html, (int) strpos($html, 'value="' . $lab['id'] . '"'));
        $card = substr($card, 0, (int) strpos($card, '</div></div>') ?: 4000);

        $this->assertStringContainsString('data-lab-status="not_applicable"', $card);

        // It stores like any other answer.
        $this->post('recipients/2014', [
            'section' => 'labs',
            'labs'    => [['id' => $lab['id'], 'name' => 'Cr clearance', 'status' => 'not_applicable']],
        ]);
        $this->seeInDatabase('lab_results', [
            'person_mrn' => 2014,
            'lab_id'     => $lab['id'],
            'status'     => 'not_applicable',
        ]);
    }

    /**
     * A blank form's cards have their answers on them.
     *
     * The Add screens build their workup from the catalogue rather than from a
     * record, and that path was handing the view a test with no answers: every
     * card arrived with nothing to press and a comment box, which is what a
     * free-text card looks like. The two paths build the same card, so they
     * read the answers the same way.
     */
    public function testABlankFormsWorkupCardsCarryTheirAnswers(): void
    {
        foreach (['recipients/new', 'donors/new'] as $screen) {
            $html = $this->get($screen)->getBody();

            $this->assertStringContainsString('data-lab-status="not_done"', $html, $screen);
            $this->assertStringContainsString('data-lab-status="pending"', $html, $screen);
            // Blood group answers with the four groups, as it does on a record.
            $this->assertStringContainsString('data-lab-status="blood_a"', $html, $screen);
            $this->assertStringContainsString('data-lab-status="acceptable"', $html, $screen);
        }

        // Add Pair builds both sides from the same catalogue.
        $pair = $this->get('pairs/new')->getBody();

        $this->assertSame(2, substr_count($pair, 'data-lab-status="blood_a"'), 'the recipient and the donor');
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

        // Nothing of that type is not the same as an empty register. The
        // sentence covers both filters now rather than naming only one.
        $none = $this->get('donors?bt=AB')->getBody();
        $this->assertStringContainsString('No unmatched donors match these filters.', $none);
        $this->assertStringNotContainsString('No unmatched donors.', $none);
    }

    /**
     * Both registers narrow by status as well, and the two chip rows narrow
     * together rather than each clearing the other.
     */
    public function testBothRegistersFilterByStatus(): void
    {
        $this->post('recipients/new', ['mrn' => '9301', 'name' => 'Active R', 'age' => '40', 'bloodType' => 'A', 'status' => 'active']);
        $this->post('recipients/new', ['mrn' => '9302', 'name' => 'Held R', 'age' => '41', 'bloodType' => 'B', 'status' => 'on_hold']);
        $this->post('donors/new', ['mrn' => '9303', 'name' => 'Active D', 'age' => '30', 'bloodType' => 'A', 'donorStatus' => 'Active']);
        $this->post('donors/new', ['mrn' => '9304', 'name' => 'Held D', 'age' => '31', 'bloodType' => 'B', 'donorStatus' => 'On Hold']);

        foreach (['recipients' => ['Active R', 'Held R'], 'donors' => ['Active D', 'Held D']] as $screen => [$active, $held]) {
            $all = $this->get($screen)->getBody();
            $this->assertStringContainsString($active, $all);
            $this->assertStringContainsString($held, $all);
            // Every status a record can hold is offered, and nothing else.
            $this->assertStringContainsString('Status:', $all);
            $this->assertStringContainsString('status=on_hold', $all);
            $this->assertStringContainsString('status=transplanted', $all);
            $this->assertStringNotContainsString('status=closed', $all);

            $onHold = $this->get($screen . '?status=on_hold')->getBody();
            $this->assertStringContainsString($held, $onHold);
            $this->assertStringNotContainsString($active, $onHold);

            // The two narrow together: the blood-type chips carry the status
            // with them, and the other way round.
            $this->assertStringContainsString('bt=B&amp;status=on_hold', $onHold);

            $both = $this->get($screen . '?bt=A&status=on_hold')->getBody();
            $this->assertStringNotContainsString($active, $both);
            $this->assertStringNotContainsString($held, $both);
        }
    }

    /**
     * Both registers export what is on the screen, not the whole table.
     *
     * The same sheet as the Pairs List's: the filtered rows, the columns the
     * screen shows, and the filters named on the letterhead so a printout says
     * what it is a printout of.
     */
    public function testBothRegistersExportTheFilteredList(): void
    {
        $this->post('recipients/new', ['mrn' => '9401', 'name' => 'Type A Listed', 'age' => '40', 'bloodType' => 'A']);
        $this->post('recipients/new', ['mrn' => '9402', 'name' => 'Type B Listed', 'age' => '41', 'bloodType' => 'B']);
        $this->post('donors/new', ['mrn' => '9403', 'name' => 'Type A Donor', 'age' => '30', 'bloodType' => 'A']);
        $this->post('donors/new', ['mrn' => '9404', 'name' => 'Type B Donor', 'age' => '31', 'bloodType' => 'B']);

        foreach ([
            'recipients' => ['Recipient Waitlist', 'Type A Listed', 'Type B Listed'],
            'donors'     => ['Donors List', 'Type A Donor', 'Type B Donor'],
        ] as $screen => [$title, $kept, $dropped]) {
            // The button is on the screen, carrying the filters with it.
            $list = $this->get($screen . '?bt=A')->getBody();
            $this->assertStringContainsString(site_url($screen . '/print') . '?bt=A', $list);
            $this->assertStringContainsString('Export PDF', $list);

            $sheet = $this->get($screen . '/print?bt=A')->getBody();
            $this->assertStringContainsString($title . ' &mdash; ', $sheet);
            $this->assertStringContainsString('Blood type A', $sheet);
            $this->assertStringContainsString($kept, $sheet);
            $this->assertStringNotContainsString($dropped, $sheet);

            // Unfiltered, the letterhead says so rather than naming nothing.
            $this->assertStringContainsString('No filters applied', $this->get($screen . '/print')->getBody());

            // And an empty sheet is a sentence, not an empty table.
            $none = $this->get($screen . '/print?bt=AB')->getBody();
            $this->assertStringContainsString('match these filters', $none);
            $this->assertStringNotContainsString('<table', $none);
        }
    }

    /**
     * The search narrows the list you are looking at, and never leaves it.
     *
     * A screen with a list carries the box; one without — the dashboard, Add
     * MRP, a record — has nothing for it to do and does not. It posts back to
     * the same address, with the filters already on the screen riding along,
     * so searching narrows what is showing rather than replacing it.
     */
    public function testTheSearchNarrowsTheListYouAreOn(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9501', 'dMrn' => '9502',
            'rName' => 'Hamad Al-Qahtani', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Sara Al-Qahtani', 'dAge' => '30', 'dBloodType' => 'A',
        ]);
        $this->post('recipients/new', ['mrn' => '9503', 'name' => 'Omar Al-Dosari', 'age' => '44', 'bloodType' => 'B']);
        $this->post('donors/new', ['mrn' => '9504', 'name' => 'Lina Al-Dosari', 'age' => '34', 'bloodType' => 'B']);

        // The lists carry it; the screens with nothing to narrow do not.
        foreach (['recipients', 'donors', 'pairs', 'exchange', 'reports'] as $screen) {
            $this->assertStringContainsString('class="app-search"', $this->get($screen)->getBody(), $screen . ' carries the search');
        }

        foreach (['dashboard', 'mrp'] as $screen) {
            $this->assertStringNotContainsString('class="app-search"', $this->get($screen)->getBody(), $screen . ' does not');
        }

        // It posts back to the screen it is on, not to a search page.
        $html = $this->get('recipients')->getBody();
        $this->assertStringContainsString('action="' . site_url('recipients') . '"', $html);
        $this->assertStringNotContainsString(site_url('search'), $html);

        // And it narrows that screen's own list.
        $narrowed = $this->get('recipients?q=Dosari')->getBody();
        $this->assertStringContainsString('Omar Al-Dosari', $narrowed);
        $this->assertStringNotContainsString('Hamad Al-Qahtani', $narrowed);

        $donors = $this->get('donors?q=Dosari')->getBody();
        $this->assertStringContainsString('Lina Al-Dosari', $donors);
        $this->assertStringNotContainsString('Sara Al-Qahtani', $donors);

        // A pair answers to either of its people, and to its own number.
        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];
        $this->assertStringContainsString('Hamad Al-Qahtani', $this->get('pairs?status=all&q=9502')->getBody());
        $this->assertStringContainsString('Hamad Al-Qahtani', $this->get('pairs?status=all&q=' . $pairId)->getBody());
        $this->assertStringNotContainsString('Hamad Al-Qahtani', $this->get('pairs?status=all&q=zzz')->getBody());

        // An MRN narrows the report as well.
        $this->assertStringContainsString('Omar Al-Dosari', $this->get('reports?q=9503')->getBody());
        $this->assertStringNotContainsString('Omar Al-Dosari', $this->get('reports?q=9502')->getBody());
    }

    /** The filters already on the screen survive a search, and the other way round. */
    public function testTheSearchAndTheChipsNarrowTogether(): void
    {
        $this->post('recipients/new', ['mrn' => '9511', 'name' => 'Nasser Group A', 'age' => '40', 'bloodType' => 'A']);
        $this->post('recipients/new', ['mrn' => '9512', 'name' => 'Nasser Group B', 'age' => '41', 'bloodType' => 'B']);

        // Searching keeps the chip: the form re-sends what is in the address.
        $html = $this->get('recipients?bt=A')->getBody();
        $this->assertStringContainsString('<input type="hidden" name="bt" value="A">', $html);

        // Pressing a chip keeps the search.
        $searched = $this->get('recipients?q=Nasser')->getBody();
        $this->assertStringContainsString('bt=A&amp;q=Nasser', $searched);

        // Both together narrow to one.
        $both = $this->get('recipients?bt=A&q=Nasser')->getBody();
        $this->assertStringContainsString('Nasser Group A', $both);
        $this->assertStringNotContainsString('Nasser Group B', $both);
    }

    /**
     * Setting a pair's status to Paired Exchange puts it on that list.
     *
     * The button and the status said the same thing, and making somebody say
     * it twice only let the two disagree — a pair marked Paired Exchange that
     * was not on the exchange list.
     */
    public function testPairedExchangeStatusOffersThePairWithoutTheButton(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9601', 'dMrn' => '9602',
            'rName' => 'Status Offered', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Their Donor', 'dAge' => '30', 'dBloodType' => 'B',
            'pairStatus' => 'active',
        ]);
        $pairId = (int) $this->db->table('pairs')->where('recipient_mrn', 9601)->get()->getRowArray()['id'];

        // Active, so not offered and not on the list.
        $this->seeInDatabase('pairs', ['id' => $pairId, 'for_exchange' => 0]);
        $this->assertStringNotContainsString('Status Offered', $this->get('exchange')->getBody());

        // The status alone puts it there.
        $this->post('pairs/' . $pairId, ['section' => 'pair', 'pairStatus' => 'paired_exchange']);

        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'paired_exchange', 'for_exchange' => 1]);
        $this->assertStringContainsString('Status Offered', $this->get('exchange')->getBody());

        // And while the status says so there is nothing to press: taking it
        // back means saying something else on the card.
        $html = $this->get('pairs/' . $pairId)->getBody();
        $this->assertStringContainsString('On the exchange list', $html);
        $this->assertStringNotContainsString('Withdraw from exchange', $html);

        $this->post('pairs/' . $pairId, ['section' => 'exchange', 'forExchange' => '0']);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'for_exchange' => 1]);
        $this->assertStringContainsString('because its status says so', (string) session('ui_error'));
    }

    /** A pair created as Paired Exchange is on the list from the start. */
    public function testAPairCreatedAsPairedExchangeIsOfferedAtOnce(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9611', 'dMrn' => '9612',
            'rName' => 'Born Offered', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Their Donor', 'dAge' => '30', 'dBloodType' => 'B',
            'pairStatus' => 'paired_exchange',
        ]);

        $this->seeInDatabase('pairs', ['recipient_mrn' => 9611, 'status' => 'paired_exchange', 'for_exchange' => 1]);
        $this->assertStringContainsString('Born Offered', $this->get('exchange')->getBody());
    }

    /** The button still works on its own for a pair with any other status. */
    public function testTheButtonStillOffersAPairThatIsNotMarkedForExchange(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9621', 'dMrn' => '9622',
            'rName' => 'Button Offered', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'Their Donor', 'dAge' => '30', 'dBloodType' => 'B',
            'pairStatus' => 'active',
        ]);
        $pairId = (int) $this->db->table('pairs')->where('recipient_mrn', 9621)->get()->getRowArray()['id'];

        $this->post('pairs/' . $pairId, ['section' => 'exchange', 'forExchange' => '1']);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'active', 'for_exchange' => 1]);

        // And takes it back, because the status is not what put it there.
        $this->post('pairs/' . $pairId, ['section' => 'exchange', 'forExchange' => '0']);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'for_exchange' => 0]);
    }

    /** A status nobody can choose is read as no filter at all. */
    public function testAnUnknownStatusIsNotAFilter(): void
    {
        $this->post('recipients/new', ['mrn' => '9305', 'name' => 'Still Here', 'age' => '40', 'bloodType' => 'A']);

        $this->assertStringContainsString('Still Here', $this->get('recipients?status=nonsense')->getBody());
    }

    /**
     * Paired Exchange has no status chips: being on the list is already a
     * status, and the few a pair can hold there are all true of every row.
     *
     * Its blood-type row asks a different question from the Pairs List's. An
     * exchange exists because a donor cannot give to their own recipient, so
     * matching either side would hide the pairs that make one work. It is the
     * recipient's group alone.
     */
    public function testPairedExchangeFiltersOnTheRecipientsBloodTypeOnly(): void
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
        $this->assertStringContainsString('Recipient blood type:', $html);
        $this->assertStringNotContainsString('Status:', $html);
        // Its own file-number box went to the bar at the top of every screen —
        // the only `q` on this page now is that one. `?q=` still narrows the
        // list, which is what the top bar's results link to.
        $this->assertStringNotContainsString('id="ex-q"', $html);
        $this->assertSame(1, substr_count($html, 'name="q"'), 'one search box, and it is the top bar\'s');
        $this->assertStringContainsString('File number:', $this->get('exchange?q=9603')->getBody());

        // The row opens the pair, and carries a real link for it as well.
        $this->assertStringContainsString('data-href="' . site_url('pairs/' . $pairId) . '"', $html);
        $this->assertStringContainsString('<a href="' . site_url('pairs/' . $pairId) . '">' . $pairId . '</a>', $html);

        // The recipient's group keeps the pair; the donor's does not bring it
        // back, which is the whole of the difference.
        $this->assertStringContainsString('Offered Recipient', $this->get('exchange?bt=A')->getBody());
        $this->assertStringNotContainsString('Offered Recipient', $this->get('exchange?bt=B')->getBody());
        $this->assertStringNotContainsString('Offered Recipient', $this->get('exchange?bt=AB')->getBody());

        // The two filters narrow together rather than clearing each other.
        $this->assertStringContainsString('bt=A&amp;q=9603', $this->get('exchange?bt=A&q=9603')->getBody());
        $this->assertStringContainsString('Offered Recipient', $this->get('exchange?bt=A&q=9603')->getBody());
        $this->assertStringNotContainsString('Offered Recipient', $this->get('exchange?bt=A&q=1234')->getBody());

        // An empty list says which kind of empty it is: nobody offered, or
        // nobody matching. The second is not a reason to explain the screen.
        $filteredEmpty = $this->get('exchange?bt=AB')->getBody();
        $this->assertStringContainsString('match these filters', $filteredEmpty);
        $this->assertStringNotContainsString('put forward for exchange', $filteredEmpty);
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
     * Either side already in a pair has nothing to choose, so Link shows them
     * the pair: a pair's further donors are added on the pair's own screen.
     */
    public function testEitherSideIsSentStraightToTheirPair(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '9004', 'dMrn' => '9005',
            'rName' => 'R', 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => 'D', 'dAge' => '30', 'dBloodType' => 'A',
        ]);

        $pairId = (int) $this->db->table('pairs')->get()->getRowArray()['id'];

        $this->get('donors/9005/link')->assertRedirectTo(site_url('pairs/' . $pairId));
        $this->get('recipients/9004/link')->assertRedirectTo(site_url('pairs/' . $pairId));
        // And the record says who, rather than offering to link again.
        $this->assertStringContainsString('Linked: D', $this->get('recipients/9004')->getBody());
        $this->assertStringNotContainsString('Link with Donor', $this->get('recipients/9004')->getBody());
    }

    /**
     * A recipient's status and their links' are separate facts.
     *
     * They were one while a recipient had one donor. With several there is no
     * saying which of them a recipient set to Declined would mean, so the
     * person's status is the person's — are they on the programme — and each
     * link carries its own.
     *
     * One direction still carries, because it says one fact rather than two:
     * the pair's status is the people's where the word is one they can hold.
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

        // The pair's card moves the pair, and hands the word to both of them:
        // four of the six are the people's as much as the pair's.
        $this->post('pairs/' . $pairId, ['section' => 'pair', 'pairStatus' => 'on_hold']);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'on_hold']);
        $this->seeInDatabase('recipients', ['mrn' => 2005, 'status' => 'on_hold']);
        $this->seeInDatabase('donors', ['mrn' => 2006, 'status' => 'on_hold']);
        $this->assertStringContainsString('Status set to On Hold on', (string) session('ui_notice'));

        // The other two are the pair's alone and leave both where they are.
        $this->post('pairs/' . $pairId, [
            'section' => 'pair', 'pairStatus' => 'closed', 'closedReason' => 'Not going ahead.',
        ]);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'closed']);
        $this->seeInDatabase('recipients', ['mrn' => 2005, 'status' => 'on_hold']);
        $this->seeInDatabase('donors', ['mrn' => 2006, 'status' => 'on_hold']);
    }

    /**
     * Helper: a pair with one donor, made the way the screens make one.
     *
     * @return array{0: int, 1: int} The pair's id, and the link's
     */
    private function pairWith(string $rMrn, string $dMrn, string $dName = 'Donor'): array
    {
        $this->post('pairs/new', [
            'rMrn' => $rMrn, 'dMrn' => $dMrn,
            'rName' => 'R ' . $rMrn, 'rAge' => '40', 'rBloodType' => 'A',
            'dName' => $dName, 'dAge' => '30', 'dBloodType' => 'A', 'dStatus' => 'Active',
        ]);

        $row = $this->db->table('pairs')->where('recipient_mrn', $rMrn)->orderBy('id')->get()->getRowArray();

        return [(int) $row['id'], (int) $row['id']];
    }

    /**
     * Moves one of a pair's donors to a status, the only way the screens do:
     * their own Personal Information card on the pair.
     */
    private function setPairDonorStatus(int $pairId, int $linkId, string $name, string $status): void
    {
        $this->post('pairs/' . $pairId, [
            'section'    => 'pd' . $linkId . '-personal',
            'dName'      => $name,
            'dBloodType' => 'A',
            'dType'      => 'living_related',
            'dStatus'    => $status,
        ]);
    }

    /** Adds another donor to a pair, through the pair's own dialog. */
    private function addPairDonor(int $pairId, string $dMrn, string $dName, string $status = 'on_hold'): int
    {
        $pair = $this->db->table('pairs')->where('id', $pairId)->get()->getRowArray();

        $this->post('donors/new?pair=' . $pair['recipient_mrn'], [
            'pair' => (string) $pair['recipient_mrn'], 'mrn' => $dMrn, 'name' => $dName,
            'age' => '33', 'bloodType' => 'A',
            'donorStatus' => $status === 'on_hold' ? 'On Hold' : ucfirst($status),
        ]);

        $row = $this->db->table('pairs')
            ->where('recipient_mrn', $pair['recipient_mrn'])
            ->where('donor_mrn', $dMrn)
            ->get()
            ->getRowArray();

        return (int) $row['id'];
    }

    /**
     * A pair's donors are tabs, and delinking one archives it rather than
     * taking it off the pair.
     */
    public function testAPairsDonorsAreTabs(): void
    {
        [$pairId] = $this->pairWith('8800', '8801', 'Donor One');
        $second   = $this->addPairDonor($pairId, '8802', 'Donor Two');

        $this->assertSame(2, $this->db->table('pairs')->where('recipient_mrn', 8800)->countAllResults());

        $html = $this->get('pairs/' . $pairId)->getBody();
        $this->assertStringContainsString('2 donors', $html);
        $this->assertStringContainsString('Donor-1', $html);
        $this->assertStringContainsString('Donor-2', $html);
        $this->assertStringContainsString('Donor One', $html);

        // The second tab shows the second donor, in full: their details and
        // their whole workup, without leaving the pair.
        $tab = $this->get('pairs/' . $pairId . '?donor=2')->getBody();
        $this->assertStringContainsString('Donor Two', $tab);
        $this->assertStringContainsString('Required Lab Tests', $tab);

        // It asks before taking anybody off, on the pair itself, and a
        // reserve is asked once.
        $this->assertStringContainsString('This pair has finished with Donor Two', $tab);
        $this->assertStringNotContainsString('Take the pair apart', $tab);
        $this->seeInDatabase('pairs', ['id' => $second, 'status' => 'on_hold']);

        $this->post('pairs/' . $pairId . '/donors/' . $second . '/delink');

        // Archived is the link closing; the donor's own word is untouched.
        $this->seeInDatabase('pairs', ['id' => $second, 'status' => 'closed']);
        $this->seeInDatabase('donors', ['mrn' => 8802, 'status' => 'on_hold']);

        $html = $this->get('pairs/' . $pairId . '?donor=2')->getBody();
        $this->assertStringContainsString('tab--delinked', $html);
        $this->assertStringContainsString('cannot be changed', $html);
        // Read-only: the archived tab has nothing left to press.
        $this->assertStringNotContainsString('/delink"', $html);
    }

    /**
     * One pair, one donor it is going ahead with.
     *
     * The way to another is to stand the current one down and raise them,
     * each on their own card. Setting a second active outright is refused on
     * the card that asks, and again in the store behind it.
     */
    public function testAPairMayHaveOnlyOneActiveDonor(): void
    {
        [$pairId] = $this->pairWith('8920', '8921', 'The Donor');
        $second   = $this->addPairDonor($pairId, '8922', 'The Reserve');

        $this->setPairDonorStatus($pairId, $second, 'The Reserve', 'Active');

        $this->seeInDatabase('donors', ['mrn' => 8922, 'status' => 'on_hold']);
        $this->assertStringContainsString('already has an active donor', (string) session('ui_error'));

        // And the card does not offer what it would refuse.
        $html   = $this->get('pairs/' . $pairId . '?donor=2&edit=pd' . $second . '-personal')->getBody();
        $picker = substr($html, (int) strpos($html, 'id="f-d-status"'));
        $picker = substr($picker, 0, (int) strpos($picker, '</select>'));
        $this->assertStringNotContainsString('value="Active"', $picker);
        $this->assertStringContainsString('value="On Hold"', $picker);

        // Stand the first down, and the way is open.
        [$first] = $this->pairLinks(8920);
        $this->setPairDonorStatus($pairId, $first, 'The Donor', 'On Hold');
        $this->setPairDonorStatus($pairId, $second, 'The Reserve', 'Active');

        $this->seeInDatabase('donors', ['mrn' => 8921, 'status' => 'on_hold']);
        $this->seeInDatabase('donors', ['mrn' => 8922, 'status' => 'active']);
        // Nobody was archived by it: both are still the pair's donors.
        $this->dontSeeInDatabase('pairs', ['recipient_mrn' => 8920, 'status' => 'closed']);
    }

    /**
     * Linking somebody already on the register in the place being vacated.
     *
     * The answer says they are set to Active, so they are: the pair has just
     * lost the donor it was going ahead with, and this is who it is going
     * ahead with instead.
     */
    public function testDelinkingCanLinkARegisteredDonorInTheirPlace(): void
    {
        [$pairId] = $this->pairWith('8970', '8971', 'The Donor');
        $this->post('donors/new', ['mrn' => '8972', 'name' => 'On The Register', 'age' => '30', 'bloodType' => 'A']);
        [$first]  = $this->pairLinks(8970);

        $this->post('pairs/' . $pairId . '/donors/' . $first . '/delink', [
            'outcome'  => 'existing',
            'donorMrn' => '8972',
        ]);

        // The one taken off is archived; the one chosen is the pair's donor.
        $this->seeInDatabase('pairs', ['id' => $first, 'status' => 'closed']);
        $this->seeInDatabase('pairs', ['recipient_mrn' => 8970, 'donor_mrn' => 8972, 'status' => 'active']);
        $this->seeInDatabase('donors', ['mrn' => 8972, 'status' => 'active']);
        $this->assertStringContainsString('going ahead with On The Register now', (string) session('ui_notice'));
    }

    /** Or entering one who is not on the system yet, which is Add Donor. */
    public function testDelinkingCanSendYouToAddTheDonorTakingTheirPlace(): void
    {
        [$pairId] = $this->pairWith('8973', '8974', 'Only Donor');
        [$first]  = $this->pairLinks(8973);

        $this->post('pairs/' . $pairId . '/donors/' . $first . '/delink', ['outcome' => 'new'])
            ->assertRedirectTo(site_url('donors/new') . '?pair=8973');

        $this->seeInDatabase('pairs', ['id' => $first, 'status' => 'closed']);
        // And that screen enters them as the pair's donor, because the pair
        // has nobody active to argue with.
        $this->assertStringContainsString('value="Active" selected', $this->get('donors/new?pair=8973')->getBody());
    }

    /** The three answers, in the warning that says why they are asked. */
    public function testTheDelinkDialogWarnsAndOffersTheThreeAnswers(): void
    {
        [$pairId] = $this->pairWith('8975', '8976', 'The Donor');
        $this->post('donors/new', ['mrn' => '8977', 'name' => 'On The Register', 'age' => '30', 'bloodType' => 'A']);

        $html = $this->get('pairs/' . $pairId)->getBody();

        $this->assertStringContainsString('takes the pair apart', $html);
        $this->assertStringContainsString('Link with a new donor', $html);
        $this->assertStringContainsString('Link with an existing donor', $html);
        $this->assertStringContainsString('Take the pair apart', $html);
        // The one that needs to know which carries the list.
        $this->assertStringContainsString('name="donorMrn"', $html);
        $this->assertStringContainsString('On The Register', $html);
    }

    /**
     * A pair's donor reads the way everything else on the pair does.
     *
     * The pair first, then the recipient — who they are, their workup, their
     * notes — and the donors under them, each as the same three cards.
     */
    public function testAPairsDonorIsShownAsCardsBelowTheRecipient(): void
    {
        [$pairId] = $this->pairWith('8870', '8871', 'The Donor');

        // The dash is written as a character in some of these headings and as
        // an entity in others, and which is which is not what this is about.
        $html = str_replace('—', '&mdash;', $this->get('pairs/' . $pairId)->getBody());

        $titles = [
            'Pair Details',
            'Recipient &mdash; Personal Information',
            'Required Lab Tests',
            'Recipient &mdash; Clinical Notes',
            'Donor &mdash; Personal Information',
            'Donor &mdash; Required Lab Tests',
            'Donor &mdash; Clinical Notes',
        ];

        // The donors' own card heads the tabs, above the first of them.
        $this->assertLessThan(
            (int) strpos($html, '>Donor &mdash; Personal Information</h2>'),
            (int) strpos($html, '>Donors</h2>'),
            'the tabs come before the first donor on them'
        );

        $at = -1;

        foreach ($titles as $title) {
            $next = strpos($html, '>' . $title . '</h2>');
            $this->assertIsInt($next, $title . ' is on the screen');
            $this->assertGreaterThan($at, $next, $title . ' comes after the card before it');
            $at = $next;
        }
    }

    /** And each of those cards is edited and saved on its own. */
    public function testEachOfAPairsDonorCardsSavesOnItsOwn(): void
    {
        [$pairId] = $this->pairWith('8880', '8881', 'The Donor');
        [$id]     = $this->pairLinks(8880);

        // Closed until a card is opened, and opened one at a time.
        $this->assertStringContainsString('edit=pd' . $id . '-personal', $this->get('pairs/' . $pairId)->getBody());

        $open = $this->get('pairs/' . $pairId . '?donor=1&edit=pd' . $id . '-personal')->getBody();
        $this->assertStringContainsString('name="section" value="pd' . $id . '-personal"', $open);
        $this->assertStringContainsString('name="dName"', $open);

        $this->post('pairs/' . $pairId, [
            'section' => 'pd' . $id . '-personal',
            'dName'   => 'Renamed', 'dCity' => 'Jeddah', 'dBloodType' => 'B',
            'dRelationship' => 'Brother', 'dStatus' => 'Active', 'dType' => 'living_related',
        ])->assertRedirectTo(site_url('pairs/' . $pairId) . '?donor=1');

        $this->seeInDatabase('donors', [
            'mrn' => 8881, 'name' => 'Renamed', 'city' => 'Jeddah',
            'blood_group' => 'B', 'relationship' => 'Brother',
        ]);

        // The notes card writes only the notes.
        $this->post('pairs/' . $pairId, ['section' => 'pd' . $id . '-notes', 'dNotes' => 'Seen in clinic.']);
        $this->seeInDatabase('donors', ['mrn' => 8881, 'name' => 'Renamed', 'notes' => 'Seen in clinic.']);
    }

    /** An archived donor is shown, and not edited. */
    public function testAnArchivedDonorHasNoEditLinks(): void
    {
        [$pairId] = $this->pairWith('8890', '8891', 'The Donor');
        $second   = $this->addPairDonor($pairId, '8892', 'The Reserve');

        $this->post('pairs/' . $pairId . '/donors/' . $second . '/delink');

        $html = $this->get('pairs/' . $pairId . '?donor=2')->getBody();
        $this->assertStringContainsString('Donor &mdash; Personal Information', $html);
        $this->assertStringNotContainsString('edit=pd' . $second . '-', $html);

        // And a post naming one of its cards changes nothing.
        $this->post('pairs/' . $pairId, ['section' => 'pd' . $second . '-notes', 'dNotes' => 'Should not land.']);
        $this->dontSeeInDatabase('donors', ['mrn' => 8892, 'notes' => 'Should not land.']);
    }

    /**
     * A pair that comes apart leaves its donors on the recipient's record.
     *
     * The recipient goes back to the waiting list, and everything the pair
     * worked out about each donor goes with them: the same Donors section,
     * the same tabs, the same cards — archived, and read only.
     */
    public function testADissolvedPairsDonorsStayOnTheRecipientsRecord(): void
    {
        [$pairId] = $this->pairWith('8710', '8711', 'The Donor');
        $this->addPairDonor($pairId, '8712', 'The Reserve');
        [$first]  = $this->pairLinks(8710);

        $this->post('pairs/' . $pairId . '/donors/' . $first . '/delink', ['outcome' => 'dissolve']);

        // Back on the waiting list, with nothing holding either of them.
        $this->dontSeeInDatabase('pairs', ['recipient_mrn' => 8710, 'status !=' => 'closed']);

        $html = str_replace('—', '&mdash;', $this->get('recipients/8710')->getBody());

        $this->assertStringContainsString('>Donors</h2>', $html);
        $this->assertStringContainsString('2 donors previously linked', $html);
        $this->assertStringContainsString('The Donor', $html);
        $this->assertStringContainsString('Donor &mdash; Required Lab Tests', $html);
        // Every tab archived, and nothing on any of them to press.
        $this->assertSame(2, substr_count($html, 'tab--delinked'));
        $this->assertStringNotContainsString('/delink"', $html);
        $this->assertStringNotContainsString('edit=pd' . $first . '-', $html);
        $this->assertStringNotContainsString('id="add-donor"', $html);
    }

    /** And each of those tabs opens, from the record's own address. */
    public function testTheRecipientsArchiveOpensEachDonorsTab(): void
    {
        [$pairId] = $this->pairWith('8720', '8721', 'The First');
        $this->addPairDonor($pairId, '8722', 'The Second');
        [$first]  = $this->pairLinks(8720);

        $this->post('pairs/' . $pairId . '/donors/' . $first . '/delink', ['outcome' => 'dissolve']);

        $opened = $this->get('recipients/8720?donor=1')->getBody();
        $this->assertStringContainsString('>The First</h3>', $opened);
        $this->assertStringContainsString('recipients/8720?donor=2', $opened);

        $second = $this->get('recipients/8720?donor=2')->getBody();
        $this->assertStringContainsString('>The Second</h3>', $second);
    }

    /** While a pair holds them, though, the tabs stay on the pair. */
    public function testAPairedRecipientsRecordLeavesTheTabsOnThePair(): void
    {
        [$pairId] = $this->pairWith('8730', '8731', 'The Donor');

        $this->assertStringContainsString('donor-tabs', $this->get('pairs/' . $pairId)->getBody());
        $this->assertStringNotContainsString('donor-tabs', $this->get('recipients/8730')->getBody());
    }

    /**
     * The donor's half of it: where their pair went.
     *
     * Their own record does not carry the pair — the recipient's does — so it
     * carries the sentence that says so, and the way across.
     */
    public function testADonorsRecordSaysWhoTheyWereLinkedWith(): void
    {
        [$pairId] = $this->pairWith('8740', '8741', 'The Donor');
        [$first]  = $this->pairLinks(8740);

        // Nothing to say while the pair is theirs.
        $this->assertStringNotContainsString('previously linked with', $this->get('donors/8741')->getBody());

        $this->post('pairs/' . $pairId . '/donors/' . $first . '/delink', ['outcome' => 'dissolve']);

        $html = $this->get('donors/8741')->getBody();
        $this->assertStringContainsString('previously linked with', $html);
        $this->assertStringContainsString(site_url('recipients/8740'), $html);
        $this->assertStringContainsString('R 8740', $html);
        // The section itself belongs to the recipient, not to them.
        $this->assertStringNotContainsString('donor-tabs', $html);
    }

    /** The status beside a tab's name is a word, and only a word. */
    public function testATabsStatusIsPlainText(): void
    {
        [$pairId] = $this->pairWith('8810', '8811', 'D');
        [$id]     = $this->pairLinks(8810);

        $this->assertStringContainsString('<span class="tab-status">Active</span>', $this->get('pairs/' . $pairId)->getBody());

        $this->setPairDonorStatus($pairId, $id, 'D', 'On Hold');

        $this->seeInDatabase('donors', ['mrn' => 8811, 'status' => 'on_hold']);
        $this->assertStringContainsString('<span class="tab-status">On Hold</span>', $this->get('pairs/' . $pairId)->getBody());
    }

    /** Somebody already on the register joins a pair without being re-entered. */
    public function testAnExistingDonorCanBeAddedToAPair(): void
    {
        [$pairId] = $this->pairWith('8850', '8851', 'The Donor');
        $this->post('donors/new', ['mrn' => '8852', 'name' => 'On The Register', 'age' => '30', 'bloodType' => 'A']);

        $this->post('pairs/' . $pairId . '/donors', ['donorMrn' => '8852', 'status' => 'on_hold']);

        $this->seeInDatabase('pairs', ['recipient_mrn' => 8850, 'donor_mrn' => 8852, 'status' => 'on_hold']);
        $this->seeInDatabase('donors', ['mrn' => 8852, 'status' => 'on_hold', 'is_listed' => 1]);
        // And never as a second active one.
        $this->post('pairs/' . $pairId . '/donors', ['donorMrn' => '8852', 'status' => 'active']);
        $this->seeInDatabase('donors', ['mrn' => 8852, 'status' => 'on_hold']);
    }

    /** A donor entered for a pair joins it, and the pair's screen comes back. */
    public function testADonorEnteredForAPairJoinsIt(): void
    {
        [$pairId] = $this->pairWith('8820', '8821', 'The Donor');

        $this->post('donors/new?pair=8820', [
            'pair' => '8820', 'mrn' => '8822', 'name' => 'Entered For It',
            'age' => '33', 'bloodType' => 'A', 'donorStatus' => 'On Hold',
        ])->assertRedirectTo(site_url('pairs/' . $pairId) . '?donor=2');

        $this->seeInDatabase('pairs', ['recipient_mrn' => 8820, 'donor_mrn' => 8822, 'status' => 'on_hold']);
        // On the register from the start: there is nobody to keep off it now.
        $this->seeInDatabase('donors', ['mrn' => 8822, 'is_listed' => 1]);
        // The form does not offer the word the pair would refuse.
        $this->assertStringNotContainsString('value="Active"', $this->get('donors/new?pair=8820')->getBody());
    }

    /** Any of a pair's links opens the same pair, at that donor's tab. */
    public function testAnyLinkOpensTheSamePair(): void
    {
        [$pairId] = $this->pairWith('8860', '8861', 'The Donor');
        $second   = $this->addPairDonor($pairId, '8862', 'The Reserve');

        $html = $this->get('pairs/' . $second)->getBody();

        // The same pair, with both tabs on it, opened at the one asked for.
        $this->assertStringContainsString('2 donors', $html);
        $this->assertStringContainsString('The Reserve', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertMatchesRegularExpression('/aria-selected="true"\s+href="[^"]*\?donor=2"/', $html);
        // And the pair's own cards are the pair's, whichever link opened it.
        $this->assertStringContainsString('edit=pair', $html);
    }

    /** The links of one recipient's pair, oldest first. */
    private function pairLinks(int $recipientMrn): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->db->table('pairs')->where('recipient_mrn', $recipientMrn)->orderBy('id')->get()->getResultArray()
        );
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
        $none = $this->get('pairs?status=declined')->getBody();
        $this->assertStringContainsString('No pairs found.', $none);
        $this->assertStringNotContainsString('Show all pairs', $none);
    }

    /**
     * A pair that is over comes off the register altogether.
     *
     * Delinking the pair's donor, or taking the pair apart, puts the recipient
     * back on the waiting list and the donor back on theirs. The pair is not a
     * pair any more, so the Pairs List does not hold a greyed "Closed" line
     * saying there is one. What happened is on the recipient's own record.
     */
    public function testAClosedPairComesOffThePairsList(): void
    {
        [$pairId] = $this->pairWith('9310', '9311', 'The Donor');
        [$first]  = $this->pairLinks(9310);
        // A second pair, left alone, so the list has something to show when
        // the first one goes.
        $this->pairWith('9320', '9321', 'Still Going');

        $this->assertStringContainsString('The Donor', $this->get('pairs?status=all')->getBody());

        $this->post('pairs/' . $pairId . '/donors/' . $first . '/delink', ['outcome' => 'dissolve']);
        $this->seeInDatabase('pairs', ['id' => $pairId, 'status' => 'closed']);

        // Where it went: the recipient's own record keeps the donor.
        $this->assertStringContainsString('The Donor', $this->get('recipients/9310')->getBody());

        // Off every view of the list, All included, and off the sheet it
        // prints — and off the search that leads to it. Read by the donor's
        // name: the recipient's is in the notice the dissolve left, which is
        // the message and not the table.
        foreach (['pairs', 'pairs?status=all', 'pairs/print?status=all', 'pairs?status=all&q=9310'] as $screen) {
            $this->assertStringNotContainsString('The Donor', $this->get($screen)->getBody(), $screen . ' is clear of it');
        }

        // And the one still going is untouched by any of it.
        $this->assertStringContainsString('Still Going', $this->get('pairs?status=all')->getBody());

        // Closed is not one of the chips either.
        $html = $this->get('pairs')->getBody();
        $this->assertStringNotContainsString('status=closed', $html);
        $this->assertStringNotContainsString('>Closed</a>', $html);

        // And asking for it by address falls back to what the screen opens
        // on, which is the pairs there are rather than an empty table.
        $closed = $this->get('pairs?status=closed')->getBody();
        $this->assertStringContainsString('Still Going', $closed);
        $this->assertStringNotContainsString('The Donor', $closed);

        // It is not deleted, though: its own address still opens it.
        $this->assertStringContainsString('Pair Profile', $this->get('pairs/' . $pairId)->getBody());
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

        // On the card as it is read, not only once the pencil is pressed:
        // adding a test is not editing this one.
        $html = $this->get('recipients/4030')->getBody();

        $this->assertStringContainsString('Add lab', $html);
        $this->assertStringContainsString(site_url('recipients/4030') . '/labs', $html);
        // Under the heading and on its own: the button says what it does.
        $this->assertStringContainsString('<div class="lab-group lab-group--add" data-lab-group-head=', $html);
        $this->assertStringNotContainsString('No tests added.', $html);

        // And it answers to the page's own form, so Enter in a box on the card
        // cannot press it.
        $this->assertStringContainsString('class="btn-add-lab" form="lab-add"', $html);
        $this->assertStringContainsString('<form id="lab-add" method="post" hidden>', $html);
    }

    /**
     * Enter in one of the card's boxes saves the card; it used to add a test.
     *
     * Add lab was the first submit button the form had, which is what a
     * browser presses when a text box is answered with Enter — so a name typed
     * and confirmed added a test nobody asked for.
     */
    public function testEnterOnAnOpenCardDoesNotAddATest(): void
    {
        $this->post('recipients/new', ['mrn' => '4034', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);
        $this->post('recipients/4034/labs');

        $html = $this->get('recipients/4034?edit=labs')->getBody();

        // The card's own default button comes before Add lab in the form…
        $save = strpos($html, 'class="offscreen-submit"');
        $add  = strpos($html, 'class="btn-add-lab"');
        $this->assertIsInt($save);
        $this->assertIsInt($add);
        $this->assertLessThan($add, $save, 'the card has a default button of its own, first');
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

        // It starts on the three every test has in common, not on all
        // seventeen: what it answers is a question only its author can answer.
        $this->assertNull($lab['answer_set']);

        // Its name, what it answers and the answer itself are all typed on the
        // card and saved together.
        $this->post('recipients/4031', [
            'section'   => 'labs',
            'name'      => 'Ahmed Test',
            'age'       => '41',
            'bloodType' => 'O',
            'labs'      => [[
                'id'      => $lab['id'],
                'name'    => 'Ultrasound Doppler Hepatic Vein',
                'status'  => 'acceptable',
                'notes'   => 'Requested.',
                'answers' => [
                    'not_done'   => ['on' => '1'],
                    'acceptable' => ['on' => '1', 'tone' => 'tone-emerald'],
                    'abnormal'   => ['on' => '1', 'tone' => 'tone-red'],
                ],
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

        // The card offers the three it was given, and not the fourteen it was
        // not. Every one of the seventeen is still there to tick.
        foreach (['not_done', 'acceptable', 'abnormal'] as $status) {
            $this->assertStringContainsString('data-lab-status="' . $status . '"', $html);
        }

        // What it answers is stored on the test itself, and is the three it
        // was given rather than the seventeen it was offered.
        $stored = json_decode(
            (string) $this->db->table('labs')->where('id', $lab['id'])->get()->getRowArray()['answer_set'],
            true
        );
        $this->assertSame(['not_done', 'acceptable', 'abnormal'], array_column($stored, 'key'));
        $this->assertSame('tone-red', $stored[2]['tone']);

        // And every one of the seventeen is still there to tick.
        foreach (UiStore::RESULT_OPTIONS['custom'] as $status) {
            $this->assertStringContainsString('[answers][' . $status . '][on]', $html);
        }
    }

    /**
     * An answer the platform has no word for.
     *
     * The box is one field on the card: typing a name and saving is the whole
     * of adding an answer, because there is nothing to press before the thing
     * you are already saving.
     */
    public function testATestCanBeGivenAnAnswerNobodyDefined(): void
    {
        $this->post('recipients/new', ['mrn' => '4051', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);
        $this->post('recipients/4051/labs');
        $lab = $this->db->table('labs')->where('person_mrn', 4051)->get()->getRowArray();

        $this->post('recipients/4051', [
            'section' => 'labs',
            'labs'    => [[
                'id'            => $lab['id'],
                'name'          => 'Courier sample',
                'answers'       => ['not_done' => ['on' => '1']],
                'newAnswer'     => 'Awaiting courier',
                'newAnswerTone' => 'tone-amber',
            ]],
        ]);

        $stored = json_decode(
            (string) $this->db->table('labs')->where('id', $lab['id'])->get()->getRowArray()['answer_set'],
            true
        );

        $this->assertSame(['not_done', 'c_awaiting_courier'], array_column($stored, 'key'));
        $this->assertSame('Awaiting courier', $stored[1]['label']);
        $this->assertSame('tone-amber', $stored[1]['tone']);

        // It is an answer like any other: on the card, and recordable.
        $html = $this->get('recipients/4051?edit=labs')->getBody();
        $this->assertStringContainsString('data-lab-status="c_awaiting_courier"', $html);
        $this->assertStringContainsString('Awaiting courier', $html);

        $this->post('recipients/4051', [
            'section' => 'labs',
            'labs'    => [[
                'id'      => $lab['id'],
                'name'    => 'Courier sample',
                'status'  => 'c_awaiting_courier',
                'answers' => [
                    'not_done'           => ['on' => '1'],
                    'c_awaiting_courier' => ['on' => '1', 'label' => 'Awaiting courier', 'tone' => 'tone-amber'],
                ],
            ]],
        ]);

        $this->seeInDatabase('lab_results', ['lab_id' => $lab['id'], 'status' => 'c_awaiting_courier']);
    }

    /** Renaming it renames it everywhere; rubbing the name out removes it. */
    public function testAnAnswerOfTheirOwnIsRenamedAndRemovedByItsName(): void
    {
        $this->post('recipients/new', ['mrn' => '4061', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);
        $this->post('recipients/4061/labs');
        $lab = $this->db->table('labs')->where('person_mrn', 4061)->get()->getRowArray();

        $save = function (array $answers, string $add = '') use ($lab): void {
            $this->post('recipients/4061', [
                'section' => 'labs',
                'labs'    => [[
                    'id' => $lab['id'], 'name' => 'Courier sample',
                    'answers' => $answers, 'newAnswer' => $add,
                ]],
            ]);
        };

        $save(['not_done' => ['on' => '1']], 'Awaiting courier');

        // Renamed.
        $save([
            'not_done'           => ['on' => '1'],
            'c_awaiting_courier' => ['on' => '1', 'label' => 'With the courier', 'tone' => 'tone-amber'],
        ]);

        $stored = json_decode((string) $this->db->table('labs')->where('id', $lab['id'])->get()->getRowArray()['answer_set'], true);
        $this->assertSame('With the courier', $stored[1]['label']);

        // Rubbed out — the same gesture as removing it.
        $save([
            'not_done'           => ['on' => '1'],
            'c_awaiting_courier' => ['on' => '1', 'label' => '  ', 'tone' => 'tone-amber'],
        ]);

        $stored = json_decode((string) $this->db->table('labs')->where('id', $lab['id'])->get()->getRowArray()['answer_set'], true);
        $this->assertSame(['not_done'], array_column($stored, 'key'));
    }

    /**
     * Each test keeps its own answers, so two tests on one record do not share
     * a vocabulary — which is the whole of what "Other" means.
     */
    public function testTwoTestsOnOneRecordKeepSeparateAnswers(): void
    {
        $this->post('recipients/new', ['mrn' => '4071', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);
        $this->post('recipients/4071/labs');
        $this->post('recipients/4071/labs');

        $labs = $this->db->table('labs')->where('person_mrn', 4071)->orderBy('id')->get()->getResultArray();
        $this->assertCount(2, $labs);

        $this->post('recipients/4071', [
            'section' => 'labs',
            'labs'    => [
                ['id' => $labs[0]['id'], 'name' => 'First', 'answers' => ['seen' => ['on' => '1'], 'not_seen' => ['on' => '1']]],
                ['id' => $labs[1]['id'], 'name' => 'Second', 'answers' => ['given' => ['on' => '1']]],
            ],
        ]);

        $rows = $this->db->table('labs')->where('person_mrn', 4071)->orderBy('id')->get()->getResultArray();
        $this->assertSame(['seen', 'not_seen'], array_column(json_decode((string) $rows[0]['answer_set'], true), 'key'));
        $this->assertSame(['given'], array_column(json_decode((string) $rows[1]['answer_set'], true), 'key'));
    }

    /**
     * After a save the card shows the answers it was given and no others; the
     * picker that chose them is only there while the card is being edited.
     */
    public function testASavedTestShowsOnlyTheAnswersItWasGiven(): void
    {
        $this->post('recipients/new', ['mrn' => '4081', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);
        $this->post('recipients/4081/labs');
        $lab = $this->db->table('labs')->where('person_mrn', 4081)->get()->getRowArray();

        $this->post('recipients/4081', [
            'section' => 'labs',
            'labs'    => [[
                'id' => $lab['id'], 'name' => 'Courier sample',
                // Green is one of the five on offer; teal is a shade the
                // palette used to have, and reads as the one it is nearest to.
                'answers' => [
                    'seen'     => ['on' => '1', 'tone' => 'tone-emerald'],
                    'not_seen' => ['on' => '1', 'tone' => 'tone-teal'],
                ],
            ]],
        ]);

        // Read-only: the answers, in their colours, and no picker.
        $view = $this->get('recipients/4081')->getBody();
        $this->assertStringContainsString('data-lab-status="seen"', $view);
        $this->assertStringContainsString('data-lab-tone="tone-emerald"', $view);
        $this->assertStringContainsString('data-lab-status="not_seen" data-lab-tone="tone-emerald"', $view);
        $this->assertStringNotContainsString('What this test answers', $view);
        // Every answer wears the colour it was given, not only the one
        // recorded: being able to tell them apart is why they were chosen.
        $this->assertStringContainsString('lab-status-btn lab-status-btn--tinted tone-emerald', $view);
        // And the saved test carries its own way back into the list.
        $this->assertStringContainsString('recipients/4081?edit=labs#lab-' . $lab['id'] . '"', $view);
        $this->assertStringContainsString('Edit results', $view);

        // Editing brings the picker back with the choices still on it.
        $edit = $this->get('recipients/4081?edit=labs')->getBody();
        $this->assertStringContainsString('What this test answers', $edit);
        // The index is wherever the custom test falls in the workup, which is
        // after everything the check list asks for.
        $this->assertStringContainsString('[answers][seen][on]" value="1" checked', $edit);
        $this->assertStringContainsString('value="tone-emerald" checked', $edit);
        // Five colours, by their own names, and a way to take one back off.
        foreach (['No colour', 'Red', 'Yellow', 'Green', 'Blue', 'Gray'] as $named) {
            $this->assertStringContainsString('<span class="tone-name">' . $named . '</span>', $edit);
        }
        $this->assertStringNotContainsString('tone-teal-soft', $edit);
        // The anchor the Edit link aims at is on the card it names.
        $this->assertStringContainsString('id="lab-' . $lab['id'] . '"', $edit);
    }

    /**
     * An answer carries no colour until somebody gives it one.
     *
     * A colour on a medical record means something, so it is said on purpose:
     * the card shows the words plain, and only the answers that were coloured
     * come out coloured.
     */
    public function testAnAnswerIsPlainUntilAColourIsChosen(): void
    {
        $this->post('recipients/new', ['mrn' => '4097', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);
        $this->post('recipients/4097/labs');
        $lab = $this->db->table('labs')->where('person_mrn', 4097)->get()->getRowArray();

        $this->post('recipients/4097', [
            'section' => 'labs',
            'labs'    => [[
                'id' => $lab['id'], 'name' => 'Courier sample',
                'answers' => [
                    'pending'  => ['on' => '1', 'tone' => 'tone-amber'],
                    'done'     => ['on' => '1'],
                    'not_done' => ['on' => '1', 'tone' => 'not-a-colour'],
                ],
            ]],
        ]);

        $stored = json_decode(
            (string) $this->db->table('labs')->where('id', $lab['id'])->get()->getRowArray()['answer_set'],
            true
        );
        $tones = array_column($stored, 'tone', 'key');

        // Only the one that was picked, and a colour nobody has is stored as
        // nothing rather than as the platform's own guess.
        $this->assertSame('tone-amber', $tones['pending']);
        $this->assertSame('', $tones['done']);
        $this->assertSame('', $tones['not_done']);

        $html = $this->get('recipients/4097')->getBody();
        $this->assertStringContainsString('data-lab-status="pending" data-lab-tone="tone-amber"', $html);
        $this->assertStringContainsString('data-lab-status="done" data-lab-tone=""', $html);
        // The coloured one is tinted on the card; the plain ones are not.
        $this->assertStringContainsString('lab-status-btn--tinted tone-amber', $html);
    }

    /**
     * The answer a test was given colours the card it is on.
     *
     * A workup is seventy cards read by running down it, so the colour is on
     * the card and not only on its pill — except the neutral one, which every
     * card would be wearing and which would therefore say nothing.
     */
    public function testTheAnswerColoursTheWholeCard(): void
    {
        $this->post('recipients/new', ['mrn' => '4240', 'name' => 'R', 'age' => '40', 'bloodType' => 'A']);

        $lab = $this->db->table('labs')
            ->where('name', 'Cross match')->where('person_type', 'recipient')
            ->get()->getRowArray();

        // The blood group card already wears the record's own group, so this
        // counts the change rather than assuming the sheet starts colourless.
        $before = substr_count($this->get('recipients/4240')->getBody(), 'lab-card--toned');

        // Positive is the one somebody has to act on, and the card says so.
        $this->post('recipients/4240', [
            'section' => 'labs',
            'labs'    => [['id' => $lab['id'], 'name' => 'Cross match', 'status' => 'positive']],
        ]);
        $html = $this->get('recipients/4240')->getBody();
        $this->assertSame($before + 1, substr_count($html, 'lab-card--toned'));
        $this->assertStringContainsString('lab-card--toned lab-card--red', $html);

        // And the check list's own answers wear the sheet's colours again,
        // every one of them rather than only the one recorded.
        $this->assertStringContainsString('data-lab-status="negative" data-lab-tone="tone-emerald"', $html);
        $this->assertStringContainsString('lab-status-btn--tinted tone-emerald', $html);

        // Not done is the resting state, so it colours nothing: the card gives
        // its colour back.
        $this->post('recipients/4240', [
            'section' => 'labs',
            'labs'    => [['id' => $lab['id'], 'name' => 'Cross match', 'status' => 'not_done']],
        ]);
        $after = $this->get('recipients/4240')->getBody();
        $this->assertSame($before, substr_count($after, 'lab-card--toned'));
        $this->assertStringNotContainsString('lab-card--red', $after);
    }

    /** A date-shaped thing that is not a date is refused, not a stack trace. */
    public function testADateTheCalendarHasNoDayForIsNotADate(): void
    {
        $this->assertSame('', UiStore::dmyToIso('0101-90-19'));
        $this->assertSame('', UiStore::dmyToIso('2026-02-30'));
        $this->assertSame('2026-02-28', UiStore::dmyToIso('2026-02-28'));
        $this->assertSame(0, UiStore::ageFrom('0101-90-19'));

        // And a form that is sent one keeps going rather than falling over:
        // a browser whose date field was filled out of order sends exactly
        // this, and a 500 would lose everything else typed on the screen.
        $this->post('recipients/new', [
            'mrn' => '4096', 'name' => 'Ahmed Test', 'birthDate' => '0101-90-19', 'bloodType' => 'O',
        ]);
        $this->assertNull($this->db->table('recipients')->where('mrn', 4096)->get()->getRowArray());
        $this->assertStringContainsString(
            'the calendar has no such day',
            $this->get('recipients/new')->getBody()
        );
    }

    /** The catalogue's own tests are untouched: their answers are the sheet's. */
    public function testACatalogueTestHasNoAnswerPicker(): void
    {
        $this->post('recipients/new', ['mrn' => '4091', 'name' => 'Ahmed Test', 'age' => '41', 'bloodType' => 'O']);

        $html = $this->get('recipients/4091?edit=labs')->getBody();

        $this->assertStringContainsString('Blood group', $html);
        $this->assertStringNotContainsString('What this test answers', $html);
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

        // It asks first, in a dialog under the card rather than on a screen
        // of its own.
        $this->assertStringContainsString('This cannot be undone.', $this->get('recipients/4034')->getBody());
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

    // ---- A donor's own facts ----------------------------------------------

    /**
     * Related to whom? A donor nobody is paired with is Living or Deceased.
     *
     * Living Related and Living Unrelated are statements about a donor *and a
     * recipient*. With no recipient they have nothing to be true of, so the
     * record does not offer them until the donor is linked — on Add Donor,
     * which never had a recipient, and now on a saved record that has none
     * either.
     */
    public function testAnUnlinkedDonorIsOnlyOfferedTheTwoRegisterTypes(): void
    {
        $this->post('donors/new', ['mrn' => '7301', 'name' => 'Free Donor', 'age' => '33', 'bloodType' => 'B']);

        $html = $this->get('donors/7301?edit=personal')->getBody();

        $this->assertStringContainsString('<option value="living"', $html);
        $this->assertStringContainsString('<option value="deceased"', $html);
        $this->assertStringNotContainsString('<option value="living_related"', $html);
        $this->assertStringNotContainsString('<option value="living_unrelated"', $html);
    }

    /** Linked, the question has a recipient to be about, so all four are. */
    public function testALinkedDonorIsOfferedEveryType(): void
    {
        $this->pairWith('7302', '7303', 'Linked Donor');

        $html = $this->get('donors/7303?edit=personal')->getBody();

        foreach (['living', 'living_related', 'living_unrelated', 'deceased'] as $type) {
            $this->assertStringContainsString('<option value="' . $type . '"', $html);
        }
    }

    /**
     * A donor archived off a pair keeps the type they were given.
     *
     * They are nobody's now, so the list is the two — but the answer the
     * record holds stays on it, or saving another card would quietly rewrite
     * what happened to them.
     */
    public function testATypeTheRecordHoldsStaysOnTheListWhenTheDonorIsFree(): void
    {
        $this->post('donors/new', ['mrn' => '7304', 'name' => 'Was Related', 'age' => '33', 'bloodType' => 'B']);
        $this->db->table('donors')->where('mrn', 7304)->update(['donation_type' => 'living_related']);

        $html = $this->get('donors/7304?edit=personal')->getBody();

        $this->assertStringContainsString('<option value="living_related" selected>', $html);
        // Still not the other one: only what the record actually says.
        $this->assertStringNotContainsString('<option value="living_unrelated"', $html);
    }

    /**
     * The entry date is the register's bookkeeping, not one of the donor's
     * details, so no donor screen shows it any more.
     */
    public function testNoDonorScreenAsksForAnEntryDate(): void
    {
        $this->post('donors/new', ['mrn' => '7305', 'name' => 'Dated Donor', 'age' => '33', 'bloodType' => 'B']);
        [$pairId] = $this->pairWith('7306', '7307', 'Paired Donor');

        foreach (['donors/new', 'donors/7305?edit=personal'] as $screen) {
            $html = $this->get($screen)->getBody();

            $this->assertStringNotContainsString('name="dateRegistered"', $html, $screen);
            $this->assertStringNotContainsString('>Entry Date<', $html, $screen);
        }

        // On the pair, the one left is the recipient's: the donor tab asks
        // for nothing of the kind.
        $pair = $this->get('pairs/' . $pairId . '?edit=dpersonal')->getBody();

        $this->assertStringNotContainsString('name="dEntryDate"', $pair);
        $this->assertStringNotContainsString('f-d-entry', $pair);
        $this->assertSame(1, substr_count($pair, '>Entry Date<'), "the recipient's, and only theirs");

        // The recipient's own is untouched: theirs is a fact about the wait.
        $this->assertStringContainsString('name="dateRegistered"', $this->get('recipients/new')->getBody());
    }

    /** And saving a donor's card leaves the date the register gave them. */
    public function testSavingADonorLeavesTheDateTheRegisterGaveThem(): void
    {
        $this->post('donors/new', ['mrn' => '7308', 'name' => 'Dated Donor', 'age' => '33', 'bloodType' => 'B']);
        $this->db->table('donors')->where('mrn', 7308)->update(['registered_on' => '2024-03-04']);

        $this->post('donors/7308', [
            'section' => 'personal', 'name' => 'Dated Donor', 'bloodType' => 'B',
            'donationType' => 'living', 'donorStatus' => 'On Hold',
        ]);

        $this->seeInDatabase('donors', ['mrn' => 7308, 'registered_on' => '2024-03-04']);
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
