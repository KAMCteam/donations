<?php

namespace App\Libraries;

use App\Models\DonorModel;
use App\Models\PairModel;
use App\Models\RecipientModel;
use CodeIgniter\Session\Session;

/**
 * A paired exchange, while it is being worked out.
 *
 * An exchange starts from one pair whose donor cannot give to their own
 * recipient, and swaps across others. The moment you take somebody out of a
 * pair to use them, their partner is left without one — and that partner is
 * now the problem. So the rule the whole screen is built around is:
 *
 *     **nobody may be left on their own.**
 *
 * Every person released by a pair this exchange breaks has to end the exchange
 * in a new pair. Until they all do, there is nothing to confirm. That is what
 * `owed()` counts and what `isComplete()` asks, and it is why picking somebody
 * who is already in a pair quietly pulls that pair in too: taking one side is
 * taking both, and the screen says so rather than discovering it later.
 *
 * The draft lives in the session, not in a table. It is a page of working out,
 * abandoned as often as it is finished, and it becomes real in one step at the
 * end — `confirm()` closes what it broke and links what it made. Nothing in
 * `pairs` moves until then, so leaving the screen costs nothing.
 *
 * One programme at a time: a draft started on Kidney is not offered on Liver.
 */
final class ExchangeDraft
{
    private const KEY = 'ui_exchange';

    /**
     * Statuses a pair can be exchanged out of.
     *
     * Everything open except a transplant that has already happened —
     * `completed` is history, and a closed pair holds nobody.
     */
    private const NOT_EXCHANGEABLE = [PairModel::CLOSED, 'completed'];

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

    /** Whether a pair is one this screen can start from, or draw on. */
    public static function isExchangeable(string $status): bool
    {
        return ! in_array($status, self::NOT_EXCHANGEABLE, true);
    }

    // ---- The draft ---------------------------------------------------------

    public function isOpen(string $organ): bool
    {
        $draft = $this->draft();

        return $draft !== null && $draft['organ'] === $organ;
    }

    /**
     * Begins an exchange from one pair, replacing any draft already open.
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
            'organ'   => $organ,
            'sources' => [(int) $pair['id']],
            'formed'  => [],
        ]);

        return '';
    }

    public function discard(): void
    {
        $this->session->remove(self::KEY);
    }

    /**
     * Pairs two people inside the draft.
     *
     * Either of them may be free already or standing in a pair; where they are
     * in one, that pair is added to the exchange and its other side joins the
     * people still owed a partner.
     *
     * @return string An empty string when it was made, else why it was not
     */
    public function link(string $organ, ?string $recipientMrn, ?string $donorMrn): string
    {
        $draft = $this->draft();

        if ($draft === null || $draft['organ'] !== $organ) {
            return 'There is no exchange being worked out.';
        }

        $recipient = $this->personOnProgramme($this->recipients, $recipientMrn, $organ);
        $donor     = $this->personOnProgramme($this->donors, $donorMrn, $organ);

        if ($recipient === null) {
            return 'Choose a recipient.';
        }

        if ($donor === null) {
            return 'Choose a donor.';
        }

        foreach ($draft['formed'] as $formed) {
            if ($formed['r'] === (int) $recipient['mrn']) {
                return $recipient['name'] . ' is already paired in this exchange.';
            }

            if ($formed['d'] === (int) $donor['mrn']) {
                return $donor['name'] . ' is already paired in this exchange.';
            }
        }

        // Taking one side of a pair takes both: the partner is released and
        // now needs a pair of their own before this can be confirmed.
        foreach ([['recipient', (int) $recipient['mrn']], ['donor', (int) $donor['mrn']]] as [$type, $mrn]) {
            $holding = $this->openPairFor($type, $mrn);

            if ($holding === null) {
                continue;
            }

            if (! self::isExchangeable((string) $holding['status'])) {
                return 'Pair #' . $holding['id'] . ' cannot be exchanged out of.';
            }

            if (! in_array((int) $holding['id'], $draft['sources'], true)) {
                $draft['sources'][] = (int) $holding['id'];
            }
        }

        $draft['formed'][] = ['r' => (int) $recipient['mrn'], 'd' => (int) $donor['mrn']];
        $this->session->set(self::KEY, $draft);

        return '';
    }

