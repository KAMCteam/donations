<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Models\MrpModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Add MRP: the one screen that makes users.
 *
 * It registers two kinds now — the responsible physician and the coordinator —
 * and the register under it says which, and whether they are still in service.
 * The hospital directory the ID is searched against is not connected yet, so
 * what is tested of the search is that it is a real control that comes back
 * saying so, rather than a button that does nothing.
 *
 * MySQL/MariaDB only, same as the other database tests.
 *
 * @internal
 */
final class MrpRegisterTest extends CIUnitTestCase
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

        $this->withSession(['auth_id' => 1, 'auth_login_id' => '1', 'auth_name' => 'Test User', 'auth_role' => 'doctor', 'auth_is_admin' => true, 'ui_organ' => 'kidney']);
    }

    /** @param array<string, mixed>|null $params */
    public function post($path, ?array $params = null): \CodeIgniter\Test\TestResponse
    {
        $security = service('security');

        return $this->carrySession(
            $this->call('post', $path, ($params ?? []) + [$security->getTokenName() => $security->getHash()])
        );
    }

    /** @param array<string, mixed>|null $params */
    public function get($path, ?array $params = null): \CodeIgniter\Test\TestResponse
    {
        return $this->carrySession($this->call('get', $path, $params));
    }

    /**
     * Carries the session into the next request, the way a cookie does.
     *
     * The feature-test client copies the seeded array over `$_SESSION` before
     * every call, so a flash message written by one request is gone by the
     * next — which no browser does, and this screen answers in flash messages.
     */
    private function carrySession(\CodeIgniter\Test\TestResponse $response): \CodeIgniter\Test\TestResponse
    {
        if (isset($_SESSION) && is_array($_SESSION)) {
            $this->withSession($_SESSION);
        }

        return $response;
    }

    // ---- Adding a user -----------------------------------------------------

    public function testTheScreenOffersBothKindsAndASearch(): void
    {
        $html = $this->get('admin')->getBody();

        $this->assertStringContainsString('name="kind" value="doctor"', $html);
        $this->assertStringContainsString('name="kind" value="coordinator"', $html);
        // One form, two actions: Search goes to the directory, Add registers.
        $this->assertStringContainsString('formaction="' . site_url('admin/users/lookup') . '"', $html);
        $this->assertStringContainsString('Search', $html);
    }

    public function testADoctorAndACoordinatorAreBothRegistered(): void
    {
        $this->post('admin/users', ['id' => 'MRP-100', 'name' => 'Dr. Amira Hassan', 'kind' => 'doctor']);
        $this->post('admin/users', ['id' => 'CO-200', 'name' => 'Nora Al-Zahrani', 'kind' => 'coordinator']);

        $this->seeInDatabase('mrp', ['code' => 'MRP-100', 'name' => 'Dr. Amira Hassan', 'kind' => 'doctor']);
        $this->seeInDatabase('mrp', ['code' => 'CO-200', 'name' => 'Nora Al-Zahrani', 'kind' => 'coordinator']);

        // A coordinator is also what the record screens point at, so they get
        // a row there too — otherwise nobody could be assigned to them.
        $this->seeInDatabase('coordinators', ['name' => 'Nora Al-Zahrani']);
    }

    /** A record's MRP field asks for the physician, so only physicians are offered. */
    public function testOnlyDoctorsAreOfferedAsARecordsMrp(): void
    {
        $this->post('admin/users', ['id' => 'MRP-101', 'name' => 'Dr. Offered', 'kind' => 'doctor']);
        $this->post('admin/users', ['id' => 'CO-201', 'name' => 'Not Offered', 'kind' => 'coordinator']);

        $html = $this->get('recipients/new')->getBody();

        // The MRP control, not the page: a coordinator is on the screen too,
        // in the Coordinator select beside it, which is where they belong.
        $picker = substr($html, (int) strpos($html, 'id="f-mrp"'));
        $picker = substr($picker, 0, (int) strpos($picker, '</select>'));

        $this->assertStringContainsString('Dr. Offered', $picker);
        $this->assertStringNotContainsString('Not Offered', $picker);
        $this->assertStringContainsString('Not Offered', $html);
    }

    public function testAnIdCannotBeRegisteredTwice(): void
    {
        $this->post('admin/users', ['id' => 'MRP-102', 'name' => 'First', 'kind' => 'doctor']);
        $this->post('admin/users', ['id' => 'MRP-102', 'name' => 'Second', 'kind' => 'doctor']);

        $this->assertSame(1, $this->db->table('mrp')->where('code', 'MRP-102')->countAllResults());
        $this->assertStringContainsString('already registered', (string) session('ui_mrp_error'));
    }

    public function testAUserNeedsBothAnIdAndAName(): void
    {
        $this->post('admin/users', ['id' => 'MRP-103', 'name' => '', 'kind' => 'doctor']);

        $this->dontSeeInDatabase('mrp', ['code' => 'MRP-103']);
        $this->assertStringContainsString('both an ID and a name', (string) session('ui_mrp_error'));
    }

    // ---- The directory the IDs come from -----------------------------------

    /**
     * The search is a real control with nothing behind it yet, which is what
     * it says: the ID is carried back so the user can be entered by hand
     * meanwhile, and no password is kept here at all.
     */
    public function testTheDirectorySearchSaysItIsNotConnectedYet(): void
    {
        $this->post('admin/users/lookup', ['id' => 'MRP-104', 'kind' => 'coordinator']);

        $html = $this->get('admin')->getBody();

        $this->assertStringContainsString('Directory lookup', $html);
        $this->assertStringContainsString('not connected yet', $html);
        // The ID searched for comes back in the box, and the kind with it.
        $this->assertStringContainsString('value="MRP-104"', $html);
        $this->assertStringContainsString('name="kind" value="coordinator" checked', $html);
        // No credential field anywhere: sign-in is the directory's business.
        $this->assertStringNotContainsString('type="password"', $html);
    }

    public function testSearchingWithNoIdSaysSo(): void
    {
        $this->post('admin/users/lookup', ['id' => '']);

        $this->assertStringContainsString('Enter the ID to search for.', $this->get('admin')->getBody());
    }

    // ---- The register ------------------------------------------------------

    public function testTheRegisterShowsNameIdTypeAndStatus(): void
    {
        $this->post('admin/users', ['id' => 'MRP-105', 'name' => 'Dr. Listed', 'kind' => 'doctor']);
        $this->post('admin/users', ['id' => 'CO-205', 'name' => 'Coordinator Listed', 'kind' => 'coordinator']);

        $html = $this->get('admin')->getBody();

        foreach (['Name', 'MRP ID', 'Type', 'Status'] as $heading) {
            $this->assertStringContainsString('<th>' . $heading . '</th>', $html);
        }

        $this->assertStringContainsString('Dr. Listed', $html);
        $this->assertStringContainsString('MRP-105', $html);
        $this->assertStringContainsString('>Doctor</span>', $html);
        $this->assertStringContainsString('>Coordinator</span>', $html);
        $this->assertStringContainsString('>Active</span>', $html);
        $this->assertStringContainsString('Deactivate', $html);
        $this->assertStringContainsString('Edit', $html);
    }

    public function testARegisteredUserCanBeEdited(): void
    {
        $this->post('admin/users', ['id' => 'MRP-106', 'name' => 'Dr. Typo', 'kind' => 'doctor']);
        $id = (int) $this->db->table('mrp')->where('code', 'MRP-106')->get()->getRowArray()['id'];

        // Edit opens the row as a form, where the row is.
        $open = $this->get('admin?edit=' . $id)->getBody();
        $this->assertStringContainsString('action="' . site_url('admin/users/' . $id) . '"', $open);
        $this->assertStringContainsString('value="Dr. Typo"', $open);

        $this->post('admin/users/' . $id, ['id' => 'MRP-107', 'name' => 'Dr. Corrected', 'kind' => 'coordinator']);

        $this->seeInDatabase('mrp', ['id' => $id, 'code' => 'MRP-107', 'name' => 'Dr. Corrected', 'kind' => 'coordinator']);
        // Changed to a coordinator, so they are one where records look.
        $this->seeInDatabase('coordinators', ['name' => 'Dr. Corrected']);
    }

    public function testAnEditCannotTakeAnIdSomebodyElseHolds(): void
    {
        $this->post('admin/users', ['id' => 'MRP-108', 'name' => 'One', 'kind' => 'doctor']);
        $this->post('admin/users', ['id' => 'MRP-109', 'name' => 'Two', 'kind' => 'doctor']);
        $id = (int) $this->db->table('mrp')->where('code', 'MRP-109')->get()->getRowArray()['id'];

        $this->post('admin/users/' . $id, ['id' => 'MRP-108', 'name' => 'Two', 'kind' => 'doctor']);

        $this->seeInDatabase('mrp', ['id' => $id, 'code' => 'MRP-109']);
        $this->assertStringContainsString('already registered', (string) session('ui_mrp_error'));
    }

    /**
     * Deactivating is never deleting: the records they are on still name them,
     * and a physician who has left is part of what those records say.
     */
    public function testDeactivatingTakesAUserOffTheChoicesAndNotOffTheList(): void
    {
        $this->post('admin/users', ['id' => 'MRP-110', 'name' => 'Dr. Retiring', 'kind' => 'doctor']);
        $id = (int) $this->db->table('mrp')->where('code', 'MRP-110')->get()->getRowArray()['id'];

        $this->post('admin/users/' . $id . '/active', ['active' => '0']);

        $this->seeInDatabase('mrp', ['id' => $id, 'is_active' => 0]);

        $html = $this->get('admin')->getBody();
        $this->assertStringContainsString('Dr. Retiring', $html);
        $this->assertStringContainsString('>Deactivated</span>', $html);
        $this->assertStringContainsString('Reactivate', $html);

        // And gone from where a record would assign them.
        $this->assertStringNotContainsString('Dr. Retiring', $this->get('recipients/new')->getBody());

        $this->post('admin/users/' . $id . '/active', ['active' => '1']);
        $this->seeInDatabase('mrp', ['id' => $id, 'is_active' => 1]);
    }

    /** The kind a record was given before there were two is still a physician. */
    public function testEverybodyRegisteredBeforeIsADoctor(): void
    {
        $this->db->table('mrp')->insert(['code' => 'MRP-111', 'name' => 'From Before']);

        $row = $this->db->table('mrp')->where('code', 'MRP-111')->get()->getRowArray();

        $this->assertSame(MrpModel::DOCTOR, $row['kind']);
    }
}
