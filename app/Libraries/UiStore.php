<?php

namespace App\Libraries;

use App\Models\CoordinatorModel;
use App\Models\DonorModel;
use App\Models\LabModel;
use App\Models\LabResultModel;
use App\Models\MrpModel;
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
 *   pairedDonorId derived from `pairs`, not stored on the person
 *   labTests      one row per test in `lab_results`, joined to the catalogue
 */
final class UiStore
{
    public const ORGANS = ['kidney', 'liver'];

    public const BLOOD_TYPES = ['A', 'B', 'O', 'AB'];

    public const PAIR_STATUSES = ['active', 'scheduled', 'completed', 'on-hold'];


    public const URGENCY_TONE = [
        'critical' => 'tone-red',
        'high'     => 'tone-orange',
        'medium'   => 'tone-amber',
        'low'      => 'tone-emerald',
    ];

    public const PAIR_STATUS_TONE = [
        'active'    => 'tone-blue',
        'scheduled' => 'tone-teal',
        'completed' => 'tone-emerald',
        'on-hold'   => 'tone-slate',
    ];

    public const LAB_STATUS_TONE = [
        'completed' => 'tone-emerald',
        'pending'   => 'tone-amber-soft',
        'flagged'   => 'tone-red',
    ];

    public const LAB_STATUS_LABEL = [
        'completed' => 'Done',
        'pending'   => 'Pending',
        'flagged'   => 'Flagged',
    ];

    public const LAB_STATUSES = ['pending', 'completed', 'flagged'];

    public const GENDER_OPTIONS = ['Male' => 'Male', 'Female' => 'Female'];

    public const URGENCY_OPTIONS = [
        'critical' => 'Critical',
        'high'     => 'High',
        'medium'   => 'Medium',
        'low'      => 'Low',
    ];

    public const URGENT_OPTIONS = [
        'yes' => 'Yes, it is urgent.',
        'no'  => 'No, it is not urgent.',
    ];

    public const DONOR_STATUS_OPTIONS = [
        'On Hold'   => 'On Hold',
        'Active'    => 'Active',
        'Completed' => 'Completed',
        'Cancelled' => 'Cancelled',
    ];

    /** The "Match Status" dropdown on the pair profile, in the source's order. */
    public const PAIR_STATUS_OPTIONS = [
        'active'    => 'Active',
        'scheduled' => 'Scheduled',
        'on-hold'   => 'On Hold',
        'completed' => 'Completed',
    ];