    /** Undoes one of the pairs made here. The pairs it pulled in stay in. */
    public function unlink(string $organ, int $index): void
    {
        $draft = $this->draft();

        if ($draft === null || $draft['organ'] !== $organ || ! isset($draft['formed'][$index])) {
            return;
        }

        unset($draft['formed'][$index]);
        $draft['formed'] = array_values($draft['formed']);
        $this->session->set(self::KEY, $draft);
    }

    // ---- What the screen shows --------------------------------------------

    /**
     * The whole draft, ready to render.
     *
     * @return array{
     *     sources: list<array<string, mixed>>,
     *     formed: list<array{recipient: array<string, mixed>, donor: array<string, mixed>}>,
     *     owedRecipients: list<array<string, mixed>>,
     *     owedDonors: list<array<string, mixed>>,
     *     recipients: list<array<string, mixed>>,
     *     donors: list<array<string, mixed>>,
     *     complete: bool,
     *     done: int,
     *     needed: int
     * }|null
     */
    public function state(string $organ): ?array
    {
        $draft = $this->draft();

        if ($draft === null || $draft['organ'] !== $organ) {
            return null;
        }

        $sources = [];

        foreach ($draft['sources'] as $id) {
            $row = $this->pairs->find($id);

            if ($row !== null) {
                $sources[] = $row;
            }
        }

        $formed = array_map(
            fn (array $f): array => [
                'recipient' => $this->recipients->find($f['r']) ?? ['mrn' => $f['r'], 'name' => 'MRN ' . $f['r']],
                'donor'     => $this->donors->find($f['d']) ?? ['mrn' => $f['d'], 'name' => 'MRN ' . $f['d']],
            ],
            $draft['formed']
        );

        $pairedRecipients = array_column($draft['formed'], 'r');
        $pairedDonors     = array_column($draft['formed'], 'd');

        // Released by a pair this exchange breaks, and not yet paired again.
        $owedRecipients = [];
        $owedDonors     = [];

        foreach ($sources as $source) {
            if (! in_array((int) $source['recipient_mrn'], $pairedRecipients, true)) {
                $owedRecipients[] = $this->recipients->find($source['recipient_mrn']);
            }

            if (! in_array((int) $source['donor_mrn'], $pairedDonors, true)) {
                $owedDonors[] = $this->donors->find($source['donor_mrn']);
            }
        }

        $owedRecipients = array_values(array_filter($owedRecipients));
        $owedDonors     = array_values(array_filter($owedDonors));

        // A pair still to make for everyone owed one; with the two sides
        // unequal it is the longer queue that says how many are left.
        $remaining = max(count($owedRecipients), count($owedDonors));

        return [
            'sources'        => $sources,
            'formed'         => $formed,
            'owedRecipients' => $owedRecipients,
            'owedDonors'     => $owedDonors,
            'recipients'     => $this->choosableRecipients($organ, $pairedRecipients),
            'donors'         => $this->choosableDonors($organ, $pairedDonors),
            'complete'       => $formed !== [] && $remaining === 0,
            'done'           => count($formed),
            'needed'         => count($formed) + $remaining,
        ];
    }

    // ---- Making it real ----------------------------------------------------

