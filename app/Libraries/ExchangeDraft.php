<?php

namespace App\Libraries;

use App\Models\DonorModel;
use App\Models\PairModel;
use App\Models\RecipientModel;
use CodeIgniter\Session\Session;

/**
 * A paired exchange, while it is being worked out.
 *
 * A pair whose donor cannot give to their own recipient looks for one who
 * can. Taking that donor out of somebody else's pair leaves *their* recipient
 * without one, who then looks in turn — and the exchange is that run of
 * consequences, a chain of recipient → donor links laid end to end. It closes
 * either by coming back round to where it started, or by reaching a donor from
 * the available register, who was nobody's to begin with.
 *
 * Two rules hold the chain to something a transplant service could act on:
 *
 *   - **No recipient without a donor.** Every recipient the exchange pulls in
 *     must leave it with one. Until they all do there is nothing to save.
 *   - **A donor without a recipient is allowed, but not left hanging.** They
 *     can only be at the end of the chain, and their fate has to be said:
 *     back to the available register, or removed. Saying nothing is not one
 *     of the options.
 *
 * Compatibility is by blood group, donor to recipient — O gives to all, A to A
 * and AB, B to B and AB, AB to AB — so the lists only ever offer a match. A
 * recipient therefore cannot be handed back to their own donor when that is
 * what the exchange was for.
 *
 * The draft lives in the session, not in a table. It is a page of working out,
 * abandoned as often as it is finished, and it becomes real in one step at the
 * end: `confirm()` closes what the chain broke, links what it made, and
 * carries out what was decided for the donors left over. Nothing in `pairs`
 * moves until then, so leaving the screen costs nothing and Undo is free.
 *
 * One programme at a time: a draft started on Kidney is not offered on Liver.
 */
final class ExchangeDraft
{
    private const KEY = 'ui_exchange';

    /** How many steps back Undo reaches, so a long session cannot bloat the session. */
    private const HISTORY = 30;

    /**
     * Statuses a pair can be exchanged out of.
     *
     * Everything open except a transplant that has already happened —
     * `completed` is history, and a closed pair holds nobody.
     */
    private const NOT_EXCHANGEABLE = [PairModel::CLOSED, 'completed'];

    /** Who a donor of each blood group can give to. */
    private const CAN_GIVE_TO = [
        'O'  => ['O', 'A', 'B', 'AB'],
        'A'  => ['A', 'AB'],
        'B'  => ['B', 'AB'],
        'AB' => ['AB'],
    ];

    /** What may become of a donor the chain leaves without a recipient. */
    public const FATES = [
        'available' => 'Move to the available donors list',
        'delete'    => 'Delete from the system',
    ];

    private Session $session;
    private RecipientModel $recipients;
    private DonorModel $donors;
    private PairModel $pairs;

    public function __construct(?Session $session = null)
    {
        $this->session    = $session ?? service('session');
        $this->recipients = model(RecipientModel::class);
        $this->donors     = model(DonorModel::class);
        $this->pairs      = model(PairModel::class);
    }

    // ---- Rules anybody can ask about ---------------------------------------

    /**
     * Whether a pair is one this screen may start from, or draw on.
     *
     * Two things, and it needs both: a status an exchange can move it out of,
     * and Pair Exchange pressed on its own screen. The second is the consent —
     * without it a pair can be swapped apart by someone who never proposed it.
     *
     * @param array<string, mixed> $pair
     */
    public static function isExchangeable(array $pair): bool
    {
        return (int) ($pair['for_exchange'] ?? 0) === 1
            && self::isExchangeableStatus((string) $pair['status']);
    }

    /** The status half of it on its own, for the screen's filter chips. */
    public static function isExchangeableStatus(string $status): bool
    {
        return ! in_array($status, self::NOT_EXCHANGEABLE, true);
    }

    /** Blood-group compatibility, donor to recipient. */
    public static function canGive(string $donorGroup, string $recipientGroup): bool
    {
        return in_array($recipientGroup, self::CAN_GIVE_TO[$donorGroup] ?? [], true);
    }

    // ---- Starting and stopping ---------------------------------------------

    public function isOpen(string $organ): bool
    {
        $draft = $this->draft();

        return $draft !== null && $draft['organ'] === $organ;
    }

