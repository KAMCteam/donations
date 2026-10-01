<?php

namespace App\Libraries;

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\ExchangeDraft;
use App\Models\CoordinatorModel;
use App\Models\DonorModel;
use App\Models\LabModel;
use App\Models\LabResultModel;
use App\Models\MrpModel;
use App\Models\OrganProgramModel;
use App\Models\PairModel;
use App\Models\RecipientModel;
use CodeIgniter\Model;
use CodeIgniter\Session\Session;

/**
 * What the screens read and write, on top of the database.
 *
 * Every method here hands the views and the controller the shape they already
 * expect — camelCase keys, `labTests` as an array on the person — and turns it
 * into rows in `recipients`, `donors`, `pairs`, `mrp` and `lab_results` on the
 * way in and out. Nothing is held in the session any more except which
 * programme is selected and who is signed in, so a record added on one screen
 * is there on the next request, on another machine, and after a restart.
 *
 * The two shapes differ in more than spelling, and the translation is all in
 * the private helpers at the bottom:
 *
 *   id            an MRN, an integer, not the "R-001" the design package used
 *   organ         organ_code
 *   bloodType     blood_group
 *   address       city
 *   dateRegistered / firstDialysis   entry_date / dialysis_start, and the
 *                 screens type dates as DD/MM/YYYY while the columns are dates
 *   gender        "Male" on screen, `male` in the column
 *   donorStatus   "On Hold" on screen, `on_hold` in the column
 *   urgent        a checkbox on screen, `is_urgent` 0/1 in the column
 *   coordinator   a name typed on screen, `coordinator_id` in the column
 *   pairedDonorId derived from `pairs`, not stored on the person
 *   labTests      one row per test in `lab_results`, joined to the catalogue
 */
final class UiStore
{
    public const ORGANS = ['kidney', 'liver'];

    public const BLOOD_TYPES = ['A', 'B', 'O', 'AB'];

    /**
     * Every status any of the three can hold, and what it is called.
     *
     * This is the label lookup, not a menu: it still names `pending`,
     * `confirmed` and `completed`, which no screen offers any more, so a
     * record saved before they were retired still reads correctly instead of
     * showing a bare key. What each screen *offers* is below.
     */
    public const STATUS_OPTIONS = [
        'on_hold'         => 'On Hold',
        'active'          => 'Active',
        'declined'        => 'Declined',
        'transplanted'    => 'Transplanted',
        'paired_exchange' => 'Paired Exchange',
        'closed'          => 'Closed',
        // Retired: still stored on older records, never offered again.
        'pending'         => 'Pending',
        'confirmed'       => 'Confirmed',
        'completed'       => 'Completed',
    ];

    /**
     * What the Pair Details card offers.
     *
     * `closed` is the one with meaning beyond its label — it is what "open
     * pair" is defined against, so closing a pair puts both sides back on
     * their lists, and the card asks why in so many words. The rest are
     * descriptive.
     */
    public const PAIR_STATUS_OPTIONS = [
        'on_hold'         => 'On Hold',
        'active'          => 'Active',
        'declined'        => 'Declined',
        'transplanted'    => 'Transplanted',
        'paired_exchange' => 'Paired Exchange',
        'closed'          => 'Closed',
    ];

    /**
     * What the Pairs List opens on when no filter is asked for.
     *
     * The register accumulates: every pair that was ever transplanted,
     * declined or closed stays in it, and the day-to-day question is about
     * the ones still being worked. So the screen opens on those, and All is
     * one click away.
     *
     * Because this is the default, it is the value the links leave out of the
     * query string — `?status=all` is the one that has to be spelled out, or
     * the All chip would lead straight back here.
     */
    public const PAIRS_DEFAULT_STATUS = 'active';

    /**
     * What a person's own record offers — a recipient's and a donor's alike.
     *
     * Three of the pair's six, and deliberately not the other three: a person
     * is not transplanted, closed or in a paired exchange; their *case* is,
     * and that is the pair's to say. Where the two do overlap they are still
     * kept in step — setting one sets the other — which is why these three are
     * exactly a subset rather than a separate vocabulary.
     */
    /**
     * What kind of dialysis a recipient is on.
     *
     * Pre-emptive is the odd one: it means a transplant before dialysis ever
     * starts, so a pre-emptive recipient has no first dialysis date — not one
     * nobody has filled in yet, but none there can be. The screens close that
     * field when it is chosen, and `recipientToRow` clears it.
     */
    public const DIALYSIS_TYPES = [
        'hemo'       => 'Hemodialysis',
        'peritoneal' => 'Peritoneal dialysis',
        'preemptive' => 'Preemptive dialysis',
    ];

    /** The one that means there is no dialysis to date. */
    public const DIALYSIS_PREEMPTIVE = 'preemptive';

    public const PERSON_STATUS_OPTIONS = [
        'on_hold'  => 'On Hold',
        'active'   => 'Active',
        'declined' => 'Declined',
    ];

    public const STATUS_TONE = [
        'on_hold'         => 'tone-amber',
        'active'          => 'tone-blue',
        'declined'        => 'tone-red',
        'transplanted'    => 'tone-emerald',
        'paired_exchange' => 'tone-teal',
        'closed'          => 'tone-slate',
        'pending'         => 'tone-amber-soft',
        'confirmed'       => 'tone-blue-soft',
        'completed'       => 'tone-emerald',
    ];

