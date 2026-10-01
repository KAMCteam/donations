<?php

namespace App\Libraries;

/**
 * A record as the blocks a printed sheet is made of.
 *
 * `ui/record_print` renders blocks and decides nothing; what a recipient,
 * a donor or a pair consists of on paper is decided here. It lives outside the
 * controllers because two of them now print the same records — one record at a
 * time from its own screen, and a filtered set of them from Reports — and the
 * one thing worse than a field missing from a medical record is the same field
 * appearing differently on two sheets.
 *
 * Every method returns one block: `fields` for a labelled grid, `labs` for a
 * workup, `text` for notes. Blocks are plain arrays, so a caller is free to
 * order them, drop one, or put its own between.
 */
class RecordBlocks
{
    public function __construct(private UiStore $store) {}

    /**
     * The workup and the notes, which every sheet carries in that order.
     *
     * @param array<string, mixed> $person
     *
     * @return list<array<string, mixed>>
     */
    public function workup(array $person, string $labsTitle, string $notesTitle): array
    {
        return [
            ['kind' => 'labs', 'title' => $labsTitle, 'tests' => $person['labTests'] ?? []],
            ['kind' => 'text', 'title' => $notesTitle, 'text' => (string) ($person['notes'] ?? '')],
        ];
    }

    /**
     * @param array<string, mixed> $recipient
     *
     * @return array<string, mixed>
     */
    public function recipient(array $recipient): array
    {
        return [
            'kind'   => 'fields',
            'title'  => 'Recipient Details',
            'fields' => [
                ['label' => 'Recipient MRN', 'value' => (string) $recipient['id'], 'mono' => true],
                ['label' => 'Recipient Name', 'value' => (string) ($recipient['name'] ?? ''), 'wide' => true, 'strong' => true],
                ['label' => 'Date of Birth', 'value' => (string) ($recipient['birthDate'] ?? ''), 'mono' => true],
                ['label' => 'Age', 'value' => isset($recipient['age']) ? (string) $recipient['age'] : ''],
                ['label' => 'Gender', 'value' => (string) ($recipient['gender'] ?? '')],
                ['label' => 'Blood Group', 'value' => (string) ($recipient['bloodType'] ?? ''), 'mono' => true, 'strong' => true],
                ['label' => 'Phone Number', 'value' => (string) ($recipient['phone'] ?? ''), 'mono' => true],
                ['label' => 'City', 'value' => (string) ($recipient['address'] ?? '')],
                ['label' => 'Recipient MRP', 'value' => $this->mrpName((string) ($recipient['selectedMrp'] ?? ''))],
                ['label' => 'Coordinator', 'value' => (string) ($recipient['coordinator'] ?? '')],
                ['label' => 'Type Dialysis', 'value' => UiStore::DIALYSIS_TYPES[$recipient['dialysisType'] ?? ''] ?? ''],
                ['label' => 'First Dialysis', 'value' => (string) ($recipient['firstDialysis'] ?? ''), 'mono' => true],
                ['label' => 'Entry Date', 'value' => UiStore::isoToDMY((string) ($recipient['dateRegistered'] ?? '')), 'mono' => true],
                ['label' => 'Urgent', 'value' => ($recipient['urgent'] ?? false) ? 'Yes' : 'No'],
                ['label' => 'Recipient Status', 'value' => UiStore::STATUS_OPTIONS[$recipient['status'] ?? ''] ?? (string) ($recipient['status'] ?? '')],
                // No "Linked Donor" here: a recipient may hold several, and
                // the Donors block names every one of them.
            ],
        ];
    }

    /**
     * A recipient's donors on paper, in the order they were linked.
     *
     * One field each, numbered as the tabs are, saying who and what became of
     * it — including the ones that were delinked.
     *
     * @param list<array<string, mixed>> $tabs
     *
     * @return array<string, mixed>
     */
    public function donors(array $tabs): array
    {
        $fields = [];

        foreach ($tabs as $tab) {
            $status = UiStore::STATUS_OPTIONS[$tab['status']] ?? $tab['status'];

            $fields[] = [
                'label' => 'Donor ' . $tab['number'],
                'value' => $tab['name'] . ' (' . $tab['donorId'] . ') — ' . $status
                    . ($tab['delinked'] ? ', delinked' : ''),
                'wide'  => true,
            ];
        }

        return ['kind' => 'fields', 'title' => 'Donors', 'fields' => $fields];
    }

