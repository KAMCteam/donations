<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Signing in, and signing out again.
 *
 * The login screen used to let anybody through: it checked that the two boxes
 * were not empty and then believed them. This is the real thing — a row in
 * `users`, a `password_verify()`, and a session that says who it belongs to.
 *
 * Two rules shape what the screen says back:
 *
 *   - **One message for a bad sign-in.** A wrong User ID and a wrong password
 *     answer identically, because saying which was wrong hands somebody half
 *     the answer and turns the form into a way of asking which staff numbers
 *     exist.
 *   - **A switched-off account is told so** — but only after the password has
 *     been checked, so the sentence is only ever shown to the person whose
 *     account it is.
 *
 * The User ID is checked for shape before either: it is a staff number, so
 * anything but digits is a typing mistake rather than a failed sign-in, and
 * saying so costs nothing — the message is true of every number alike.
 */
class Auth extends BaseController
{
    /** Where each role lands. The one place this mapping is written. */
    public const HOME = [
        'admin'       => 'admin/dashboard',
        'doctor'      => 'doctor/dashboard',
        'coordinator' => 'coordinator/dashboard',
    ];

    private const BAD_CREDENTIALS = 'Invalid User ID or password';
    private const INACTIVE        = 'Your account is inactive. Please contact the administrator.';
    private const EMPTY_FIELDS    = 'Please enter your User ID and password.';
    private const NOT_A_NUMBER    = 'User ID must be numbers only.';

    public function login(): string|RedirectResponse
    {
        // Already signed in: the login screen has nothing to ask them.
        if (self::signedIn()) {
            return redirect()->to(site_url(self::homeFor(self::role())));
        }

        return $this->screen();
    }

    public function attemptLogin(): string|RedirectResponse
    {
        if (self::signedIn()) {
            return redirect()->to(site_url(self::homeFor(self::role())));
        }

        $loginId  = trim((string) $this->request->getPost('login_id'));
        // Not trimmed. A space is a character somebody may have chosen, and
        // taking it off would refuse the password they actually set.
        $password = (string) $this->request->getPost('password');

        if ($loginId === '' || $password === '') {
            return $this->screen($loginId, self::EMPTY_FIELDS);
        }

        // Digits only, and no minimum length: a staff number is short at this
        // hospital and may get shorter.
        if (preg_match('/^\d+$/', $loginId) !== 1) {
            return $this->screen($loginId, self::NOT_A_NUMBER);
        }

        $users = model(UserModel::class);
        $user  = $users->authenticate($loginId, $password);

        if ($user === null) {
            return $this->screen($loginId, self::BAD_CREDENTIALS);
        }

        // The password was right, so this is their account and they can be
        // told what is the matter with it.
        if ((int) $user['is_active'] !== 1) {
            return $this->screen($loginId, self::INACTIVE);
        }

        // A new session id for the signed-in session, so a token somebody was
        // given before they signed in cannot be used afterwards.
        $this->session->regenerate(true);
        $this->session->set([
            'auth_id'       => (int) $user['id'],
            'auth_login_id' => (string) $user['login_id'],
            'auth_name'     => (string) $user['name'],
            'auth_role'     => (string) $user['role'],
        ]);

        $users->touchLogin($user['id']);

        return redirect()->to(site_url(self::homeFor($user['role'])));
    }

    public function logout(): RedirectResponse
    {
        // The whole session and not the four keys: the programme picked, the
        // card left open, anything a screen put there — none of it belongs to
        // whoever signs in next on this machine.
        //
        // Emptied and then destroyed, in that order and not just the second.
        // `destroy()` throws the session id away, which is the part that
        // matters to a browser — but it is a no-op under the testing
        // environment, and a logout that cannot be tested is a logout nobody
        // finds out about. Clearing the keys first says the same thing in a
        // way that is true everywhere.
        foreach (array_keys($this->session->get() ?? []) as $key) {
            $this->session->remove($key);
        }

        $this->session->destroy();

        return redirect()->to(site_url('login'));
    }

    // ---- What the rest of the application asks -----------------------------

    /** Whether this request belongs to somebody who has signed in. */
    public static function signedIn(): bool
    {
        return session()->has('auth_id');
    }

    /** Their role, or '' when nobody is signed in. */
    public static function role(): string
    {
        return (string) (session('auth_role') ?? '');
    }

    /** Their name, for the screens that greet them. */
    public static function name(): string
    {
        return (string) (session('auth_name') ?? '');
    }

    /**
     * The screen a role lands on.
     *
     * A role nothing is mapped for falls back to the login screen rather than
     * to somebody else's dashboard: an unmapped role is a mistake, and the
     * safe reading of a mistake is that this person has nowhere to be.
     */
    public static function homeFor(string $role): string
    {
        return self::HOME[$role] ?? 'login';
    }

    /**
     * The login screen, with what was typed and what was wrong with it.
     *
     * The User ID comes back; the password never does. Retyping a number is
     * the annoying half, and a password echoed into HTML is a password in the
     * page source, in the browser's cache and in anything that logs a body.
     */
    private function screen(string $loginId = '', string $error = ''): string
    {
        return view('ui/login', [
            'title'   => 'User login',
            'loginId' => $loginId,
            'error'   => $error,
            // Why they are looking at this screen rather than the one they
            // asked for, when a filter sent them here. Only on the way in: a
            // refused attempt replaces it with the error, which is the newer
            // and more useful of the two.
            'notice'  => $error === '' ? (string) ($this->session->getFlashdata('auth_notice') ?? '') : '',
        ]);
    }
}