    /**
     * The answers the check list offers, and which tests offer which.
     *
     * Every card shows its own test's answers rather than one generic
     * Pending / Done / Flagged: a serology is Positive or Negative, a referral
     * is Cleared or not, a vaccination is Given or not. `not_done` starts them
     * all — nobody has looked yet.
     *
     * `not_applicable` is only where the sheet puts it: on the cancer
     * screening, the imaging, the clearances and B-HCG, the tests a patient's
     * sex or history can rule out. The bloods and serologies are asked of
     * everyone, so they do not offer it — which is why acceptable/abnormal
     * comes in two lists that differ by that one answer.
     *
     * Blood group is the one that answers with a value rather than a verdict,
     * so its answers are prefixed: in the column, `blood_ab` is unmistakably a
     * blood group and not an abbreviation of something else.
     */
    public const RESULT_OPTIONS = [
        'blood_group'            => ['not_done', 'blood_a', 'blood_b', 'blood_ab', 'blood_o'],
        'done'                   => ['not_done', 'pending', 'done'],
        'positive_negative'      => ['not_done', 'pending', 'positive', 'negative'],
        'acceptable_abnormal'    => ['not_done', 'pending', 'acceptable', 'abnormal'],
        'acceptable_abnormal_na' => ['not_done', 'pending', 'acceptable', 'abnormal', 'not_applicable'],
        'cleared_not_cleared'    => ['not_done', 'pending', 'cleared', 'not_cleared', 'not_applicable'],
        // No "Not done": a vaccination that was not given says so, and the
        // two would have been the same answer under two names. Until one of
        // these is pressed the card simply has no answer on it — the record
        // still holds `not_done`, which is how the workup knows it is
        // outstanding, but nothing on the screen says it out loud.
        'given_not_given'        => ['given', 'not_required', 'not_given', 'not_applicable'],
        'seen_not_seen'          => ['not_done', 'seen', 'not_seen'],
        // No answer at all: the card is its comment box and nothing else.
        'free_text'              => [],
        // A test somebody added to their own record. The sheet cannot know
        // what it answers, so it offers everything the platform can say —
        // each keeping the colour it carries on every other card.
        'custom'                 => [
            'not_done', 'pending', 'done',
            'acceptable', 'abnormal',
            'negative', 'positive',
            'applicable', 'not_applicable',
            'cleared', 'not_cleared',
            'given', 'not_given',
            'required', 'not_required',
            'seen', 'not_seen',
        ],
        'text'                   => ['not_done', 'pending', 'done', 'not_applicable'],
        'numeric'                => ['not_done', 'pending', 'done', 'not_applicable'],
    ];

    public const RESULT_LABEL = [
        'not_done'       => 'Not done',
        'pending'        => 'Pending',
        'done'           => 'Done',
        'positive'       => 'Positive',
        'negative'       => 'Negative',
        'acceptable'     => 'Acceptable',
        'abnormal'       => 'Abnormal',
        'cleared'        => 'Cleared',
        'not_cleared'    => 'Not cleared',
        'given'          => 'Given',
        'required'       => 'Required',
        'not_required'   => 'Not required',
        'not_given'      => 'Not given',
        'applicable'     => 'Applicable',
        'not_applicable' => 'Not applicable',
        'seen'           => 'Seen',
        'not_seen'       => 'Not seen',
        'blood_a'        => 'A',
        'blood_b'        => 'B',
        'blood_ab'       => 'AB',
        'blood_o'        => 'O',
    ];

    /** Red is the answer somebody has to act on, not merely a bad one. */
    public const RESULT_TONE = [
        'not_done'       => 'tone-slate',
        'pending'        => 'tone-amber-soft',
        'done'           => 'tone-emerald',
        'positive'       => 'tone-red',
        'negative'       => 'tone-emerald',
        'acceptable'     => 'tone-emerald',
        'abnormal'       => 'tone-red',
        'cleared'        => 'tone-emerald',
        'not_cleared'    => 'tone-red',
        'given'          => 'tone-emerald',
        // Something still to do reads as attention; something that need not
        // be done, or does not apply, is neither good news nor bad.
        'required'       => 'tone-amber',
        'not_required'   => 'tone-slate',
        'not_given'      => 'tone-amber',
        'applicable'     => 'tone-slate',
        'not_applicable' => 'tone-slate',
        'seen'           => 'tone-emerald',
        'not_seen'       => 'tone-amber',
        'blood_a'        => 'tone-blue',
        'blood_b'        => 'tone-blue',
        'blood_ab'       => 'tone-blue',
        'blood_o'        => 'tone-blue',
    ];

    /** Where a test starts: nobody has looked at it yet. */
    public const RESULT_UNANSWERED = ['not_done', 'pending'];

    /**
     * Tests whose comment box the sheet gives a shape to.
     *
     * HLA typing is the only one: under its comment line the sheet prints the
     * loci to fill in — A / B / Cw on one row, DRB1 / DRB2 / DQ / DP on the
     * next — so the box is asking for a typing, not for a remark, and it is
     * sized and prompted for one.
     *
     * @var array<string, array{rows: int, placeholder: string}>
     */
    public const COMMENT_HINT = [
        'HLA Typing' => [
            'rows'        => 3,
            'placeholder' => "A  /  B  /  Cw\nDRB1  /  DRB2  /  DQ  /  DP",
        ],
    ];

    /** The answers a test can hold, whichever kind it is. */
    public const LAB_STATUSES = [
        'not_done', 'pending', 'done', 'positive', 'negative', 'acceptable',
        'abnormal', 'cleared', 'not_cleared', 'given', 'not_required', 'required',
        'not_given', 'not_applicable', 'applicable', 'seen', 'not_seen',
        'blood_a', 'blood_b', 'blood_ab', 'blood_o',
    ];

    /**
     * Tests with no answer to give, only something to write.
     *
     * "Other" is the sheet's blank line: one box for whatever the workup has
     * no row for. It has no status to set, so it is not something that can be
     * completed — which is why the progress count leaves it out entirely
     * rather than counting a card nobody can ever tick.
     */
    public const FREE_TEXT_TYPES = ['free_text'];

    public const GENDER_OPTIONS = ['Male' => 'Male', 'Female' => 'Female'];

    /**
     * What kind of donation this is, and the two lists the screens ask it with.
     *
     * Relatedness is a question about a donor *and a recipient*, so it can only
     * be answered where both are in view. Registering a donor on their own asks
     * the part that can be known — living or deceased — and the pair screens,
     * where the recipient is right there, ask the finer question. `living` is
     * therefore "living, relatedness not recorded yet", not a fourth kind.
     */
    public const DONATION_TYPES = [
        'living'           => 'Living',
        'living_related'   => 'Living Related',
        'living_unrelated' => 'Living Unrelated',
        'deceased'         => 'Deceased',
    ];

    /** Add Donor: no recipient in view. */
    public const DONATION_TYPES_ON_REGISTER = ['living', 'deceased'];

    /** Add Pair and the pair profile: the recipient is known. */
    public const DONATION_TYPES_ON_PAIR = ['living_related', 'living_unrelated', 'deceased'];

    /**
     * The same three, in the spelling the donor screens post.
     *
     * The donor form has always sent the label and converted on the way in
     * ("On Hold" -> `on_hold`), where the recipient form sends the key. Left
     * as it is: changing it would rewrite the mapping for no gain, and the
     * three values are the recipient's three.
     */
    public const DONOR_STATUS_OPTIONS = [
        'On Hold'  => 'On Hold',
        'Active'   => 'Active',
        'Declined' => 'Declined',
    ];

    /**
     * The columns an empty field may clear.
     *
     * Everything on `recipients` and `donors` that the migrations declare
     * nullable. The rest — name, blood group, the organ, the entry date — is
     * NOT NULL, so an empty box there is a slip and the stored value stands.
     */
    private const NULLABLE_COLUMNS = [
        'gender', 'age', 'city', 'phone',
        'dialysis_start', 'mrp_id', 'coordinator_id', 'relationship', 'notes',
    ];

