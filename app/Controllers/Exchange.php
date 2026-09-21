<?php

namespace App\Controllers;

use App\Libraries\ExchangeDraft;
use App\Libraries\UiStore;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Paired exchange: swapping donors between pairs that cannot use their own.
 *
 * Two screens. The first lists the pairs an exchange can be built from and
 * narrows them — by file number, by status, by blood group. The second is the
 * building itself: the chosen pair comes apart into a column of recipients and
 * a column of donors, each drawn from the pairs in play plus everyone on the
 * waiting list and the donor register who is free.
 *
 * Nothing is written until Confirm, and Confirm is refused while anybody the
 * exchange has released is still without a pair. {@see ExchangeDraft} holds
 * that rule and the working-out; this controller is the screens around it.
 */
class Exchange extends BaseController
{
    /** @var list<string> */
    protected $helpers = ['url', 'form', 'auth', 'lang', 'ui'];

    private UiStore $store;
    private ExchangeDraft $draft;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $this->store = new UiStore($this->session);
        $this->draft = new ExchangeDraft($this->session);
    }

    /**
     * The pairs an exchange can start from, searched and filtered.
     *
     * No sign-in check, for the same reason no other screen has one: there is
     * nothing to check yet. `Ui::attemptLogin()` still takes any non-empty
     * staff ID, so a guard here would be the appearance of a lock and not one.
     */
    public function index(): string
    {
        $organ  = $this->store->organ();
        $query  = trim((string) $this->request->getGet('q'));
        $status = (string) ($this->request->getGet('status') ?: 'all');
        $bt     = (string) ($this->request->getGet('bt') ?: 'all');

        return view('ui/exchange_list', [
            'title'        => 'Paired Exchange',
            'navPage'      => 'exchange',
            'organ'        => $organ,
            'rows'         => $this->draft->exchangeablePairs($organ, $query, $status, $bt),
            'query'        => $query,
            'statusFilter' => $status,
            'btFilter'     => $bt,
            'hasDraft'     => $this->draft->isOpen($organ),
            'error'        => (string) ($this->session->getFlashdata('ui_error') ?? ''),
        ]);
    }

    /** Begins an exchange from one pair. POST: it changes what the next page is. */
    public function start(?string $pairId = null): RedirectResponse
    {
        $error = $this->draft->start($this->store->organ(), $pairId);

        if ($error !== '') {
            $this->session->setFlashdata('ui_error', $error);

            return redirect()->to(site_url('exchange'));
        }

        return redirect()->to(site_url('exchange/build'));
    }

    /**
     * The builder, and everything done in it.
     *
     * One address for the screen and its four actions, so every button is a
     * plain form post and the page that comes back is the page you were on.
     */
    public function build(): string|RedirectResponse
    {
        $organ = $this->store->organ();

        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->act($organ);
        }

        $state = $this->draft->state($organ);

        if ($state === null) {
            return redirect()->to(site_url('exchange'));
        }

        return view('ui/exchange_build', [
            'title'   => 'Paired Exchange',
            'navPage' => 'exchange',
            'organ'   => $organ,
            'state'   => $state,
            'error'   => (string) ($this->session->getFlashdata('ui_error') ?? ''),
        ]);
    }

    /** Link, undo, discard or confirm — whichever button was pressed. */
    private function act(string $organ): RedirectResponse
    {
        $action = (string) $this->request->getPost('action');

        if ($action === 'discard') {
            $this->draft->discard();

            return redirect()->to(site_url('exchange'));
        }

        if ($action === 'unlink') {
            $this->draft->unlink($organ, (int) $this->request->getPost('index'));

            return redirect()->to(site_url('exchange/build'));
        }

        if ($action === 'confirm') {
            $error = $this->draft->confirm($organ);

            if ($error === '') {
                $this->session->setFlashdata('ui_notice', 'The exchange is done. Its new pairs are below.');

                return redirect()->to(site_url('pairs'));
            }

            $this->session->setFlashdata('ui_error', $error);

            return redirect()->to(site_url('exchange/build'));
        }

        $error = $this->draft->link(
            $organ,
            (string) $this->request->getPost('recipientMrn'),
            (string) $this->request->getPost('donorMrn')
        );

        if ($error !== '') {
            $this->session->setFlashdata('ui_error', $error);
        }

        return redirect()->to(site_url('exchange/build'));
    }
}