    /**
     * Closes what the exchange broke and links what it made, in one go.
     *
     * Re-checked against the tables first rather than trusted from the
     * session: the draft may have been open a while, and a pair it counted on
     * can have been closed or deleted on another screen since.
     *
     * @return string An empty string when it is done, else why it was not
     */
    public function confirm(string $organ): string
    {
        $state = $this->state($organ);

        if ($state === null) {
            return 'There is no exchange being worked out.';
        }

        if (! $state['complete']) {
            return 'Every person this exchange releases needs a pair before it can be confirmed.';
        }

        $draft = $this->draft();

        // The old pairs go first: a person cannot be in two open pairs, so
        // nothing can be linked while the pair that holds them is still open.
        foreach ($state['sources'] as $source) {
            if (self::isExchangeable((string) $source['status'])) {
                $this->pairs->close((int) $source['id'], 'Paired exchange');
            }
        }

        foreach ($draft['formed'] as $formed) {
            $this->pairs->link($formed['r'], $formed['d'], ['status' => 'paired_exchange']);
            // The two are one status from the moment the pair exists.
            $this->recipients->update($formed['r'], ['status' => 'paired_exchange']);
        }

        $this->discard();

        return '';
    }

    // ---- Reading the registers --------------------------------------------

    /**
     * The pairs this screen can start from: open, not already transplanted.
     *
     * @return list<array<string, mixed>>
     */
    public function exchangeablePairs(string $organ, string $query, string $status, string $bloodType): array
    {
        $rows = $this->pairs->overview($organ, $status === 'all' ? null : $status, $bloodType === 'all' ? null : $bloodType);

        $rows = array_filter($rows, static fn (array $r): bool => self::isExchangeable((string) $r['status']));

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

    /**
     * Everyone the recipients column offers: those the exchange has released,
     * and those on the waiting list who are free anyway.
     *
     * @param list<int> $alreadyPaired
     *
     * @return list<array<string, mixed>>
     */
    private function choosableRecipients(string $organ, array $alreadyPaired): array
    {
        $rows = $this->recipients->where('organ_code', $organ)->orderBy('name')->findAll();

        return $this->choosable($rows, $alreadyPaired, 'recipient');
    }

    /**
     * @param list<int> $alreadyPaired
     *
     * @return list<array<string, mixed>>
     */
    private function choosableDonors(string $organ, array $alreadyPaired): array
    {
        $rows = $this->donors->where('organ_code', $organ)->orderBy('name')->findAll();

        return $this->choosable($rows, $alreadyPaired, 'donor');
    }

    /**
     * Marks each person with where they stand, and leaves out the ones there
     * is no point offering: already paired in this exchange, or held by a pair
     * that cannot be exchanged out of.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<int>                  $alreadyPaired
     *
     * @return list<array<string, mixed>>
     */
    private function choosable(array $rows, array $alreadyPaired, string $personType): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (in_array((int) $row['mrn'], $alreadyPaired, true)) {
                continue;
            }

            $holding = $this->openPairFor($personType, (int) $row['mrn']);

            if ($holding !== null && ! self::isExchangeable((string) $holding['status'])) {
                continue;
            }

            $row['heldByPair'] = $holding === null ? null : (int) $holding['id'];
            $out[]             = $row;
        }

        return $out;
    }

    // ---- Small helpers -----------------------------------------------------

    /** @return array<string, mixed>|null */
    private function draft(): ?array
    {
        $draft = $this->session->get(self::KEY);

        return is_array($draft) && isset($draft['organ'], $draft['sources'], $draft['formed']) ? $draft : null;
    }

    /** @return array<string, mixed>|null */
    private function openPairFor(string $personType, int $mrn): ?array
    {
        return $personType === 'recipient'
            ? $this->pairs->openPairForRecipient($mrn)
            : $this->pairs->openPairForDonor($mrn);
    }

    /** @return array<string, mixed>|null */
    private function exchangeablePair(?string $pairId, string $organ): ?array
    {
        if ($pairId === null || ! ctype_digit($pairId)) {
            return null;
        }

        $pair = $this->pairs->find((int) $pairId);

        if ($pair === null || ! self::isExchangeable((string) $pair['status'])) {
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