    private Session $session;
    private RecipientModel $recipients;
    private DonorModel $donors;
    private PairModel $pairs;
    private MrpModel $mrp;
    private CoordinatorModel $coordinators;
    private LabModel $labs;
    private LabResultModel $labResults;
    private OrganProgramModel $programs;

    public function __construct(?Session $session = null)
    {
        $this->session      = $session ?? service('session');
        $this->recipients   = model(RecipientModel::class);
        $this->donors       = model(DonorModel::class);
        $this->pairs        = model(PairModel::class);
        $this->mrp          = model(MrpModel::class);
        $this->coordinators = model(CoordinatorModel::class);
        $this->labs         = model(LabModel::class);
        $this->labResults   = model(LabResultModel::class);
        $this->programs     = model(OrganProgramModel::class);
    }

    /** Signs out: clears the session keys, never the records. */
    public function reset(): void
    {
        $this->session->remove(['ui_organ', 'ui_staff_id']);
    }

    // ---- Session-level selections -----------------------------------------

    public function organ(): string
    {
        $organ = $this->session->get('ui_organ');

        return in_array($organ, self::ORGANS, true) ? $organ : 'kidney';
    }

    /** The programme's name as the picker shows it, for a title or a header. */
    public function organLabel(): string
    {
        $row = $this->programs->find($this->organ());

        return (string) ($row['label'] ?? ucfirst($this->organ()));
    }

    /**
     * Every programme, for a filter that offers them all rather than the one
     * the session is in.
     *
     * @return list<array<string, mixed>>
     */
    public function organs(): array
    {
        return $this->programs->orderBy('sort_order')->findAll();
    }

    /**
     * Every coordinator, for the same reason.
     *
     * @return list<array<string, mixed>>
     */
    public function coordinators(): array
    {
        return $this->coordinators->orderBy('name')->findAll();
    }

    public function setOrgan(string $organ): void
    {
        if (in_array($organ, self::ORGANS, true)) {
            $this->session->set('ui_organ', $organ);
        }
    }

    public function isSignedIn(): bool
    {
        return (string) $this->session->get('ui_staff_id') !== '';
    }

    public function signIn(string $staffId): void
    {
        $this->session->set('ui_staff_id', $staffId);
    }

    // ---- Reads -------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function recipients(?string $organ = null): array
    {
        $rows = $this->recipients->where('organ_code', $organ ?? $this->organ())->orderBy('mrn')->findAll();

        return array_map(fn (array $row): array => $this->recipientToUi($row), $rows);
    }

    /** @return list<array<string, mixed>> */
    /**
     * The waiting list as the screen shows it: everyone not held by an open
     * pair, most urgent first and then by score, with the score itself.
     *
     * Filtered and ordered in SQL by RecipientModel, because the score is a
     * computed column — PHP cannot sort by a number the query did not select,
     * which is why the screen used to show a table of made-up figures.
     *
     * @return list<array<string, mixed>>
     */
    public function waitingList(?string $bloodGroup = null): array
    {
        $rows = $this->recipients->waitingList($this->organ(), $bloodGroup);

        return array_map(function (array $row): array {
            $ui = $this->recipientToUi($row);
            // NULL when there is no dialysis date: the original system scored
            // those as nothing rather than as zero, and that is kept.
            $ui['score'] = $row['score'] === null ? null : (float) $row['score'];

            return $ui;
        }, $rows);
    }

    /**
     * The donors screen: every donor not held by an open pair.
     *
     * @return list<array<string, mixed>>
     */
    public function availableDonors(?string $bloodGroup = null): array
    {
        return array_map(
            fn (array $row): array => $this->donorToUi($row),
            $this->donors->register($this->organ(), true, $bloodGroup)
        );
    }

