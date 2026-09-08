<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sends visitors to the organ picker until they have chosen one.
 *
 * Replaces the CodeIgniter 3 `organ_chosen()` helper that every controller
 * constructor called; in CI4 a redirect has to be returned, not emitted from
 * deep inside a helper, so the guard belongs in a filter.
 */
class OrganFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (empty(session()->get('organ'))) {
            return redirect()->to(site_url('Organ'));
        }
    }

    /**
     * @param list<string>|null $arguments
     *
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do after the response is built.
    }
}