    /**
     * Begins an exchange from one pair, replacing any draft already open.
     *
     * The pair is broken from the first moment: that is what the screen is
     * for, and its two sides are the chain's first open nodes.
     *
     * @return string An empty string when it began, else why it did not
     */
    public function start(string $organ, ?string $pairId): string
    {
        $pair = $this->exchangeablePair($pairId, $organ);

        if ($pair === null) {
            return 'That pair is not one this programme can exchange.';
        }

        $this->session->set(self::KEY, [
            'organ'  => $organ,
            'start'  => (int) $pair['id'],
            'broken' => [(int) $pair['id']],
            'assign' => [],
            'fate'   => [],
            'past'   => [],
        ]);

        return '';
    }

    public function discard(): void
    {
        $this->session->remove(self::KEY);
    }

    // ---- Building the chain ------------------------------------------------

    /**
     * Gives a recipient a donor, and follows what that costs.
     *
     * Whoever the donor was standing with loses them, so that pair breaks and
     * its recipient becomes the chain's next open node. If the recipient
     * already had a donor here, that donor is freed rather than quietly
     * dropped — they become an open donor with a fate still to decide.
     *
     * @return string An empty string when it was made, else why it was not
     */
    public function assign(string $organ, ?string $recipientMrn, ?string $donorMrn): string
    {
        $draft = $this->draft();

        if ($draft === null || $draft['organ'] !== $organ) {
            return 'There is no exchange being worked out.';
        }

        $recipient = $this->personOnProgramme($this->recipients, $recipientMrn, $organ);
        $donor     = $this->personOnProgramme($this->donors, $donorMrn, $organ);

        if ($recipient === null || $donor === null) {
            return 'Choose a recipient and a donor.';
        }

        if (! self::canGive((string) $donor['blood_group'], (string) $recipient['blood_group'])) {
            return $donor['name'] . ' (' . $donor['blood_group'] . ') cannot give to '
                . $recipient['name'] . ' (' . $recipient['blood_group'] . ').';
        }

        $donorMrnInt = (int) $donor['mrn'];

        foreach ($draft['assign'] as $withMrn => $theirDonor) {
            if ((int) $theirDonor === $donorMrnInt && (int) $withMrn !== (int) $recipient['mrn']) {
                return $donor['name'] . ' is already matched in this exchange.';
            }
        }

        $holding = $this->pairs->openPairForDonor($donorMrnInt);

        if ($holding !== null && ! self::isExchangeable($holding)) {
            return 'Pair #' . $holding['id'] . ' has not been put forward for exchange.';
        }

        $this->remember($draft);

        // Taking the donor out of a pair breaks it; its recipient is now the
        // chain's next open node.
        if ($holding !== null && ! in_array((int) $holding['id'], $draft['broken'], true)) {
            $draft['broken'][] = (int) $holding['id'];
        }

        $draft['assign'][(int) $recipient['mrn']] = $donorMrnInt;
        // A donor who has just been given a recipient is no longer spare.
        unset($draft['fate'][$donorMrnInt]);

        $this->session->set(self::KEY, $draft);

        return '';
    }

    /**
     * The same from the donor's side: gives a donor a recipient.
     *
     * Whoever the recipient was standing with loses them, so that pair breaks
     * and its donor becomes an open donor.
     *
     * @return string An empty string when it was made, else why it was not
     */
    public function assignRecipient(string $organ, ?string $donorMrn, ?string $recipientMrn): string
    {
        $draft = $this->draft();

        if ($draft === null || $draft['organ'] !== $organ) {
            return 'There is no exchange being worked out.';
        }

        $recipient = $this->personOnProgramme($this->recipients, $recipientMrn, $organ);

        if ($recipient === null) {
            return 'Choose a recipient.';
        }

        // Pulling a recipient out of their pair breaks it, the same way.
        $holding = $this->pairs->openPairForRecipient((int) $recipient['mrn']);

        if ($holding !== null && ! self::isExchangeable($holding)) {
            return 'Pair #' . $holding['id'] . ' has not been put forward for exchange.';
        }

        if ($holding !== null && ! in_array((int) $holding['id'], $draft['broken'], true)) {
            $this->remember($draft);
            $draft['broken'][] = (int) $holding['id'];
            $this->session->set(self::KEY, $draft);
        }

        return $this->assign($organ, (string) $recipient['mrn'], $donorMrn);
    }

