<?php

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * The login screen, now that it decides something.
 *
 * It used to let anybody through: two non-empty boxes and you were in. These
 * are the rules that replaced that, written down so they cannot quietly go
 * back — above all the ones that are easy to undo by being helpful. A message
 * that says *which* of the two was wrong, an inactive account announced before
 * the password is checked, a password echoed back into the form: each of those
 * is a kindness that hands something to whoever is guessing.
 *
 * MySQL/MariaDB only, like the rest of the database group.
 *
 * @internal
 */
final class AuthTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private UserModel $users;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db->DBDriver !== 'MySQLi') {
            $this->markTestSkipped('This schema is MySQL-specific; the tests group uses ' . $this->db->DBDriver . '.');
        }

        $this->users = model(UserModel::class);
    }

    /** One account, made the way the seeder makes one. */
    private function account(string $loginId, string $role, string $password = 'A', bool $active = true, bool $admin = false): int|string
    {
        return $this->users->store([
            'login_id'  => $loginId,
            'name'      => ucfirst($role) . ' ' . $loginId,
            'role'      => $role,
            'is_admin'  => $admin ? 1 : 0,
            'is_active' => $active ? 1 : 0,
        ], $password);
    }

    /** Posts the form the way the screen does, token and all. */
    private function signIn(string $loginId, string $password): \CodeIgniter\Test\TestResponse
    {
        return $this->post('login', ['login_id' => $loginId, 'password' => $password]);
    }

    /**
     * Posts the way a screen does.
     *
     * Every form emits `csrf_field()` and the filter checks it, so a post
     * without one is refused — as it should be. There is no page here to take
     * the token from, so this mints one and sends it the way the field would.
     *
     * @param array<string, mixed>|null $params
     */
    public function post($path, ?array $params = null): \CodeIgniter\Test\TestResponse
    {
        $security = service('security');

        return $this->call('post', $path, ($params ?? []) + [$security->getTokenName() => $security->getHash()]);
    }

    // ---- What is stored ----------------------------------------------------

    /**
     * The password is never the password.
     *
     * A single letter is still hashed. The length of what somebody chose is
     * not a reason to store it where a backup, a dump or a stray SELECT would
     * read it straight out.
     */
    public function testAPasswordIsStoredAsAHashEvenWhenItIsOneLetter(): void
    {
        $this->account('1', 'doctor', 'A');

        $row = $this->db->table('users')->where('login_id', '1')->get()->getRowArray();

        $this->assertNotSame('A', $row['password_hash']);
        $this->assertStringStartsWith('$2y$', $row['password_hash']);
        $this->assertTrue(password_verify('A', $row['password_hash']));
    }

    /** Two accounts cannot answer to one User ID. */
    public function testAUserIdIsTakenOnlyOnce(): void
    {
        $this->account('1', 'doctor');

        $this->expectException(\Throwable::class);
        $this->account('1', 'doctor');
    }

    // ---- Signing in --------------------------------------------------------

    public function testEachRoleLandsOnItsOwnDashboard(): void
    {
        foreach (['doctor', 'coordinator'] as $i => $role) {
            $this->account((string) ($i + 1), $role);

            $this->signIn((string) ($i + 1), 'A')
                ->assertRedirectTo(site_url($role . '/dashboard'));

            $this->assertSame($role, session('auth_role'));
            $this->assertSame(ucfirst($role) . ' ' . ($i + 1), session('auth_name'));

            $this->get('logout');
        }
    }

    /** And the moment is stamped on the row. */
    public function testSigningInStampsLastLoginAt(): void
    {
        $id = $this->account('1', 'doctor');

        $this->assertNull($this->users->find($id)['last_login_at']);

        $this->signIn('1', 'A');

        $this->assertNotNull($this->users->find($id)['last_login_at']);
    }

    /**
     * A wrong number and a wrong password answer the same.
     *
     * Two messages would make the form a way of asking which staff numbers
     * exist: type numbers until the message changes.
     */
    public function testAWrongUserIdAndAWrongPasswordReadIdentically(): void
    {
        $this->account('1', 'doctor', 'A');

        $wrongPassword = $this->signIn('1', 'nope')->getBody();
        $wrongId       = $this->signIn('99999', 'A')->getBody();

        $this->assertStringContainsString('Invalid User ID or password', $wrongPassword);
        $this->assertStringContainsString('Invalid User ID or password', $wrongId);
        $this->assertFalse(session()->has('auth_id'));
    }

    /** Both boxes are asked for before either is checked. */
    public function testBothFieldsAreRequired(): void
    {
        $this->assertStringContainsString(
            'Please enter your User ID and password.',
            $this->signIn('', '')->getBody()
        );
        $this->assertStringContainsString(
            'Please enter your User ID and password.',
            $this->signIn('1', '')->getBody()
        );
    }

    /** A staff number is digits. Anything else is a typing mistake. */
    public function testTheUserIdTakesDigitsOnly(): void
    {
        $this->assertStringContainsString(
            'User ID must be numbers only.',
            $this->signIn('DR-00421', 'A')->getBody()
        );

        // No minimum length, though: one digit is a User ID.
        $this->account('7', 'doctor');
        $this->signIn('7', 'A')->assertRedirectTo(site_url('doctor/dashboard'));
    }

    /**
     * A switched-off account is told so — and only to whoever owns it.
     *
     * The password is checked first. Answering "inactive" to a wrong password
     * would say that the number is real, which is the thing the single generic
     * message exists to avoid.
     */
    public function testAnInactiveAccountIsRefusedAndToldWhy(): void
    {
        $this->account('1', 'doctor', 'A', false);

        $this->assertStringContainsString(
            'Your account is inactive. Please contact the administrator.',
            $this->signIn('1', 'A')->getBody()
        );
        $this->assertFalse(session()->has('auth_id'));

        // The wrong password on the same account says nothing about it.
        $body = $this->signIn('1', 'nope')->getBody();
        $this->assertStringContainsString('Invalid User ID or password', $body);
        $this->assertStringNotContainsString('inactive', $body);
    }

    /** What was typed comes back; what was typed in secret does not. */
    public function testTheUserIdIsKeptAfterAFailureAndThePasswordIsNot(): void
    {
        $body = $this->signIn('4242', 'hunter2')->getBody();

        $this->assertStringContainsString('value="4242"', $body);
        $this->assertStringNotContainsString('hunter2', $body);
    }

    /** The form is not postable from another site. */
    public function testTheLoginFormIsCsrfProtected(): void
    {
        $this->account('1', 'doctor');

        $this->assertStringContainsString('name="csrf_test_name"', $this->get('login')->getBody());

        // Posted without the token, the way another site would have to.
        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);
        $this->call('post', 'login', ['login_id' => '1', 'password' => 'A']);
    }

    /**
     * A new session id, so a token handed out before the sign-in is spent.
     *
     * Read off the mock session the test harness installs: there is no real
     * PHP session here to watch the id of, and `didRegenerate` is the mock's
     * record of the call having been made.
     */
    public function testSigningInRegeneratesTheSession(): void
    {
        $this->account('1', 'doctor');
        $this->withSession([]);

        $this->assertFalse(service('session')->didRegenerate);

        $this->signIn('1', 'A');

        $this->assertTrue(service('session')->didRegenerate);
    }

    // ---- Being signed in ---------------------------------------------------

    /** The login screen has nothing to ask somebody who is already in. */
    public function testSignedInVisitorsAreSentOnFromTheLoginScreen(): void
    {
        $this->withSession(['auth_id' => 1, 'auth_role' => 'doctor', 'auth_name' => 'D'])
            ->get('login')
            ->assertRedirectTo(site_url('doctor/dashboard'));

        $this->withSession(['auth_id' => 1, 'auth_role' => 'doctor', 'auth_name' => 'D'])
            ->get('/')
            ->assertRedirectTo(site_url('doctor/dashboard'));
    }

    /** And somebody with no session gets the screen itself. */
    public function testTheLoginScreenIsOpenToEverybodyElse(): void
    {
        $body = $this->get('login')->getBody();

        $this->assertStringContainsString('User login', $body);
        $this->assertStringContainsString('name="login_id"', $body);
        $this->assertStringContainsString('name="password"', $body);
    }

    public function testLogoutEmptiesTheSessionAndReturnsToTheLoginScreen(): void
    {
        $this->withSession(['auth_id' => 1, 'auth_role' => 'doctor', 'auth_name' => 'A', 'ui_organ' => 'liver'])
            ->get('logout')
            ->assertRedirectTo(site_url('login'));

        $this->assertFalse(session()->has('auth_id'));
        // The whole session, not the four keys: the programme somebody picked
        // is not the next person's business either.
        $this->assertFalse(session()->has('ui_organ'));
    }

    // ---- The two filters ---------------------------------------------------

    /** `auth`: no session, no screen — and it says why on the way. */
    public function testAProtectedScreenSendsASignedOutVisitorToTheLogin(): void
    {
        foreach (['dashboard', 'recipients', 'donors', 'pairs', 'reports', 'mrp', 'admin'] as $screen) {
            $this->get($screen)->assertRedirectTo(site_url('login'), $screen . ' is behind the login');
        }

        // And says so on the screen it sends them to. The notice is flashdata,
        // so the session has to travel between the two calls the way a cookie
        // carries it in a browser.
        $this->get('dashboard');
        $this->withSession($_SESSION);

        $this->assertStringContainsString('Please sign in to continue.', $this->get('login')->getBody());
    }

    /** `role`: the right session, the wrong role. */
    public function testARoleCannotReachAnotherRolesDashboard(): void
    {
        $wrong = [
            'doctor'      => ['coordinator/dashboard'],
            'coordinator' => ['doctor/dashboard'],
        ];

        foreach ($wrong as $role => $screens) {
            foreach ($screens as $screen) {
                $response = $this->withSession([
                    'auth_id' => 1, 'auth_role' => $role, 'auth_name' => 'Somebody',
                ])->get($screen);

                $response->assertStatus(403);
                $this->assertStringContainsString('Not your screen', $response->getBody(), $role . ' on ' . $screen);
                // The way out is their own, named on the page.
                $this->assertStringContainsString(site_url($role . '/dashboard'), $response->getBody());
            }
        }
    }

    // ---- Admin, which is a permission and not a role -----------------------

    /**
     * It is laid over the job rather than instead of it.
     *
     * An administrator is a doctor or a coordinator who also looks after the
     * register. They land where their own job lands, they keep every screen
     * their role has, and what the permission adds is on top.
     */
    public function testAnAdminKeepsTheirOwnRoleAndLandsWithIt(): void
    {
        $this->account('1', 'doctor', 'A', true, true);

        $this->signIn('1', 'A')->assertRedirectTo(site_url('doctor/dashboard'));

        $this->assertSame('doctor', session('auth_role'));
        $this->assertTrue(session('auth_is_admin'));

        // And a coordinator holds it the same way, still a coordinator.
        $this->account('2', 'coordinator', 'A', true, true);
        $this->withSession([])->signIn('2', 'A')->assertRedirectTo(site_url('coordinator/dashboard'));
        $this->assertSame('coordinator', session('auth_role'));
        $this->assertTrue(session('auth_is_admin'));
    }

    /** Somebody without it carries the fact that they do not have it. */
    public function testAnOrdinaryUserIsNotAnAdmin(): void
    {
        $this->account('1', 'doctor');

        $this->signIn('1', 'A');

        $this->assertNotTrue(session('auth_is_admin'));
    }

    /** The Admin screen is the permission's, and it is open to both roles. */
    public function testTheAdminScreenNeedsThePermissionAndNotARole(): void
    {
        foreach (['doctor', 'coordinator'] as $role) {
            $body = $this->withSession($this->session($role, true))->get('admin')->getBody();

            $this->assertStringContainsString('Add MRP', $body, $role . ' with the permission');
            $this->assertStringContainsString('Login Activity', $body);
        }

        foreach (['doctor', 'coordinator'] as $role) {
            $response = $this->withSession($this->session($role, false))->get('admin');

            $response->assertStatus(403);
            $this->assertStringContainsString('Not your screen', $response->getBody(), $role . ' without it');
        }
    }

    /**
     * The delete buttons, which are the permission's other half.
     *
     * Not rendered for anybody else — and refused as well, because a hidden
     * button is a courtesy and the route is the lock.
     */
    public function testOnlyAnAdminCanDeleteARecord(): void
    {
        // A programme for the record to belong to. This class does not run the
        // workup seeder — it is about signing in, and seeding seventy tests
        // for one row would be most of its running time.
        $this->db->table('organ_programs')->insert([
            'code' => 'kidney', 'label' => 'Kidney', 'description' => 'Renal transplant program',
            'sort_order' => 1, 'is_active' => 1,
        ]);

        $this->withSession($this->session('doctor', true))
            ->post('recipients/new', ['mrn' => '6600', 'name' => 'Still Here', 'age' => '40', 'bloodType' => 'O']);

        // Shown to one and not the other.
        $this->assertStringContainsString(
            'Delete recipient',
            $this->withSession($this->session('doctor', true))->get('recipients')->getBody()
        );
        $this->assertStringNotContainsString(
            'Delete recipient',
            $this->withSession($this->session('doctor', false))->get('recipients')->getBody()
        );

        // And refused when posted anyway.
        $security = service('security');
        $this->withSession($this->session('coordinator', false))
            ->call('post', 'recipients/6600/delete', [$security->getTokenName() => $security->getHash()])
            ->assertStatus(403);

        $this->seeInDatabase('recipients', ['mrn' => 6600]);
    }

    /** Granting it changes the permission and nothing about the person. */
    public function testGrantingAndTakingBackThePermission(): void
    {
        $this->withSession($this->session('doctor', true))
            ->post('admin/users', ['id' => '7701', 'name' => 'Dr. Ordinary', 'kind' => 'doctor']);

        $mrp     = $this->db->table('mrp')->where('code', '7701')->get()->getRowArray();
        $account = $this->users->where('login_id', '7701')->first();

        // Registering somebody makes their account — with no password, so it
        // exists and cannot be signed into until one is set.
        $this->assertNotNull($account);
        $this->assertSame('', $account['password_hash']);
        $this->assertSame((int) $mrp['id'], (int) $account['mrp_id']);
        $this->assertSame(0, (int) $account['is_admin']);

        $this->withSession($this->session('doctor', true))
            ->post('admin/users/' . $mrp['id'] . '/admin', ['admin' => '1']);

        $this->seeInDatabase('users', ['login_id' => '7701', 'is_admin' => 1, 'role' => 'doctor']);
        // Their type is untouched: Admin is not a kind of person.
        $this->seeInDatabase('mrp', ['id' => $mrp['id'], 'kind' => 'doctor']);

        // The register says both, separately.
        $html = $this->withSession($this->session('doctor', true))->get('admin')->getBody();
        $this->assertStringContainsString('>Doctor</span>', $html);
        $this->assertStringContainsString('Admin', $html);

        $this->withSession($this->session('doctor', true))
            ->post('admin/users/' . $mrp['id'] . '/admin', ['admin' => '0']);
        $this->seeInDatabase('users', ['login_id' => '7701', 'is_admin' => 0]);
    }

    /** Deactivating is the register's delete, and it shuts the door. */
    public function testDeactivatingAUserStopsThemSigningIn(): void
    {
        $this->withSession($this->session('doctor', true))
            ->post('admin/users', ['id' => '7702', 'name' => 'Dr. Leaving', 'kind' => 'doctor']);

        $mrp = $this->db->table('mrp')->where('code', '7702')->get()->getRowArray();

        // Give them a password, so the only thing refusing them is the flag.
        $this->users->update(
            (int) $this->users->where('login_id', '7702')->first()['id'],
            ['password_hash' => password_hash('A', PASSWORD_DEFAULT)]
        );

        $this->withSession([])->signIn('7702', 'A')->assertRedirectTo(site_url('doctor/dashboard'));

        $this->withSession($this->session('doctor', true))
            ->post('admin/users/' . $mrp['id'] . '/active', ['active' => '0']);

        // The row stays, on both sides, and the sign-in is refused.
        $this->seeInDatabase('mrp', ['id' => $mrp['id'], 'is_active' => 0]);
        $this->seeInDatabase('users', ['login_id' => '7702', 'is_active' => 0]);
        // From a clean session, or the admin's own would send them straight on.
        $this->assertStringContainsString(
            'Your account is inactive',
            $this->withSession([])->signIn('7702', 'A')->getBody()
        );
    }

    /** Resetting a password is the screen and nothing behind it, and says so. */
    public function testResettingAPasswordChangesNothingYetAndSaysSo(): void
    {
        $this->withSession($this->session('doctor', true))
            ->post('admin/users', ['id' => '7703', 'name' => 'Dr. Forgetful', 'kind' => 'doctor']);

        $mrp    = $this->db->table('mrp')->where('code', '7703')->get()->getRowArray();
        $before = $this->users->where('login_id', '7703')->first()['password_hash'];

        $this->withSession($this->session('doctor', true))
            ->post('admin/users/' . $mrp['id'] . '/password', ['password' => 'TemporaryOne1']);

        $this->assertSame($before, $this->users->where('login_id', '7703')->first()['password_hash']);
        $this->assertStringContainsString('not connected yet', (string) session('ui_mrp_saved'));

        // The dialog on the screen warns before anybody presses it.
        $this->assertStringContainsString(
            'what is typed here is not stored',
            $this->withSession($this->session('doctor', true))->get('admin')->getBody()
        );
    }

    // ---- The log ------------------------------------------------------------

    /**
     * Every attempt, successful or not, and the password in none of them.
     */
    public function testEveryAttemptToSignInIsRecorded(): void
    {
        $this->account('1', 'doctor', 'Correct1');

        $this->signIn('1', 'Correct1');
        $this->signIn('1', 'WrongOne');
        $this->signIn('9999', 'WrongOne');

        $rows = $this->db->table('login_activity')->orderBy('id')->get()->getResultArray();

        $this->assertCount(3, $rows);
        $this->assertSame('1', $rows[0]['login_id']);
        $this->assertSame(1, (int) $rows[0]['succeeded']);
        $this->assertSame('Doctor 1', $rows[0]['name']);

        $this->assertSame(0, (int) $rows[1]['succeeded']);
        $this->assertSame('Wrong User ID or password', $rows[1]['reason']);

        // Nobody holds that number, so there is no name and no account — and
        // what was typed is kept, because that is the thing worth reading.
        $this->assertSame('9999', $rows[2]['login_id']);
        $this->assertSame('', $rows[2]['name']);
        $this->assertNull($rows[2]['user_id']);

        foreach ($rows as $row) {
            $this->assertStringNotContainsString('Correct1', implode(' ', array_map('strval', $row)));
            $this->assertStringNotContainsString('WrongOne', implode(' ', array_map('strval', $row)));
        }
    }

    /** And the Admin screen shows them, read-only. */
    public function testTheAdminScreenShowsTheLogAndOffersNothingOnIt(): void
    {
        $this->account('1', 'doctor', 'Correct1');
        $this->signIn('1', 'Correct1');
        $this->signIn('4242', 'nope');

        $html = $this->withSession($this->session('doctor', true))->get('admin')->getBody();

        $this->assertStringContainsString('Login Activity', $html);
        $this->assertStringContainsString('>Successful</span>', $html);
        $this->assertStringContainsString('>Failed</span>', $html);
        $this->assertStringContainsString('4242', $html);
        // Nothing posts to it and nothing edits it.
        $this->assertStringNotContainsString('login_activity', $html);
    }

    /** A session, as the filters read one. */
    private function session(string $role, bool $admin): array
    {
        return [
            'auth_id'       => 1,
            'auth_login_id' => '1',
            'auth_name'     => 'Somebody',
            'auth_role'     => $role,
            'auth_is_admin' => $admin,
            'ui_organ'      => 'kidney',
        ];
    }

    /** And their own is open. */
    public function testEachRoleReachesItsOwnDashboard(): void
    {
        foreach (['doctor', 'coordinator'] as $role) {
            $body = $this->withSession([
                'auth_id' => 1, 'auth_role' => $role, 'auth_name' => 'Somebody',
            ])->get($role . '/dashboard')->getBody();

            $this->assertStringContainsString(ucfirst($role) . ' dashboard', $body);
            $this->assertStringContainsString('Somebody', $body);
            $this->assertStringContainsString(site_url('logout'), $body);
        }
    }
}
