<?php

namespace App\Libraries;

use CodeIgniter\Session\Session;

/**
 * Per-session working copy of the transplant registry used by the HTML UI.
 *
 * The HTML prototype kept recipients, donors, pairs and MRPs in a JavaScript
 * object that every page re-rendered from. Now that the screens are plain HTML
 * rendered by PHP, the same data lives here: seeded from {@see UiSeed} the
 * first time a session touches it, then mutated by the form posts. It is
 * session scoped, so — exactly like the prototype — one user's edits never
 * reach another's, and signing out clears them.
 *
 * Replace the read methods with model calls to put the screens on the real
 * `patients` / `pairs` tables; the views need no changes.
 */
final class UiStore
{
    public const SESSION_KEY = 'ui_store';

    public const ORGANS = ['kidney', 'liver'];

    public const BLOOD_TYPES = ['A', 'B', 'O', 'AB'];

    public const PAIR_STATUSES = ['active', 'scheduled', 'completed', 'on-hold'];

    /** Sort weight — lower sorts first, as in the prototype's URGENCY_ORDER. */
    public const URGENCY_ORDER = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

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

    private Session $session;

    /** @var array{recipients: list<array<string, mixed>>, donors: list<array<string, mixed>>, pairs: list<array<string, mixed>>, mrps: list<array<string, string>>} */
    private array $data;

    public function __construct(?Session $session = null)
    {
        $this->session = $session ?? service('session');

        /** @var array<string, mixed>|null $stored */
        $stored = $this->session->get(self::SESSION_KEY);

        $this->data = is_array($stored) ? $stored : [
            'recipients' => UiSeed::recipients(),
            'donors'     => UiSeed::donors(),
            'pairs'      => UiSeed::pairs(),
            'mrps'       => UiSeed::mrps(),
        ];
    }

    private function persist(): void
    {
        $this->session->set(self::SESSION_KEY, $this->data);
    }

    /** Drops the working copy; the next request re-seeds it. */
    public function reset(): void
    {
        $this->session->remove([self::SESSION_KEY, 'ui_organ', 'ui_staff_id']);
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
        return $this->byOrgan($this->data['recipients'], $organ);
    }

    /** @return list<array<string, mixed>> */
    public function donors(?string $organ = null): array
    {
        return $this->byOrgan($this->data['donors'], $organ);
    }

    /** @return list<array<string, mixed>> */
    public function pairs(?string $organ = null): array
    {
        return $this->byOrgan($this->data['pairs'], $organ);
    }

    /** @return list<array<string, string>> */
    public function mrps(): array
    {
        return $this->data['mrps'];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function byOrgan(array $rows, ?string $organ): array
    {
        $organ ??= $this->organ();

        return array_values(array_filter($rows, static fn (array $row): bool => $row['organ'] === $organ));
    }

    /** Looked up across every organ, as the prototype's `store.recipients.find` was. */
    public function findRecipient(?string $id): ?array
    {
        return $this->find($this->data['recipients'], $id);
    }

    public function findDonor(?string $id): ?array
    {
        return $this->find($this->data['donors'], $id);
    }

    public function findPair(?string $id): ?array
    {
        return $this->find($this->data['pairs'], $id);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>|null
     */
    private function find(array $rows, ?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        foreach ($rows as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    // ---- Writes ------------------------------------------------------------

    /** @param array<string, mixed> $recipient */
    public function addRecipient(array $recipient): void
    {
        $this->data['recipients'][] = $recipient;
        $this->persist();
    }

    /** @param array<string, mixed> $donor */
    public function addDonor(array $donor): void
    {
        $this->data['donors'][] = $donor;
        $this->persist();
    }

    /** @param array<string, mixed> $pair */
    public function addPair(array $pair): void
    {
        $this->data['pairs'][] = $pair;
        $this->persist();
    }

    public function addMrp(string $id, string $name): void
    {
        $this->data['mrps'][] = ['id' => $id, 'name' => $name];
        $this->persist();
    }

    /** @param array<string, mixed> $changes */
    public function updateRecipient(string $id, array $changes): void
    {
        $this->update('recipients', $id, $changes);
    }

    /** @param array<string, mixed> $changes */
    public function updateDonor(string $id, array $changes): void
    {
        $this->update('donors', $id, $changes);
    }

    /** @param array<string, mixed> $changes */
    public function updatePair(string $id, array $changes): void
    {
        $this->update('pairs', $id, $changes);
    }

    /** @param array<string, mixed> $changes */
    private function update(string $bucket, string $id, array $changes): void
    {
        foreach ($this->data[$bucket] as $i => $row) {
            if ($row['id'] === $id) {
                $this->data[$bucket][$i] = array_merge($row, $changes);
                $this->persist();

                return;
            }
        }
    }

    // ---- Derived values ----------------------------------------------------

    /**
     * Next free `R-007` style id, matching the prototype's genId().
     *
     * @param list<array<string, mixed>> $existing
     */
    public static function genId(string $prefix, array $existing): string
    {
        $numbers = [];

        foreach ($existing as $row) {
            $n = (int) preg_replace('/\D/', '', (string) $row['id']);

            if ($n > 0) {
                $numbers[] = $n;
            }
        }

        return $prefix . '-' . str_pad((string) ($numbers === [] ? 1 : max($numbers) + 1), 3, '0', STR_PAD_LEFT);
    }

    /** Next id for a bucket, counted across every organ as the prototype did. */
    public function nextRecipientId(): string
    {
        return self::genId('R', $this->data['recipients']);
    }

    public function nextDonorId(): string
    {
        return self::genId('D', $this->data['donors']);
    }

    /** Pair ids were generated from the organ-filtered list in the source. */
    public function nextPairId(): string
    {
        return self::genId('P', $this->pairs());
    }

    /**
     * The blank workup a new person starts with (defaultLabTests in the source).
     *
     * @return list<array{id: string, name: string, status: string}>
     */
    public static function defaultLabTests(string $organ, string $personType): array
    {
        if ($organ === 'kidney') {
            $names = [
                'eGFR / Creatinine',
                'Crossmatch',
                'HLA Typing',
                'Virology Panel (HIV, HBV, HCV)',
                'Renal Ultrasound',
                'Cardiac Clearance',
            ];

            if ($personType === 'donor') {
                $names[] = 'Renal CT Angiogram';
                $names[] = 'Psychiatric Evaluation';
            }
        } else {
            $names = [
                'LFTs (ALT / AST / Bilirubin)',
                'INR / Coagulation',
                'Crossmatch',
                'Virology Panel (HIV, HBV, HCV)',
                'Liver CT / MRI',
                'Cardiac Clearance',
            ];

            if ($personType === 'recipient') {
                array_unshift($names, 'MELD Score');
            } else {
                $names[] = 'Liver Volumetry (CT)';
                $names[] = 'Liver Biopsy';
                $names[] = 'Psychiatric Evaluation';
            }
        }

        return array_map(
            static fn (string $name): array => ['id' => bin2hex(random_bytes(8)), 'name' => $name, 'status' => 'pending'],
            $names
        );
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
     * Sorts a recipient list critical-first (URGENCY_ORDER in the source).
     *
     * @param list<array<string, mixed>> $recipients
     *
     * @return list<array<string, mixed>>
     */
    public static function sortByUrgency(array $recipients): array
    {
        usort(
            $recipients,
            static fn (array $a, array $b): int => (self::URGENCY_ORDER[$a['urgency']] ?? 9) <=> (self::URGENCY_ORDER[$b['urgency']] ?? 9)
        );

        return $recipients;
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