    /** Says what becomes of a donor the chain has left without a recipient. */
    public function setFate(string $organ, ?string $donorMrn, string $fate): string
    {
        $draft = $this->draft();

        if ($draft === null || $draft['organ'] !== $organ) {
            return 'There is no exchange being worked out.';
        }

        if (! array_key_exists($fate, self::FATES)) {
            return 'Choose what becomes of this donor.';
        }

        $donor = $this->personOnProgramme($this->donors, $donorMrn, $organ);

        if ($donor === null) {
            return 'That donor is not on this programme.';
        }

        $this->remember($draft);
        $draft['fate'][(int) $donor['mrn']] = $fate;
        $this->session->set(self::KEY, $draft);

        return '';
    }

    /** Steps back one choice. The pairs the chain broke stay broken. */
    public function undo(string $organ): void
    {
        $draft = $this->draft();

        if ($draft === null || $draft['organ'] !== $organ || $draft['past'] === []) {
            return;
        }

        $previous = array_pop($draft['past']);

        $this->session->set(self::KEY, [
            'organ'  => $draft['organ'],
            'start'  => $draft['start'],
            'broken' => $previous['broken'],
            'assign' => $previous['assign'],
            'fate'   => $previous['fate'],
            'past'   => $draft['past'],
        ]);
    }

    public function canUndo(string $organ): bool
    {
        $draft = $this->draft();

        return $draft !== null && $draft['organ'] === $organ && $draft['past'] !== [];
    }

    // ---- What the screen shows --------------------------------------------

    /**
     * The whole draft, ready to render.
     *
     * `chain` is the run of links in the order they follow one another, so the
     * screen can draw it as nodes and arrows rather than as a list: the pair it
     * started from first, then whoever that displaced, and so on.
     *
     * @return array<string, mixed>|null
     */
    public function state(string $organ): ?array
    {
        $draft = $this->draft();

        if ($draft === null || $draft['organ'] !== $organ) {
            return null;
        }

        $inPlay = $this->inPlay($draft);
        $chain  = $this->chain($draft, $inPlay);

        // Recipients still without a donor: the chain cannot be saved with any.
        $openRecipients = array_values(array_filter(
            $inPlay['recipients'],
            static fn (array $r): bool => ! isset($draft['assign'][(int) $r['mrn']])
        ));

        // Donors nobody is taking: allowed, but each needs a fate.
        $taken       = array_map('intval', array_values($draft['assign']));
        $spareDonors = [];

        foreach ($inPlay['donors'] as $donor) {
            if (in_array((int) $donor['mrn'], $taken, true)) {
                continue;
            }

            $donor['fate']              = $draft['fate'][(int) $donor['mrn']] ?? '';
            $donor['choosableRecipients'] = $this->compatibleRecipients($organ, $draft, $donor);
            $spareDonors[]              = $donor;
        }

        $undecided = array_values(array_filter($spareDonors, static fn (array $d): bool => $d['fate'] === ''));

        return [
            'chain'          => $chain,
            'openRecipients' => $openRecipients,
            'spareDonors'    => $spareDonors,
            'undecided'      => $undecided,
            'assigned'       => count($draft['assign']),
            'canUndo'        => $draft['past'] !== [],
            'complete'       => $draft['assign'] !== [] && $openRecipients === [] && $undecided === [],
            'summary'        => $this->summary($draft, $inPlay),
        ];
    }

    /**
     * Compatible donors for one recipient, each labelled with where they came
     * from, and never one this exchange has already spoken for.
     *
     * @param array<string, mixed> $recipient
     *
     * @return list<array<string, mixed>>
     */
    public function compatibleDonors(string $organ, array $draft, array $recipient): array
    {
        $taken = array_map('intval', array_values($draft['assign']));
        $out   = [];

        foreach ($this->donors->where('organ_code', $organ)->orderBy('name')->findAll() as $donor) {
            if (in_array((int) $donor['mrn'], $taken, true)) {
                continue;
            }

            if (! self::canGive((string) $donor['blood_group'], (string) $recipient['blood_group'])) {
                continue;
            }

            $holding = $this->pairs->openPairForDonor((int) $donor['mrn']);

            // In a pair nobody offered? Then they are not this chain's to take.
            if ($holding !== null && ! self::isExchangeable($holding)) {
                continue;
            }

            $donor['source'] = $holding === null
                ? 'From the available donors'
                : 'From pair #' . $holding['id'];
            $out[] = $donor;
        }

        return $out;
    }

