<?php

namespace App\Filters;

use App\Controllers\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The screens only somebody who looks after the register may reach.
 *
 * Put on a route as `admin`. It asks one question — does this session carry
 * the permission — and it asks it of doctors and coordinators alike, because
 * the permission sits over both and replaces neither.
 *
 * It is the *only* thing that decides. A link hidden from the sidebar is a
 * courtesy, not a lock: the address is still typeable, so what keeps somebody
 * out is this filter, and every screen it guards is listed in Config\Routes
 * rather than assumed.
 */
class AdminFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // A filter runs before any controller, so nothing has loaded this yet
        // and site_url() would be undefined.
        helper('url');

        // No session at all is not a refusal: they have not said who they are.
        if (! Auth::signedIn()) {
            return redirect()
                ->to(site_url('login'))
                ->with('auth_notice', 'Please sign in to continue.');
        }

        if (Auth::isAdmin()) {
            return null;
        }

        return service('response')
            ->setStatusCode(403)
            ->setBody(view('errors/403', [
                'title'   => 'Not your screen',
                'role'    => Auth::role(),
                'name'    => Auth::name(),
                'homeUrl' => site_url(Auth::homeFor(Auth::role())),
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
