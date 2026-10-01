<?php

namespace App\Controllers;

use App\Libraries\UiStore;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * One question asked of every register at once.
 *
 * The bar at the top of every screen is for the moment before you know which
 * list somebody is on: there is an MRN on a form, or half a name, and the
 * answer might be a recipient, a donor, a pair, or one of the people records
 * are assigned to. Each of those screens can already be searched once you are
 * on it; this is for when you are not.
 *
 * It only reads. Every result is a link to the screen that owns the record,
 * which is where anything is done about it.
 */
class Search extends BaseController
{
    /** @var list<string> */
    protected $helpers = ['url', 'form', 'auth', 'lang', 'ui'];

    private UiStore $store;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $this->store = new UiStore($this->session);
    }

    public function index(): string
    {
        $query   = trim((string) ($this->request->getGet('q') ?? ''));
        $results = $this->store->search($query);

        return view('ui/search', [
            'title'   => $query === '' ? 'Search' : 'Search: ' . $query,
            'navPage' => '',
            'organ'   => $this->store->organ(),
            'query'       => $query,
            // The bar at the top keeps what was asked, so a result opens with
            // the question still on the screen.
            'searchQuery' => $query,
            'results' => $results,
            'total'   => array_sum(array_map('count', $results)),
        ]);
    }
}
