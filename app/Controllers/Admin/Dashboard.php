<?php

namespace App\Controllers\Admin;

use App\Controllers\Auth;
use App\Controllers\BaseController;

/**
 * Where a admin lands after signing in.
 *
 * A placeholder, and honest about being one: it says who is signed in, as
 * what, and offers the way out. The screens a admin actually works on are
 * the platform's, and the link below is how they reach them until this grows
 * into something of its own.
 *
 * Reaching it at all is settled before this runs — the route carries
 * `auth` and `role:admin`, so a signed-out visitor is redirected and
 * anybody else is refused. Nothing here re-checks that: a guard written twice
 * is a guard that can disagree with itself.
 */
class Dashboard extends BaseController
{
    /**
     * The view uses the platform's own icons and wording helpers, so this
     * asks for `ui` on top of what every controller gets.
     *
     * @var list<string>
     */
    protected $helpers = ['url', 'form', 'auth', 'lang', 'ui'];

    public function index(): string
    {
        return view('auth/dashboard', [
            'title' => ucfirst(Auth::role()) . ' dashboard',
            'name'  => Auth::name(),
            'role'  => Auth::role(),
        ]);
    }
}
