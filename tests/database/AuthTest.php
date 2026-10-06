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
    private function account(string $loginId, string $role, string $password = 'A', bool $active = true): int|string
    {
        return $this->users->store([
            'login_id'  => $loginId,
            'name'      => ucfirst($role) . ' ' . $loginId,
            'role'      => $role,
            'is_active' => $active ? 1 : 0,
        ], $password);
    }

    /** Posts the form the way the screen does, token and all. */
    private function signIn(string $loginId, string $password): \CodeIgniter\Test\TestResponse
    {
        $security = service('security');

        return $this->call('post', 'login', [
            'login_id'                 => $loginId,
            'password'                 => $password,
            $security->getTokenName()  => $security->getHash(),
        ]);
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
        $this->account('1', 'admin', 'A');

        $row = $this->db->table('users')->where('login_id', '1')->get()->getRowArray();

        $this->assertNotSame('A', $row['password_hash']);
        $this->assertStringStartsWith('$2y$', $row['password_hash']);
        $this->assertTrue(password_verify('A', $row['password_hash']));
    }

    /** Two accounts cannot answer to one User ID. */
    public function testAUserIdIsTakenOnlyOnce(): void
    {
        $this->account('1', 'admin');

        $this->expectException(\Throwable::class);
        $this->account('1', 'doctor');
    }

    // ---- Signing in --------------------------------------------------------

    public function testTheThreeRolesEachLandOnTheirOwnDashboard(): void
    {
        foreach (['admin', 'doctor', 'coordinator'] as $i => $role) {
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
        $id = $this->account('1', 'admin');

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
        $this->account('1', 'admin', 'A');

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
        $this->account('1', 'admin', 'A', false);

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
        $this->account('1', 'admin');

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
        $this->account('1', 'admin');
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
        $this->withSession(['auth_id' => 1, 'auth_role' => 'admin', 'auth_name' => 'A', 'ui_organ' => 'liver'])
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
        foreach (['dashboard', 'recipients', 'donors', 'pairs', 'reports', 'mrp', 'admin/dashboard'] as $screen) {
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
            'admin'       => ['doctor/dashboard', 'coordinator/dashboard'],
            'doctor'      => ['admin/dashboard', 'coordinator/dashboard'],
            'coordinator' => ['admin/dashboard', 'doctor/dashboard'],
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

    /** And their own is open. */
    public function testEachRoleReachesItsOwnDashboard(): void
    {
        foreach (['admin', 'doctor', 'coordinator'] as $role) {
            $body = $this->withSession([
                'auth_id' => 1, 'auth_role' => $role, 'auth_name' => 'Somebody',
            ])->get($role . '/dashboard')->getBody();

            $this->assertStringContainsString(ucfirst($role) . ' dashboard', $body);
            $this->assertStringContainsString('Somebody', $body);
            $this->assertStringContainsString(site_url('logout'), $body);
        }
    }
}