    /**
     * Compatible recipients for one donor, the mirror of the above.
     *
     * @param array<string, mixed> $donor
     *
     * @return list<array<string, mixed>>
     */
    public function compatibleRecipients(string $organ, array $draft, array $donor): array
    {
        $out = [];

        foreach ($this->recipients->where('organ_code', $organ)->orderBy('name')->findAll() as $recipient) {
            if (isset($draft['assign'][(int) $recipient['mrn']])) {
                continue;
            }

            if (! self::canGive((string) $donor['blood_group'], (string) $recipient['blood_group'])) {
                continue;
            }

            $holding = $this->pairs->openPairForRecipient((int) $recipient['mrn']);

            if ($holding !== null && ! self::isExchangeable($holding)) {
                continue;
            }

            $recipient['source'] = $holding === null
                ? 'From the waiting list'
                : 'From pair #' . $holding['id'];
            $out[] = $recipient;
        }

        return $out;
    }

    /** The draft as the session holds it, for the two lookups above. */
    public function raw(string $organ): ?array
    {
        $draft = $this->draft();

        return $draft !== null && $draft['organ'] === $organ ? $draft : null;
    }

    // ---- Making it real ----------------------------------------------------

    /**
     * Closes what the chain broke, links what it made, and carries out what
     * was decided for the donors left over — in that order, and only once
     * every rule the screen states is actually true.
     *
     * Re-checked here rather than trusted from the session: the draft may have
     * been open a while, and a pair it counted on can have been closed on
     * another screen since.
     *
     * @return string An empty string when it is done, else why it was not
     */
    public function confirm(string $organ): string
    {
        $state = $this->state($organ);

        if ($state === null) {
            return 'There is no exchange being worked out.';
        }

        if ($state['assigned'] === 0) {
            return 'Match at least one recipient with a donor before saving.';
        }

        if ($state['openRecipients'] !== []) {
            return 'Every recipient in the chain needs a donor before it can be saved.';
        }

        if ($state['undecided'] !== []) {
            return 'Say what becomes of each donor left without a recipient before saving.';
        }

        $draft = $this->draft();

        // The old pairs go first: a person cannot be in two open pairs, so
        // nothing can be linked while the pair that holds them is still open.
        foreach ($draft['broken'] as $pairId) {
            $pair = $this->pairs->find($pairId);

            if ($pair !== null && self::isExchangeable($pair)) {
                $this->pairs->close($pairId, 'Paired exchange');
            }
        }

        foreach ($draft['assign'] as $recipientMrn => $donorMrn) {
            $this->pairs->link((int) $recipientMrn, (int) $donorMrn, ['status' => 'paired_exchange']);
            // The two are one status from the moment the pair exists.
            $this->recipients->update((int) $recipientMrn, ['status' => 'paired_exchange']);
        }

        // A donor sent back to the register needs nothing doing: closing their
        // pair is what put them there. A deleted one is deleted — and so are
        // the pair rows that name them, since `pairs.donor_mrn` is ON DELETE
        // RESTRICT and the closed pair this exchange just made would otherwise
        // hold the record in place. The summary says as much before anyone
        // presses save; it is the difference between removing a donor and
        // removing every trace of who they were matched with.
        foreach ($draft['fate'] as $donorMrn => $fate) {
            if ($fate !== 'delete' || $this->pairs->openPairForDonor((int) $donorMrn) !== null) {
                continue;
            }

            $this->pairs->where('donor_mrn', (int) $donorMrn)->delete();
            $this->donors->delete((int) $donorMrn);
        }

        $this->discard();

        return '';
    }

    // ---- Reading the registers --------------------------------------------

