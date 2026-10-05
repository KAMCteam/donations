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
     * Four of the pair's six, and deliberately not the other two: a person is
     * not "closed" and is not "in a paired exchange"; their *case* is, and
     * that is the pair's to say. Transplanted is theirs, though — a transplant
     * is a thing that happens to a person — so the word belongs on both sides.
     *
     * Being exactly a subset is what lets the pair's status carry into them:
     * set the pair to one of these four and the recipient and the donor on it
     * are given the same word, because it is the same fact said once.
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
        'on_hold'      => 'On Hold',
        'active'       => 'Active',
        'declined'     => 'Declined',
        'transplanted' => 'Transplanted',
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
     * The colours a lab answer can carry, and what each is for.
     *
     * The platform's own, not a colour wheel: a test somebody adds picks from
     * the same ten the check list's answers already use, so a card they made
     * reads like every other card. The words are what the picker shows under
     * each swatch — a colour on a medical record means something, and naming
     * it is how somebody chooses the right one rather than the nicest one.
     */
    public const LAB_TONES = [
        'tone-red'     => 'Red',
        'tone-amber'   => 'Yellow',
        'tone-emerald' => 'Green',
        'tone-blue'    => 'Blue',
        'tone-slate'   => 'Gray',
    ];

    /**
     * The nine the check list itself uses, as the five on offer.
     *
     * The platform's own answers were painted in shades — amber and amber-soft,
     * teal and teal-soft — which is a distinction worth making on a sheet
     * somebody else wrote and not one worth asking a coordinator to make. So
     * the picker offers five colours by their own names, and a shade stored
     * before that reads as the one it is nearest to.
     */
    public const LAB_TONE_ALIAS = [
        'tone-amber-soft' => 'tone-amber',
        'tone-orange'     => 'tone-amber',
        'tone-teal'       => 'tone-emerald',
        'tone-teal-soft'  => 'tone-emerald',
        'tone-blue-soft'  => 'tone-blue',
    ];

    /** The colour an answer nobody has given a colour to carries. */
    public const LAB_TONE_DEFAULT = 'tone-slate';

    /**
     * What a test added under "Other" answers until somebody says otherwise.
     *
     * The three every test has in common: not looked at, being looked at,
     * looked at. Seventeen was not a choice, it was a list — and the point of
     * the card is that the person adding the test knows what it asks.
     */
    public const CUSTOM_ANSWER_DEFAULT = ['not_done', 'pending', 'done'];

    /** How wide an answer somebody writes themselves may be. */
    public const CUSTOM_ANSWER_MAX = 40;

    /** What marks an answer as one somebody wrote rather than one of ours. */
    public const CUSTOM_ANSWER_PREFIX = 'c_';

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
     * The same four, in the spelling the donor screens post.
     *
     * The donor form has always sent the label and converted on the way in
     * ("On Hold" -> `on_hold`), where the recipient form sends the key. Left
     * as it is: changing it would rewrite the mapping for no gain, and the
     * four values are the recipient's four.
     */
    public const DONOR_STATUS_OPTIONS = [
        'On Hold'      => 'On Hold',
        'Active'       => 'Active',
        'Declined'     => 'Declined',
        'Transplanted' => 'Transplanted',
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
     * The coordinators a record can be given, for the select that offers them.
     *
     * Active ones only: deactivating somebody on Add MRP is saying they are
     * not to be assigned any more, and a list that went on offering them would
     * be ignoring that. A record that already names them keeps them — the
     * control adds the name back at the bottom rather than dropping it.
     *
     * @return list<array<string, mixed>>
     */
    public function coordinators(): array
    {
        return $this->coordinators->where('is_active', 1)->orderBy('name')->findAll();
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
    public function waitingList(?string $bloodGroup = null, ?string $status = null, ?string $query = null): array
    {
        $rows = $this->recipients->waitingList($this->organ(), $bloodGroup, $status, $query);

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
    public function availableDonors(?string $bloodGroup = null, ?string $status = null, ?string $query = null): array
    {
        return array_map(
            fn (array $row): array => $this->donorToUi($row),
            $this->donors->register($this->organ(), true, $bloodGroup, $status, $query)
        );
    }

    public function donors(?string $organ = null): array
    {
        $rows = $this->donors
            ->where('organ_code', $organ ?? $this->organ())
            ->where('is_listed', 1)
            ->orderBy('mrn')
            ->findAll();

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

    /**
     * The pair a link belongs to, read as the whole case.
     *
     * A pair is one recipient and all the donors worked up for them, and every
     * one of those is a row in `pairs` with an id of its own. So any of those
     * ids opens the same pair — what differs is only which tab the screen
     * lands on. The row this returns is the pair's own: the link to the donor
     * it is going ahead with, or, when it has none, the last one it had.
     *
     * With `$byRecipient`, the id is a recipient's file number instead, which
     * is how a screen that knows who it is about finds their pair.
     */
    public function findPairCase(?string $id, bool $byRecipient = false): ?array
    {
        if (! $this->isMrn($id)) {
            return null;
        }

        if ($byRecipient) {
            $recipientMrn = (string) $id;
        } else {
            $row = $this->pairs->find((int) $id);

            if ($row === null) {
                return null;
            }

            $recipientMrn = (string) $row['recipient_mrn'];
        }

        $links = $this->pairs->pairsForRecipient($recipientMrn);

        if ($links === []) {
            return null;
        }

        $primary = null;

        foreach ($links as $link) {
            if ($link['status'] === PairModel::CLOSED) {
                continue;
            }

            // The donor it is going ahead with wins outright; failing that,
            // the last link still open is the one the pair is working from.
            if ($primary === null || ($link['donor_status'] ?? '') === 'active') {
                $primary = $link;
            }
        }

        $primary ??= $links[array_key_last($links)];
        $pair = $this->findPair((string) $primary['id']);

        if ($pair === null) {
            return null;
        }

        $recipient = $this->recipients->find((int) $recipientMrn);
        $pair['recipientName'] = (string) ($recipient['name'] ?? '');

        return $pair;
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
            $this->donorToRow($donor) + [
                'mrn'           => (int) $donor['id'],
                'registered_on' => date('Y-m-d'),
                // On the register from the start. A donor used to be able to
                // be entered as somebody's candidate and kept off it until a
                // pair was made; there are no candidates now, so there is
                // nobody to keep off.
                'is_listed'     => 1,
            ]
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
            // A pair created as Paired Exchange is on that list from the
            // moment it exists, for the same reason.
            'for_exchange'    => $status === PairModel::EXCHANGE ? 1 : 0,
            'relationship'    => $pair['relationship'] ?? null,
            'crossmatch_date' => $this->toDate($pair['scheduledDate'] ?? null),
            'surgery_date'    => $status === 'transplanted' ? $this->toDate($pair['transplantDate'] ?? null) : null,
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

    /**
     * Registers a user — a physician or a coordinator.
     *
     * A coordinator also gets a row in `coordinators`, which is what the
     * record screens point at: the MRP register is where people are made, and
     * that table is where a record's coordinator lives. Without both, somebody
     * registered here could not be assigned to anybody.
     *
     * @return string '' on success, or why not
     */
    /**
     * Registers a user — a physician or a coordinator.
     *
     * A coordinator also gets a row in `coordinators`, which is what the
     * record screens point at: the MRP register is where people are made, and
     * that table is where a record's coordinator lives. Without both, somebody
     * registered here could not be assigned to anybody.
     *
     * @return string '' on success, or why not
     */
    /**
     * Everything on this programme that answers to a name or a number.
     *
     * One question asked of every register at once, which is what somebody
     * typing into the bar at the top of the screen is doing: they have an MRN
     * on a form, or half a name, and they do not yet know which list it is on.
     *
     * Matched on the MRN and on the name, because those are the two things
     * written on the paperwork a person arrives with. Capped, because a search
     * that returns the whole register has not answered anything.
     *
     * @return array{recipients: list<array<string, mixed>>, donors: list<array<string, mixed>>, pairs: list<array<string, mixed>>, users: list<array<string, mixed>>}
     */
    public function search(string $query, int $limit = 25): array
    {
        $query = trim($query);

        if ($query === '') {
            return ['recipients' => [], 'donors' => [], 'pairs' => [], 'users' => []];
        }

        $organ = $this->organ();

        $people = static function (RecipientModel|DonorModel $model) use ($query, $organ, $limit): array {
            return $model
                ->where('organ_code', $organ)
                ->groupStart()
                    ->like('mrn', $query)
                    ->orLike('name', $query)
                ->groupEnd()
                ->orderBy('name')
                ->findAll($limit);
        };

        // A pair answers to either of its people, by either of their names or
        // numbers — and to its own number, which is what the Pairs List shows.
        $pairs = array_values(array_filter(
            $this->pairs->overview($organ),
            static function (array $row) use ($query): bool {
                foreach (['id', 'r_mrn', 'r_name', 'd_mrn', 'd_name'] as $field) {
                    if (stripos((string) $row[$field], $query) !== false) {
                        return true;
                    }
                }

                return false;
            }
        ));

        return [
            'recipients' => array_map(fn (array $r): array => $this->recipientToUi($r), $people($this->recipients)),
            'donors'     => array_map(fn (array $r): array => $this->donorToUi($r), $people($this->donors)),
            'pairs'      => array_slice(array_map(fn (array $r): array => $this->pairToUi($r), $pairs), 0, $limit),
            'users'      => array_values(array_filter(
                $this->mrpRegister(),
                static fn (array $u): bool => stripos($u['name'], $query) !== false
                    || stripos($u['code'], $query) !== false
            )),
        ];
    }

    public function addMrp(string $code, string $name, string $kind = MrpModel::DOCTOR): string
    {
        $code = trim($code);
        $name = trim($name);
        $kind = isset(MrpModel::KINDS[$kind]) ? $kind : MrpModel::DOCTOR;

        if ($code === '' || $name === '') {
            return 'A user needs both an ID and a name.';
        }

        if ($this->mrp->byCode($code) !== null) {
            return 'That ID is already registered.';
        }

        $this->mrp->insert(['code' => $code, 'name' => $name, 'kind' => $kind]);

        if ($kind === MrpModel::COORDINATOR) {
            $this->coordinatorId($name);
        }

        return '';
    }

    /**
     * Everybody the MRP screen has registered, for its own list.
     *
     * @return list<array<string, mixed>>
     */
    public function mrpRegister(): array
    {
        return array_map(static fn (array $row): array => [
            'id'     => (string) $row['id'],
            'code'   => (string) $row['code'],
            'name'   => (string) $row['name'],
            'kind'   => (string) $row['kind'],
            'active' => (int) $row['is_active'] === 1,
        ], $this->mrp->register());
    }

    /** Changes a registered user's ID, name or kind. '' on success. */
    public function updateMrp(string $id, string $code, string $name, string $kind): string
    {
        $row = $this->mrp->find((int) $id);

        if ($row === null) {
            return 'That user could not be found.';
        }

        $code = trim($code);
        $name = trim($name);
        $kind = isset(MrpModel::KINDS[$kind]) ? $kind : (string) $row['kind'];

        if ($code === '' || $name === '') {
            return 'A user needs both an ID and a name.';
        }

        $clash = $this->mrp->byCode($code);

        if ($clash !== null && (int) $clash['id'] !== (int) $row['id']) {
            return 'That ID is already registered.';
        }

        $this->mrp->update((int) $row['id'], ['code' => $code, 'name' => $name, 'kind' => $kind]);

        if ($kind === MrpModel::COORDINATOR) {
            $this->coordinatorId($name);
        }

        return '';
    }

    /**
     * Takes a registered user out of service, or puts them back.
     *
     * Never a delete: the records they are on still name them, and a physician
     * who has left is part of what those records say.
     */
    public function setMrpActive(string $id, bool $active): string
    {
        $row = $this->mrp->find((int) $id);

        if ($row === null) {
            return 'That user could not be found.';
        }

        $this->mrp->update((int) $row['id'], ['is_active' => $active ? 1 : 0]);

        // A coordinator is two rows — the directory's and the one the records
        // point at — so deactivating has to reach both, or they would go on
        // being offered on every record screen.
        if ($row['kind'] === MrpModel::COORDINATOR) {
            $this->coordinators
                ->where('name', $row['name'])
                ->set(['is_active' => $active ? 1 : 0])
                ->update();
        }

        return '';
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

            // Setting the status to Paired Exchange is offering the pair for
            // one: the two said the same thing, and making somebody say it
            // twice only let them disagree.
            if ($status === PairModel::EXCHANGE) {
                $row['for_exchange'] = 1;
            }
        }

        if (array_key_exists('scheduledDate', $changes)) {
            $row['crossmatch_date'] = $this->toDate($changes['scheduledDate']);
        }

        // The day the transplant happened, kept only while the pair says one
        // did. Moving a pair off Transplanted clears it, as Closed clears its
        // reason: a date for something that has been taken back is worse than
        // no date at all.
        if (array_key_exists('transplantDate', $changes)) {
            $date                 = $this->toDate($changes['transplantDate']);
            $row['surgery_date'] = $status === 'transplanted' ? $date : null;
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
     * Gives a pair's two people the word the pair has just been given.
     *
     * Four of the six are a person's as much as a pair's — On Hold, Active,
     * Declined, Transplanted — and where the fact is the same fact, saying it
     * on the pair says it. A pair that is Transplanted is a recipient who has
     * had their transplant and a donor who gave it; leaving the two of them
     * reading "Active" on the registers afterwards was the register being
     * wrong rather than being careful.
     *
     * The other two do nothing here, and that is the point: a recipient whose
     * pair has been closed is not himself "closed", he is whatever he was —
     * and the waiting list, defined against the pair rather than against this
     * column, already shows him again. Nor is a person "in a paired exchange":
     * their case is.
     *
     * One rule survives the carrying: only one of a case's donors may be
     * Active, so a pair set Active while another of its donors holds that word
     * sets the recipient and leaves the donor alone.
     *
     * @return list<string> Who it was set on, for the screen to say.
     */
    public function applyPairStatus(string $pairId, string $status): array
    {
        $status = $this->statusKey($status);

        if (! isset(self::PERSON_STATUS_OPTIONS[$status])) {
            return [];
        }

        $link = $this->pairs->find((int) $pairId);

        if ($link === null) {
            return [];
        }

        $given     = [];
        $recipient = $this->recipients->find((int) $link['recipient_mrn']);

        if ($recipient !== null) {
            $this->recipients->update((int) $link['recipient_mrn'], ['status' => $status]);
            $given[] = (string) $recipient['name'];
        }

        $donor      = $this->donors->find((int) $link['donor_mrn']);
        $takenByAnother = $status === 'active'
            && $this->hasActiveDonor((string) $link['recipient_mrn'], (string) $link['id']);

        if ($donor !== null && ! $takenByAnother) {
            $this->donors->update((int) $link['donor_mrn'], ['status' => $status]);
            $given[] = (string) $donor['name'];
        }

        return $given;
    }

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
                // The same answers a saved record's cards carry, because they
                // are the same cards: the card renders the answers it is
                // given, and a blank form that handed it none showed every
                // test with nothing to press.
                'answers'    => self::answerSet(
                    (string) ($lab['result_type'] ?? 'text'),
                    $lab['answer_set'] ?? null
                ),
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
            // Every donor their pair has had, the archived ones too: a donor
            // the pair worked up and did not go ahead with is part of what
            // happened, and the screens keep them rather than forgetting them.
            'donors'         => $this->pairDonors($row['mrn']),
        ];
    }

    /**
     * A pair's donors, one entry per donor ever linked to this recipient.
     *
     * A pair is a recipient and the donors being worked up for them: the one
     * it is going ahead with, the ones kept in reserve, and the ones it has
     * finished with. `pairs` holds one row for each, and this is that list in
     * the order it was made — which is what numbers the tabs. A donor is
     * donor-1 because they were the first, and stays donor-1 whatever becomes
     * of the rest.
     *
     * Two facts ride on each entry and they are easy to confuse:
     *
     *   **status** is the donor's own — Active, On Hold, Declined. It is set
     *   on their record and on their tab, and it is theirs wherever they are
     *   read.
     *
     *   **archived** is the pair's doing: this link is finished with, so the
     *   tab is kept for the history and nothing on it can be changed. It is a
     *   mode and not a status, and it leaves the donor's own word alone.
     *
     * @return list<array<string, mixed>>
     */
    public function pairDonors(int|string $recipientMrn): array
    {
        $tabs = [];

        foreach ($this->pairs->pairsForRecipient($recipientMrn) as $i => $row) {
            $archived = $row['status'] === PairModel::CLOSED;
            $status   = (string) ($row['donor_status'] ?? 'on_hold');

            $tabs[] = [
                'number'    => $i + 1,
                // The link, not the donor: two pairs may hold the same person
                // over time, and each link is its own tab.
                'id'        => (string) $row['id'],
                'donorId'   => (string) $row['donor_mrn'],
                'name'      => (string) ($row['donor_name'] ?? ''),
                'bloodType' => (string) ($row['donor_blood_group'] ?? ''),
                'status'    => $status,
                'archived'  => $archived,
                // The one the pair is going ahead with. At most one at a time,
                // which is the rule everything else here is built around.
                'isActive'  => ! $archived && $status === 'active',
                'linkedOn'  => substr((string) $row['created_at'], 0, 10),
                'endedOn'   => $archived ? substr((string) $row['updated_at'], 0, 10) : '',
                'reason'    => (string) ($row['closed_reason'] ?? ''),
            ];
        }

        return $tabs;
    }

    /**
     * The recipients this donor has been linked with and is not any more.
     *
     * Read on the donor's own record, where it is the one thing their screen
     * cannot otherwise say: their workup on that pair, and everything the pair
     * recorded, is kept on the recipient's record, so the donor gets the
     * sentence and the way across to it. Only closed links are here — an open
     * one is already said, in "Linked: …" at the top of the screen.
     *
     * @return list<array<string, mixed>>
     */
    public function donorPastRecipients(int|string $donorMrn): array
    {
        $past = [];

        foreach ($this->pairs->pairsForDonor($donorMrn) as $row) {
            if ($row['status'] !== PairModel::CLOSED) {
                continue;
            }

            $past[] = [
                'id'        => (string) $row['recipient_mrn'],
                'name'      => (string) ($row['recipient_name'] ?? ''),
                'bloodType' => (string) ($row['recipient_blood_group'] ?? ''),
                'linkedOn'  => substr((string) $row['created_at'], 0, 10),
                'endedOn'   => substr((string) $row['updated_at'], 0, 10),
                'reason'    => (string) ($row['closed_reason'] ?? ''),
            ];
        }

        return $past;
    }

    /** One of a pair's donors, by the id of the link. */
    public function pairDonor(int|string $recipientMrn, int|string $id): ?array
    {
        foreach ($this->pairDonors($recipientMrn) as $tab) {
            if ((string) $tab['id'] === (string) $id) {
                return $tab;
            }
        }

        return null;
    }

    /**
     * Whether this pair already has the donor it is going ahead with.
     *
     * One Active donor at a time: a pair that said it was going ahead with two
     * people would be saying nothing. Everything that could make a second one
     * — adding a donor, changing a status — asks this first, and the way past
     * it is to stand the current one down, or to swap.
     */
    public function hasActiveDonor(int|string $recipientMrn, int|string $except = 0): bool
    {
        foreach ($this->pairDonors($recipientMrn) as $tab) {
            if ($tab['isActive'] && (string) $tab['id'] !== (string) $except) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a donor to a pair, with the word the pair starts them on.
     *
     * On Hold or Declined, ordinarily: Active is the donor the pair is going
     * ahead with and there is one of those. A pair that has nobody active —
     * the first donor, or one whose donor has stood down — may start somebody
     * there directly, because there is nothing to clash with.
     */
    public function addPairDonor(string $recipientMrn, string $donorMrn, string $status): string
    {
        if (! $this->isMrn($recipientMrn) || ! $this->isMrn($donorMrn)) {
            return 'That record could not be found.';
        }

        if ($this->findRecipient($recipientMrn) === null || $this->findDonor($donorMrn) === null) {
            return 'That record could not be found.';
        }

        if (! isset(self::PERSON_STATUS_OPTIONS[$status])) {
            return 'That is not a status a donor can be added on.';
        }

        if ($status === 'active' && $this->hasActiveDonor($recipientMrn)) {
            return 'This pair already has an active donor. Stand them down first.';
        }

        if ($this->pairs->openPairForDonor($donorMrn) !== null) {
            return 'That donor is already in an open pair.';
        }

        if ($this->pairs->openPairFor($recipientMrn, $donorMrn) !== null) {
            return 'That donor is already on this pair.';
        }

        $this->pairs->link((int) $recipientMrn, (int) $donorMrn, [
            'status'       => $status,
            'relationship' => $this->donors->find((int) $donorMrn)['relationship'] ?? null,
        ]);
        $this->donors->update((int) $donorMrn, ['status' => $status, 'is_listed' => 1]);

        return '';
    }

    /**
     * Moves one of a pair's donors between the three words.
     *
     * The ordinary way to change which donor a pair is going ahead with: stand
     * the current one down, then set the other active. Nothing is archived by
     * it — both are still the pair's donors, and either can be taken back up.
     */
    public function setPairDonorStatus(string $recipientMrn, string $id, string $status): string
    {
        $tab = $this->pairDonor($recipientMrn, $id);

        if ($tab === null) {
            return 'That donor is not on this pair.';
        }

        if ($tab['archived']) {
            return 'That donor has been archived, so the tab cannot be changed.';
        }

        if (! isset(self::PERSON_STATUS_OPTIONS[$status])) {
            return 'That is not a status a donor can be moved to.';
        }

        if ($status === 'active' && $this->hasActiveDonor($recipientMrn, $id)) {
            return 'This pair already has an active donor. Stand them down first.';
        }

        $this->donors->update((int) $tab['donorId'], ['status' => $status]);
        $this->mirrorLinkStatus((int) $tab['id'], $status);

        return '';
    }

    /**
     * Archives one of a pair's donors: this link is finished with.
     *
     * The tab stays, read-only, because a donor the pair worked up and did not
     * go ahead with is part of what happened. Their own record is untouched —
     * the word they were given stands, and they are free to be linked again
     * from their own screen, because the link that held them is closed.
     */
    public function delinkPairDonor(string $recipientMrn, string $id, string $reason = ''): string
    {
        $tab = $this->pairDonor($recipientMrn, $id);

        if ($tab === null) {
            return 'That donor is not on this pair.';
        }

        if ($tab['archived']) {
            return 'That donor has already been archived.';
        }

        $this->pairs->close((int) $tab['id'], trim($reason) === '' ? 'Delinked from the pair.' : trim($reason));

        return '';
    }

    /**
     * Takes the whole pair apart.
     *
     * Every link closes at once, so the recipient goes back to the waiting
     * list and each donor back to the register. Nothing is deleted: the pair's
     * own screen is still there, every tab on it archived, which is where
     * anybody asking what happened goes.
     */
    public function dissolvePair(string $recipientMrn, string $reason = ''): string
    {
        $tabs = array_filter($this->pairDonors($recipientMrn), static fn (array $t): bool => ! $t['archived']);

        if ($tabs === []) {
            return 'This pair has already been taken apart.';
        }

        foreach ($tabs as $tab) {
            $this->pairs->close((int) $tab['id'], trim($reason) === '' ? 'The pair was dissolved.' : trim($reason));
        }

        return '';
    }

    /**
     * Keeps a link's own word in step with its donor's, where it can.
     *
     * A link carries the pair's Match Status, which says more than the three
     * words a person can hold — Transplanted, Paired Exchange. Those are the
     * pair's business and a donor's status has no say in them, so this only
     * writes where the link is already holding one of the three.
     */
    private function mirrorLinkStatus(int $linkId, string $status): void
    {
        $row = $this->pairs->find($linkId);

        if ($row !== null && isset(self::PERSON_STATUS_OPTIONS[$row['status']])) {
            $this->pairs->update($linkId, ['status' => $status]);
        }
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
            // The day it happened. Only a transplanted pair has one, which is
            // why it is asked for beside the word rather than always.
            'transplantDate' => ($row['surgery_date'] ?? null) === null ? '' : self::isoToDMY($row['surgery_date']),
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
                // What this test answers, with the colour each answer carries
                // on it. The check list's tests answer what their type says;
                // one somebody added answers what they chose.
                'answers'    => self::answerSet(
                    (string) ($row['result_type'] ?? 'text'),
                    $row['answer_set'] ?? null
                ),
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

            // Their own test, so its name is theirs to type, and so is what
            // it answers. The catalogue's names and answers are the check
            // list's and are never posted back.
            if ($lab['person_mrn'] !== null) {
                $own = [];

                $typed = trim((string) ($test['name'] ?? ''));

                if ($typed !== '' && $typed !== $lab['name']) {
                    $own['name'] = mb_substr($typed, 0, 150);
                }

                // What this test answers, and in what colours. Only sent by a
                // card that was open for editing, so an absent key leaves the
                // set as it was rather than clearing it.
                if (isset($test['answers']) && is_array($test['answers'])) {
                    $own['answer_set'] = self::answerSetToJson($this->withNewAnswer($test));
                }

                if ($own !== []) {
                    $this->labs->update($labId, $own);
                    $lab = $this->labs->find($labId) ?? $lab;
                }
            }

            // The answer has to be one this test actually offers — its own, if
            // it has one. The form renders only those, so anything else was
            // not pressed on a screen.
            $offered = array_column(
                self::answerSet((string) $lab['result_type'], $lab['answer_set'] ?? null),
                'key'
            );
            $status  = (string) ($test['status'] ?? 'not_done');
            $status  = in_array($status, $offered, true) ? $status : 'not_done';

            // Nothing recorded and nothing said: no row to write — for a test
            // nobody has answered yet. A test that *has* an answer is a
            // different case: pressing Not done on it is taking the answer
            // back, which is a correction, and a record that cannot be
            // corrected is worse than one with an empty row in it.
            if ($status === 'not_done' && $notes === ''
                && ! $this->labResults->has($mrn, $personType, $labId)) {
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

    /**
     * "On Hold" as the column spells it, and '' for anything that is not one
     * of the three a person can hold.
     *
     * The screens collect a donor's status as the words on the control, and
     * the column is an ENUM — so somewhere the one has to become the other,
     * and a value that is neither has to come back empty rather than take the
     * request down.
     */
    public static function personStatusFromUi(string $status): string
    {
        $key = str_replace(' ', '_', strtolower(trim($status)));

        return isset(self::PERSON_STATUS_OPTIONS[$key]) ? $key : '';
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

    /**
     * One of the five, or '' for no colour at all.
     *
     * Everything that reads a colour off a stored answer goes through here, so
     * a shade written before the palette was cut to five reads as the colour
     * it is nearest to rather than as nothing.
     */
    public static function labTone(string $tone): string
    {
        $tone = self::LAB_TONE_ALIAS[$tone] ?? $tone;

        return isset(self::LAB_TONES[$tone]) ? $tone : '';
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
            return self::realDate("{$m[3]}-{$m[2]}-{$m[1]}");
        }

        return preg_match('~^\d{4}-\d{2}-\d{2}$~', $value) === 1 ? self::realDate($value) : '';
    }

    /**
     * An ISO date, or '' when the calendar has no such day.
     *
     * Date-shaped is not the same as a date: a browser whose date field was
     * filled out of order sends 0101-90-19, which is four digits, two and two
     * and means nothing. Everything here asks "is this a date" through
     * `dmyToIso`, so this is the one place that has to know, and the answer
     * has to be '' rather than an exception — the forms report a bad date,
     * they do not fall over on one.
     */
    private static function realDate(string $iso): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', $iso));

        return checkdate($m, $d, $y) ? $iso : '';
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
     * The answers one test offers, each with its label and its colour.
     *
     * A test from the check list answers what its result type says, in the
     * colours those words carry everywhere. A test somebody added answers what
     * they chose — some of ours, some of their own — in the colours they
     * picked, which is what `answer_set` holds.
     *
     * @return list<array{key: string, label: string, tone: string, own: bool}>
     */
    public static function answerSet(string $resultType, ?string $stored): array
    {
        $chosen = $stored === null || trim($stored) === '' ? null : json_decode($stored, true);

        if (! is_array($chosen)) {
            // Nothing chosen: the type's own list, which is every test the
            // check list seeded — and a new custom test until it is saved.
            $own  = $resultType === 'custom';
            $keys = $own
                ? self::CUSTOM_ANSWER_DEFAULT
                : (self::RESULT_OPTIONS[$resultType] ?? self::RESULT_OPTIONS['text']);

            return array_map(static fn (string $key): array => [
                'key'   => $key,
                'label' => self::RESULT_LABEL[$key] ?? $key,
                // The check list's own tests keep the colours the sheet gives
                // them — Positive is red wherever it is read, and that is not
                // anybody's to decide. A test the record added starts with no
                // colour at all: a colour on it is something the record
                // decided to say, and one nobody chose would be saying it by
                // accident.
                // A test the record added arrives with the colours the
                // platform would have given these answers anyway — there is
                // nothing to be gained by making somebody paint Positive red
                // — and the picker is there to change them.
                'tone'  => self::labTone(self::RESULT_TONE[$key] ?? ''),
                'own'   => false,
            ], $keys);
        }

        $answers = [];

        foreach ($chosen as $entry) {
            if (! is_array($entry) || ($entry['key'] ?? '') === '') {
                continue;
            }

            $key = (string) $entry['key'];
            $own = str_starts_with($key, self::CUSTOM_ANSWER_PREFIX);

            $answers[] = [
                'key'   => $key,
                // One of ours keeps its name wherever it is read; one of
                // theirs is whatever they called it.
                'label' => $own
                    ? mb_substr((string) ($entry['label'] ?? $key), 0, self::CUSTOM_ANSWER_MAX)
                    : (self::RESULT_LABEL[$key] ?? $key),
                // '' for an answer nobody gave a colour to, which the card
                // shows plain.
                'tone'  => self::labTone((string) ($entry['tone'] ?? '')),
                'own'   => $own,
            ];
        }

        return $answers;
    }

    /**
     * An answer set as the column keeps it, from what a card posted.
     *
     * Only what was ticked, in the order the card lists it, and only colours
     * from the platform's own palette. An answer somebody wrote keeps its
     * typed name; one of ours is only a key, because its name is ours.
     *
     * @param array<string, mixed> $posted
     */
    public static function answerSetToJson(array $posted): ?string
    {
        $answers = [];

        foreach ($posted as $key => $entry) {
            $key = (string) $key;

            if (! is_array($entry) || ($entry['on'] ?? '') !== '1' || $key === '') {
                continue;
            }

            $own   = str_starts_with($key, self::CUSTOM_ANSWER_PREFIX);
            $label = trim((string) ($entry['label'] ?? ''));

            // An answer of their own with its name rubbed out is an answer
            // with nothing to show, so it is one they removed.
            if ($own && $label === '') {
                continue;
            }

            if (! $own && ! isset(self::RESULT_LABEL[$key])) {
                continue;
            }

            $answers[] = [
                'key'   => $key,
                'label' => $own ? mb_substr($label, 0, self::CUSTOM_ANSWER_MAX) : (self::RESULT_LABEL[$key] ?? $key),
                // Only a colour somebody picked is stored, as one of the
                // five. Nothing picked is stored as nothing, and the card
                // shows the answer plain.
                'tone'  => self::labTone((string) ($entry['tone'] ?? '')),
            ];
        }

        // A test that answers nothing cannot be answered, so an empty set
        // falls back to the three every test has in common.
        if ($answers === []) {
            return null;
        }

        return json_encode($answers, JSON_UNESCAPED_UNICODE);
    }

    /**
     * The posted answers plus the one the card's "add" box was holding.
     *
     * The box is one field, always on the card, so adding an answer is typing
     * a name and saving — no step of its own, and nothing to press before the
     * thing you are already saving.
     *
     * @param array<string, mixed> $test
     *
     * @return array<string, mixed>
     */
    private function withNewAnswer(array $test): array
    {
        $answers = is_array($test['answers'] ?? null) ? $test['answers'] : [];

        // The box at the foot, and any row the + button added beside it. Both
        // arrive without a key, because a key is this method's to mint.
        $fresh = is_array($test['newAnswers'] ?? null) ? $test['newAnswers'] : [];
        $fresh[] = ['label' => $test['newAnswer'] ?? '', 'tone' => $test['newAnswerTone'] ?? ''];

        foreach ($fresh as $one) {
            $label = trim((string) (is_array($one) ? ($one['label'] ?? '') : $one));

            if ($label === '') {
                continue;
            }

            $key = self::customAnswerKey($label, array_map('strval', array_keys($answers)));

            $answers[$key] = [
                'on'    => '1',
                'label' => $label,
                'tone'  => is_array($one) ? (string) ($one['tone'] ?? '') : '',
            ];
        }

        return $answers;
    }

    /**
     * A key for an answer somebody typed, from the name they typed.
     *
     * Derived from the name so the same answer written twice is the same
     * answer, and suffixed when two different names would reduce to one.
     *
     * @param list<string> $taken
     */
    public static function customAnswerKey(string $label, array $taken = []): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($label)));
        $slug = trim((string) $slug, '_');

        if ($slug === '') {
            $slug = 'answer';
        }

        $key = self::CUSTOM_ANSWER_PREFIX . mb_substr($slug, 0, 30);
        $try = $key;
        $n   = 2;

        while (in_array($try, $taken, true)) {
            $try = $key . '_' . $n++;
        }

        return $try;
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
