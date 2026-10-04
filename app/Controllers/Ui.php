<?php

namespace App\Controllers;

use App\Libraries\RecordBlocks;
use App\Libraries\UiStore;
use App\Models\PairModel;
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
            'name', 'age', 'birthDate', 'bloodType', 'phone', 'address',
            'urgent', 'coordinator', 'status', 'gender', 'selectedMrp',
            'dialysisType', 'firstDialysis', 'dateRegistered',
            'donationType', 'relationship', 'donorGender', 'donorMrp', 'donorStatus',
            'donorCoordinator',
        ],
        'labs'  => ['labTests'],
        'notes' => ['notes'],
    ];

    /**
     * The cards a potential donor has on a recipient's screen.
     *
     * One set per candidate, so the section a save names says which of several
     * donors it is about: `pd12-personal`, `pd12-labs`, `pd12-notes`.
     */
    private const CANDIDATE_SECTIONS = ['personal', 'labs', 'notes'];

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
        return view('ui/login', ['title' => 'User login']);
    }

    public function attemptLogin(): string|RedirectResponse
    {
        $id       = trim((string) $this->request->getPost('id'));
        $password = trim((string) $this->request->getPost('password'));

        // The prototype accepted any non-empty pair; wire this to the real
        // staff directory before the screens go anywhere near production.
        if ($id === '' || $password === '') {
            return view('ui/login', [
                'title' => 'User login',
                'id'    => $id,
                'error' => 'Please enter your User ID and password.',
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
        $waiting = $this->store->waitingList();

        // The physician the last stat is about. `?mrp=` chooses; with nothing
        // chosen, or something chosen that is no longer on the list, it falls
        // back to the first rather than showing a count belonging to nobody.
        $mrps        = $this->store->mrps();
        $mrpIds      = array_column($mrps, 'id');
        $selectedMrp = (string) $this->request->getGet('mrp');

        if (! in_array($selectedMrp, $mrpIds, true)) {
            $selectedMrp = $mrpIds[0] ?? '';
        }

        return view('ui/dashboard', [
            'title'       => ucfirst($organ) . ' Transplant Program',
            'navPage'     => 'dashboard',
            'organ'       => $organ,
            'stats'       => [
                'total'     => count($recipients),
                'unmatched' => count($waiting),
                'pairs'     => count($pairs),
                'mrp'       => $this->store->recipientCountForMrp($selectedMrp),
            ],
            'maxBar'      => max(count($recipients), count($pairs), 1),
            'topUrgent'   => array_slice($waiting, 0, 3),
            'mrps'        => $mrps,
            'selectedMrp' => $selectedMrp,
        ]);
    }

    // ---- Recipients --------------------------------------------------------

    public function recipients(): string
    {
        $filter = $this->bloodTypeFilter();
        $status = $this->personStatusFilter();
        $query  = $this->listQuery();

        return view('ui/recipient_waitlist', [
            'title'   => 'Recipient Waitlist',
            'navPage' => 'recipients',
            'organ'   => $this->store->organ(),
            // Unpaired only, most urgent first and then by score — all of it in
            // SQL, because the score is computed and PHP cannot sort by it.
            'recipients' => $this->store->waitingList(
                $filter === 'all' ? null : $filter,
                $status === 'all' ? null : $status,
                $query
            ),
            'btFilter'     => $filter,
            'statusFilter' => $status,
            'searchQuery'  => $query,
            'searchPlaceholder' => 'Search this waitlist by MRN or name',
        ]);
    }

    /**
     * The waitlist as a printed sheet, under whatever the chips were set to.
     *
     * The filtered rows and no others, in the columns the screen shows — the
     * delete button aside, which is not a column so much as a control.
     */
    public function printRecipients(): string
    {
        $filter = $this->bloodTypeFilter();
        $status = $this->personStatusFilter();
        $query  = $this->listQuery();
        $rows   = $this->store->waitingList(
            $filter === 'all' ? null : $filter,
            $status === 'all' ? null : $status,
            $query
        );

        $score = static fn (?float $value): string => $value === null ? '' : number_format($value, 1);

        return view('ui/register_print', [
            'sheetTitle' => 'Recipient Waitlist',
            'organLabel' => $this->store->organLabel(),
            'count'      => ui_plural(count($rows), 'unmatched recipient'),
            'filters'    => $this->registerFilterSummary($filter, $status, $query),
            'printedOn'  => date('d/m/Y'),
            'headers'    => ['#', 'Name', 'MRN', 'Age', 'Gender', 'Blood Group', 'Score', 'Urgent'],
            'rows'       => array_map(static fn (int $i, array $r): array => [
                [(string) ($i + 1), 'c-pairno'],
                [(string) $r['name'], 'c-name'],
                [(string) $r['id'], 'c-mono'],
                [(string) $r['age'], ''],
                [(string) $r['gender'], ''],
                [(string) $r['bloodType'], 'c-mono'],
                [$score($r['score'] ?? null), 'c-mono'],
                [$r['urgent'] ? 'Urgent' : 'Not Urgent', ''],
            ], array_keys($rows), $rows),
            'empty'      => 'No recipients match these filters.',
            'backUrl'    => site_url('recipients') . $this->registerFilterQuery($filter, $status, $query),
            'backLabel'  => 'Back to Recipient Waitlist',
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
        // The same chips the waitlist has, narrowing the same way: the two
        // registers are read with the same question in mind.
        $filter = $this->bloodTypeFilter();
        $status = $this->personStatusFilter();
        $query  = $this->listQuery();

        return view('ui/donors_list', [
            'title'    => 'Donors List',
            'navPage'  => 'donors',
            'organ'    => $this->store->organ(),
            'donors'   => $this->store->availableDonors(
                $filter === 'all' ? null : $filter,
                $status === 'all' ? null : $status,
                $query
            ),
            'btFilter'     => $filter,
            'statusFilter' => $status,
            'searchQuery'  => $query,
            'searchPlaceholder' => 'Search this register by MRN or name',
        ]);
    }

    /** The donor register as a printed sheet, under its own chips. */
    public function printDonors(): string
    {
        $filter = $this->bloodTypeFilter();
        $status = $this->personStatusFilter();
        $query  = $this->listQuery();
        $rows   = $this->store->availableDonors(
            $filter === 'all' ? null : $filter,
            $status === 'all' ? null : $status,
            $query
        );

        return view('ui/register_print', [
            'sheetTitle' => 'Donors List',
            'organLabel' => $this->store->organLabel(),
            'count'      => ui_plural(count($rows), 'unmatched donor'),
            'filters'    => $this->registerFilterSummary($filter, $status, $query),
            'printedOn'  => date('d/m/Y'),
            'headers'    => ['Name', 'MRN', 'Age', 'Gender', 'Blood Group', 'Type', 'Labs'],
            'rows'       => array_map(static function (array $d): array {
                $progress = UiStore::labProgress($d['labTests']);

                return [
                    [(string) $d['name'], 'c-name'],
                    [(string) $d['id'], 'c-mono'],
                    [(string) $d['age'], ''],
                    [(string) $d['donorGender'], ''],
                    [(string) $d['bloodType'], 'c-mono'],
                    [UiStore::DONATION_TYPES[$d['donationType']] ?? (string) $d['donationType'], ''],
                    [$progress['done'] . '/' . $progress['total'], 'c-mono'],
                ];
            }, $rows),
            'empty'      => 'No unmatched donors match these filters.',
            'backUrl'    => site_url('donors') . $this->registerFilterQuery($filter, $status, $query),
            'backLabel'  => 'Back to Donors List',
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
            // A card belonging to one of this recipient's potential donors,
            // rather than to the recipient themselves.
            $card = $this->candidateCard((string) $this->request->getPost('section'));

            if ($isRecipient && $person !== null && $card !== null) {
                return $this->saveCandidateCard($person, $card[0], $card[1]);
            }

            return $this->savePerson($personType, $person);
        }

        $labTests = $person !== null ? $person['labTests'] : UiStore::defaultLabTests($organ, $personType);

        $linkedId = $isRecipient ? ($person['pairedDonorId'] ?? null) : ($person['pairedRecipientId'] ?? null);
        $linked   = $isRecipient ? $this->store->findDonor($linkedId) : $this->store->findRecipient($linkedId);
        $links    = $person === null ? [] : $this->linkUrls($personType, $person['id']);

        // A recipient's donors, and which of them the address is asking for.
        // The first by default, so opening the record shows somebody rather
        // than a row of tabs with nothing under it.
        $donorTabs = $isRecipient && $person !== null ? ($person['donors'] ?? []) : [];
        $openTab   = (int) ($this->request->getGet('donor') ?? 1);
        $openTab   = $openTab >= 1 && $openTab <= count($donorTabs) ? $openTab : ($donorTabs === [] ? 0 : 1);
        $openDonor = $openTab === 0 ? null : $this->store->findDonor($donorTabs[$openTab - 1]['donorId']);

        // The cards on this screen are the record's own plus one set for each
        // potential donor, so which card is open is decided against both.
        $sections = array_keys(self::PERSON_SECTIONS);

        foreach ($donorTabs as $tab) {
            foreach (self::CANDIDATE_SECTIONS as $card) {
                $sections[] = 'pd' . $tab['id'] . '-' . $card;
            }
        }

        // Whose candidate this new donor is being entered as, if the screen
        // was opened from a recipient's own. Nothing on the Add Donor screen
        // changes — only where the record goes when it is saved.
        $forRecipient = $person === null && ! $isRecipient
            ? trim((string) ($this->request->getGet('for') ?? ''))
            : '';

        if ($forRecipient !== '' && $this->store->findRecipient($forRecipient) === null) {
            $forRecipient = '';
        }

        return view('ui/person_form', [
            'title'      => $person !== null ? $person['name'] : ($isRecipient ? 'Add Recipient' : 'Add Donor'),
            // Set when a save bounced back; the fields themselves come from
            // old() so nothing typed is lost.
            'error'      => (string) ($this->session->getFlashdata('ui_error') ?? ''),
            // Which card the Edit link opened. A new record has no view mode,
            // so every card on it is editable regardless.
            'editing'    => $this->openSection($sections),
            // The prototype highlighted a nav item only on the five top-level
            // screens; a record or pair sub-screen left the sidebar unhighlighted.
            'navPage'    => '',
            'organ'      => $organ,
            'mode'       => $person !== null ? 'view' : 'add',
            'personType' => $personType,
            'person'     => $person,
            'linked'     => $linked,
            'donorTabs'  => $donorTabs,
            'openTab'    => $openTab,
            'openDonor'  => $openDonor,
            // The open tab's donor, in the shape their cards expect: the
            // same d-prefixed fields the pair screen collects, because they
            // are the same cards.
            'openDonorLabs' => $openDonor['labTests'] ?? [],
            'donorValues'   => $openDonor === null ? [] : $this->donorValues($openDonor),
            // Every pair this recipient has had, for the archive under the
            // tabs. Empty until there has been one.
            'pairHistory'   => $isRecipient && $person !== null
                ? $this->store->pairingHistory($person['id'])
                : [],
            'forRecipient'  => $forRecipient,
            // Who the choice dialog can offer: everybody on the register this
            // recipient is not already considering.
            'candidates' => $isRecipient && $person !== null
                ? $this->offerableDonors($person['id'], $donorTabs)
                : [],
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
                'birthDate'        => $person['birthDate'] ?? '',
                'bloodType'        => $person['bloodType'] ?? 'O',
                'phone'            => $person['phone'] ?? '',
                'address'          => $person['address'] ?? '',
                'notes'            => $person['notes'] ?? '',
                'urgent'           => (bool) ($person['urgent'] ?? false),
                'coordinator'      => $person['coordinator'] ?? '',
                'status'           => $person['status'] ?? 'pending',
                // A new record is dated today, which is almost always right and
                // is the one date somebody can correct without having to know
                // it was there. A saved one shows the day it was entered.
                'dateRegistered'   => $person['dateRegistered'] ?? date('Y-m-d'),
                'dialysisType'     => $person['dialysisType'] ?? '',
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

        $error = $this->futureDateError(['birthDate', 'firstDialysis', 'dateRegistered']);

        if ($error !== '') {
            return redirect()->back()->withInput()->with('ui_error', $error);
        }

        $base = [
            'name'      => (string) $this->request->getPost('name'),
            // The form asks for the date of birth; the age is worked out from
            // it. The number still travels, for the records entered before
            // there was a date to work it out from.
            'age'       => (int) $this->request->getPost('age'),
            'birthDate' => (string) $this->request->getPost('birthDate'),
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
                // Collected now rather than carried over: the screen offers the
                // date and defaults it to today, so what comes back is what is
                // stored.
                'dateRegistered' => (string) $this->request->getPost('dateRegistered'),
                // The form has always posted these; nothing read them until
                // there were columns to put them in.
                'gender'         => (string) $this->request->getPost('gender'),
                'selectedMrp'    => (string) $this->request->getPost('selectedMrp'),
                'dialysisType'   => (string) $this->request->getPost('dialysisType'),
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
            'dateRegistered'   => (string) $this->request->getPost('dateRegistered'),
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

            // Entered from a recipient's screen: this donor is that
            // recipient's candidate, so they are stored off the register and
            // the screen that asked is the one that comes back.
            $forRecipient = trim((string) $this->request->getPost('for'));

            if ($forRecipient !== '' && $this->store->findRecipient($forRecipient) !== null) {
                $this->store->addDonor(array_merge($fields, ['id' => $mrn, 'listed' => false]));
                $this->store->considerDonor($forRecipient, $mrn);

                $recipient = $this->store->findRecipient($forRecipient);

                return redirect()
                    ->to(site_url('recipients/' . rawurlencode($forRecipient))
                        . '?donor=' . count($recipient['donors'] ?? []))
                    ->with('ui_notice', $fields['name'] . ' has been added as a potential donor.');
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
        [$rows, $btFilter, $statusFilter, $query] = $this->filteredPairs();

        return view('ui/pairs_list', [
            'title'        => 'Pairs List',
            'mrps'         => $this->store->mrps(),
            'navPage'      => 'pairs',
            'organ'        => $this->store->organ(),
            'rows'         => $rows,
            'btFilter'     => $btFilter,
            'statusFilter' => $statusFilter,
            'searchQuery'  => $query,
            'searchPlaceholder' => 'Search these pairs by MRN, name or pair number',
        ]);
    }

    // ---- Removing a record -------------------------------------------------

    /**
     * The three lists' delete button, for all three of them.
     *
     * GET asks, POST does. Never the other way round: a GET that deletes goes
     * off on its own the moment a browser prefetches the link or something
     * crawls the page, and on a patient register that is not recoverable.
     *
     * The question is a page of its own so it is still asked with JavaScript
     * off; the lists open the same question as a dialog when it is on.
     */
    public function deleteRecipient(?string $mrn = null): string|RedirectResponse
    {
        return $this->confirmThenDelete(
            $this->store->findRecipient($mrn),
            'recipients/' . rawurlencode((string) $mrn) . '/delete',
            site_url('recipients'),
            'recipient',
            fn (): string => $this->store->deleteRecipient($mrn)
        );
    }

    public function deleteDonor(?string $mrn = null): string|RedirectResponse
    {
        return $this->confirmThenDelete(
            $this->store->findDonor($mrn),
            'donors/' . rawurlencode((string) $mrn) . '/delete',
            site_url('donors'),
            'donor',
            fn (): string => $this->store->deleteDonor($mrn)
        );
    }

    public function deletePair(?string $id = null): string|RedirectResponse
    {
        $pair = $this->store->findPair($id);

        return $this->confirmThenDelete(
            $pair,
            'pairs/' . rawurlencode((string) $id) . '/delete',
            site_url('pairs'),
            'pair',
            fn (): string => $this->store->deletePair($id)
        );
    }

    /**
     * Ask on GET, act on POST, and say what happened either way.
     *
     * @param array<string, mixed>|null $record  Null when there is nothing to delete
     * @param callable(): string        $delete  Returns '' or why it was refused
     */
    private function confirmThenDelete(
        ?array $record,
        string $action,
        string $listUrl,
        string $kind,
        callable $delete
    ): string|RedirectResponse {
        if ($record === null) {
            return redirect()->to($listUrl);
        }

        $name = $kind === 'pair'
            ? 'Pair #' . $record['id']
            : (string) $record['name'];

        if (strtolower($this->request->getMethod()) !== 'post') {
            return view('ui/confirm_delete', [
                'title'   => 'Delete ' . $name,
                'navPage' => '',
                'organ'   => $this->store->organ(),
                'name'    => $name,
                'kind'    => $kind,
                'detail'  => $this->deleteDetail($kind, $record),
                'action'  => site_url($action),
                'backUrl' => $listUrl,
            ]);
        }

        $error = $delete();

        $this->session->setFlashdata(
            $error === '' ? 'ui_notice' : 'ui_error',
            $error === '' ? $name . ' has been deleted.' : $error
        );

        return redirect()->to($listUrl);
    }

    /** What exactly goes, spelled out before anyone presses the button. */
    private function deleteDetail(string $kind, array $record): string
    {
        if ($kind === 'pair') {
            $recipient = $this->store->findRecipient($record['recipientId']);
            $donor     = $this->store->findDonor($record['donorId']);

            return 'The link between ' . ($recipient['name'] ?? 'MRN ' . $record['recipientId'])
                . ' and ' . ($donor['name'] ?? 'MRN ' . $record['donorId'])
                . ' will be removed. Both records stay on the register, with their '
                . 'workups, and each can be matched again.';
        }

        return 'MRN ' . $record['id'] . '. The record and its whole lab workup '
            . 'will be removed. This cannot be undone.';
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
        [$rows, $btFilter, $statusFilter, $query] = $this->filteredPairs();

        return view('ui/pairs_print', [
            'rows'       => $rows,
            'mrps'       => $this->store->mrps(),
            'organLabel' => $this->store->organLabel(),
            'filters'    => $this->filterSummary($btFilter, $statusFilter, $query),
            'printedOn'  => date('d/m/Y'),
            'backUrl'    => site_url('pairs') . $this->filterQuery($btFilter, $statusFilter, $query),
        ]);
    }

    // ---- A recipient's donors -----------------------------------------------

    /**
     * Adds somebody already on the register to a recipient's candidates.
     *
     * Posted from the choice dialog on the recipient's own screen. No pair is
     * made and nothing moves on the Donors List: this says only that the two
     * are being looked at together.
     */
    public function considerDonor(string $mrn): RedirectResponse
    {
        $back = site_url('recipients/' . rawurlencode($mrn));

        if ($this->store->findRecipient($mrn) === null) {
            return redirect()->to(site_url('recipients'));
        }

        $error = $this->store->considerDonor($mrn, trim((string) $this->request->getPost('donorMrn')));

        if ($error !== '') {
            return redirect()->to($back)->with('ui_error', $error);
        }

        $recipient = $this->store->findRecipient($mrn);

        return redirect()->to($back . '?donor=' . count($recipient['donors'] ?? []))
            ->with('ui_notice', 'Added as a potential donor.');
    }

    /**
     * Sets one of a recipient's candidates aside.
     *
     * The tab stays, read-only: a donor who was looked at and set aside is
     * part of what happened, and taking the tab away would lose that. The
     * question is asked first, because it cannot be pressed again afterwards.
     */
    public function delinkDonor(string $mrn, string $id): string|RedirectResponse
    {
        [$recipient, $tab, $back] = $this->candidate($mrn, $id);

        if ($recipient === null) {
            return redirect()->to(site_url('recipients'));
        }

        if ($tab === null || $tab['delinked']) {
            return redirect()->to($back);
        }

        if (strtolower($this->request->getMethod()) !== 'post') {
            return view('ui/confirm_delete', [
                'title'   => 'Delink ' . $tab['name'],
                'navPage' => '',
                'organ'   => $this->store->organ(),
                'name'    => $tab['name'],
                'kind'    => 'potential donor',
                'verb'    => 'Delink',
                'detail'  => 'This potential donor will be set to Declined for '
                    . $recipient['name'] . '. Their record stays on the system with its workup, '
                    . 'and they keep their place on this screen — shown as declined, and no longer '
                    . 'editable from it.',
                'action'  => site_url('recipients/' . rawurlencode($mrn) . '/donors/' . rawurlencode($id) . '/delink'),
                'backUrl' => $back,
            ]);
        }

        $error = $this->store->declineCandidate($mrn, $id);

        return redirect()->to($back)->with(
            $error === '' ? 'ui_notice' : 'ui_error',
            $error === '' ? $tab['name'] . ' has been set to Declined.' : $error
        );
    }

    /** Moves a candidate between Active and On Hold, from their own tab. */
    public function candidateStatus(string $mrn, string $id): RedirectResponse
    {
        [$recipient, $tab, $back] = $this->candidate($mrn, $id);

        if ($recipient === null) {
            return redirect()->to(site_url('recipients'));
        }

        if ($tab === null) {
            return redirect()->to($back);
        }

        $error = $this->store->setCandidateStatus($mrn, $id, (string) $this->request->getPost('status'));

        return $error === ''
            ? redirect()->to($back)
            : redirect()->to($back)->with('ui_error', $error);
    }

    /**
     * Makes the pair, from the candidate whose tab is open.
     *
     * The decision the list was leading to: one of them is the donor, the rest
     * are set aside, and the screen that opens is the pair's own. No step in
     * between — everything the pair needs is already on both records.
     */
    public function pairUp(string $mrn, string $id): RedirectResponse
    {
        [$recipient, $tab, $back] = $this->candidate($mrn, $id);

        if ($recipient === null) {
            return redirect()->to(site_url('recipients'));
        }

        if ($tab === null) {
            return redirect()->to($back);
        }

        [$pairId, $error] = $this->store->pairUpCandidate($mrn, $id);

        if ($error !== '') {
            return redirect()->to($back)->with('ui_error', $error);
        }

        return redirect()->to(site_url('pairs/' . rawurlencode($pairId)))
            ->with('ui_notice', $recipient['name'] . ' and ' . $tab['name'] . ' are now a pair.');
    }

    /**
     * Adds a blank test to a candidate's workup, without leaving the screen it
     * is read on.
     */
    public function addCandidateLab(string $mrn, string $id): RedirectResponse
    {
        [$recipient, $tab, $back] = $this->candidate($mrn, $id);

        if ($recipient === null) {
            return redirect()->to(site_url('recipients'));
        }

        if ($tab === null || $tab['delinked']) {
            return redirect()->to($back);
        }

        $back .= '&edit=pd' . $tab['id'] . '-labs';

        $this->keepWhatWasTyped('donor', $tab['donorId'], 'dLabs');

        if ($this->store->addCustomLab($tab['donorId'], 'donor') === 0) {
            $this->session->setFlashdata('ui_error', 'That test could not be added.');
        }

        return redirect()->to($back);
    }

    /** And takes one away again, asking first. */
    public function removeCandidateLab(string $mrn, string $id, string $labId): string|RedirectResponse
    {
        [$recipient, $tab, $back] = $this->candidate($mrn, $id);

        if ($recipient === null) {
            return redirect()->to(site_url('recipients'));
        }

        if ($tab === null || $tab['delinked']) {
            return redirect()->to($back);
        }

        return $this->confirmRemoveLab(
            'donor',
            $tab['donorId'],
            (int) $labId,
            'recipients/' . rawurlencode($mrn) . '/donors/' . rawurlencode($id) . '/labs/' . rawurlencode($labId) . '/delete',
            $back . '&edit=pd' . $tab['id'] . '-labs'
        );
    }

    /**
     * One recipient, one of their candidates, and where to go back to.
     *
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null, 2: string}
     */
    private function candidate(string $mrn, string $id): array
    {
        $recipient = $this->store->findRecipient($mrn);
        $back      = site_url('recipients/' . rawurlencode($mrn));

        if ($recipient === null) {
            return [null, null, $back];
        }

        foreach ($recipient['donors'] ?? [] as $tab) {
            if ((string) $tab['id'] === (string) $id) {
                return [$recipient, $tab, $back . '?donor=' . (int) $tab['number']];
            }
        }

        return [$recipient, null, $back];
    }

    // ---- Tests a record adds for itself ------------------------------------

    /**
     * Adds a blank test to one record's workup and returns to the card.
     *
     * A post, because it creates something. `$back` is where the screen that
     * asked is: a person's own record, or the pair screen showing that
     * person's half of the workup.
     */
    public function addLab(string $personType, string $mrn): RedirectResponse
    {
        $this->keepWhatWasTyped($personType, $mrn, 'labs');

        if ($this->store->addCustomLab($mrn, $personType) === 0) {
            $this->session->setFlashdata('ui_error', 'That test could not be added.');
        }

        return redirect()->to($this->labScreenUrl($personType, $mrn));
    }

    public function addPairLab(string $id, string $side): RedirectResponse
    {
        [$pair, $personType, $mrn] = $this->pairSide($id, $side);

        if ($pair === null) {
            return redirect()->to(site_url('pairs'));
        }

        $back = site_url('pairs/' . rawurlencode($pair['id'])) . '?edit=' . ($side === 'recipient' ? 'rlabs' : 'dlabs');

        $this->keepWhatWasTyped($personType, $mrn, $side === 'recipient' ? 'rLabs' : 'dLabs');

        if ($this->store->addCustomLab($mrn, $personType) === 0) {
            $this->session->setFlashdata('ui_error', 'That test could not be added.');
        }

        return redirect()->to($back);
    }

    /**
     * Asks before taking one away, as every other delete on the platform does.
     *
     * Whatever was recorded against it goes with it, and there is no undo, so
     * it is worth a question.
     */
    public function removeLab(string $personType, string $mrn, string $labId): string|RedirectResponse
    {
        return $this->confirmRemoveLab(
            $personType,
            $mrn,
            (int) $labId,
            $personType . 's/' . rawurlencode($mrn) . '/labs/' . rawurlencode($labId) . '/delete',
            $this->labScreenUrl($personType, $mrn)
        );
    }

    public function removePairLab(string $id, string $side, string $labId): string|RedirectResponse
    {
        [$pair, $personType, $mrn] = $this->pairSide($id, $side);

        if ($pair === null) {
            return redirect()->to(site_url('pairs'));
        }

        return $this->confirmRemoveLab(
            $personType,
            $mrn,
            (int) $labId,
            'pairs/' . rawurlencode($pair['id']) . '/labs/' . rawurlencode($side) . '/' . rawurlencode($labId) . '/delete',
            site_url('pairs/' . rawurlencode($pair['id'])) . '?edit=' . ($side === 'recipient' ? 'rlabs' : 'dlabs')
        );
    }

    /** The one confirmation, whichever screen asked for it. */
    private function confirmRemoveLab(string $personType, string $mrn, int $labId, string $action, string $backUrl): string|RedirectResponse
    {
        $lab = $this->store->customLab($mrn, $personType, $labId);

        if ($lab === null) {
            return redirect()->to($backUrl);
        }

        if (strtolower($this->request->getMethod()) !== 'post') {
            return view('ui/confirm_delete', [
                'title'   => 'Remove ' . $lab['name'],
                'navPage' => '',
                'organ'   => $this->store->organ(),
                'name'    => $lab['name'],
                'kind'    => 'test',
                'detail'  => 'This test was added to this record, so only this record has it. '
                    . 'It will be removed along with the answer and the comment on it. This cannot be undone.',
                'action'  => site_url($action),
                'backUrl' => $backUrl,
            ]);
        }

        $removed = $this->store->removeCustomLab($mrn, $personType, $labId);

        $this->session->setFlashdata(
            $removed ? 'ui_notice' : 'ui_error',
            $removed ? $lab['name'] . ' has been removed.' : 'That test could not be removed.'
        );

        return redirect()->to($backUrl);
    }

    /**
     * Saves the workup the page was holding before adding to it.
     *
     * Add lab is a button inside the card's own form, so pressing it posts
     * everything on the card. Without this, an answer or a comment typed
     * just before it would be thrown away by the redirect — the card comes
     * back from the database, and the database would not have heard.
     */
    private function keepWhatWasTyped(string $personType, string $mrn, string $field): void
    {
        $tests = $this->postedLabTests($field);

        if ($tests === []) {
            return;
        }

        if ($personType === 'recipient') {
            $this->store->updateRecipient($mrn, ['labTests' => $tests]);

            return;
        }

        $this->store->updateDonor($mrn, ['labTests' => $tests]);
    }

    /** Back to the record's own screen, with the workup card open. */
    private function labScreenUrl(string $personType, string $mrn): string
    {
        return site_url($personType . 's/' . rawurlencode($mrn)) . '?edit=labs';
    }

    /**
     * The pair, and which of its two people a screen is asking about.
     *
     * @return array{0: array<string, mixed>|null, 1: string, 2: string}
     */
    private function pairSide(string $id, string $side): array
    {
        $pair = $this->store->findPair($id);

        if ($pair === null
            || $pair['organ'] !== $this->store->organ()
            || ! in_array($side, ['recipient', 'donor'], true)) {
            return [null, '', ''];
        }

        return [
            $pair,
            $side,
            (string) ($side === 'recipient' ? $pair['recipientId'] : $pair['donorId']),
        ];
    }

    // ---- One record on paper -----------------------------------------------

    /**
     * A recipient's, a donor's or a pair's record as a printed sheet.
     *
     * Three routes into one document, because the three sheets differ only in
     * which blocks go on them. The blocks themselves are built here rather
     * than in the view so the sheet stays a renderer: a pair is the pair's own
     * details followed by each person's, in the order the pair screen shows
     * them.
     *
     * A PDF by printing, as the Pairs List sheet is, and for the same reasons.
     */
    public function printRecipient(string $id): string|RedirectResponse
    {
        $recipient = $this->store->findRecipient($id);

        if ($recipient === null) {
            return redirect()->to(site_url('recipients'));
        }

        $donor  = $this->store->findDonor($recipient['pairedDonorId'] ?? null);
        $blocks = [$this->blocks()->recipient($recipient)];

        // Every donor they have been linked with, the declined ones too: a
        // donor who was considered and set aside is part of the record, and a
        // sheet that left them off would read as though they never were.
        if (($recipient['donors'] ?? []) !== []) {
            $blocks[] = $this->blocks()->donors($recipient['donors']);
        }

        return $this->recordSheet(
            'Recipient Record',
            $recipient,
            'Back to record',
            site_url('recipients/' . rawurlencode($recipient['id'])),
            array_merge($blocks, $this->blocks()->workup($recipient, 'Required Lab Tests', 'Clinical Notes'))
        );
    }

    public function printDonor(string $id): string|RedirectResponse
    {
        $donor = $this->store->findDonor($id);

        if ($donor === null) {
            return redirect()->to(site_url('donors'));
        }

        $recipient = $this->store->findRecipient($donor['pairedRecipientId'] ?? null);

        return $this->recordSheet(
            'Donor Record',
            $donor,
            'Back to record',
            site_url('donors/' . rawurlencode($donor['id'])),
            array_merge(
                [$this->blocks()->donor($donor, $recipient)],
                $this->blocks()->workup($donor, 'Required Lab Tests', 'Clinical Notes')
            )
        );
    }

    public function printPair(string $id): string|RedirectResponse
    {
        $pair = $this->store->findPair($id);

        if ($pair === null || $pair['organ'] !== $this->store->organ()) {
            return redirect()->to(site_url('pairs'));
        }

        $recipient = $this->store->findRecipient($pair['recipientId']);
        $donor     = $this->store->findDonor($pair['donorId']);

        $blocks = [$this->blocks()->pair($pair, $recipient, $donor)];

        if ($recipient !== null) {
            $blocks[] = $this->blocks()->recipient($recipient);
            $blocks   = array_merge($blocks, $this->blocks()->workup(
                $recipient,
                'Recipient — Required Lab Tests',
                'Recipient — Clinical Notes'
            ));
        }

        if ($donor !== null) {
            $blocks[] = $this->blocks()->donor($donor, $recipient);
            $blocks   = array_merge($blocks, $this->blocks()->workup(
                $donor,
                'Donor — Required Lab Tests',
                'Donor — Clinical Notes'
            ));
        }

        $subject = trim(($recipient['name'] ?? '?') . ' & ' . ($donor['name'] ?? '?'));

        return view('ui/record_print', [
            'sheetTitle' => 'Pair Record',
            'subject'    => $subject,
            'meta'       => [
                'Pair ' . $pair['id'],
                $this->store->organLabel() . ' Programme',
                'Printed ' . date('d/m/Y'),
            ],
            'blocks'     => $blocks,
            'backUrl'    => site_url('pairs/' . rawurlencode($pair['id'])),
            'backLabel'  => 'Back to pair',
        ]);
    }

    /**
     * The sheet around one person's blocks.
     *
     * @param array<string, mixed>         $person
     * @param list<array<string, mixed>>   $blocks
     */
    private function recordSheet(string $sheetTitle, array $person, string $backLabel, string $backUrl, array $blocks): string
    {
        return view('ui/record_print', [
            'sheetTitle' => $sheetTitle,
            'subject'    => (string) ($person['name'] ?? ''),
            'meta'       => [
                'MRN ' . $person['id'],
                $this->store->organLabel() . ' Programme',
                'Printed ' . date('d/m/Y'),
            ],
            'blocks'     => $blocks,
            'backUrl'    => $backUrl,
            'backLabel'  => $backLabel,
        ]);
    }

    /**
     * What a record looks like on paper.
     *
     * The blocks themselves live in `RecordBlocks`, because Reports prints the
     * same records in bulk and the two sheets must say the same things.
     */
    private function blocks(): RecordBlocks
    {
        return new RecordBlocks($this->store);
    }

    /** An MRP's name from the id the record stores. */
    private function mrpName(string $id): string
    {
        return $this->blocks()->mrpName($id);
    }

    /** What the chips were narrowed to, for the line under the title. */
    private function filterSummary(string $btFilter, string $statusFilter, string $search = ''): string
    {
        $applied = [];

        if ($btFilter !== 'all') {
            $applied[] = 'Blood type ' . $btFilter;
        }

        if ($statusFilter !== 'all') {
            $applied[] = UiStore::STATUS_OPTIONS[$statusFilter] ?? $statusFilter;
        }

        if ($search !== '') {
            $applied[] = 'matching “' . $search . '”';
        }

        return $applied === [] ? 'All pairs' : implode(', ', $applied);
    }

    /** The filters as a query string, so Back returns to the same view. */
    private function filterQuery(string $btFilter, string $statusFilter, string $search = ''): string
    {
        // Each filter drops out of the URL when it is on its own default —
        // `all` for blood type, Active for status — so the address stays short
        // and says only what was actually narrowed.
        $query = array_filter([
            'bt'     => $btFilter === 'all' ? null : $btFilter,
            'status' => $statusFilter === UiStore::PAIRS_DEFAULT_STATUS ? null : $statusFilter,
            'q'      => $search === '' ? null : $search,
        ], static fn (?string $value): bool => $value !== null);

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
        $btFilter = $this->bloodTypeFilter();
        $query    = $this->listQuery();

        // No `status` in the query means the screen's own default rather than
        // everything; `all` is a filter the chips ask for by name.
        $statusFilter = (string) ($this->request->getGet('status') ?? UiStore::PAIRS_DEFAULT_STATUS);

        if ($statusFilter !== 'all' && ! isset(UiStore::STATUS_OPTIONS[$statusFilter])) {
            $statusFilter = UiStore::PAIRS_DEFAULT_STATUS;
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

            // The search box above the list. A pair answers to either of its
            // people, by name or by number, and to its own pair number — which
            // is the column the list leads with.
            if ($query !== '' && ! $this->pairMatches($pair, $recipient, $donor, $query)) {
                continue;
            }

            $rows[] = ['pair' => $pair, 'recipient' => $recipient, 'donor' => $donor];
        }

        return [$rows, $btFilter, $statusFilter, $query];
    }

    /**
     * Whether a pair answers to what was typed into the search box.
     *
     * @param array<string, mixed>      $pair
     * @param array<string, mixed>|null $recipient
     * @param array<string, mixed>|null $donor
     */
    private function pairMatches(array $pair, ?array $recipient, ?array $donor, string $query): bool
    {
        $fields = [
            (string) $pair['id'],
            (string) ($recipient['id'] ?? ''),
            (string) ($recipient['name'] ?? ''),
            (string) ($donor['id'] ?? ''),
            (string) ($donor['name'] ?? ''),
        ];

        foreach ($fields as $field) {
            if ($field !== '' && stripos($field, $query) !== false) {
                return true;
            }
        }

        return false;
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

        if ($fixed !== null && $fixedSide === 'donor') {
            $open = $this->store->openPairFor('donor', $fixed['id']);

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
                // Active is where a pair has always started; it is a field to
                // change now rather than a value to discover afterwards.
                'pairStatus'     => 'active',
                'closedReason'   => '',
                'rStatus'        => UiStore::PAIRS_DEFAULT_STATUS,
                'rMrn'           => '',
                'rName'          => '',
                'rAge'           => '',
                'rBirthDate'     => '',
                'rBloodType'     => 'O',
                'rPhone'         => '',
                'rCity'          => '',
                'rUrgent'        => false,
                'rCoordinator'   => '',
                'rGender'        => 'Male',
                'rDialysisType'  => '',
                'rFirstDialysis' => '',
                'rMrp'           => $mrps[0]['id'] ?? '',
                'rNotes'         => '',
                'dMrn'           => '',
                'dType'          => 'living_related',
                'dName'          => '',
                'dAge'           => '',
                'dBirthDate'     => '',
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
            $prefix . 'BirthDate' => (string) ($person['birthDate'] ?? ''),
            $prefix . 'BloodType' => (string) $person['bloodType'],
            $prefix . 'Phone'     => (string) $person['phone'],
            $prefix . 'City'      => (string) $person['address'],
            $prefix . 'Gender'    => (string) ($isRecipient ? $person['gender'] : $person['donorGender']),
            $prefix . 'Mrp'       => (string) ($isRecipient ? $person['selectedMrp'] : $person['donorMrp']),
            $prefix . 'Notes'     => (string) $person['notes'],
        ] + ($isRecipient ? [
            'rUrgent'        => (bool) $person['urgent'],
            'rCoordinator'   => (string) $person['coordinator'],
            'rDialysisType'  => (string) ($person['dialysisType'] ?? ''),
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

        // The pair's own status, from its card. Anything the menu does not
        // offer is somebody editing the form by hand, and a new pair is
        // Active.
        $pairStatus = (string) $this->request->getPost('pairStatus');
        $pairStatus = isset(UiStore::PAIR_STATUS_OPTIONS[$pairStatus]) ? $pairStatus : 'active';

        // Both numbers come off the form, and both are checked before either
        // person is stored — half a pair is worse than none.
        $recipientId = trim((string) $this->request->getPost('rMrn'));
        $donorId     = trim((string) $this->request->getPost('dMrn'));

        $error = $this->futureDateError(['rBirthDate', 'dBirthDate', 'rFirstDialysis', 'rEntryDate']);
        $error = $error ?: ($fixedSide === 'recipient' ? '' : $this->mrnError($recipientId, 'recipient', 'Recipient MRN'));
        $error = $error ?: ($fixedSide === 'donor' ? '' : $this->mrnError($donorId, 'donor', 'Donor MRN'));

        // Separate registers, so the same number on both sides is accepted by
        // the tables; here it would mean a person donating to themselves.
        if ($error === '' && $recipientId === $donorId) {
            $error = 'The recipient and the donor cannot share an MRN.';
        }

        // The known half is on the system already, so what has to hold is that
        // nothing has paired them since the form was opened.
        if ($error === '' && $fixedSide !== '') {
            $side = $fixedSide === 'recipient' ? $recipientId : $donorId;

            if ($this->findPerson($fixedSide, $side) === null) {
                $error = 'That record could not be found.';
            } elseif ($fixedSide === 'donor' && $this->store->openPairFor('donor', $side) !== null) {
                // Only the donor's half is exclusive; see pairableError().
                $error = 'That record is already in an open pair.';
            }
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
                'birthDate'      => (string) $this->request->getPost('rBirthDate'),
                'bloodType'      => (string) $this->request->getPost('rBloodType'),
                'phone'          => (string) $this->request->getPost('rPhone'),
                'address'        => (string) $this->request->getPost('rCity'),
                'urgent'         => (bool) $this->request->getPost('rUrgent'),
                'coordinator'    => (string) $this->request->getPost('rCoordinator'),
                'gender'         => (string) $this->request->getPost('rGender'),
                'selectedMrp'    => (string) $this->request->getPost('rMrp'),
                'dialysisType'   => (string) $this->request->getPost('rDialysisType'),
                'firstDialysis'  => (string) $this->request->getPost('rFirstDialysis'),
                'status'         => (string) $this->request->getPost('rStatus'),
                'dateRegistered' => (string) ($this->request->getPost('rEntryDate') ?: $entryDate),
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
                'birthDate'        => (string) $this->request->getPost('dBirthDate'),
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

        // Created with the status the form asked for. `addPair` carries it
        // down to the recipient where a person's own record can hold the same
        // word, which is why this comes after the recipient is stored: the two
        // are one fact, and the pair's card is where the case's status is set.
        $pairId = $this->store->addPair([
            'organ'         => $organ,
            'status'        => $pairStatus,
            'recipientId'   => $recipientId,
            'donorId'       => $donorId,
            'relationship'  => $relationship,
            'scheduledDate' => $crossmatch,
            'createdDate'   => $entryDate,
        ]);

        // Only a closed pair keeps a reason, and `updatePair` drops it again
        // on any other status, so this is safe to send unconditionally.
        if ($pairStatus === PairModel::CLOSED) {
            $this->store->updatePair((string) $pairId, [
                'status'       => $pairStatus,
                'closedReason' => (string) $this->request->getPost('closedReason'),
            ]);
        }

        return redirect()->to($this->afterLinking($fixedSide, $recipientId, $donorId, (string) $pairId));
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
                'closedReason'   => $pair['closedReason'] ?? '',
                'rName'          => $recipient['name'] ?? '',
                'rAge'           => isset($recipient['age']) ? (string) $recipient['age'] : '',
                'rBirthDate'     => $recipient['birthDate'] ?? '',
                'rBloodType'     => $recipient['bloodType'] ?? 'O',
                'rPhone'         => $recipient['phone'] ?? '',
                'rCity'          => $recipient['address'] ?? '',
                'rUrgent'        => (bool) ($recipient['urgent'] ?? false),
                'rCoordinator'   => $recipient['coordinator'] ?? '',
                'rGender'        => $recipient['gender'] ?? 'Male',
                'rMrp'           => $recipient['selectedMrp'] ?? '',
                'rDialysisType'  => $recipient['dialysisType'] ?? '',
                'rFirstDialysis' => $recipient['firstDialysis'] ?? '',
                'rStatus'        => $recipient['status'] ?? UiStore::PAIRS_DEFAULT_STATUS,
                'rNotes'         => $recipient['notes'] ?? '',
                'dName'          => $donor['name'] ?? '',
                'dAge'           => isset($donor['age']) ? (string) $donor['age'] : '',
                'dBirthDate'     => $donor['birthDate'] ?? '',
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

        // Not a card: the one button in the header, putting this pair forward
        // for a paired exchange or taking it back.
        if ($section === 'exchange') {
            $offered = $post('forExchange') === '1';

            // A pair whose status is Paired Exchange is on that list because
            // of the status, so withdrawing it here would be undone by the
            // next save of the card. The button is not on the screen in that
            // case; this is the same rule for a post that arrives anyway.
            if (! $offered && $pair['status'] === PairModel::EXCHANGE) {
                return $back->with(
                    'ui_error',
                    'This pair is on the exchange list because its status says so. Change the status to take it off.'
                );
            }

            $error = $this->store->offerPairForExchange($pair['id'], $offered);

            $this->session->setFlashdata(
                $error === '' ? 'ui_notice' : 'ui_error',
                $error === ''
                    ? ($offered
                        ? 'This pair is now on the Paired Exchange list.'
                        : 'This pair has been taken off the Paired Exchange list.')
                    : $error
            );

            return $back;
        }

        // One card at a time, so each branch writes only what its own card
        // collects. Relationship sits on the pair and on the donor — the
        // donors list shows it — so the pair card keeps the two in step.
        if ($section === 'pair') {
            $this->store->updatePair($pair['id'], [
                'status'        => $post('pairStatus'),
                'scheduledDate' => $post('crossmatchDate'),
                'relationship'  => $post('relationship'),
                'closedReason'  => $post('closedReason'),
            ]);

            if ($donor !== null) {
                $this->store->updateDonor($donor['id'], ['relationship' => $post('relationship')]);
            }

            return $back;
        }

        if ($recipient !== null && $section === 'recipient') {
            $error = $this->futureDateError(['rBirthDate', 'rFirstDialysis', 'rEntryDate']);

            if ($error !== '') {
                return redirect()->back()->withInput()->with('ui_error', $error);
            }

            $this->store->updateRecipient($recipient['id'], [
                'name'          => $post('rName'),
                'age'           => (int) $post('rAge') ?: $recipient['age'],
                'birthDate'     => $post('rBirthDate'),
                'bloodType'     => $post('rBloodType'),
                'phone'         => $post('rPhone'),
                'address'       => $post('rCity'),
                'urgent'        => (bool) $post('rUrgent'),
                'coordinator'   => $post('rCoordinator'),
                'gender'        => $post('rGender'),
                'selectedMrp'   => $post('rMrp'),
                'dialysisType'  => $post('rDialysisType'),
                'firstDialysis' => $post('rFirstDialysis'),
                'dateRegistered' => $post('rEntryDate'),
                // The recipient's own, and nobody else's: the pair's card sets
                // the pair's status and the donor's card sets the donor's.
                'status'        => $post('rStatus'),
            ]);
        }

        if ($recipient !== null && $section === 'rlabs') {
            $this->store->updateRecipient($recipient['id'], ['labTests' => $this->postedLabTests('rLabs')]);
        }

        if ($recipient !== null && $section === 'rnotes') {
            $this->store->updateRecipient($recipient['id'], ['notes' => $post('rNotes')]);
        }

        if ($donor !== null && $section === 'donor') {
            $error = $this->futureDateError(['dBirthDate']);

            if ($error !== '') {
                return redirect()->back()->withInput()->with('ui_error', $error);
            }

            $this->store->updateDonor($donor['id'], [
                'name'             => $post('dName'),
                'age'              => (int) $post('dAge') ?: $donor['age'],
                'birthDate'        => $post('dBirthDate'),
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

    public function linkDonor(string $id): string|RedirectResponse
    {
        return $this->linkChoice('donor', $id);
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

        // A donor already in a pair has nothing to choose, so they are shown
        // it. A recipient always has something to choose: another donor.
        if ($personType === 'donor') {
            $open = $this->store->openPairFor('donor', $person['id']);

            if ($open !== null) {
                return redirect()->to(site_url('pairs/' . rawurlencode($open['id'])));
            }
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

        return redirect()->to($this->afterLinking($personType, $recipientId, $donorId, (string) $pairId));
    }

    /**
     * Where linking leaves you.
     *
     * Not on the pair, when the link was made from a recipient's record. A
     * recipient collects donors — that is the point of the tabs — and there is
     * usually another to add before any one of them is the one. Sending them
     * to the pair every time made the first donor look like the decision.
     *
     * So: back to the recipient, with the donor just linked showing. The pair
     * has its own screen and the tab leads to it; it is not where the work is
     * at this point.
     *
     * From a donor's record, or from Add Pair with neither side fixed, the
     * pair is the thing that was just made and is where to go.
     */
    private function afterLinking(string $startedFrom, string $recipientMrn, string $donorMrn, string $pairId): string
    {
        if ($startedFrom !== 'recipient') {
            return site_url('pairs/' . rawurlencode($pairId));
        }

        $recipient = $this->store->findRecipient($recipientMrn);
        $tab       = 0;

        foreach ($recipient['donors'] ?? [] as $candidate) {
            if ((string) $candidate['donorId'] === $donorMrn) {
                $tab = (int) $candidate['number'];
            }
        }

        return site_url('recipients/' . rawurlencode($recipientMrn)) . ($tab === 0 ? '' : '?donor=' . $tab);
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

        // A recipient may hold several links at once — donors are looked at
        // one after another, and sometimes together — so nothing stops a
        // second. A donor may not: being promised to two recipients is not a
        // thing the register should be able to say.
        if ($this->store->openPairFor('donor', $donorMrn) !== null) {
            return 'That donor is already in an open pair.';
        }

        if ($this->store->pairWith($recipientMrn, $donorMrn) !== null) {
            return 'These two are already linked.';
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
    /**
     * A candidate's card, from the section a save names, or null.
     *
     * @return array{0: string, 1: string}|null The candidate's id, and the card
     */
    private function candidateCard(string $section): ?array
    {
        if (preg_match('/^pd(\d+)-([a-z]+)$/', $section, $m) !== 1) {
            return null;
        }

        return in_array($m[2], self::CANDIDATE_SECTIONS, true) ? [$m[1], $m[2]] : null;
    }

    /**
     * One card of one potential donor, saved from the recipient's screen.
     *
     * The donor's own record is what is written — these are the same cards
     * their record has, shown where they are being compared — so a change here
     * is a change there, and the other way round.
     *
     * @param array<string, mixed> $recipient
     */
    private function saveCandidateCard(array $recipient, string $candidateId, string $card): RedirectResponse
    {
        [, $tab, $back] = $this->candidate($recipient['id'], $candidateId);

        if ($tab === null || $tab['delinked']) {
            return redirect()->to($back);
        }

        $post = fn (string $field): string => (string) $this->request->getPost($field);

        if ($card === 'labs') {
            $this->store->updateDonor($tab['donorId'], ['labTests' => $this->postedLabTests('dLabs')]);

            return redirect()->to($back);
        }

        if ($card === 'notes') {
            $this->store->updateDonor($tab['donorId'], ['notes' => $post('dNotes')]);

            return redirect()->to($back);
        }

        $error = $this->futureDateError(['dBirthDate', 'dEntryDate']);

        if ($error !== '') {
            return redirect()->back()->withInput()->with('ui_error', $error);
        }

        $this->store->updateDonor($tab['donorId'], [
            'name'             => $post('dName'),
            'age'              => (int) $post('dAge') ?: null,
            'birthDate'        => $post('dBirthDate'),
            'bloodType'        => $post('dBloodType'),
            'phone'            => $post('dPhone'),
            'address'          => $post('dCity'),
            'donorGender'      => $post('dGender'),
            'donationType'     => $post('dType'),
            'relationship'     => $post('dRelationship'),
            'donorMrp'         => $post('dMrp'),
            'donorStatus'      => $post('dStatus'),
            'donorCoordinator' => $post('dCoordinator'),
            'dateRegistered'   => $post('dEntryDate'),
        ]);

        return redirect()->to($back);
    }

    /**
     * A donor as the cards on a recipient's screen read them.
     *
     * @param array<string, mixed> $donor
     *
     * @return array<string, string>
     */
    private function donorValues(array $donor): array
    {
        return [
            'dName'         => (string) ($donor['name'] ?? ''),
            'dAge'          => isset($donor['age']) ? (string) $donor['age'] : '',
            'dBirthDate'    => (string) ($donor['birthDate'] ?? ''),
            'dBloodType'    => (string) ($donor['bloodType'] ?? 'O'),
            'dPhone'        => (string) ($donor['phone'] ?? ''),
            'dCity'         => (string) ($donor['address'] ?? ''),
            'dGender'       => (string) ($donor['donorGender'] ?? 'Male'),
            'dType'         => (string) ($donor['donationType'] ?? 'living_related'),
            'dRelationship' => (string) ($donor['relationship'] ?? ''),
            'dMrp'          => (string) ($donor['donorMrp'] ?? ''),
            'dCoordinator'  => (string) ($donor['donorCoordinator'] ?? ''),
            'dStatus'       => (string) ($donor['donorStatus'] ?? 'On Hold'),
            'dEntryDate'    => ($donor['dateRegistered'] ?? '') === ''
                ? ''
                : UiStore::isoToDMY((string) $donor['dateRegistered']),
            'dNotes'        => (string) ($donor['notes'] ?? ''),
        ];
    }

    /**
     * The donors a recipient could be given as candidates.
     *
     * Everybody on the register who is not in an open pair, less this
     * recipient's own candidates — adding somebody twice is adding them once,
     * so there is nothing to offer — and less anyone holding this recipient's
     * own MRN on the other register.
     *
     * @param list<array<string, mixed>> $tabs
     *
     * @return list<array<string, mixed>>
     */
    private function offerableDonors(string $recipientMrn, array $tabs): array
    {
        $already = array_map(static fn (array $tab): string => (string) $tab['donorId'], $tabs);

        return array_values(array_filter(
            $this->store->availableDonors(),
            static fn (array $donor): bool => (string) $donor['id'] !== $recipientMrn
                && ! in_array((string) $donor['id'], $already, true)
        ));
    }

    private function linkUrls(string $personType, string $id): array
    {
        $base = ($personType === 'recipient' ? 'recipients/' : 'donors/') . rawurlencode($id);

        return [
            // A recipient collects candidates rather than making a pair, so
            // theirs goes to Add Donor; the pair comes later, from a tab. A
            // donor's still goes to Add Pair, which is the only direction that
            // still makes one in a single step.
            'newUrl'      => $personType === 'recipient'
                ? site_url('donors/new') . '?for=' . rawurlencode($id)
                : site_url('pairs/new') . '?' . $personType . '=' . rawurlencode($id),
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
            'users'   => $this->store->mrpRegister(),
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

    public function addMrp(): RedirectResponse
    {
        $error = $this->store->addMrp(
            (string) $this->request->getPost('id'),
            (string) $this->request->getPost('name'),
            (string) $this->request->getPost('kind')
        );

        return redirect()->to(site_url('mrp'))->with(
            $error === '' ? 'ui_mrp_saved' : 'ui_mrp_error',
            $error === '' ? 'User added successfully.' : $error
        );
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
    public function lookupMrp(): RedirectResponse
    {
        $code = trim((string) $this->request->getPost('id'));

        return redirect()->to(site_url('mrp'))->with('ui_mrp_lookup', [
            'code'   => $code,
            'kind'   => (string) $this->request->getPost('kind'),
            'found'  => false,
            'reason' => $code === ''
                ? 'Enter the ID to search for.'
                : 'The hospital directory is not connected yet, so nothing could be looked up. Enter the name below and the user will be registered against this ID.',
        ]);
    }

    /** Changes a registered user's ID, name or kind. */
    public function updateMrp(string $id): RedirectResponse
    {
        $error = $this->store->updateMrp(
            $id,
            (string) $this->request->getPost('id'),
            (string) $this->request->getPost('name'),
            (string) $this->request->getPost('kind')
        );

        return redirect()->to(site_url('mrp') . ($error === '' ? '' : '?edit=' . rawurlencode($id)))->with(
            $error === '' ? 'ui_mrp_saved' : 'ui_mrp_error',
            $error === '' ? 'User updated.' : $error
        );
    }

    /** Takes a registered user out of service, or puts them back. */
    public function setMrpActive(string $id): RedirectResponse
    {
        $active = (string) $this->request->getPost('active') === '1';
        $error  = $this->store->setMrpActive($id, $active);

        return redirect()->to(site_url('mrp'))->with(
            $error === '' ? 'ui_mrp_saved' : 'ui_mrp_error',
            $error === '' ? ($active ? 'User reactivated.' : 'User deactivated.') : $error
        );
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
     * Refuses a date that has not happened yet, or that never will.
     *
     * Everything the personal details collect is in the past — when somebody
     * was born, when their dialysis began, the day they joined the register —
     * so a later date is a typing slip. The screens stop it twice over, in the
     * picker and in `ui.js`, and neither of those is on the server.
     *
     * The other slip is a day the calendar has not got: a date field filled
     * out of order sends 0101-90-19, which is date-shaped and nothing more.
     * Saying so is better than storing a blank where a birthday was typed.
     *
     * A field the open card did not post is not checked: it was not asked.
     *
     * @param list<string> $fields
     */
    private function futureDateError(array $fields): string
    {
        foreach ($fields as $field) {
            $value = $this->request->getPost($field);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            if (UiStore::isFutureDate($value)) {
                return 'A date cannot be in the future.';
            }

            if (UiStore::dmyToIso($value) === '') {
                return 'Check the dates: the calendar has no such day.';
            }
        }

        return '';
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

    /** What the search box above a list is asking for, trimmed. */
    private function listQuery(): string
    {
        return trim((string) ($this->request->getGet('q') ?? ''));
    }

    /** What the two registers' chips were narrowed to, for the sheet's meta line. */
    private function registerFilterSummary(string $bloodType, string $status, string $search = ''): string
    {
        $applied = [];

        if ($bloodType !== 'all') {
            $applied[] = 'Blood type ' . $bloodType;
        }

        if ($status !== 'all') {
            $applied[] = UiStore::PERSON_STATUS_OPTIONS[$status] ?? $status;
        }

        if ($search !== '') {
            $applied[] = 'Matching “' . $search . '”';
        }

        return $applied === [] ? 'No filters applied' : implode(' · ', $applied);
    }

    /** The same ones as a query string, so Back returns to the same list. */
    private function registerFilterQuery(string $bloodType, string $status, string $search = ''): string
    {
        $query = array_filter([
            'bt'     => $bloodType === 'all' ? '' : $bloodType,
            'status' => $status === 'all' ? '' : $status,
            'q'      => $search,
        ], static fn (string $value): bool => $value !== '');

        return $query === [] ? '' : '?' . http_build_query($query);
    }

    /**
     * Which status the two registers are being narrowed to, or `all`.
     *
     * The three a record is ever set to, and `all` for anything else — a
     * hand-edited address asking for a status nobody can choose would
     * otherwise return an empty list and look like a bug.
     */
    private function personStatusFilter(): string
    {
        $filter = (string) ($this->request->getGet('status') ?? 'all');

        return isset(UiStore::PERSON_STATUS_OPTIONS[$filter]) ? $filter : 'all';
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

            // An answer somebody wrote for their own test is not one of ours,
            // so it cannot be checked against our list. The store checks it
            // against the test's own answers, which is the only list that can
            // say whether this test offers it.
            $own    = str_starts_with($status, UiStore::CUSTOM_ANSWER_PREFIX);
            $status = $own || in_array($status, UiStore::LAB_STATUSES, true) ? $status : 'pending';

            $tests[] = [
                'id'     => (string) ($row['id'] ?? ''),
                'name'   => (string) $row['name'],
                'status' => $status,
                'notes'  => (string) ($row['notes'] ?? ''),
            ] + (
                // Only a card that was open for editing sends these, and only
                // for a test the record added: what it answers, and the one
                // the "add" box was holding.
                isset($row['answers']) && is_array($row['answers'])
                    ? [
                        'answers'       => $row['answers'],
                        'newAnswer'     => (string) ($row['newAnswer'] ?? ''),
                        'newAnswerTone' => (string) ($row['newAnswerTone'] ?? UiStore::LAB_TONE_DEFAULT),
                    ]
                    : []
            );
        }

        return $tests;
    }
}
