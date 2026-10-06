<?php

namespace App\Filters;

use App\Controllers\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * No session, no screen.
 *
 * Put on a route as `auth`, this is the whole of "you have to be signed in":
 * anybody who is not goes back to the login screen, and nothing behind the
 * filter is run — not the controller, not a query, not a view.
 *
 * It answers with a redirect rather than a 403 because not being signed in is
 * not a refusal; it is a state somebody can leave by signing in, and the way
 * out is the screen they are being sent to. Being signed in as the wrong role
 * *is* a refusal, and {@see RoleFilter} answers that one differently.
 */
class AuthFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // A filter runs before any controller, so nothing has loaded this yet
        // and site_url() would be undefined.
        helper('url');

        if (Auth::signedIn()) {
            return null;
        }

        // Said on the screen they land on, so the redirect does not look like
        // the link was broken.
        return redirect()
            ->to(site_url('login'))
            ->with('auth_notice', 'Please sign in to continue.');
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
