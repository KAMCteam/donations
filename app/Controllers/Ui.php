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

    /**
     * Which fields each card of a record screen owns.
     *
     * A saved record is edited one card at a time, and the cards that are not
     * open render inside a disabled <fieldset>, so the browser posts only the
     * open card's fields. This is the server's half of that: it applies only
     * the fields the named card owns, so a form that arrives with anything
     * else in it — a stale tab, a hand-made post — still cannot reach past the
     * card it claims to be.
     */
    private const PERSON_SECTIONS = [
        'personal' => [
            'name', 'age', 'bloodType', 'phone', 'address',
            'urgent', 'coordinator', 'status', 'gender', 'selectedMrp', 'firstDialysis',
            'donationType', 'relationship', 'donorGender', 'donorMrp', 'donorStatus',
            'donorCoordinator',
        ],
        'labs'  => ['labTests'],
        'notes' => ['notes'],
    ];

    /** The pair screen's cards: the pair itself, then each person's three. */
    private const PAIR_SECTIONS = ['pair', 'recipient', 'rlabs', 'rnotes', 'donor', 'dlabs', 'dnotes'];

    /** The other half of a pair: a recipient is linked with a donor, and back. */
    private const COUNTERPART = ['recipient' => 'donor', 'donor' => 'recipient'];

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
        $links    = $person === null ? [] : $this->linkUrls($personType, $person['id']);

        return view('ui/person_form', [
            'title'      => $person !== null ? $person['name'] : ($isRecipient ? 'Add Recipient' : 'Add Donor'),
            // Set when a save bounced back; the fields themselves come from
            // old() so nothing typed is lost.
            'error'      => (string) ($this->session->getFlashdata('ui_error') ?? ''),
            // Which card the Edit link opened. A new record has no view mode,
            // so every card on it is editable regardless.
            'editing'    => $this->openSection(array_keys(self::PERSON_SECTIONS)),
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
            // "Link with …" is a link to the choice at its own URL; the
            // screen also carries it as a dialog for when JavaScript is on.
            'linkUrl'         => $person === null ? '' : $links['backUrl'] . '/link',
            'linkNewUrl'      => $person === null ? '' : $links['newUrl'],
            'linkExistingUrl' => $person === null ? '' : $links['existingUrl'],
            'v'          => [
                // Entered, not generated: a real MRN comes from the hospital.
                'mrn'              => $person['id'] ?? '',
                'name'             => $person['name'] ?? '',
                'age'              => isset($person['age']) ? (string) $person['age'] : '',
                'bloodType'        => $person['bloodType'] ?? 'O',
                'phone'            => $person['phone'] ?? '',
                'address'          => $person['address'] ?? '',
                'notes'            => $person['notes'] ?? '',
                'urgent'           => (bool) ($person['urgent'] ?? false),
                'coordinator'      => $person['coordinator'] ?? '',
                'status'           => $person['status'] ?? 'pending',
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
            'notes'     => (string) $this->request->getPost('notes'),
            'labTests'  => $this->postedLabTests('labs'),
            'organ'     => $this->store->organ(),
        ];

        if ($isRecipient) {
            $fields = array_merge($base, [
                'type'           => 'recipient',
                'urgent'         => (bool) $this->request->getPost('urgent'),
                'coordinator'    => (string) $this->request->getPost('coordinator'),
                'status'         => (string) $this->request->getPost('status'),
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

            $this->store->updateRecipient($person['id'], $this->sectionFields($fields));

            return redirect()->to(site_url('recipients/' . rawurlencode($person['id'])));
        }

        $fields = array_merge($base, [
            'type'             => 'donor',
            'donationType'     => (string) $this->request->getPost('donationType'),
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

        $this->store->updateDonor($person['id'], $this->sectionFields($fields));

        return redirect()->to(site_url('donors/' . rawurlencode($person['id'])));
    }

    // ---- Pairs -------------------------------------------------------------

    public function pairs(): string
    {
        [$rows, $btFilter, $statusFilter] = $this->filteredPairs();

        return view('ui/pairs_list', [
            'title'        => 'Pairs List',
            'mrps'         => $this->store->mrps(),
            'navPage'      => 'pairs',
            'organ'        => $this->store->organ(),
            'rows'         => $rows,
            'btFilter'     => $btFilter,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * "Export PDF" — the Pairs List as a printed sheet.
     *
     * A page rather than a file: the browser's own print dialog turns it into
     * the PDF, and Save as PDF is where Chrome's destination already points.
     * That is the one route that renders an Arabic name correctly — joining
     * and right-to-left are the print engine's job and it already does them —
     * and it adds nothing to install on a machine running this off XAMPP.
     *
     * Built from the same filtered rows the table shows, so the sheet and the
     * screen can never disagree.
     */
    public function printPairs(): string
    {
        [$rows, $btFilter, $statusFilter] = $this->filteredPairs();

        return view('ui/pairs_print', [
            'rows'       => $rows,
            'mrps'       => $this->store->mrps(),
            'organLabel' => $this->store->organLabel(),
            'filters'    => $this->filterSummary($btFilter, $statusFilter),
            'printedOn'  => date('d/m/Y'),
            'backUrl'    => site_url('pairs') . $this->filterQuery($btFilter, $statusFilter),
        ]);
    }

    /** What the chips were narrowed to, for the line under the title. */
    private function filterSummary(string $btFilter, string $statusFilter): string
    {
        $applied = [];

        if ($btFilter !== 'all') {
            $applied[] = 'Blood type ' . $btFilter;
        }

        if ($statusFilter !== 'all') {
            $applied[] = UiStore::STATUS_OPTIONS[$statusFilter] ?? $statusFilter;
        }

        return $applied === [] ? 'All pairs' : implode(', ', $applied);
    }

    /** The filters as a query string, so Back returns to the same view. */
    private function filterQuery(string $btFilter, string $statusFilter): string
    {
        $query = array_filter(
            ['bt' => $btFilter, 'status' => $statusFilter],
            static fn (string $value): bool => $value !== 'all'
        );

        return $query === [] ? '' : '?' . http_build_query($query);
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

        if (! isset(UiStore::STATUS_OPTIONS[$statusFilter])) {
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

        // Reached from a record's "Link with…": that person is already on the
        // system, so their half is filled in and fixed and the form collects
        // only the other one.
        $fixedSide = $this->fixedSide();
        $fixed     = $fixedSide === '' ? null : $this->findPerson($fixedSide, (string) $this->request->getGet($fixedSide));

        if ($fixedSide !== '' && $fixed === null) {
            return redirect()->to(site_url('pairs/new'));
        }

        if ($fixed !== null) {
            $open = $this->store->openPairFor($fixedSide, $fixed['id']);

            if ($open !== null) {
                return redirect()->to(site_url('pairs/' . rawurlencode($open['id'])));
            }
        }

        $prefix = $fixedSide === 'recipient' ? 'r' : 'd';

        return view('ui/add_pair', [
            'title'     => 'Add Pair',
            'error'     => (string) ($this->session->getFlashdata('ui_error') ?? ''),
            'navPage'   => '',
            'organ'     => $organ,
            'mrps'      => $mrps,
            'entryDate' => date('Y-m-d'),
            'fixedSide' => $fixedSide,
            'fixed'     => $fixed,
            'rLabTests' => $fixedSide === 'recipient' ? $fixed['labTests'] : UiStore::defaultLabTests($organ, 'recipient'),
            'dLabTests' => $fixedSide === 'donor' ? $fixed['labTests'] : UiStore::defaultLabTests($organ, 'donor'),
            'v'         => ($fixed === null ? [] : $this->fixedValues($prefix, $fixedSide, $fixed)) + [
                'relationship'   => '',
                'crossmatchDate' => '',
                'rMrn'           => '',
                'rName'          => '',
                'rAge'           => '',
                'rBloodType'     => 'O',
                'rPhone'         => '',
                'rCity'          => '',
                'rUrgent'        => false,
                'rCoordinator'   => '',
                'rGender'        => 'Male',
                'rFirstDialysis' => '',
                'rMrp'           => $mrps[0]['id'] ?? '',
                'rNotes'         => '',
                'dMrn'           => '',
                'dType'          => 'living_related',
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

    /**
     * Which half of the pair Add Pair was given, or '' when both are new.
     *
     * Read from the query string on the way in and from a hidden field on the
     * way back, so a save knows the same thing the form did.
     */
    private function fixedSide(): string
    {
        $side = $this->request->is('post')
            ? (string) $this->request->getPost('fixedSide')
            : ((string) $this->request->getGet('recipient') !== '' ? 'recipient'
                : ((string) $this->request->getGet('donor') !== '' ? 'donor' : ''));

        return isset(self::COUNTERPART[$side]) ? $side : '';
    }

    /**
     * The known person's record in the shape Add Pair's fields expect.
     *
     * @param array<string, mixed> $person
     *
     * @return array<string, string>
     */
    private function fixedValues(string $prefix, string $side, array $person): array
    {
        $isRecipient = $side === 'recipient';

        return [
            $prefix . 'Mrn'       => (string) $person['id'],
            $prefix . 'Name'      => (string) $person['name'],
            $prefix . 'Age'       => (string) $person['age'],
            $prefix . 'BloodType' => (string) $person['bloodType'],
            $prefix . 'Phone'     => (string) $person['phone'],
            $prefix . 'City'      => (string) $person['address'],
            $prefix . 'Gender'    => (string) ($isRecipient ? $person['gender'] : $person['donorGender']),
            $prefix . 'Mrp'       => (string) ($isRecipient ? $person['selectedMrp'] : $person['donorMrp']),
            $prefix . 'Notes'     => (string) $person['notes'],
        ] + ($isRecipient ? [
            'rUrgent'        => (bool) $person['urgent'],
            'rCoordinator'   => (string) $person['coordinator'],
            'rFirstDialysis' => (string) $person['firstDialysis'],
        ] : [
            'dType'        => (string) $person['donationType'],
            'dCoordinator' => (string) $person['donorCoordinator'],
            'dStatus'      => (string) $person['donorStatus'],
            'relationship' => (string) $person['relationship'],
        ]);
    }

    /**
     * Creates the pair, and whichever of the two people is new.
     *
     * Reached from Pairs with both sides new, or from a record's "Link with a
     * new …" with that side already on the system — `fixedSide` says which,
     * and that half is neither re-validated as a new MRN nor written again.
     */
    private function savePair(): RedirectResponse
    {
        $organ        = $this->store->organ();
        $entryDate    = date('Y-m-d');
        $relationship = (string) $this->request->getPost('relationship');
        $crossmatch   = (string) $this->request->getPost('crossmatchDate');
        $fixedSide    = $this->fixedSide();

        // Both numbers come off the form, and both are checked before either
        // person is stored — half a pair is worse than none.
        $recipientId = trim((string) $this->request->getPost('rMrn'));
        $donorId     = trim((string) $this->request->getPost('dMrn'));

        $error = $fixedSide === 'recipient' ? '' : $this->mrnError($recipientId, 'recipient', 'Recipient MRN');
        $error = $error ?: ($fixedSide === 'donor' ? '' : $this->mrnError($donorId, 'donor', 'Donor MRN'));

        // Separate registers, so the same number on both sides is accepted by
        // the tables; here it would mean a person donating to themselves.
        if ($error === '' && $recipientId === $donorId) {
            $error = 'The recipient and the donor cannot share an MRN.';
        }

        // The known half is on the system already, so what has to hold is that
        // nothing has paired them since the form was opened.
        if ($error === '' && $fixedSide !== '') {
            $side  = $fixedSide === 'recipient' ? $recipientId : $donorId;
            $error = $this->findPerson($fixedSide, $side) === null
                ? 'That record could not be found.'
                : ($this->store->openPairFor($fixedSide, $side) === null ? '' : 'That record is already in an open pair.');
        }

        if ($error !== '') {
            return redirect()->back()->withInput()->with('ui_error', $error);
        }

        if ($fixedSide !== 'recipient') {
            $this->store->addRecipient([
                'id'             => $recipientId,
                'type'           => 'recipient',
                'organ'          => $organ,
                'name'           => (string) $this->request->getPost('rName'),
                'age'            => (int) $this->request->getPost('rAge'),
                'bloodType'      => (string) $this->request->getPost('rBloodType'),
                'phone'          => (string) $this->request->getPost('rPhone'),
                'address'        => (string) $this->request->getPost('rCity'),
                'urgent'         => (bool) $this->request->getPost('rUrgent'),
                'coordinator'    => (string) $this->request->getPost('rCoordinator'),
                'gender'         => (string) $this->request->getPost('rGender'),
                'selectedMrp'    => (string) $this->request->getPost('rMrp'),
                'firstDialysis'  => (string) $this->request->getPost('rFirstDialysis'),
                'dateRegistered' => $entryDate,
                'notes'          => (string) $this->request->getPost('rNotes'),
                'labTests'       => $this->postedLabTests('rLabs'),
            ]);
        }

        if ($fixedSide !== 'donor') {
            $this->store->addDonor([
                'id'               => $donorId,
                'type'             => 'donor',
                'organ'            => $organ,
                'name'             => (string) $this->request->getPost('dName'),
                'age'              => (int) $this->request->getPost('dAge'),
                'bloodType'        => (string) $this->request->getPost('dBloodType'),
                'phone'            => (string) $this->request->getPost('dPhone'),
                'address'          => (string) $this->request->getPost('dCity'),
                'donationType'     => (string) $this->request->getPost('dType'),
                'relationship'     => $relationship,
                'donorGender'      => (string) $this->request->getPost('dGender'),
                'donorMrp'         => (string) $this->request->getPost('dMrp'),
                'donorStatus'      => (string) $this->request->getPost('dStatus'),
                'donorCoordinator' => (string) $this->request->getPost('dCoordinator'),
                'notes'            => (string) $this->request->getPost('dNotes'),
                'labTests'         => $this->postedLabTests('dLabs'),
            ]);
        }

        // The donors list shows the relationship, so an existing donor picks
        // up the one entered here too.
        if ($fixedSide === 'donor' && $relationship !== '') {
            $this->store->updateDonor($donorId, ['relationship' => $relationship]);
        }

        $pairId = $this->store->addPair([
            'organ'         => $organ,
            'status'        => 'active',
            'recipientId'   => $recipientId,
            'donorId'       => $donorId,
            'relationship'  => $relationship,
            'scheduledDate' => $crossmatch,
            'createdDate'   => $entryDate,
        ]);

        return redirect()->to(site_url('pairs/' . rawurlencode((string) $pairId)));
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
            'editing'   => $this->openSection(self::PAIR_SECTIONS),
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
                'rUrgent'        => (bool) ($recipient['urgent'] ?? false),
                'rCoordinator'   => $recipient['coordinator'] ?? '',
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
                'dType'          => $donor['donationType'] ?? 'living_related',
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
        $back    = redirect()->to(site_url('pairs/' . rawurlencode($pair['id'])));
        $section = (string) $this->request->getPost('section');
        $post    = fn (string $field): string => (string) $this->request->getPost($field);

        // One card at a time, so each branch writes only what its own card
        // collects. Relationship sits on the pair and on the donor — the
        // donors list shows it — so the pair card keeps the two in step.
        if ($section === 'pair') {
            $this->store->updatePair($pair['id'], [
                'status'        => $post('pairStatus'),
                'scheduledDate' => $post('crossmatchDate'),
                'relationship'  => $post('relationship'),
            ]);

            if ($donor !== null) {
                $this->store->updateDonor($donor['id'], ['relationship' => $post('relationship')]);
            }

            return $back;
        }

        if ($recipient !== null && $section === 'recipient') {
            $this->store->updateRecipient($recipient['id'], [
                'name'          => $post('rName'),
                'age'           => (int) $post('rAge') ?: $recipient['age'],
                'bloodType'     => $post('rBloodType'),
                'phone'         => $post('rPhone'),
                'address'       => $post('rCity'),
                'urgent'        => (bool) $post('rUrgent'),
                'coordinator'   => $post('rCoordinator'),
                'gender'        => $post('rGender'),
                'selectedMrp'   => $post('rMrp'),
                'firstDialysis' => $post('rFirstDialysis'),
            ]);
        }

        if ($recipient !== null && $section === 'rlabs') {
            $this->store->updateRecipient($recipient['id'], ['labTests' => $this->postedLabTests('rLabs')]);
        }

        if ($recipient !== null && $section === 'rnotes') {
            $this->store->updateRecipient($recipient['id'], ['notes' => $post('rNotes')]);
        }

        if ($donor !== null && $section === 'donor') {
            $this->store->updateDonor($donor['id'], [
                'name'             => $post('dName'),
                'age'              => (int) $post('dAge') ?: $donor['age'],
                'bloodType'        => $post('dBloodType'),
                'phone'            => $post('dPhone'),
                'address'          => $post('dCity'),
                'donorGender'      => $post('dGender'),
                'donationType'     => $post('dType'),
                'donorMrp'         => $post('dMrp'),
                'donorStatus'      => $post('dStatus'),
                'donorCoordinator' => $post('dCoordinator'),
            ]);
        }

        if ($donor !== null && $section === 'dlabs') {
            $this->store->updateDonor($donor['id'], ['labTests' => $this->postedLabTests('dLabs')]);
        }

        if ($donor !== null && $section === 'dnotes') {
            $this->store->updateDonor($donor['id'], ['notes' => $post('dNotes')]);
        }

        return $back;
    }

    // ---- Linking a person to a counterpart ---------------------------------

    public function linkRecipient(string $id): string|RedirectResponse
    {
        return $this->linkChoice('recipient', $id);
    }

    public function linkDonor(string $id): string|RedirectResponse
    {
        return $this->linkChoice('donor', $id);
    }

    public function linkRecipientExisting(string $id): string|RedirectResponse
    {
        return $this->linkExisting('recipient', $id);
    }

    public function linkDonorExisting(string $id): string|RedirectResponse
    {
        return $this->linkExisting('donor', $id);
    }

    /**
     * The two ways to pair somebody: with a counterpart who is not on the
     * system yet, or with one who is.
     *
     * The record screen opens this as a dialog. This is the page behind it, so
     * the choice is reachable with JavaScript off and by its own URL.
     */
    private function linkChoice(string $personType, string $id): string|RedirectResponse
    {
        $person = $this->findPerson($personType, $id);

        if ($person === null) {
            return redirect()->to(site_url($personType === 'recipient' ? 'recipients' : 'donors'));
        }

        $open = $this->store->openPairFor($personType, $person['id']);

        // Already paired: there is nothing to choose, so show the pair.
        if ($open !== null) {
            return redirect()->to(site_url('pairs/' . rawurlencode($open['id'])));
        }

        return view('ui/link_choice', [
            'title'      => 'Link ' . $person['name'],
            'navPage'    => '',
            'organ'      => $this->store->organ(),
            'personType' => $personType,
            'person'     => $person,
        ] + $this->linkUrls($personType, $person['id']));
    }

    /**
     * Pick the counterpart from those already registered and not yet paired.
     *
     * The list is one form: each row's button carries that person's MRN, and
     * the relationship and crossmatch date at the top are filled in once and
     * ride along with whichever row is chosen.
     */
    private function linkExisting(string $personType, string $id): string|RedirectResponse
    {
        $person = $this->findPerson($personType, $id);

        if ($person === null) {
            return redirect()->to(site_url($personType === 'recipient' ? 'recipients' : 'donors'));
        }

        $counterpart = self::COUNTERPART[$personType];

        if ($this->request->is('post')) {
            return $this->linkToExisting($personType, $person);
        }

        $candidates = $counterpart === 'donor'
            ? $this->store->availableDonors()
            : $this->store->waitingList();

        // One person can hold a row in both registers under the one hospital
        // number. Offering them as their own counterpart would only be
        // refused, so they are not offered.
        $candidates = array_values(array_filter(
            $candidates,
            static fn (array $row): bool => (string) $row['id'] !== (string) $person['id']
        ));

        return view('ui/link_existing', [
            'title'       => 'Link ' . $person['name'],
            'navPage'     => '',
            'organ'       => $this->store->organ(),
            'personType'  => $personType,
            'counterpart' => $counterpart,
            'person'      => $person,
            'candidates'  => $candidates,
            'error'       => (string) ($this->session->getFlashdata('ui_error') ?? ''),
        ] + $this->linkUrls($personType, $person['id']));
    }

    /**
     * @param array<string, mixed> $person
     */
    private function linkToExisting(string $personType, array $person): RedirectResponse
    {
        $counterpart = self::COUNTERPART[$personType];
        $otherMrn    = trim((string) $this->request->getPost('mrn'));
        $other       = $this->findPerson($counterpart, $otherMrn);

        if ($other === null) {
            return redirect()->back()->with('ui_error', 'Choose somebody from the list to link with.');
        }

        $recipientId = $personType === 'recipient' ? $person['id'] : $other['id'];
        $donorId     = $personType === 'recipient' ? $other['id'] : $person['id'];
        $error       = $this->pairableError($recipientId, $donorId);

        if ($error !== '') {
            return redirect()->back()->with('ui_error', $error);
        }

        $pairId = $this->store->addPair([
            'organ'         => $this->store->organ(),
            'status'        => 'active',
            'recipientId'   => $recipientId,
            'donorId'       => $donorId,
            'relationship'  => (string) $this->request->getPost('relationship'),
            'scheduledDate' => (string) $this->request->getPost('crossmatchDate'),
        ]);

        // The donors list shows the relationship, so the donor carries it too.
        $this->store->updateDonor($donorId, ['relationship' => (string) $this->request->getPost('relationship')]);

        return redirect()->to(site_url('pairs/' . rawurlencode((string) $pairId)));
    }

    /**
     * Why these two cannot be paired, or '' when they can.
     *
     * PairModel::link() enforces the same rules and throws; checking here
     * turns what would be a stack trace into a sentence on the screen.
     */
    private function pairableError(string $recipientMrn, string $donorMrn): string
    {
        if ($recipientMrn === $donorMrn) {
            return 'The recipient and the donor cannot be the same person.';
        }

        if ($this->store->openPairFor('recipient', $recipientMrn) !== null) {
            return 'That recipient is already in an open pair.';
        }

        if ($this->store->openPairFor('donor', $donorMrn) !== null) {
            return 'That donor is already in an open pair.';
        }

        return '';
    }

    /** @return array<string, mixed>|null */
    private function findPerson(string $personType, ?string $id): ?array
    {
        return $personType === 'recipient'
            ? $this->store->findRecipient($id)
            : $this->store->findDonor($id);
    }

    /**
     * The two destinations the choice offers.
     *
     * @return array{newUrl: string, existingUrl: string, backUrl: string}
     */
    private function linkUrls(string $personType, string $id): array
    {
        $base = ($personType === 'recipient' ? 'recipients/' : 'donors/') . rawurlencode($id);

        return [
            // Add Pair with this person already filled in; the form collects
            // the other one.
            'newUrl'      => site_url('pairs/new') . '?' . $personType . '=' . rawurlencode($id),
            'existingUrl' => site_url($base . '/link/existing'),
            'backUrl'     => site_url($base),
        ];
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
     * The card a record screen was asked to open for editing.
     *
     * '' — the screen's own default — is every card read-only. An unknown name
     * is the same as none, so a mistyped link opens the record rather than an
     * error.
     *
     * @param list<string> $known
     */
    private function openSection(array $known): string
    {
        $section = (string) ($this->request->getGet('edit') ?? '');

        return in_array($section, $known, true) ? $section : '';
    }

    /**
     * Narrows a screen's posted fields to the ones the card it came from owns.
     *
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private function sectionFields(array $fields): array
    {
        $section = (string) $this->request->getPost('section');
        $owned   = self::PERSON_SECTIONS[$section] ?? [];

        // `type` and `organ` say which register the row is in, not what the
        // card collects, so every save carries them.
        return array_intersect_key(
            $fields,
            array_flip([...$owned, 'type', 'organ'])
        );
    }

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
