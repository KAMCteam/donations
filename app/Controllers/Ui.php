<?php

namespace App\Controllers;

use App\Libraries\UiStore;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * The transplant screens (login, programme picker, dashboard, waitlist, donors,
 * pairs, MRPs), ported from the standalone HTML/JS prototype in
 * `donations_html_ui.zip`.
 *
 * The prototype was a single page: `index.html` held one empty `<div>` and ten
 * JavaScript files wrote every screen into it as concatenated strings. Here the
 * screens are HTML — one view per screen under `app/Views/ui/` — and this
 * controller does what `js/app.js` used to: pick the screen, hand it its data,
 * and apply what the forms post.
 *
 * Data comes from {@see UiStore}, a session-scoped copy of the demo records.
 * Point those reads at the existing models to run the screens on the live
 * `patients` / `pairs` tables; the views do not change.
 */
class Ui extends BaseController
{
    /**
     * BaseController's list plus `ui`, which adds ui_icon() and the small
     * class-name lookups the views use. The other four stay because
     * BaseController::initController() calls set_language() from `lang`.
     *
     * @var list<string>
     */
    protected $helpers = ['url', 'form', 'auth', 'lang', 'ui'];

    private UiStore $store;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $this->store = new UiStore($this->session);
    }

    // ---- Session screens ---------------------------------------------------

    public function index(): RedirectResponse
    {
        return redirect()->to(site_url($this->store->isSignedIn() ? 'dashboard' : 'login'));
    }

    public function login(): string
    {
        return view('ui/login', ['title' => 'Staff login']);
    }

    public function attemptLogin(): string|RedirectResponse
    {
        $id       = trim((string) $this->request->getPost('id'));
        $password = trim((string) $this->request->getPost('password'));

        // The prototype accepted any non-empty pair; wire this to the real
        // staff directory before the screens go anywhere near production.
        if ($id === '' || $password === '') {
            return view('ui/login', [
                'title' => 'Staff login',
                'id'    => $id,
                'error' => 'Please enter your Staff ID and password.',
            ]);
        }

        $this->store->signIn($id);

        return redirect()->to(site_url('organ'));
    }

    public function logout(): RedirectResponse
    {
        $this->store->reset();

        return redirect()->to(site_url('login'));
    }

    public function organSelector(): string
    {
        return view('ui/organ_selector', [
            'title'  => 'Select organ program',
            'organs' => [
                ['organ' => 'kidney', 'label' => 'Kidney', 'desc' => 'Renal transplant program',   'icon' => 'kidney.svg'],
                ['organ' => 'liver',  'label' => 'Liver',  'desc' => 'Hepatic transplant program', 'icon' => 'liver.svg'],
            ],
        ]);
    }

    public function chooseOrgan(string $organ): RedirectResponse
    {
        $this->store->setOrgan($organ);

        return redirect()->to(site_url('dashboard'));
    }

    // ---- Dashboard ---------------------------------------------------------

    public function dashboard(): string
    {
        // The organ toggle is a link, so a `?organ=` on the way in switches
        // programme for the rest of the session — as clicking it used to.
        $requested = (string) $this->request->getGet('organ');

        if ($requested !== '') {
            $this->store->setOrgan($requested);
        }

        $organ      = $this->store->organ();
        $recipients = $this->store->recipients();
        $pairs      = $this->store->pairs();

        // The same query the waiting list uses, so the dashboard's idea of
        // "most urgent" cannot drift from the screen it links to.
        $waiting     = $this->store->waitingList();
        $activePairs = array_filter(
            $pairs,
            static fn (array $p): bool => in_array($p['status'], ['active', 'scheduled'], true)
        );

        return view('ui/dashboard', [
            'title'     => ucfirst($organ) . ' Transplant Program',
            'navPage'   => 'dashboard',
            'organ'     => $organ,
            'stats'     => [
                'total'     => count($recipients),
                'unmatched' => count($waiting),
                'pairs'     => count($pairs),
                'active'    => count($activePairs),
            ],
            'maxBar'    => max(count($recipients), count($pairs), 1),
            'topUrgent' => array_slice($waiting, 0, 3),
        ]);
    }

    // ---- Recipients --------------------------------------------------------

    public function recipients(): string
    {
        $filter = $this->bloodTypeFilter();

        return view('ui/recipient_waitlist', [
            'title'   => 'Recipient Waitlist',
            'navPage' => 'recipients',
            'organ'   => $this->store->organ(),
            // Unpaired only, most urgent first and then by score — all of it in
            // SQL, because the score is computed and PHP cannot sort by it.
            'recipients' => $this->store->waitingList($filter === 'all' ? null : $filter),
            'btFilter'   => $filter,
        ]);
    }

    public function addRecipient(): string|RedirectResponse
    {
        return $this->personScreen('recipient', null);
    }

    public function recipient(string $id): string|RedirectResponse
    {
        $recipient = $this->store->findRecipient($id);

        if ($recipient === null) {
            return redirect()->to(site_url('recipients'));
        }

        return $this->personScreen('recipient', $recipient);
    }

    // ---- Donors ------------------------------------------------------------

    public function donors(): string
    {
        return view('ui/donors_list', [
            'title'   => 'Donors List',
            'navPage' => 'donors',
            'organ'   => $this->store->organ(),
            'donors'  => $this->store->availableDonors(),
        ]);
    }

    public function addDonor(): string|RedirectResponse
    {
        return $this->personScreen('donor', null);
    }

    public function donor(string $id): string|RedirectResponse
    {
        $donor = $this->store->findDonor($id);

        if ($donor === null) {
            return redirect()->to(site_url('donors'));
        }

        return $this->personScreen('donor', $donor);
    }

    /**
     * Renders — and on POST applies — the recipient / donor record screen.
     *
     * One method for what the prototype expressed as four `mountPage()` cases,
     * because add and view differ only in whether there is a record to start from.
     *
     * @param array<string, mixed>|null $person
     */
    private function personScreen(string $personType, ?array $person): string|RedirectResponse
    {
        $isRecipient = $personType === 'recipient';
        $organ       = $this->store->organ();
        $mrps        = $this->store->mrps();

        if ($this->request->is('post')) {
            return $this->savePerson($personType, $person);
        }

        $labTests = $person !== null ? $person['labTests'] : UiStore::defaultLabTests($organ, $personType);

        $linkedId = $isRecipient ? ($person['pairedDonorId'] ?? null) : ($person['pairedRecipientId'] ?? null);
        $linked   = $isRecipient ? $this->store->findDonor($linkedId) : $this->store->findRecipient($linkedId);

        return view('ui/person_form', [
            'title'      => $person !== null ? $person['name'] : ($isRecipient ? 'Add Recipient' : 'Add Donor'),
            // Set when a save bounced back; the fields themselves come from
            // old() so nothing typed is lost.
            'error'      => (string) ($this->session->getFlashdata('ui_error') ?? ''),
            // The prototype highlighted a nav item only on the five top-level
            // screens; a record or pair sub-screen left the sidebar unhighlighted.
            'navPage'    => '',
            'organ'      => $organ,
            'mode'       => $person !== null ? 'view' : 'add',
            'personType' => $personType,
            'person'     => $person,
            'linked'     => $linked,
            'labTests'   => $labTests,
            'mrps'       => $mrps,
            'v'          => [
                // Entered, not generated: a real MRN comes from the hospital.
                'mrn'              => $person['id'] ?? '',
                'name'             => $person['name'] ?? '',
                'age'              => isset($person['age']) ? (string) $person['age'] : '',
                'bloodType'        => $person['bloodType'] ?? 'O',
                'phone'            => $person['phone'] ?? '',
                'address'          => $person['address'] ?? '',
                'hospital'         => $person['hospital'] ?? '',
                'notes'            => $person['notes'] ?? '',
                'diagnosis'        => $person['diagnosis'] ?? '',
                'urgency'          => $person['urgency'] ?? 'medium',
                'dateRegistered'   => $person['dateRegistered'] ?? date('Y-m-d'),
                // A saved record shows what was saved; only a blank form falls
                // back to a default. These used to be hard-coded, which meant
                // reopening a record and pressing Save reassigned its MRP to
                // whoever happened to be first in the list.
                'gender'           => $person['gender'] ?? 'Male',
                'firstDialysis'    => $person['firstDialysis'] ?? '',
                'selectedMrp'      => $person['selectedMrp'] ?? ($mrps[0]['id'] ?? ''),
                'donationType'     => $person['donationType'] ?? 'living',
                'relationship'     => $person['relationship'] ?? '',
                'donorGender'      => $person['donorGender'] ?? 'Male',
                'donorCoordinator' => $person['donorCoordinator'] ?? '',
                'donorStatus'      => $person['donorStatus'] ?? 'On Hold',
                'donorMrp'         => $person['donorMrp'] ?? ($mrps[0]['id'] ?? ''),
            ],
        ]);
    }

    /** @param array<string, mixed>|null $person */
    private function savePerson(string $personType, ?array $person): RedirectResponse
    {
        $isRecipient = $personType === 'recipient';

        $base = [
            'name'      => (string) $this->request->getPost('name'),
            'age'       => (int) $this->request->getPost('age'),
            'bloodType' => (string) $this->request->getPost('bloodType'),
            'phone'     => (string) $this->request->getPost('phone'),
            'address'   => (string) $this->request->getPost('address'),
            'hospital'  => (string) $this->request->getPost('hospital'),
            'notes'     => (string) $this->request->getPost('notes'),
            'labTests'  => $this->postedLabTests('labs'),
            'organ'     => $this->store->organ(),
        ];

        if ($isRecipient) {
            $fields = array_merge($base, [
                'type'           => 'recipient',
                'diagnosis'      => (string) $this->request->getPost('diagnosis'),
                'urgency'        => (string) $this->request->getPost('urgency'),
                'dateRegistered' => $person['dateRegistered'] ?? date('Y-m-d'),
                // The form has always posted these; nothing read them until
                // there were columns to put them in.
                'gender'         => (string) $this->request->getPost('gender'),
                'selectedMrp'    => (string) $this->request->getPost('selectedMrp'),
                'firstDialysis'  => (string) $this->request->getPost('firstDialysis'),
            ]);

            if ($person === null) {
                $mrn   = trim((string) $this->request->getPost('mrn'));
                $error = $this->mrnError($mrn, 'recipient');

                if ($error !== '') {
                    return redirect()->back()->withInput()->with('ui_error', $error);
                }

                $this->store->addRecipient(array_merge($fields, ['id' => $mrn]));

                return redirect()->to(site_url('recipients/' . rawurlencode($mrn)));
            }

            $this->store->updateRecipient($person['id'], $fields);

            return redirect()->to(site_url('recipients/' . rawurlencode($person['id'])));
        }

        $fields = array_merge($base, [
            'type'             => 'donor',
            'donationType'     => $person['donationType'] ?? 'living',
            'relationship'     => $person['relationship'] ?? '',
            'donorGender'      => (string) $this->request->getPost('donorGender'),
            'donorMrp'         => (string) $this->request->getPost('donorMrp'),
            'donorStatus'      => (string) $this->request->getPost('donorStatus'),
            'donorCoordinator' => (string) $this->request->getPost('donorCoordinator'),
        ]);

        if ($person === null) {
            $mrn   = trim((string) $this->request->getPost('mrn'));
            $error = $this->mrnError($mrn, 'donor');

            if ($error !== '') {
                return redirect()->back()->withInput()->with('ui_error', $error);
            }

            $this->store->addDonor(array_merge($fields, ['id' => $mrn]));

            return redirect()->to(site_url('donors/' . rawurlencode($mrn)));
        }

        $this->store->updateDonor($person['id'], $fields);

        return redirect()->to(site_url('donors/' . rawurlencode($person['id'])));
    }

    // ---- Pairs -------------------------------------------------------------

    public function pairs(): string
    {
        [$rows, $btFilter, $statusFilter] = $this->filteredPairs();

        return view('ui/pairs_list', [
            'title'        => 'Pairs List',
            'navPage'      => 'pairs',
            'organ'        => $this->store->organ(),
            'rows'         => $rows,
            'btFilter'     => $btFilter,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * "Export CSV" — the same columns the prototype's `exportCSV()` wrote,
     * over the same filtered set, built here so the file matches the table.
     */
    public function exportPairs(): ResponseInterface
    {
        [$rows] = $this->filteredPairs();

        $lines = ['Pair ID,Organ,Status,Recipient,Recipient Blood,Donor,Donor Blood,Scheduled Date,Created'];

        foreach ($rows as $row) {
            $pair      = $row['pair'];
            $recipient = $row['recipient'];
            $donor     = $row['donor'];

            $lines[] = implode(',', [
                $pair['id'],
                $pair['organ'],
                $pair['status'],
                $recipient['name'] ?? $pair['recipientId'],
                $recipient['bloodType'] ?? '',
                $donor['name'] ?? $pair['donorId'],
                $donor['bloodType'] ?? '',
                $pair['scheduledDate'] ?? '',
                $pair['createdDate'],
            ]);
        }

        return $this->response
            ->setContentType('text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="pairs-list.csv"')
            ->setBody(implode("\n", $lines));
    }

    /**
     * Pairs for the current programme, narrowed by the two chip rows and joined
     * to their recipient and donor.
     *
     * @return array{0: list<array{pair: array<string, mixed>, recipient: array<string, mixed>|null, donor: array<string, mixed>|null}>, 1: string, 2: string}
     */
    private function filteredPairs(): array
    {
        $btFilter     = $this->bloodTypeFilter();
        $statusFilter = (string) ($this->request->getGet('status') ?? 'all');

        if (! in_array($statusFilter, UiStore::PAIR_STATUSES, true)) {
            $statusFilter = 'all';
        }

        $rows = [];

        foreach ($this->store->pairs() as $pair) {
            if ($statusFilter !== 'all' && $pair['status'] !== $statusFilter) {
                continue;
            }

            $recipient = $this->store->findRecipient($pair['recipientId']);
            $donor     = $this->store->findDonor($pair['donorId']);

            // A pair matches a blood type if either side has it, as in the source.
            if ($btFilter !== 'all'
                && ($recipient['bloodType'] ?? null) !== $btFilter
                && ($donor['bloodType'] ?? null) !== $btFilter) {
                continue;
            }

            $rows[] = ['pair' => $pair, 'recipient' => $recipient, 'donor' => $donor];
        }

        return [$rows, $btFilter, $statusFilter];
    }

    public function addPair(): string|RedirectResponse
    {
        $organ = $this->store->organ();
        $mrps  = $this->store->mrps();

        if ($this->request->is('post')) {
            return $this->savePair();
        }

        return view('ui/add_pair', [
            'title'     => 'Add Pair',
            'error'     => (string) ($this->session->getFlashdata('ui_error') ?? ''),
            'navPage'   => '',
            'organ'     => $organ,
            'mrps'      => $mrps,
            'entryDate' => date('Y-m-d'),
            'rLabTests' => UiStore::defaultLabTests($organ, 'recipient'),
            'dLabTests' => UiStore::defaultLabTests($organ, 'donor'),
            'v'         => [
                'relationship'   => '',
                'crossmatchDate' => '',
                'rMrn'           => '',
                'rName'          => '',
                'rAge'           => '',
                'rBloodType'     => 'O',
                'rPhone'         => '',
                'rCity'          => '',
                'rHospital'      => '',
                'rDiagnosis'     => '',
                'rUrgency'       => 'medium',
                'rGender'        => 'Male',
                'rFirstDialysis' => '',
                'rMrp'           => $mrps[0]['id'] ?? '',
                'rNotes'         => '',
                'dMrn'           => '',
                'dName'          => '',
                'dAge'           => '',
                'dBloodType'     => 'O',
                'dPhone'         => '',
                'dCity'          => '',
                'dGender'        => 'Male',
                'dMrp'           => $mrps[0]['id'] ?? '',
                'dCoordinator'   => '',
                'dStatus'        => 'On Hold',
                'dNotes'         => '',
            ],
        ]);
    }

    /** Creates the recipient, the donor and the pair that links them. */
    private function savePair(): RedirectResponse
    {
        $organ        = $this->store->organ();
        $entryDate    = date('Y-m-d');
        $relationship = (string) $this->request->getPost('relationship');
        $crossmatch   = (string) $this->request->getPost('crossmatchDate');

        // Both numbers come off the form, and both are checked before either
        // person is stored — half a pair is worse than none.
        $recipientId = trim((string) $this->request->getPost('rMrn'));
        $donorId     = trim((string) $this->request->getPost('dMrn'));

        $error = $this->mrnError($recipientId, 'recipient', 'Recipient MRN')
            ?: $this->mrnError($donorId, 'donor', 'Donor MRN');

        // Separate registers, so the same number on both sides is accepted by
        // the tables; here it would mean a person donating to themselves.
        if ($error === '' && $recipientId === $donorId) {
            $error = 'The recipient and the donor cannot share an MRN.';
        }

        if ($error !== '') {
            return redirect()->back()->withInput()->with('ui_error', $error);
        }

        $this->store->addRecipient([
            'id'             => $recipientId,
            'type'           => 'recipient',
            'organ'          => $organ,
            'name'           => (string) $this->request->getPost('rName'),
            'age'            => (int) $this->request->getPost('rAge'),
            'bloodType'      => (string) $this->request->getPost('rBloodType'),
            'phone'          => (string) $this->request->getPost('rPhone'),
            'address'        => (string) $this->request->getPost('rCity'),
            'hospital'       => (string) $this->request->getPost('rHospital'),
            'diagnosis'      => (string) $this->request->getPost('rDiagnosis'),
            'urgency'        => (string) $this->request->getPost('rUrgency'),
            'gender'         => (string) $this->request->getPost('rGender'),
            'selectedMrp'    => (string) $this->request->getPost('rMrp'),
            'firstDialysis'  => (string) $this->request->getPost('rFirstDialysis'),
            'dateRegistered' => $entryDate,
            'notes'          => (string) $this->request->getPost('rNotes'),
            'labTests'       => $this->postedLabTests('rLabs'),
            'pairedDonorId'  => '',
        ]);

        $this->store->addDonor([
            'id'                => $donorId,
            'type'              => 'donor',
            'organ'             => $organ,
            'name'              => (string) $this->request->getPost('dName'),
            'age'               => (int) $this->request->getPost('dAge'),
            'bloodType'         => (string) $this->request->getPost('dBloodType'),
            'phone'             => (string) $this->request->getPost('dPhone'),
            'address'           => (string) $this->request->getPost('dCity'),
            'hospital'          => '',
            'donationType'      => 'living',
            'relationship'      => $relationship,
            'donorGender'       => (string) $this->request->getPost('dGender'),
            'donorMrp'          => (string) $this->request->getPost('dMrp'),
            'donorStatus'       => (string) $this->request->getPost('dStatus'),
            'donorCoordinator'  => (string) $this->request->getPost('dCoordinator'),
            'notes'             => (string) $this->request->getPost('dNotes'),
            'labTests'          => $this->postedLabTests('dLabs'),
            'pairedRecipientId' => $recipientId,
        ]);

        $this->store->updateRecipient($recipientId, ['pairedDonorId' => $donorId]);

        $this->store->addPair([
            'organ'         => $organ,
            'status'        => 'active',
            'recipientId'   => $recipientId,
            'donorId'       => $donorId,
            'relationship'  => $relationship,
            'scheduledDate' => $crossmatch,
            'notes'         => '',
            'createdDate'   => $entryDate,
        ]);

        return redirect()->to(site_url('pairs'));
    }

    public function pair(string $id): string|RedirectResponse
    {
        $pair = $this->store->findPair($id);

        if ($pair === null || $pair['organ'] !== $this->store->organ()) {
            return redirect()->to(site_url('pairs'));
        }

        $recipient = $this->store->findRecipient($pair['recipientId']);
        $donor     = $this->store->findDonor($pair['donorId']);

        if ($this->request->is('post')) {
            return $this->updatePair($pair, $recipient, $donor);
        }

        return view('ui/pair_profile', [
            'title'     => 'Pair Profile',
            'navPage'   => '',
            'organ'     => $this->store->organ(),
            'pair'      => $pair,
            'recipient' => $recipient,
            'donor'     => $donor,
            'mrps'      => $this->store->mrps(),
            'entryDate' => $recipient['dateRegistered'] ?? date('Y-m-d'),
            'rLabTests' => $recipient['labTests'] ?? [],
            'dLabTests' => $donor['labTests'] ?? [],
            'v'         => [
                // The source seeded Relationship from the donor, falling back to
                // the pair's notes; Save then writes it back to both.
                'relationship'   => $donor['relationship'] ?? $pair['notes'] ?? '',
                'crossmatchDate' => $pair['scheduledDate'] ?? '',
                'pairStatus'     => $pair['status'],
                'rName'          => $recipient['name'] ?? '',
                'rAge'           => isset($recipient['age']) ? (string) $recipient['age'] : '',
                'rBloodType'     => $recipient['bloodType'] ?? 'O',
                'rPhone'         => $recipient['phone'] ?? '',
                'rCity'          => $recipient['address'] ?? '',
                'rHospital'      => $recipient['hospital'] ?? '',
                'rDiagnosis'     => $recipient['diagnosis'] ?? '',
                'rUrgency'       => $recipient['urgency'] ?? 'medium',
                'rGender'        => $recipient['gender'] ?? 'Male',
                'rMrp'           => $recipient['selectedMrp'] ?? '',
                'rFirstDialysis' => $recipient['firstDialysis'] ?? '',
                'rNotes'         => $recipient['notes'] ?? '',
                'dName'          => $donor['name'] ?? '',
                'dAge'           => isset($donor['age']) ? (string) $donor['age'] : '',
                'dBloodType'     => $donor['bloodType'] ?? 'O',
                'dPhone'         => $donor['phone'] ?? '',
                'dCity'          => $donor['address'] ?? '',
                'dGender'        => $donor['donorGender'] ?? 'Male',
                'dMrp'           => $donor['donorMrp'] ?? '',
                'dCoordinator'   => $donor['donorCoordinator'] ?? '',
                'dStatus'        => $donor['donorStatus'] ?? 'On Hold',
                'dNotes'         => $donor['notes'] ?? '',
            ],
        ]);
    }

    /**
     * @param array<string, mixed>      $pair
     * @param array<string, mixed>|null $recipient
     * @param array<string, mixed>|null $donor
     */
    private function updatePair(array $pair, ?array $recipient, ?array $donor): RedirectResponse
    {
        $relationship = (string) $this->request->getPost('relationship');

        $this->store->updatePair($pair['id'], [
            'status'        => (string) $this->request->getPost('pairStatus'),
            'scheduledDate' => (string) $this->request->getPost('crossmatchDate'),
            'relationship'  => $relationship,
        ]);

        if ($recipient !== null) {
            $this->store->updateRecipient($recipient['id'], [
                'name'          => (string) $this->request->getPost('rName'),
                'age'           => (int) $this->request->getPost('rAge') ?: $recipient['age'],
                'bloodType'     => (string) $this->request->getPost('rBloodType'),
                'phone'         => (string) $this->request->getPost('rPhone'),
                'address'       => (string) $this->request->getPost('rCity'),
                'hospital'      => (string) $this->request->getPost('rHospital'),
                'diagnosis'     => (string) $this->request->getPost('rDiagnosis'),
                'urgency'       => (string) $this->request->getPost('rUrgency'),
                'gender'        => (string) $this->request->getPost('rGender'),
                'selectedMrp'   => (string) $this->request->getPost('rMrp'),
                'firstDialysis' => (string) $this->request->getPost('rFirstDialysis'),
                'notes'         => (string) $this->request->getPost('rNotes'),
                'labTests'      => $this->postedLabTests('rLabs'),
            ]);
        }

        if ($donor !== null) {
            $this->store->updateDonor($donor['id'], [
                'name'             => (string) $this->request->getPost('dName'),
                'age'              => (int) $this->request->getPost('dAge') ?: $donor['age'],
                'bloodType'        => (string) $this->request->getPost('dBloodType'),
                'phone'            => (string) $this->request->getPost('dPhone'),
                'address'          => (string) $this->request->getPost('dCity'),
                'notes'            => (string) $this->request->getPost('dNotes'),
                'relationship'     => $relationship,
                'donorGender'      => (string) $this->request->getPost('dGender'),
                'donorMrp'         => (string) $this->request->getPost('dMrp'),
                'donorStatus'      => (string) $this->request->getPost('dStatus'),
                'donorCoordinator' => (string) $this->request->getPost('dCoordinator'),
                'labTests'         => $this->postedLabTests('dLabs'),
            ]);
        }

        return redirect()->to(site_url('pairs/' . rawurlencode($pair['id'])));
    }

    // ---- MRPs --------------------------------------------------------------

    public function mrp(): string
    {
        return view('ui/add_mrp', [
            'title'   => 'Add MRP',
            'navPage' => 'add-mrp',
            'organ'   => $this->store->organ(),
            'mrps'    => $this->store->mrps(),
            'saved'   => (bool) $this->session->getFlashdata('ui_mrp_saved'),
        ]);
    }

    public function addMrp(): RedirectResponse
    {
        $id   = trim((string) $this->request->getPost('id'));
        $name = trim((string) $this->request->getPost('name'));

        // Both required, silently as in the source: it just did not submit.
        if ($id !== '' && $name !== '') {
            $this->store->addMrp($id, $name);
            $this->session->setFlashdata('ui_mrp_saved', true);
        }

        return redirect()->to(site_url('mrp'));
    }

    // ---- Shared ------------------------------------------------------------

    /**
     * Checks a medical record number typed on a form.
     *
     * Returns the message to show, or '' when the number is usable. The MRN is
     * the hospital's own — it comes with the patient, off TrakCare — so the
     * system takes what is entered rather than inventing one. What it can
     * check is that a number was given, that it is one, and that this register
     * does not already hold it: the MRN is the primary key, so a second row
     * under it would be one person filed under another's identity.
     */
    private function mrnError(string $mrn, string $personType, string $label = 'MRN'): string
    {
        $mrn = trim($mrn);

        if ($mrn === '') {
            return $label . ' is required — enter the number from the hospital record.';
        }

        if (preg_match('/^[0-9]+$/', $mrn) !== 1 || (int) $mrn < 1) {
            return $label . ' must be a number.';
        }

        if ($this->store->mrnTaken($mrn, $personType)) {
            $register = $personType === 'recipient' ? 'A recipient' : 'A donor';

            return $register . ' with MRN ' . $mrn . ' is already registered.';
        }

        return '';
    }

    /** The blood-type chip row, validated against the known types. */
    private function bloodTypeFilter(): string
    {
        $filter = (string) ($this->request->getGet('bt') ?? 'all');

        return in_array($filter, UiStore::BLOOD_TYPES, true) ? $filter : 'all';
    }

    /**
     * Rebuilds a lab workup from the hidden inputs the lab card posts.
     *
     * @return list<array<string, string>>
     */
    private function postedLabTests(string $field): array
    {
        $posted = $this->request->getPost($field);

        if (! is_array($posted)) {
            return [];
        }

        $tests = [];

        foreach ($posted as $row) {
            if (! is_array($row) || ! isset($row['name'])) {
                continue;
            }

            $status = (string) ($row['status'] ?? 'pending');

            $tests[] = [
                'id'     => (string) ($row['id'] ?? ''),
                'name'   => (string) $row['name'],
                'status' => in_array($status, UiStore::LAB_STATUSES, true) ? $status : 'pending',
                'result' => (string) ($row['result'] ?? ''),
                'date'   => (string) ($row['date'] ?? ''),
                'notes'  => (string) ($row['notes'] ?? ''),
            ];
        }

        return $tests;
    }
}
