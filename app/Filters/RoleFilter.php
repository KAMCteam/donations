<?php

namespace App\Filters;

use App\Controllers\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Which roles a screen is for.
 *
 * Written on the route as the roles that may pass:
 *
 *     ['filter' => 'role:admin']
 *     ['filter' => 'role:doctor,coordinator']
 *
 * Allowing rather than denying, on purpose. A filter that listed who is kept
 * out would let a role added later through every screen it was not thought of
 * for; this one keeps it out of all of them until somebody writes it down.
 *
 * A filter with no roles after it lets nobody through. An empty list is far
 * more likely to be a `role:` somebody left half-written than a screen meant
 * for everybody — and a screen meant for everybody wants `auth`, not this.
 */
class RoleFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // A filter runs before any controller, so nothing has loaded this yet
        // and site_url() would be undefined.
        helper('url');

        // Not signed in at all is the other filter's answer, and the right one
        // even when this filter is the only one on the route: somebody with no
        // session is not being refused, they have not said who they are.
        if (! Auth::signedIn()) {
            return redirect()
                ->to(site_url('login'))
                ->with('auth_notice', 'Please sign in to continue.');
        }

        $allowed = array_map('trim', $arguments ?? []);

        if (in_array(Auth::role(), $allowed, true)) {
            return null;
        }

        // Their own screen, named on the page, so the way out of a wrong link
        // is one press rather than a guess.
        return service('response')
            ->setStatusCode(403)
            ->setBody(view('errors/403', [
                'title'    => 'Not your screen',
                'role'     => Auth::role(),
                'name'     => Auth::name(),
                'homeUrl'  => site_url(Auth::homeFor(Auth::role())),
            ]));
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