    /**
     * @param array<string, mixed>      $donor
     * @param array<string, mixed>|null $recipient
     *
     * @return array<string, mixed>
     */
    public function donor(array $donor, ?array $recipient): array
    {
        return [
            'kind'   => 'fields',
            'title'  => 'Donor Details',
            'fields' => [
                ['label' => 'Donor MRN', 'value' => (string) $donor['id'], 'mono' => true],
                ['label' => 'Donor Name', 'value' => (string) ($donor['name'] ?? ''), 'wide' => true, 'strong' => true],
                ['label' => 'Date of Birth', 'value' => (string) ($donor['birthDate'] ?? ''), 'mono' => true],
                ['label' => 'Age', 'value' => isset($donor['age']) ? (string) $donor['age'] : ''],
                ['label' => 'Gender', 'value' => (string) ($donor['donorGender'] ?? '')],
                ['label' => 'Blood Group', 'value' => (string) ($donor['bloodType'] ?? ''), 'mono' => true, 'strong' => true],
                ['label' => 'Phone Number', 'value' => (string) ($donor['phone'] ?? ''), 'mono' => true],
                ['label' => 'City', 'value' => (string) ($donor['address'] ?? '')],
                ['label' => 'Donor MRP', 'value' => $this->mrpName((string) ($donor['donorMrp'] ?? ''))],
                ['label' => 'Coordinator', 'value' => (string) ($donor['donorCoordinator'] ?? '')],
                ['label' => 'Donor Type', 'value' => UiStore::DONATION_TYPES[$donor['donationType'] ?? ''] ?? ''],
                ['label' => 'Relationship', 'value' => (string) ($donor['relationship'] ?? '')],
                ['label' => 'Donor Status', 'value' => (string) ($donor['donorStatus'] ?? '')],
                ['label' => 'Linked Recipient', 'value' => $recipient === null ? 'Not linked' : $recipient['name'] . ' (' . $recipient['id'] . ')', 'wide' => true],
            ],
        ];
    }

    /**
     * @param array<string, mixed>      $pair
     * @param array<string, mixed>|null $recipient
     * @param array<string, mixed>|null $donor
     *
     * @return array<string, mixed>
     */
    public function pair(array $pair, ?array $recipient, ?array $donor): array
    {
        $fields = [
            ['label' => 'Pair #', 'value' => (string) $pair['id'], 'mono' => true],
            ['label' => 'Recipient', 'value' => (string) ($recipient['name'] ?? '—')],
            ['label' => 'Donor', 'value' => (string) ($donor['name'] ?? '—')],
            ['label' => 'Relationship', 'value' => (string) ($donor['relationship'] ?? $pair['notes'] ?? '')],
            ['label' => 'Date of Crossmatch', 'value' => (string) ($pair['scheduledDate'] ?? ''), 'mono' => true],
            ['label' => 'Status', 'value' => UiStore::STATUS_OPTIONS[$pair['status']] ?? (string) $pair['status'], 'strong' => true],
            ['label' => 'Offered for Exchange', 'value' => ($pair['forExchange'] ?? false) ? 'Yes' : 'No'],
        ];

        // Only a closed pair has a reason, and when it has one it is the whole
        // point of the block.
        if (($pair['closedReason'] ?? '') !== '') {
            $fields[] = ['label' => 'Reason for Closing', 'value' => (string) $pair['closedReason'], 'wide' => true];
        }

        return ['kind' => 'fields', 'title' => 'Pair Details', 'fields' => $fields];
    }

    /** An MRP's name from the id the record stores. */
    public function mrpName(string $id): string
    {
        foreach ($this->store->mrps() as $mrp) {
            if ($mrp['id'] === $id) {
                return $mrp['name'];
            }
        }

        return '';
    }
}