    /**
     * The columns an empty field may clear.
     *
     * Everything on `recipients` and `donors` that the migrations declare
     * nullable. The rest — name, blood group, the organ, the entry date — is
     * NOT NULL, so an empty box there is a slip and the stored value stands.
     */
    private const NULLABLE_COLUMNS = [
        'gender', 'age', 'city', 'phone', 'hospital', 'diagnosis',
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
    public function availableDonors(): array
    {
        return array_map(
            fn (array $row): array => $this->donorToUi($row),
            $this->donors->register($this->organ(), true)
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
        $this->recipients->insert($this->recipientToRow($recipient) + ['mrn' => (int) $recipient['id']]);
        $this->saveLabTests((int) $recipient['id'], 'recipient', $recipient['labTests'] ?? []);
    }

    /** @param array<string, mixed> $donor */
    public function addDonor(array $donor): void
    {
        $this->donors->insert($this->donorToRow($donor) + ['mrn' => (int) $donor['id']]);
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
        return (int) $this->pairs->link((int) $pair['recipientId'], (int) $pair['donorId'], [
            'status'          => $this->pairStatusToRow(($pair['status'] ?? '') ?: 'active'),
            'relationship'    => $pair['relationship'] ?? null,
            'crossmatch_date' => $this->toDate($pair['scheduledDate'] ?? null),
            'notes'           => $pair['notes'] ?? null,
        ]);
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

        $this->recipients->update((int) $id, $this->recipientToRow($changes));

        if (isset($changes['labTests'])) {
            $this->saveLabTests((int) $id, 'recipient', $changes['labTests']);
        }
    }

    /** @param array<string, mixed> $changes */
    public function updateDonor(string $id, array $changes): void
    {
        if (! $this->isMrn($id)) {
            return;
        }

        $this->donors->update((int) $id, $this->donorToRow($changes));

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

        $row = [];

        if (($changes['status'] ?? '') !== '') {
            $row['status'] = $this->pairStatusToRow($changes['status']);
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

        if ($row !== []) {
            $this->pairs->update((int) $id, $row);
        }
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
                'id'     => (string) $lab['id'],
                'name'   => $lab['name'],
                'status' => 'pending',
                'result' => '',
                'date'   => '',
                'notes'  => '',
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
            'age'            => (int) $row['age'],
            'bloodType'      => $row['blood_group'],
            'gender'         => $this->genderToUi($row['gender']),
            'phone'          => (string) $row['phone'],
            'address'        => (string) $row['city'],
            'hospital'       => (string) $row['hospital'],
            'diagnosis'      => (string) $row['diagnosis'],
            'urgency'        => $row['urgency'],
            'dateRegistered' => (string) $row['entry_date'],
            'firstDialysis'  => $row['dialysis_start'] === null ? '' : self::isoToDMY($row['dialysis_start']),
            'selectedMrp'    => (string) ($row['mrp_id'] ?? ''),
            'notes'          => (string) $row['notes'],
            'labTests'       => $this->labTestsFor($row['mrn'], 'recipient', $row['organ_code']),
            'pairedDonorId'  => $pair === null ? '' : (string) $pair['donor_mrn'],
        ];
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
            'bloodType'         => $row['blood_group'],
            'donorGender'       => $this->genderToUi($row['gender']),
            'phone'             => (string) $row['phone'],
            'address'           => (string) $row['city'],
            'hospital'          => (string) $row['hospital'],
            'donationType'      => $row['donation_type'],
            'relationship'      => (string) $row['relationship'],
            'donorStatus'       => $this->statusToUi($row['status']),
            'donorMrp'          => (string) ($row['mrp_id'] ?? ''),
            'donorCoordinator'  => (string) ($this->coordinatorName($row['coordinator_id'] ?? null)),
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
            'status'        => str_replace('_', '-', $row['status']),
            'recipientId'   => (string) $row['recipient_mrn'],
            'donorId'       => (string) $row['donor_mrn'],
            'relationship'  => (string) $row['relationship'],
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
            'phone' => 'phone', 'address' => 'city', 'hospital' => 'hospital',
            'diagnosis' => 'diagnosis', 'urgency' => 'urgency', 'notes' => 'notes',
            'organ' => 'organ_code', 'selectedMrp' => 'mrp_id',
            'dateRegistered' => 'entry_date', 'firstDialysis' => 'dialysis_start',
        ];

        $row = $this->mapFields($ui, $map);

        if (($ui['gender'] ?? '') !== '') {
            $row['gender'] = $this->genderToRow($ui['gender']);
        }

        foreach (['entry_date', 'dialysis_start'] as $dateColumn) {
            if (array_key_exists($dateColumn, $row)) {
                $row[$dateColumn] = $this->toDate($row[$dateColumn]);
            }
        }

        $row['entry_date'] ??= date('Y-m-d');

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
            'phone' => 'phone', 'address' => 'city', 'hospital' => 'hospital',
            'notes' => 'notes', 'organ' => 'organ_code', 'donorMrp' => 'mrp_id',
            'donationType' => 'donation_type', 'relationship' => 'relationship',
        ];

        $row = $this->mapFields($ui, $map);

        if (($ui['donorGender'] ?? '') !== '') {
            $row['gender'] = $this->genderToRow($ui['donorGender']);
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

        $row['registered_on'] ??= date('Y-m-d');

        return $row;
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
     * A person's workup: every test their programme calls for, with whatever
     * has been recorded against it — so an untouched test is still a card.
     *
     * @return list<array<string, mixed>>
     */
    private function labTestsFor(int $mrn, string $personType, string $organCode): array
    {
        return array_map(
            static fn (array $row): array => [
                'id'     => (string) $row['lab_id'],
                'name'   => $row['lab_name'],
                'status' => $row['status'],
                'result' => (string) $row['value'],
                'date'   => $row['taken_on'] === null ? '' : self::isoToDMY($row['taken_on']),
                'notes'  => (string) $row['notes'],
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

            $status = $test['status'] ?? 'pending';
            $value  = (string) ($test['result'] ?? '');
            $date   = (string) ($test['date'] ?? '');
            $notes  = (string) ($test['notes'] ?? '');

            if ($status === 'pending' && $value === '' && $date === '' && $notes === '') {
                continue;
            }

            $this->labResults->record($mrn, $personType, $labId, [
                'status'   => $status,
                'value'    => $value === '' ? null : $value,
                'taken_on' => $this->toDate($date),
                'notes'    => $notes === '' ? null : $notes,
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

    private function pairStatusToRow(string $status): string
    {
        return str_replace('-', '_', $status);
    }

    /** The "Urgent?" dropdown is derived from urgency, as in the source. */
    public static function isUrgent(string $urgency): string
    {
        return in_array($urgency, ['critical', 'high'], true) ? 'yes' : 'no';
    }

    /** "2026-01-15" -> "15/01/2026" */
    public static function isoToDMY(string $iso): string
    {
        return implode('/', array_reverse(explode('-', $iso)));
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
        $total = count($labTests);
        $done  = count(array_filter($labTests, static fn (array $t): bool => $t['status'] === 'completed'));

        return ['done' => $done, 'total' => $total, 'pct' => (int) round($done / max($total, 1) * 100)];
    }
}