    /**
     * The pairs this screen can start from: offered, open, not transplanted.
     *
     * @return list<array<string, mixed>>
     */
    public function exchangeablePairs(string $organ, string $query = '', string $bloodGroup = ''): array
    {
        // No status filter: being on this list is already a status, and the
        // few a pair can hold here are all true of every row on it.
        $rows = $this->pairs->overview($organ);
        $rows = array_filter($rows, static fn (array $r): bool => self::isExchangeable($r));

        // The recipient's blood group, and only theirs. An exchange exists
        // because a donor cannot give to their own recipient, so matching on
        // either side would hide the very pairs that make one work — what
        // somebody narrowing this list is asking is "who needs a group I can
        // place", which is a question about the recipients.
        if ($bloodGroup !== '') {
            $rows = array_filter(
                $rows,
                static fn (array $r): bool => (string) $r['r_blood_group'] === $bloodGroup
            );
        }

        if ($query !== '') {
            $rows = array_filter($rows, static function (array $r) use ($query): bool {
                foreach (['r_mrn', 'd_mrn', 'r_name', 'd_name'] as $field) {
                    if (stripos((string) $r[$field], $query) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        return array_values($rows);
    }

    // ---- Working the chain out ---------------------------------------------

    /**
     * Everyone the exchange has a hold of: both sides of every pair it broke,
     * plus anybody brought in from the waiting list or the register.
     *
     * @return array{recipients: list<array<string, mixed>>, donors: list<array<string, mixed>>, origin: array<int, int>}
     */
    private function inPlay(array $draft): array
    {
        $recipients = [];
        $donors     = [];
        // Which pair each person came out of, for the chain's own labels.
        $origin = [];

        foreach ($draft['broken'] as $pairId) {
            $pair = $this->pairs->find($pairId);

            if ($pair === null) {
                continue;
            }

            $recipient = $this->recipients->find($pair['recipient_mrn']);
            $donor     = $this->donors->find($pair['donor_mrn']);

            if ($recipient !== null) {
                $recipients[(int) $recipient['mrn']]   = $recipient;
                $origin['r' . (int) $recipient['mrn']] = (int) $pairId;
            }

            if ($donor !== null) {
                $donors[(int) $donor['mrn']]       = $donor;
                $origin['d' . (int) $donor['mrn']] = (int) $pairId;
            }
        }

        // Anybody the chain reached for who was not in a broken pair: a
        // recipient off the waiting list, a donor off the register.
        foreach ($draft['assign'] as $recipientMrn => $donorMrn) {
            if (! isset($recipients[(int) $recipientMrn])) {
                $row = $this->recipients->find((int) $recipientMrn);

                if ($row !== null) {
                    $recipients[(int) $recipientMrn] = $row;
                }
            }

            if (! isset($donors[(int) $donorMrn])) {
                $row = $this->donors->find((int) $donorMrn);

                if ($row !== null) {
                    $donors[(int) $donorMrn] = $row;
                }
            }
        }

        return [
            'recipients' => array_values($recipients),
            'donors'     => array_values($donors),
            'origin'     => $origin,
        ];
    }

    /**
     * The links in the order they follow one another.
     *
     * Walks from the pair the exchange started with: its recipient, the donor
     * they have been given, then the recipient that donor was taken from, and
     * on. Anybody in play the walk does not reach is appended, so nothing is
     * ever silently missing from the picture.
     *
     * @return list<array<string, mixed>>
     */
    private function chain(array $draft, array $inPlay): array
    {
        $byMrn = [];

        foreach ($inPlay['recipients'] as $row) {
            $byMrn[(int) $row['mrn']] = $row;
        }

        $startPair = $this->pairs->find($draft['start']);
        $nodes     = [];
        $seen      = [];
        $next      = $startPair === null ? null : (int) $startPair['recipient_mrn'];

        while ($next !== null && isset($byMrn[$next]) && ! isset($seen[$next])) {
            $seen[$next] = true;
            $nodes[]     = $this->node($draft, $inPlay, $byMrn[$next]);

            $donorMrn = $draft['assign'][$next] ?? null;
            $next     = null;

            if ($donorMrn === null) {
                break;
            }

            // The chain continues through whoever that donor was standing with.
            $fromPair = $inPlay['origin']['d' . (int) $donorMrn] ?? null;

            if ($fromPair !== null) {
                $pair = $this->pairs->find($fromPair);
                $mrn  = $pair === null ? null : (int) $pair['recipient_mrn'];
                $next = $mrn !== null && ! isset($seen[$mrn]) ? $mrn : null;
            }
        }

        foreach ($inPlay['recipients'] as $row) {
            if (! isset($seen[(int) $row['mrn']])) {
                $nodes[] = $this->node($draft, $inPlay, $row);
            }
        }

        return $nodes;
    }

    /** One link of the chain: a recipient, and the donor they now have. */
    private function node(array $draft, array $inPlay, array $recipient): array
    {
        $donorMrn = $draft['assign'][(int) $recipient['mrn']] ?? null;
        $donor    = $donorMrn === null ? null : $this->donors->find((int) $donorMrn);
        $fromPair = $inPlay['origin']['r' . (int) $recipient['mrn']] ?? null;

        return [
            'recipient'        => $recipient,
            'donor'            => $donor,
            'fromPair'         => $fromPair,
            'wasTheirDonor'    => $fromPair === null ? null : $this->originalDonor($fromPair),
            'choosableDonors'  => $donor === null
                ? $this->compatibleDonors($draft['organ'], $draft, $recipient)
                : [],
        ];
    }

    /** @return array<string, mixed>|null */
    private function originalDonor(int $pairId): ?array
    {
        $pair = $this->pairs->find($pairId);

        return $pair === null ? null : $this->donors->find($pair['donor_mrn']);
    }

    /**
     * What saving would actually do, for the review before it happens.
     *
     * @return array{links: list<array<string, mixed>>, released: list<array<string, mixed>>, deleted: list<array<string, mixed>>, closed: list<int>}
     */
    private function summary(array $draft, array $inPlay): array
    {
        $links = [];

        foreach ($draft['assign'] as $recipientMrn => $donorMrn) {
            $recipient = $this->recipients->find((int) $recipientMrn);
            $donor     = $this->donors->find((int) $donorMrn);

            if ($recipient !== null && $donor !== null) {
                $links[] = ['recipient' => $recipient, 'donor' => $donor];
            }
        }

        $released = [];
        $deleted  = [];

        foreach ($draft['fate'] as $donorMrn => $fate) {
            $donor = $this->donors->find((int) $donorMrn);

            if ($donor === null) {
                continue;
            }

            if ($fate === 'delete') {
                $deleted[] = $donor;
            } else {
                $released[] = $donor;
            }
        }

        return [
            'links'    => $links,
            'released' => $released,
            'deleted'  => $deleted,
            'closed'   => $draft['broken'],
        ];
    }

    // ---- Small helpers -----------------------------------------------------

    /** Keeps the state Undo would come back to. */
    private function remember(array &$draft): void
    {
        $draft['past'][] = [
            'broken' => $draft['broken'],
            'assign' => $draft['assign'],
            'fate'   => $draft['fate'],
        ];

        if (count($draft['past']) > self::HISTORY) {
            array_shift($draft['past']);
        }
    }

    /** @return array<string, mixed>|null */
    private function draft(): ?array
    {
        $draft = $this->session->get(self::KEY);

        return is_array($draft) && isset($draft['organ'], $draft['start'], $draft['broken'], $draft['assign'], $draft['fate'], $draft['past'])
            ? $draft
            : null;
    }

    /** @return array<string, mixed>|null */
    private function exchangeablePair(?string $pairId, string $organ): ?array
    {
        if ($pairId === null || ! ctype_digit($pairId)) {
            return null;
        }

        $pair = $this->pairs->find((int) $pairId);

        if ($pair === null || ! self::isExchangeable($pair)) {
            return null;
        }

        // A pair belongs to the programme its recipient is registered on.
        $recipient = $this->recipients->find($pair['recipient_mrn']);

        return $recipient !== null && $recipient['organ_code'] === $organ ? $pair : null;
    }

    /** @return array<string, mixed>|null */
    private function personOnProgramme(object $model, ?string $mrn, string $organ): ?array
    {
        if ($mrn === null || ! ctype_digit($mrn)) {
            return null;
        }

        $row = $model->find((int) $mrn);

        return $row !== null && $row['organ_code'] === $organ ? $row : null;
    }
}
