<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Remembers the screen somebody was on, so the back arrow can go there.
 *
 * Every back arrow used to name one fixed destination: a donor's record said
 * *Back to Donors List* whether you had come from the register, from a pair,
 * or from the search. Pressing it took you somewhere you had not been, and the
 * filters you had set on the list you really came from were gone.
 *
 * This keeps two URLs in the session — **here** and **the one before here** —
 * and {@see ui_back()} points the arrow at the second.
 *
 * The rule that makes it behave is that *before* means **a different screen**,
 * not a different address. Opening a card for editing, saving it, switching a
 * donor tab and paging a list are all the same screen answering again; if any
 * of them counted, the arrow would take you back one card instead of back to
 * the list you came from. So a new address on the path you are already on
 * refreshes **here** and leaves **before** alone.
 *
 * Recorded after the response, because what is being recorded is the page that
 * was served. Only ordinary HTML pages: a redirect is not a screen anybody was
 * on, a print sheet opens in its own tab and carries its own way back, and an
 * export is a file.
 */
class TrailFilter implements FilterInterface
{
    /**
     * Screens that are not somewhere to go back to.
     *
     * The login screens because the way back to them is signing out; the
     * printable sheets and the exports because they are not screens in the
     * session's sense — they open in a tab of their own, already holding the
     * filters of the list that opened them.
     */
    private const NOT_A_SCREEN = ['login', 'logout', 'mrn-taken', 'reports/export'];

    /** What a screen is called, where the arrow has to name it. */
    private const NAMES = [
        'dashboard'   => 'Dashboard',
        'recipients'  => 'Recipient Waitlist',
        'donors'      => 'Donors List',
        'pairs'       => 'Pairs List',
        'exchange'    => 'Paired Exchange',
        'reports'     => 'Reports',
        'admin'       => 'User Management',
        'organ'       => 'the programme picker',
    ];

    /** And what one of its records is called, when the address names one. */
    private const RECORDS = [
        'recipients' => 'the recipient',
        'donors'     => 'the donor',
        'pairs'      => 'the pair',
    ];

    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! $this->isAScreen($request, $response)) {
            return null;
        }

        helper('url');

        $path    = trim($request->getPath(), '/');
        $query   = $request->getUri()->getQuery();
        $session = session();
        $here    = (array) ($session->get('ui_here') ?? []);

        // The same screen answering again — a card opened, a tab switched, a
        // filter pressed. Where it goes back to has not changed.
        if (($here['path'] ?? null) !== $path) {
            $session->set('ui_back', $here);
        }

        $session->set('ui_here', [
            'path'  => $path,
            'url'   => site_url($path) . ($query === '' ? '' : '?' . $query),
            'label' => $this->name($path),
        ]);

        return null;
    }

    /** Whether what was just served is a screen somebody could come back to. */
    private function isAScreen(RequestInterface $request, ResponseInterface $response): bool
    {
        if (! $request->is('get') || $response->getStatusCode() !== 200) {
            return false;
        }

        // A whole page and not a fragment or a payload. Read off the body,
        // because `Content-Type` is a header on a service the whole process
        // shares and a JSON endpoint earlier in it can leave its own behind —
        // and read as "has an <html>" rather than "starts with a doctype",
        // because what comes first in the body is not always the doctype.
        if (! str_contains((string) $response->getBody(), '<html')) {
            return false;
        }

        $path = trim($request->getPath(), '/');

        if ($path === '' || str_ends_with($path, '/print')) {
            return false;
        }

        foreach (self::NOT_A_SCREEN as $not) {
            if ($path === $not || str_starts_with($path, $not . '/')) {
                return false;
            }
        }

        return true;
    }

    /**
     * What to call the screen at this address.
     *
     * The lists are named for themselves. A record is named for what it is and
     * not for whose it is: the arrow is read on the way out, where "Back to
     * the pair" says everything "Back to Mishal Al-Harthy and Reem Al-Harthy"
     * would, in the width a header actually has.
     */
    private function name(string $path): string
    {
        if (isset(self::NAMES[$path])) {
            return self::NAMES[$path];
        }

        $first = explode('/', $path)[0];

        if (isset(self::RECORDS[$first])) {
            return self::RECORDS[$first];
        }

        return self::NAMES[$first] ?? 'the previous screen';
    }
}
