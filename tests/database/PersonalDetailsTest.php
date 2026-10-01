<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\UiStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * The personal details, and the five rules they now keep.
 *
 * Dates in the past only; the age worked out from a date of birth rather than
 * typed; an entry date that starts at today and can be corrected; three
 * statuses that no longer follow one another; and a kind of dialysis, with the
 * one answer that means there is no first dialysis date to give.
 *
 * Each of these is a rule the screens enforce and the server has to enforce
 * again, because the screens' half of it is JavaScript and an attribute on a
 * picker — neither of which is on the server.
 *
 * MySQL/MariaDB only, same as the other database tests.
 *
 * @internal
 */
final class PersonalDetailsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db->DBDriver !== 'MySQLi') {
            $this->markTestSkipped('This schema is MySQL-specific; the tests group uses ' . $this->db->DBDriver . '.');
        }

        $this->withSession(['ui_signed_in' => true, 'ui_organ' => 'kidney']);
    }

    /** @param array<string, mixed>|null $params */
    public function post($path, ?array $params = null): \CodeIgniter\Test\TestResponse
    {
        $security = service('security');
        $params   = ($params ?? []) + [$security->getTokenName() => $security->getHash()];

        return $this->carrySession($this->call('post', $path, $params));
    }

    /** @param array<string, mixed>|null $params */
    public function get($path, ?array $params = null): \CodeIgniter\Test\TestResponse
    {
        return $this->carrySession($this->call('get', $path, $params));
    }

    private function carrySession(\CodeIgniter\Test\TestResponse $response): \CodeIgniter\Test\TestResponse
    {
        if (isset($_SESSION) && is_array($_SESSION)) {
            $this->withSession($_SESSION);
        }

        return $response;
    }

    private function tomorrow(): string
    {
        return date('d/m/Y', strtotime('+1 day'));
    }

    // ---- 1. Nothing the personal details ask for has happened yet ----------

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function futureDateProvider(): array
    {
        return [
            'date of birth'  => ['birthDate', 'recipients/new'],
            'first dialysis' => ['firstDialysis', 'recipients/new'],
            'entry date'     => ['dateRegistered', 'recipients/new'],
            'donor birth'    => ['birthDate', 'donors/new'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('futureDateProvider')]
    public function testADateInTheFutureIsRefused(string $field, string $path): void
    {
        $mrn = (string) random_int(60000, 69999);

        $this->post($path, [
            'mrn' => $mrn, 'name' => 'Future Test', 'bloodType' => 'O',
            $field => $this->tomorrow(),
        ]);

        $this->assertSame('A date cannot be in the future.', session()->getFlashdata('ui_error'));
        $this->dontSeeInDatabase(str_starts_with($path, 'recipients') ? 'recipients' : 'donors', ['mrn' => $mrn]);
    }

    /** Today itself is not the future, and is the commonest entry date there is. */
    public function testTodayIsAccepted(): void
    {
        $this->post('recipients/new', [
            'mrn' => '6100', 'name' => 'Today Test', 'bloodType' => 'O',
            'dateRegistered' => date('d/m/Y'),
        ]);

        $this->seeInDatabase('recipients', ['mrn' => 6100, 'entry_date' => date('Y-m-d')]);
    }

    // ---- 2. The age is the date of birth read out --------------------------

    public function testTheAgeIsWorkedOutFromTheDateOfBirth(): void
    {
        $born = date('d/m/Y', strtotime('-40 years -2 days'));

        $this->post('recipients/new', [
            'mrn' => '6200', 'name' => 'Born Test', 'bloodType' => 'O',
            // What the hidden field carries is what a record without a date
            // keeps; a date beside it overrules it.
            'age' => '7', 'birthDate' => $born,
        ]);

        $this->seeInDatabase('recipients', [
            'mrn'        => 6200,
            'age'        => 40,
            'birth_date' => date('Y-m-d', strtotime('-40 years -2 days')),
        ]);
    }

    /** A birthday that has not come round yet is a year not yet turned. */
    public function testTheAgeCountsBirthdaysRatherThanYears(): void
    {
        $this->assertSame(40, UiStore::ageFrom(date('d/m/Y', strtotime('-40 years -1 day'))));
        $this->assertSame(39, UiStore::ageFrom(date('d/m/Y', strtotime('-40 years +1 day'))));
        $this->assertSame(0, UiStore::ageFrom(''));
    }

    /** The records entered before birth dates were collected keep their number. */
    public function testARecordWithNoBirthDateKeepsTheAgeItWasGiven(): void
    {
        $this->post('donors/new', [
            'mrn' => '6300', 'name' => 'Old Record', 'bloodType' => 'A', 'age' => '52',
        ]);

        $this->seeInDatabase('donors', ['mrn' => 6300, 'age' => 52, 'birth_date' => null]);
    }

    /** The form asks for the date, and shows the age it comes to beside it. */
    public function testTheFormAsksForTheDateAndShowsTheAge(): void
    {
        $born = date('d/m/Y', strtotime('-33 years -1 day'));

        $this->post('recipients/new', ['mrn' => '6400', 'name' => 'Shown', 'bloodType' => 'O', 'birthDate' => $born]);

        $html = $this->get('recipients/6400?edit=personal')->getBody();

        $this->assertStringContainsString('name="birthDate"', $html);
        $this->assertStringContainsString('Age 33', $html);
        // No box asking for the number itself any more.
        $this->assertStringNotContainsString('<input type="number" id="f-age"', $html);
    }

    // ---- 3. The entry date starts at today, and can be corrected -----------

    public function testANewRecordOpensOnTodayAndTheDateCanBeChanged(): void
    {
        $html = $this->get('recipients/new')->getBody();

        $this->assertStringContainsString('value="' . date('d/m/Y') . '"', $html);
        $this->assertStringContainsString('name="dateRegistered"', $html);

        $this->post('recipients/new', [
            'mrn' => '6500', 'name' => 'Backdated', 'bloodType' => 'O',
            'dateRegistered' => '04/03/2024',
        ]);

        $this->seeInDatabase('recipients', ['mrn' => 6500, 'entry_date' => '2024-03-04']);
    }

    /**
     * And nothing else moves it. Editing any other card used to re-date the
     * record to the day of the edit, because the row builder defaulted the
     * column whether the card had asked for it or not.
     */
    public function testEditingAnotherCardLeavesTheEntryDateAlone(): void
    {
        $this->post('recipients/new', [
            'mrn' => '6600', 'name' => 'Steady', 'bloodType' => 'O',
            'dateRegistered' => '04/03/2024',
        ]);

        $this->post('recipients/6600', ['section' => 'notes', 'notes' => 'a note']);

        $this->seeInDatabase('recipients', ['mrn' => 6600, 'entry_date' => '2024-03-04', 'notes' => 'a note']);
    }

    // ---- 4. Three statuses, none of them following another -----------------

    public function testThePersonsStatusAndThePairsAreSeparateFacts(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '6700', 'rName' => 'R', 'rBloodType' => 'O', 'rStatus' => 'active',
            'dMrn' => '6701', 'dName' => 'D', 'dBloodType' => 'O', 'dStatus' => 'Active',
            'pairStatus' => 'active', 'relationship' => 'Brother',
        ]);

        $pair = $this->db->table('pairs')->where('recipient_mrn', 6700)->get()->getRowArray();
        $this->assertNotNull($pair);

        // Close the pair: neither person is closed by it.
        $this->post('pairs/' . $pair['id'], [
            'section' => 'pair', 'pairStatus' => 'closed', 'closedReason' => 'done',
        ]);

        $this->seeInDatabase('pairs', ['id' => $pair['id'], 'status' => 'closed']);
        $this->seeInDatabase('recipients', ['mrn' => 6700, 'status' => 'active']);
        $this->seeInDatabase('donors', ['mrn' => 6701, 'status' => 'active']);
    }

    /** And setting a person's own status leaves the pair where it was. */
    public function testAPersonsStatusDoesNotReachTheirPair(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '6800', 'rName' => 'R', 'rBloodType' => 'O',
            'dMrn' => '6801', 'dName' => 'D', 'dBloodType' => 'O', 'dStatus' => 'Active',
            'pairStatus' => 'active',
        ]);

        $this->post('recipients/6800', ['section' => 'personal', 'status' => 'declined', 'bloodType' => 'O']);

        $this->seeInDatabase('recipients', ['mrn' => 6800, 'status' => 'declined']);
        $this->seeInDatabase('pairs', ['recipient_mrn' => 6800, 'status' => 'active']);
        $this->seeInDatabase('donors', ['mrn' => 6801, 'status' => 'active']);
    }

    /** The screens no longer wire the two selects together either. */
    public function testTheAddPairFormNoLongerTiesTheTwoStatusesTogether(): void
    {
        $html = $this->get('pairs/new')->getBody();

        $this->assertStringContainsString('name="pairStatus"', $html);
        $this->assertStringContainsString('name="rStatus"', $html);
        $this->assertStringNotContainsString('data-pair-status', $html);
        $this->assertStringNotContainsString('data-person-status', $html);
    }

    // ---- 5. Which kind of dialysis, and the one that has no date -----------

    public function testTheKindOfDialysisIsStoredAndOfferedBeforeTheDate(): void
    {
        $html = $this->get('recipients/new')->getBody();

        foreach (UiStore::DIALYSIS_TYPES as $value => $label) {
            $this->assertStringContainsString('value="' . $value . '"', $html);
            $this->assertStringContainsString($label, $html);
        }

        // Asked before the date it decides the existence of.
        $this->assertLessThan(
            strpos($html, 'name="firstDialysis"'),
            strpos($html, 'name="dialysisType"'),
            'Type Dialysis comes before First Dialysis'
        );

        $this->post('recipients/new', [
            'mrn' => '6900', 'name' => 'Hemo', 'bloodType' => 'O',
            'dialysisType' => 'hemo', 'firstDialysis' => '01/03/2024',
        ]);

        $this->seeInDatabase('recipients', [
            'mrn' => 6900, 'dialysis_type' => 'hemo', 'dialysis_start' => '2024-03-01',
        ]);
    }

    /**
     * Pre-emptive means a transplant before dialysis ever starts, so there is
     * no first dialysis — and a date sent with it is cleared rather than kept.
     */
    public function testPreemptiveLeavesNoFirstDialysisDate(): void
    {
        $this->post('recipients/new', [
            'mrn' => '6901', 'name' => 'Preemptive', 'bloodType' => 'O',
            'dialysisType' => 'preemptive', 'firstDialysis' => '01/03/2024',
        ]);

        $this->seeInDatabase('recipients', [
            'mrn' => 6901, 'dialysis_type' => 'preemptive', 'dialysis_start' => null,
        ]);

        // And the screen closes the field rather than waiting for a date.
        $html = $this->get('recipients/6901?edit=personal')->getBody();
        $this->assertStringContainsString('date-field is-closed', $html);
    }

    /** Changing to pre-emptive clears a date that is already there. */
    public function testMovingToPreemptiveClearsTheDateAlreadyStored(): void
    {
        $this->post('recipients/new', [
            'mrn' => '6902', 'name' => 'Was Hemo', 'bloodType' => 'O',
            'dialysisType' => 'hemo', 'firstDialysis' => '01/03/2024',
        ]);

        $this->post('recipients/6902', [
            'section' => 'personal', 'bloodType' => 'O',
            'dialysisType' => 'preemptive', 'firstDialysis' => '01/03/2024',
        ]);

        $this->seeInDatabase('recipients', ['mrn' => 6902, 'dialysis_start' => null]);
    }

    /** It reaches the Pairs List as a column of its own, and the report too. */
    public function testTheKindOfDialysisReachesTheListsThatShowIt(): void
    {
        $this->post('pairs/new', [
            'rMrn' => '6903', 'rName' => 'Listed', 'rBloodType' => 'O',
            'dMrn' => '6904', 'dName' => 'D', 'dBloodType' => 'O',
            'pairStatus' => 'active', 'rDialysisType' => 'peritoneal',
        ]);

        $pairs = $this->get('pairs')->getBody();
        $this->assertStringContainsString('<th>Type Dialysis</th>', $pairs);
        $this->assertStringContainsString('Peritoneal dialysis', $pairs);

        // The Reports column that had nothing behind it now has.
        $this->assertStringContainsString('Peritoneal dialysis', $this->get('reports')->getBody());
    }
}