    public function donors(?string $organ = null): array
    {
        $rows = $this->donors->where('organ_code', $organ ?? $this->organ())->orderBy('mrn')->findAll();

        return array_map(fn (array $row): array => $this->donorToUi($row), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function pairs(?string $organ = null): array
    {
        $rows = $this->pairs->overview($organ ?? $this->organ());

        return array_map(fn (array $row): array => $this->pairToUi($row), $rows);
    }

    /** @return list<array{id: string, name: string}> */
    public function mrps(): array
    {
        return array_map(
            static fn (array $row): array => ['id' => (string) $row['id'], 'name' => $row['name']],
            $this->mrp->active()
        );
    }

    /**
     * How many recipients this programme has under one physician.
     *
     * Counted in SQL rather than by filtering the list, because the dashboard
     * only wants the number and the list is everything but.
     */
    public function recipientCountForMrp(?string $mrpId): int
    {
        if ($mrpId === null || $mrpId === '' || ! ctype_digit($mrpId)) {
            return 0;
        }

        return $this->recipients
            ->where('organ_code', $this->organ())
            ->where('mrp_id', (int) $mrpId)
            ->countAllResults();
    }

    public function findRecipient(?string $id): ?array
    {
        $row = $this->rowFor($this->recipients, $id);

        return $row === null ? null : $this->recipientToUi($row);
    }

    public function findDonor(?string $id): ?array
    {
        $row = $this->rowFor($this->donors, $id);

        return $row === null ? null : $this->donorToUi($row);
    }

    // ---- Removing a record -------------------------------------------------

    /**
     * Takes a recipient off the register, with their whole workup.
     *
     * The lab results go with them: a trigger on `recipients` deletes them,
     * standing in for the ON DELETE CASCADE that `lab_results.person_mrn`
     * cannot have, since it points at either register depending on the row.
     *
     * A recipient held by a pair is refused rather than deleted. Removing them
     * would leave the pair naming a patient who is not there — the foreign key
     * would block it anyway, and a message says so better than an SQL error.
     *
     * @return string An empty string when it is done, else why it was not
     */
    public function deleteRecipient(?string $id): string
    {
        $row = $this->onThisProgramme($this->rowFor($this->recipients, $id));

        if ($row === null) {
            return 'That recipient is not on this programme.';
        }

        $pair = $this->anyPairFor('recipient_mrn', (int) $row['mrn']);

        if ($pair !== null) {
            return $this->heldByAPair($row['name'], $pair);
        }

        $this->recipients->delete($row['mrn']);

        return '';
    }

    /** The same for a donor, and for the same reasons. */
    public function deleteDonor(?string $id): string
    {
        $row = $this->onThisProgramme($this->rowFor($this->donors, $id));

        if ($row === null) {
            return 'That donor is not on this programme.';
        }

        $pair = $this->anyPairFor('donor_mrn', (int) $row['mrn']);

        if ($pair !== null) {
            return $this->heldByAPair($row['name'], $pair);
        }

        $this->donors->delete($row['mrn']);

        return '';
    }

    /**
     * Unmakes a pair. Both people stay.
     *
     * Deleting the pairing is not deleting the two it joined: they go back to
     * their lists, records and workups intact, free to be matched again. This
     * is the difference between a pair entered by mistake and one that ended —
     * a pair that ended is closed, and the row stays as history.
     *
     * @return string An empty string when it is done, else why it was not
     */
    public function deletePair(?string $id): string
    {
        $pair = $this->findPair($id);

        if ($pair === null || $pair['organ'] !== $this->organ()) {
            return 'That pair is not on this programme.';
        }

        $this->pairs->delete((int) $id);

        return '';
    }

    /**
     * The row, but only if the current programme is the one holding it.
     *
     * An MRN is unique across both registers, so finding one by number reaches
     * a record on the other programme too. Reading one that way is harmless
     * and the record screens rely on it; deleting one is not, so the deletes
     * check the programme even though nothing else does.
     *
     * @param array<string, mixed>|null $row
     *
     * @return array<string, mixed>|null
     */
    private function onThisProgramme(?array $row): ?array
    {
        return $row !== null && $row['organ_code'] === $this->organ() ? $row : null;
    }

    /**
     * Puts a pair forward for a paired exchange, or takes it back.
     *
     * The exchange screen shows nothing that has not been through here, so
     * this is where a pair consents to being swapped apart.
     *
     * @return string An empty string when it is done, else why it was not
     */
    public function offerPairForExchange(?string $id, bool $offered): string
    {
        $pair = $this->findPair($id);

        if ($pair === null || $pair['organ'] !== $this->organ()) {
            return 'That pair is not on this programme.';
        }

        if ($offered && ! ExchangeDraft::isExchangeableStatus($pair['status'])) {
            return 'A pair that is ' . (self::STATUS_OPTIONS[$pair['status']] ?? $pair['status'])
                . ' cannot be offered for exchange.';
        }

        $this->pairs->offerForExchange((int) $id, $offered);

        return '';
    }

    /** Any pair naming this person, open or closed — a row is a row. */
    private function anyPairFor(string $column, int $mrn): ?array
    {
        return $this->pairs->where($column, $mrn)->orderBy('id')->first();
    }

    private function heldByAPair(string $name, array $pair): string
    {
        return $name . ' is in pair #' . $pair['id']
            . '. Delete that pair first — the two records are kept, only the link goes.';
    }

    public function findPair(?string $id): ?array
    {
        if (! $this->isMrn($id)) {
            return null;
        }

        $rows = $this->pairs->overview();

        foreach ($rows as $row) {
            if ((int) $row['id'] === (int) $id) {
                return $this->pairToUi($row);
            }
        }

        return null;
    }

    // ---- Writes ------------------------------------------------------------

    /** @param array<string, mixed> $recipient */
    public function addRecipient(array $recipient): void
    {
        $this->recipients->insert(
            $this->recipientToRow($recipient) + ['mrn' => (int) $recipient['id'], 'entry_date' => date('Y-m-d')]
        );
        $this->saveLabTests((int) $recipient['id'], 'recipient', $recipient['labTests'] ?? []);
    }

    /** @param array<string, mixed> $donor */
    public function addDonor(array $donor): void
    {
        $this->donors->insert(
            $this->donorToRow($donor) + ['mrn' => (int) $donor['id'], 'registered_on' => date('Y-m-d')]
        );
        $this->saveLabTests((int) $donor['id'], 'donor', $donor['labTests'] ?? []);
    }

    /**
     * Links a recipient and a donor.
     *
     * Goes through PairModel::link(), so the rule that neither side may
     * already be in an open pair is enforced here too, not just in the model.
     *
     * @param array<string, mixed> $pair
     */
    public function addPair(array $pair): int
    {
        $status = $this->statusKey((string) ($pair['status'] ?? '')) ?: 'active';

        $id = (int) $this->pairs->link((int) $pair['recipientId'], (int) $pair['donorId'], [
            'status'          => $status,
            'relationship'    => $pair['relationship'] ?? null,
            'crossmatch_date' => $this->toDate($pair['scheduledDate'] ?? null),
            'notes'           => $pair['notes'] ?? null,
        ]);

        return $id;
    }

    /**
     * The open pair holding this person, if one does.
     *
     * Used before offering to link them: somebody already in a pair is sent to
     * it rather than to a second one, which the tables would refuse anyway.
     */
    public function openPairFor(string $personType, int|string $mrn): ?array
    {
        $row = $personType === 'recipient'
            ? $this->pairs->openPairForRecipient($mrn)
            : $this->pairs->openPairForDonor($mrn);

        return $row === null ? null : $this->findPair((string) $row['id']);
    }

    /** The open pair joining these two specifically, or null. */
    public function pairWith(int|string $recipientMrn, int|string $donorMrn): ?array
    {
        $row = $this->pairs->openPairFor($recipientMrn, $donorMrn);

        return $row === null ? null : $this->findPair((string) $row['id']);
    }

    public function addMrp(string $code, string $name): void
    {
        $this->mrp->insert(['code' => $code, 'name' => $name]);
    }

    /** @param array<string, mixed> $changes */
    public function updateRecipient(string $id, array $changes): void
    {
        if (! $this->isMrn($id)) {
            return;
        }

        // A card that owns no column of its own — the workup, the notes —
        // leaves nothing for the row, and `update()` refuses an empty one.
        // It used to be saved from that by an entry_date default that also
        // moved the record's entry date to the day of every edit.
        $row = $this->recipientToRow($changes);

        if ($row !== []) {
            $this->recipients->update((int) $id, $row);
        }

        if (isset($changes['labTests'])) {
            $this->saveLabTests((int) $id, 'recipient', $changes['labTests']);
        }

        // A recipient's status used to be the same fact as their pair's, and
        // setting one set the other. It cannot be, now that a recipient may
        // hold several donors at once: there would be no saying which of them
        // Declined meant. The person's status is the person's — are they on
        // the programme — and each link carries its own.
    }

    /** @param array<string, mixed> $changes */
    public function updateDonor(string $id, array $changes): void
    {
        if (! $this->isMrn($id)) {
            return;
        }

        $row = $this->donorToRow($changes);

        if ($row !== []) {
            $this->donors->update((int) $id, $row);
        }

        if (isset($changes['labTests'])) {
            $this->saveLabTests((int) $id, 'donor', $changes['labTests']);
        }
    }

    /** @param array<string, mixed> $changes */
    public function updatePair(string $id, array $changes): void
    {
        if (! $this->isMrn($id)) {
            return;
        }

        $row    = [];
        $status = $this->statusKey((string) ($changes['status'] ?? ''));

        if ($status !== '') {
            $row['status'] = $status;
        }

        if (array_key_exists('scheduledDate', $changes)) {
            $row['crossmatch_date'] = $this->toDate($changes['scheduledDate']);
        }

        if (array_key_exists('notes', $changes)) {
            $row['notes'] = $changes['notes'];
        }

        if (array_key_exists('relationship', $changes)) {
            $row['relationship'] = $changes['relationship'];
        }

        // Why it was closed, kept only while it is. Moving a pair off Closed
        // clears the reason rather than leaving a sentence about an ending
        // that has been undone.
        if (array_key_exists('closedReason', $changes)) {
            $reason              = trim((string) $changes['closedReason']);
            $row['closed_reason'] = $status === 'closed' && $reason !== '' ? $reason : null;
        }

        if ($row !== []) {
            $this->pairs->update((int) $id, $row);
        }

    }

    /**
     * Gives a recipient their pair's status, where it is one they can hold.
     *
     * Silently does nothing for the three that only describe a pair. That is
     * the point: a recipient whose pair has just been closed is not himself
     * "closed", he is whatever he was — and the waiting list, which is defined
     * against the pair rather than against this column, already shows him
     * again.
     */
    // ---- Medical record numbers --------------------------------------------

    /**
     * Whether a register already holds this MRN.
     *
     * The MRN is the hospital's own number — it comes off TrakCare with the
     * patient — so the forms collect it and the system never invents one. All
     * it can do is refuse a number this register already has, since the MRN is
     * the primary key and a second row under it would be a different person
     * wearing the first one's identity.
     *
     * The two registers are checked separately on purpose: the same MRN on
     * both sides is one person who is a recipient in one programme and a donor
     * in another, which is allowed. Only the pair screen refuses it, because
     * there it would mean donating to oneself.
     */
    public function mrnTaken(int|string $mrn, string $personType): bool
    {
        $model = $personType === 'recipient' ? $this->recipients : $this->donors;

        return $model->find((int) $mrn) !== null;
    }

    /**
     * The blank workup a new record starts with, read from the catalogue.
     *
     * Was a hardcoded list; it is the `labs` table now, so changing a workup
     * is a row rather than a deployment.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultLabTests(string $organ, string $personType): array
    {
        return array_map(
            static fn (array $lab): array => [
                'id'         => (string) $lab['id'],
                'name'       => $lab['name'],
                'group'      => (string) ($lab['parent_name'] ?? ''),
                'resultType' => (string) ($lab['result_type'] ?? 'text'),
                'custom'     => false,
                'status'     => 'not_done',
                'result'     => '',
                'date'       => '',
                'notes'      => '',
            ],
            model(LabModel::class)->workupFor($organ, $personType)
        );
    }

    // ---- Translating between the screens and the tables --------------------

    /** @return array<string, mixed>|null */
    private function rowFor(Model $model, ?string $id): ?array
    {
        return $this->isMrn($id) ? $model->find((int) $id) : null;
    }

    private function isMrn(?string $id): bool
    {
        return $id !== null && $id !== '' && ctype_digit($id);
    }

    /** @param array<string, mixed> $row */
    private function recipientToUi(array $row): array
    {
        $pair = $this->pairs->openPairForRecipient($row['mrn']);

        return [
            'id'             => (string) $row['mrn'],
            'type'           => 'recipient',
            'organ'          => $row['organ_code'],
            'name'           => $row['name'],
            // Stored and derived, in that order: a record entered before
            // birth dates were collected has a number and no date, and one
            // entered since has both — with the number rewritten from the
            // date every time it is saved, so the two cannot disagree.
            'age'            => (int) $row['age'],
            'birthDate'      => $row['birth_date'] === null ? '' : self::isoToDMY($row['birth_date']),
            'bloodType'      => $row['blood_group'],
            'gender'         => $this->genderToUi($row['gender']),
            'phone'          => (string) $row['phone'],
            'address'        => (string) $row['city'],
            'urgent'         => (bool) $row['is_urgent'],
            'status'         => $row['status'],
            'dateRegistered' => (string) $row['entry_date'],
            'dialysisType'   => (string) ($row['dialysis_type'] ?? ''),
            'firstDialysis'  => $row['dialysis_start'] === null ? '' : self::isoToDMY($row['dialysis_start']),
            'selectedMrp'    => (string) ($row['mrp_id'] ?? ''),
            'coordinator'    => $this->coordinatorName($row['coordinator_id'] ?? null),
            'notes'          => (string) $row['notes'],
            'labTests'       => $this->labTestsFor($row['mrn'], 'recipient', $row['organ_code']),
            'pairedDonorId'  => $pair === null ? '' : (string) $pair['donor_mrn'],
            // Every donor they have been linked with, the undone ones too:
            // the screens show those greyed rather than forgetting them.
            'donors'         => $this->donorTabs($row['mrn']),
        ];
    }

    /**
     * A recipient's donors, one entry per link, in the order they were made.
     *
     * Each is a tab on their screen: the number it is, whose link it is, and
     * what became of it. A closed pair is a link that was undone — it stays
     * on the record, read-only.
     *
     * @return list<array<string, mixed>>
     */
    private function donorTabs(int|string $mrn): array
    {
        $tabs = [];

        foreach ($this->pairs->pairsForRecipient($mrn) as $i => $pair) {
            $delinked = $pair['status'] === PairModel::CLOSED;

            $tabs[] = [
                'number'       => $i + 1,
                'pairId'       => (string) $pair['id'],
                'donorId'      => (string) $pair['donor_mrn'],
                'name'         => (string) ($pair['donor_name'] ?? ''),
                'bloodType'    => (string) ($pair['donor_blood_group'] ?? ''),
                // The link's own status is what the tab shows. A delinked one
                // reads Declined whatever the pair row says, because that is
                // what delinking means.
                'status'       => $delinked ? 'declined' : (string) $pair['status'],
                'donorStatus'  => (string) ($pair['donor_status'] ?? ''),
                'relationship' => (string) ($pair['relationship'] ?? ''),
                'delinked'     => $delinked,
                'closedReason' => (string) ($pair['closed_reason'] ?? ''),
            ];
        }

        return $tabs;
    }

    /**
     * Undoes one of a recipient's links.
     *
     * The pair closes and the donor is Declined — they were looked at for
     * this recipient and are not going ahead. Nothing is deleted: the tab
     * stays on the record, greyed, because a donor who was considered and
     * set aside is part of what happened.
     */
    public function delinkDonor(string $recipientMrn, string $pairId, string $reason = ''): string
    {
        $pair = $this->findPair($pairId);

        if ($pair === null || (string) $pair['recipientId'] !== $recipientMrn) {
            return 'That link could not be found.';
        }

        if ($pair['status'] === PairModel::CLOSED) {
            return 'That link has already been undone.';
        }

        $this->pairs->update((int) $pair['id'], [
            'status'        => PairModel::CLOSED,
            'closed_reason' => trim($reason) === '' ? 'Delinked from the recipient.' : trim($reason),
        ]);

        $this->donors->update((int) $pair['donorId'], ['status' => 'declined']);

        return '';
    }

    /** @param array<string, mixed> $row */
    private function donorToUi(array $row): array
    {
        $pair = $this->pairs->openPairForDonor($row['mrn']);

        return [
            'id'                => (string) $row['mrn'],
            'type'              => 'donor',
            'organ'             => $row['organ_code'],
            'name'              => $row['name'],
            'age'               => (int) $row['age'],
            'birthDate'         => $row['birth_date'] === null ? '' : self::isoToDMY($row['birth_date']),
            'bloodType'         => $row['blood_group'],
            'donorGender'       => $this->genderToUi($row['gender']),
            'phone'             => (string) $row['phone'],
            'address'           => (string) $row['city'],
            'donationType'      => $row['donation_type'],
            'relationship'      => (string) $row['relationship'],
            'donorStatus'       => $this->statusToUi($row['status']),
            'donorMrp'          => (string) ($row['mrp_id'] ?? ''),
            'donorCoordinator'  => (string) ($this->coordinatorName($row['coordinator_id'] ?? null)),
            'dateRegistered'    => (string) $row['registered_on'],
            'notes'             => (string) $row['notes'],
            'labTests'          => $this->labTestsFor($row['mrn'], 'donor', $row['organ_code']),
            'pairedRecipientId' => $pair === null ? '' : (string) $pair['recipient_mrn'],
        ];
    }

    /** @param array<string, mixed> $row A PairModel::overview() row. */
    private function pairToUi(array $row): array
    {
        return [
            'id'            => (string) $row['id'],
            'organ'         => $row['organ_code'],
            'status'        => $row['status'],
            'forExchange'   => (int) ($row['for_exchange'] ?? 0) === 1,
            'recipientId'   => (string) $row['recipient_mrn'],
            'donorId'       => (string) $row['donor_mrn'],
            'relationship'  => (string) $row['relationship'],
            'closedReason'  => (string) ($row['closed_reason'] ?? ''),
            'scheduledDate' => $row['crossmatch_date'] === null ? '' : self::isoToDMY($row['crossmatch_date']),
            'createdDate'   => substr((string) $row['created_at'], 0, 10),
            'notes'         => (string) $row['notes'],
        ];
    }

    /**
     * The screen's fields as a `recipients` row. Only what was supplied, so
     * this works for both an insert and a partial update.
     *
     * @param array<string, mixed> $ui
     *
     * @return array<string, mixed>
     */
    private function recipientToRow(array $ui): array
    {
        $map = [
            'name' => 'name', 'age' => 'age', 'bloodType' => 'blood_group',
            'phone' => 'phone', 'address' => 'city', 'notes' => 'notes',
            'organ' => 'organ_code', 'selectedMrp' => 'mrp_id',
            'dateRegistered' => 'entry_date', 'firstDialysis' => 'dialysis_start',
            'birthDate' => 'birth_date',
        ];

        $row = $this->mapFields($ui, $map);

        if (($ui['gender'] ?? '') !== '') {
            $row['gender'] = $this->genderToRow($ui['gender']);
        }

        $this->ageFromBirthDate($ui, $row);

        // Pre-emptive means a transplant before dialysis begins, so there is
        // no first dialysis to record — and a date left behind from before the
        // answer changed would be a date for something that never happened.
        if (array_key_exists('dialysisType', $ui)) {
            $type                  = (string) $ui['dialysisType'];
            $row['dialysis_type']  = isset(self::DIALYSIS_TYPES[$type]) ? $type : null;

            if ($type === self::DIALYSIS_PREEMPTIVE) {
                $row['dialysis_start'] = null;
            }
        }

        // A checkbox is absent from the post when it is unticked, so the card
        // it sits on says whether the question was asked at all.
        if (array_key_exists('urgent', $ui)) {
            $row['is_urgent'] = $ui['urgent'] ? 1 : 0;
        }

        if (isset($ui['coordinator'])) {
            $row['coordinator_id'] = $this->coordinatorId((string) $ui['coordinator']);
        }

        if ($this->statusKey((string) ($ui['status'] ?? '')) !== '') {
            $row['status'] = $ui['status'];
        }

        foreach (['entry_date', 'dialysis_start', 'birth_date'] as $dateColumn) {
            if (array_key_exists($dateColumn, $row) && $row[$dateColumn] !== null) {
                $row[$dateColumn] = $this->toDate($row[$dateColumn]);
            }
        }

        // No `entry_date` default here. This builds a partial row for an
        // update as well as for an insert, and defaulting it meant every edit
        // of any card moved the record's entry date to the day of the edit.
        // `addRecipient` sets it, because that is where it is a new record.
        return $row;
    }

    /**
     * @param array<string, mixed> $ui
     *
     * @return array<string, mixed>
     */
    private function donorToRow(array $ui): array
    {
        $map = [
            'name' => 'name', 'age' => 'age', 'bloodType' => 'blood_group',
            'phone' => 'phone', 'address' => 'city',
            'notes' => 'notes', 'organ' => 'organ_code', 'donorMrp' => 'mrp_id',
            'relationship' => 'relationship', 'birthDate' => 'birth_date',
            'dateRegistered' => 'registered_on',
        ];

        $row = $this->mapFields($ui, $map);

        if (($ui['donorGender'] ?? '') !== '') {
            $row['gender'] = $this->genderToRow($ui['donorGender']);
        }

        $this->ageFromBirthDate($ui, $row);

        if ($this->donationTypeKey((string) ($ui['donationType'] ?? '')) !== '') {
            $row['donation_type'] = $ui['donationType'];
        }

        // An absent or empty control means the screen did not offer the field,
        // so the column keeps what it had. Writing '' would be neither a
        // status nor a clear: the ENUM rejects it and takes the request down.
        if (($ui['donorStatus'] ?? '') !== '') {
            $row['status'] = $this->statusToRow($ui['donorStatus']);
        }

        if (isset($ui['donorCoordinator'])) {
            $row['coordinator_id'] = $this->coordinatorId((string) $ui['donorCoordinator']);
        }

        foreach (['registered_on', 'birth_date'] as $dateColumn) {
            if (array_key_exists($dateColumn, $row) && $row[$dateColumn] !== null) {
                $row[$dateColumn] = $this->toDate($row[$dateColumn]);
            }
        }

        // As on the recipients' side: `addDonor` dates a new record, and an
        // edit leaves the date the record already has alone.
        return $row;
    }

    /**
     * Keeps the stored age in step with the stored birth date.
     *
     * The screens collect the date and work the age out from it; this is what
     * puts that number in the column every list, filter and report reads. A
     * record with no birth date — one entered before they were collected —
     * keeps whatever age it was given, which is why the number is still a
     * column and not a calculation.
     *
     * @param array<string, mixed> $ui
     * @param array<string, mixed> $row
     */
    private function ageFromBirthDate(array $ui, array &$row): void
    {
        if (! array_key_exists('birthDate', $ui)) {
            return;
        }

        $birth = trim((string) $ui['birthDate']);

        if ($birth === '') {
            // Cleared: the date goes, and the age stays whatever was typed
            // beside it rather than dropping to zero.
            $row['birth_date'] = null;

            return;
        }

        $row['age'] = self::ageFrom($birth);
    }

    /**
     * @param array<string, mixed> $ui
     * @param array<string, string> $map
     *
     * @return array<string, mixed>
     */
    private function mapFields(array $ui, array $map): array
    {
        $row = [];

        foreach ($map as $uiKey => $column) {
            if (! array_key_exists($uiKey, $ui)) {
                continue;
            }

            if ($ui[$uiKey] !== '') {
                $row[$column] = $ui[$uiKey];

                continue;
            }

            // An emptied box on a card that was open for editing means the
            // value was removed, so a nullable column is cleared rather than
            // left as it was — otherwise a wrong phone number could never be
            // taken off a record. A column that cannot be NULL keeps what it
            // had: blanking a name is a mistake, not an instruction.
            if (in_array($column, self::NULLABLE_COLUMNS, true)) {
                $row[$column] = null;
            }
        }

        return $row;
    }

    /**
     * Adds a test to one record, under the group that heads such tests.
     *
     * Blank to begin with: the card it becomes carries the name field, so it
     * is typed in the same place it is read, and saved with the answer. It
     * offers every answer the platform has, because the check list is not the
     * one asking the question.
     *
     * Returns the new test's id, or 0 when the record is not one this
     * programme holds.
     */
    public function addCustomLab(string $mrn, string $personType): int
    {
        if (! $this->isMrn($mrn) || ! in_array($personType, ['recipient', 'donor'], true)) {
            return 0;
        }

        $organ  = $this->organ();
        $person = $personType === 'recipient' ? $this->findRecipient($mrn) : $this->findDonor($mrn);

        if ($person === null) {
            return 0;
        }

        $parentId = $this->labs->customGroupId(DatabaseSeeder::CUSTOM_GROUP, $personType);

        if ($parentId === null) {
            return 0;
        }

        // After everything the check list asks for, in the order they were
        // added. Two blank names would collide under the unique key, so each
        // starts as its own number until somebody types over it.
        $last = $this->labs->lastSortOrder($organ, $personType) + 1;

        $this->labs->insert([
            'name'          => 'New test ' . $last,
            'lab_parent_id' => $parentId,
            'organ_code'    => $organ,
            'person_type'   => $personType,
            'person_mrn'    => (int) $mrn,
            'result_type'   => 'custom',
            'sort_order'    => $last,
            'is_active'     => 1,
        ]);

        return (int) $this->labs->getInsertID();
    }

    /**
     * One of a record's own tests, or null when it is not theirs.
     *
     * @return array<string, mixed>|null
     */
    public function customLab(string $mrn, string $personType, int $labId): ?array
    {
        if (! $this->isMrn($mrn)) {
            return null;
        }

        $lab = $this->labs->find($labId);

        if ($lab === null
            || $lab['person_mrn'] === null
            || (int) $lab['person_mrn'] !== (int) $mrn
            || $lab['person_type'] !== $personType) {
            return null;
        }

        return $lab;
    }

    /**
     * Takes one of a record's own tests away again.
     *
     * Only ever its own: a catalogue test is the programme's and cannot be
     * removed from one record. Whatever was recorded against it goes with it —
     * `lab_results.lab_id` cascades — which is right, because the test it was
     * recorded against will not exist.
     */
    public function removeCustomLab(string $mrn, string $personType, int $labId): bool
    {
        if ($this->customLab($mrn, $personType, $labId) === null) {
            return false;
        }

        $this->labs->delete($labId);

        return true;
    }

    /**
     * A person's workup: every test their programme calls for, with whatever
     * has been recorded against it — so an untouched test is still a card.
     *
     * @return list<array<string, mixed>>
     */
    private function labTestsFor(int $mrn, string $personType, string $organCode): array
    {
        return array_map(
            static fn (array $row): array => [
                'id'         => (string) $row['lab_id'],
                'name'       => $row['lab_name'],
                'group'      => (string) ($row['parent_name'] ?? ''),
                'resultType' => (string) ($row['result_type'] ?? 'text'),
                // A test this record added for itself: its name is theirs to
                // type and theirs to take away again.
                'custom'     => $row['owner_mrn'] !== null,
                'status'     => $row['status'],
                'result'     => (string) $row['value'],
                'date'       => $row['taken_on'] === null ? '' : self::isoToDMY($row['taken_on']),
                'notes'      => (string) $row['notes'],
            ],
            $this->labResults->workupFor($mrn, $personType, $organCode)
        );
    }

    /**
     * Writes back the lab cards a form posted. A card left untouched — pending
     * with nothing filled in — is not written, so an empty workup stays empty
     * rather than filling the table with blank rows.
     *
     * @param list<array<string, mixed>> $tests
     */
    private function saveLabTests(int $mrn, string $personType, array $tests): void
    {
        foreach ($tests as $test) {
            $labId = (int) ($test['id'] ?? 0);

            if ($labId === 0) {
                continue;
            }

            $notes = (string) ($test['notes'] ?? '');

            $lab = $this->labs->find($labId);

            // The test has to be one this side's sheet asks for. Each sheet
            // has its own row for a test both ask for, so a donor's CBC and a
            // recipient's are different ids and a result filed against the
            // wrong one would be invisible on the screen that entered it.
            if ($lab === null || $lab['person_type'] !== $personType) {
                continue;
            }

            // A test somebody added belongs to one record, and only that
            // record may answer it. Without this, a posted id would reach
            // another patient's test.
            $ownedByAnother = $lab['person_mrn'] !== null && (int) $lab['person_mrn'] !== $mrn;

            if ($ownedByAnother) {
                continue;
            }

            // Their own test, so its name is theirs to type. The catalogue's
            // names are the check list's and are never posted back.
            if ($lab['person_mrn'] !== null) {
                $typed = trim((string) ($test['name'] ?? ''));

                if ($typed !== '' && $typed !== $lab['name']) {
                    $this->labs->update($labId, ['name' => mb_substr($typed, 0, 150)]);
                }
            }

            // The answer has to be one this test actually offers. The form
            // renders only those, so anything else was not typed on a screen.
            $offered = self::RESULT_OPTIONS[$lab['result_type']] ?? self::RESULT_OPTIONS['text'];
            $status  = (string) ($test['status'] ?? 'not_done');
            $status  = in_array($status, $offered, true) ? $status : 'not_done';

            // Nothing recorded and nothing said: no row to write.
            if ($status === 'not_done' && $notes === '') {
                continue;
            }

            // `value` and `taken_on` are not written from here any more — the
            // cards stopped asking for them. Leaving the keys out means an
            // existing row keeps whatever it already holds rather than having
            // it nulled by a screen that can no longer show it.
            $this->labResults->record($mrn, $personType, $labId, [
                'status' => $status,
                'notes'  => $notes === '' ? null : $notes,
            ]);
        }
    }

    /** "15/01/2026" or "2026-01-15" -> "2026-01-15"; anything else -> null. */
    private function toDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $value, $m) === 1) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return preg_match('~^\d{4}-\d{2}-\d{2}$~', $value) === 1 ? $value : null;
    }

    private function genderToUi(?string $gender): string
    {
        return $gender === null ? 'Male' : ucfirst($gender);
    }

    private function genderToRow(string $gender): string
    {
        return strtolower($gender) === 'female' ? 'female' : 'male';
    }

    /** "On Hold" <-> on_hold */
    private function statusToUi(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    private function statusToRow(string $status): string
    {
        return str_replace(' ', '_', strtolower($status));
    }

    /** The screens write "on-hold"; the column is `on_hold`. */
    /**
     * The id of the coordinator with this name, registering them if this is the
     * first time the name has been typed.
     *
     * The design collects the coordinator as free text — there is no screen
     * that registers one — but the column is a foreign key, so the name has to
     * become a row before it can be stored. Matching is on the trimmed name, so
     * typing the same person twice does not create two of them. An empty box
     * clears the field rather than registering a coordinator with no name.
     */
    private function coordinatorId(string $name): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $existing = $this->coordinators->where('name', $name)->first();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $this->coordinators->insert(['name' => $name, 'is_active' => 1]);

        return (int) $this->coordinators->getInsertID();
    }

    /** The name behind a `coordinator_id`, or '' when there is none. */
    private function coordinatorName(int|string|null $id): string
    {
        if ($id === null || $id === '') {
            return '';
        }

        $row = $this->coordinators->find((int) $id);

        return $row === null ? '' : (string) $row['name'];
    }

    /**
     * A status from the shared list, or '' for anything else.
     *
     * The keys are what the columns store, so there is no translation to do —
     * only the check that a value is one of them, since both are ENUMs and
     * anything else takes the request down.
     */
    private function statusKey(string $status): string
    {
        return isset(self::STATUS_OPTIONS[$status]) ? $status : '';
    }

    /** The three a person's own record may be set to, and nothing else. */
    private function personStatusKey(string $status): string
    {
        return isset(self::PERSON_STATUS_OPTIONS[$status]) ? $status : '';
    }

    /** The same, for the donation type: both columns are ENUMs. */
    private function donationTypeKey(string $type): string
    {
        return isset(self::DONATION_TYPES[$type]) ? $type : '';
    }

    /** "2026-01-15" -> "15/01/2026" */
    public static function isoToDMY(string $iso): string
    {
        return implode('/', array_reverse(explode('-', $iso)));
    }

    /**
     * Somebody's age today, from the date they were born.
     *
     * Whole years, counted the way a birthday is: you are 40 until the day
     * comes round again. 0 for anything that is not a date, or a date in the
     * future — neither of which can be an age.
     */
    public static function ageFrom(string $value): int
    {
        $iso = self::dmyToIso($value);

        if ($iso === '') {
            return 0;
        }

        $born  = new \DateTimeImmutable($iso);
        $today = new \DateTimeImmutable('today');

        return $born > $today ? 0 : (int) $born->diff($today)->y;
    }

    /**
     * Whether a date the screens collect is still to come.
     *
     * Everything the personal details ask for has already happened — when
     * somebody was born, when their dialysis began, the day they joined the
     * register — so a date after today is a typing mistake, and the forms say
     * so rather than storing it. Anything that is not a date is not a future
     * one: the field's own rules decide what to do about that.
     */
    public static function isFutureDate(string $value): bool
    {
        $iso = self::dmyToIso($value);

        return $iso !== '' && $iso > date('Y-m-d');
    }

    /** The other way, for a date typed into a filter. '' when it is neither. */
    public static function dmyToIso(string $value): string
    {
        $value = trim($value);

        if (preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $value, $m) === 1) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return preg_match('~^\d{4}-\d{2}-\d{2}$~', $value) === 1 ? $value : '';
    }

    /**
     * Whether a test's own answer list contains the answer it is holding.
     *
     * It does not when the record has never been answered and the test does
     * not offer "Not done" — a vaccination, as of now. Such a card shows no
     * answer at all rather than one the test cannot give.
     */
    public static function offersAnswer(string $resultType, string $status): bool
    {
        return in_array($status, self::RESULT_OPTIONS[$resultType] ?? self::RESULT_OPTIONS['text'], true);
    }

    /**
     * Completed / total for a person's workup.
     *
     * @param list<array<string, mixed>> $labTests
     *
     * @return array{done: int, total: int, pct: int}
     */
    public static function labProgress(array $labTests): array
    {
        // A free-text card has no answer to give, so it is neither done nor
        // outstanding — counting it would hold the bar below 100% for ever.
        $countable = array_filter(
            $labTests,
            static fn (array $t): bool => ! in_array($t['resultType'] ?? '', self::FREE_TEXT_TYPES, true)
        );

        $total = count($countable);
        $done  = count(array_filter(
            $countable,
            static fn (array $t): bool => ! in_array($t['status'], self::RESULT_UNANSWERED, true)
        ));

        return ['done' => $done, 'total' => $total, 'pct' => (int) round($done / max($total, 1) * 100)];
    }
}
