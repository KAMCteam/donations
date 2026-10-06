<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\UiStore;
use App\Models\LoginActivityModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * The register, and who may sign into it.
 *
 * One screen, because it is one job: the people this platform knows about, and
 * everything about them. It was two things in two places — Add MRP in the
 * sidebar for everybody, and no way at all to look at an account — and
 * everything here is now behind the `admin` permission, which a doctor or a
 * coordinator may hold on top of their own work.
 *
 * Three sections, in the order somebody uses them:
 *
 *   - **Add MRP**, unchanged: look a staff number up in the hospital
 *     directory, and register the person it belongs to as a doctor or a
 *     coordinator.
 *   - **Registered MRPs**: everybody registered, with their row to edit,
 *     deactivate, grant the permission on, and reset the password of.
 *   - **Login Activity**: every attempt to sign in, read-only.
 *
 * Nothing here deletes a person. A doctor who has left is still the doctor
 * named on the records they were responsible for, and a register that forgot
 * them would make those records say nobody. Deactivating is the answer: they
 * cannot sign in, they stop being offered on new records, and every old one
 * still names them. {@see setActive()}
 */
class Users extends BaseController
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
        return view('ui/admin_users', [
            'title'   => 'Admin',
            'navPage' => 'admin',
            'organ'   => $this->store->organ(),
            'users'   => $this->store->mrpRegister(),
            'activity' => model(LoginActivityModel::class)->recent(),
            // Its own keys, not the layout's: this screen has a banner of its
            // own, and `ui_notice` would be shown twice.
            'saved'   => (string) ($this->session->getFlashdata('ui_mrp_saved') ?? ''),
            'error'   => (string) ($this->session->getFlashdata('ui_mrp_error') ?? ''),
            // Which row the register opened for editing, and what the
            // directory lookup last came back with.
            'editing' => (string) ($this->request->getGet('edit') ?? ''),
            'lookup'  => (array) ($this->session->getFlashdata('ui_mrp_lookup') ?? []),
        ]);
    }

    public function add(): RedirectResponse
    {
        return $this->back($this->store->addMrp(
            (string) $this->request->getPost('id'),
            (string) $this->request->getPost('name'),
            (string) $this->request->getPost('kind')
        ), 'User added successfully.');
    }

    /**
     * Looks a user up in the hospital directory.
     *
     * The directory itself is not connected yet, so this is the screen the
     * search will drive and nothing behind it: it comes back saying so, with
     * the ID that was searched for carried into the form, so the user can be
     * entered by hand meanwhile. When the directory is wired, what changes is
     * what fills `ui_mrp_lookup` — not this screen.
     */
    public function lookup(): RedirectResponse
    {
        $code = trim((string) $this->request->getPost('id'));

        return redirect()->to(site_url('admin'))->with('ui_mrp_lookup', [
            'code'   => $code,
            'kind'   => (string) $this->request->getPost('kind'),
            'found'  => false,
            'reason' => $code === ''
                ? 'Enter the ID to search for.'
                : 'The hospital directory is not connected yet, so nothing could be looked up. Enter the name below and the user will be registered against this ID.',
        ]);
    }

    /** Changes a registered user's ID, name or kind. */
    public function update(string $id): RedirectResponse
    {
        $error = $this->store->updateMrp(
            $id,
            (string) $this->request->getPost('id'),
            (string) $this->request->getPost('name'),
            (string) $this->request->getPost('kind')
        );

        // The permission travels with the row's own form, so one Save is one
        // save: changing somebody's name and granting them the register in
        // two presses would be two chances to walk away halfway.
        if ($error === '') {
            $error = $this->store->setMrpAdmin($id, (string) $this->request->getPost('isAdmin') === '1');
        }

        return redirect()
            ->to(site_url('admin') . ($error === '' ? '' : '?edit=' . rawurlencode($id)))
            ->with($error === '' ? 'ui_mrp_saved' : 'ui_mrp_error', $error === '' ? 'User updated.' : $error);
    }

    /**
     * Takes a registered user out of service, or puts them back.
     *
     * This is what the register's delete button does, and the button says so.
     * A deactivated person cannot sign in and is not offered on a new record;
     * every record that already names them goes on naming them.
     */
    public function setActive(string $id): RedirectResponse
    {
        $active = (string) $this->request->getPost('active') === '1';

        return $this->back(
            $this->store->setMrpActive($id, $active),
            $active ? 'User reactivated.' : 'User deactivated.'
        );
    }

    /** Grants the register to somebody, or takes it back. */
    public function setAdmin(string $id): RedirectResponse
    {
        $admin = (string) $this->request->getPost('admin') === '1';

        return $this->back(
            $this->store->setMrpAdmin($id, $admin),
            $admin ? 'Admin permission granted.' : 'Admin permission removed.'
        );
    }

    /**
     * Sets a new password for somebody, without sending them round the
     * directory again.
     *
     * **The screen and nothing behind it, on purpose.** What is typed here is
     * read, checked for being there at all, and thrown away: no hash is
     * written and no account changes. The flow is what is being built — where
     * the button sits, what it asks, what it says back — so that wiring it is
     * one method and not a screen.
     *
     * It says so on the page as well as here. A screen that looks like it set
     * a password and did not is worse than no screen, because somebody would
     * hand the password over and walk away.
     */
    public function resetPassword(string $id): RedirectResponse
    {
        $person = $this->store->mrpPerson($id);

        if ($person === null) {
            return $this->back('That user could not be found.', '');
        }

        $password = (string) $this->request->getPost('password');

        if (trim($password) === '') {
            return $this->back('Enter the new password, or generate one.', '');
        }

        return redirect()->to(site_url('admin'))->with(
            'ui_mrp_saved',
            'Nothing was changed: setting a password is not connected yet. '
                . $person['name'] . "'s password is unchanged."
        );
    }

    /** One way back from every action on this screen, with one message. */
    private function back(string $error, string $done): RedirectResponse
    {
        return redirect()->to(site_url('admin'))->with(
            $error === '' ? 'ui_mrp_saved' : 'ui_mrp_error',
            $error === '' ? $done : $error
        );
    }
}
