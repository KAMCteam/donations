<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The linking system: which recipient is matched to which donor, and when
 * either of them is free again.
 *
 * One rule runs through all of it — **a pair is open unless its status is
 * `closed`** — and everything else follows from it:
 *
 *   - a recipient is on the waiting list when no open pair holds them
 *   - a donor is on the register when no open pair holds them
 *   - closing a pair releases both, and they can be linked again
 *
 * `OPEN` below is that rule written once, so no query can drift from it.
 */
class PairModel extends Model
{
    protected $table         = 'pairs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'recipient_mrn', 'donor_mrn', 'status', 'for_exchange', 'relationship',
        'crossmatch_date', 'surgery_date', 'closed_reason', 'notes',
    ];

    /** Every status but this one means both sides are spoken for. */
    public const CLOSED = 'closed';

    /**
     * The status that says this pair is in a paired exchange.
     *
     * Saying so is offering it: a pair set to this is on the Paired Exchange
     * list without anybody pressing the button, because the status already
     * said what the button says.
     */
    public const EXCHANGE = 'paired_exchange';

    /**
     * The link this recipient's pair is going ahead with, or null when they
     * have no pair at all.
     *
     * A pair holds several donors at once — one it is going ahead with, and
     * any it is keeping in reserve — so this is the one that answers "who is
     * their donor": the Active one, and failing that the first still open.
     * {@see self::pairsForRecipient()} is the whole list.
     */
    public function openPairForRecipient(int|string $mrn): ?array
    {
        return $this->db->table('pairs p')
            ->select('p.*')
            ->join('donors d', 'd.mrn = p.donor_mrn', 'left')
            ->where('p.recipient_mrn', $mrn)
            ->where('p.status !=', self::CLOSED)
            ->orderBy("d.status = 'active'", 'DESC', false)
            ->orderBy('p.id')
            ->get()
            ->getRowArray();
    }

    /**
     * Every donor this recipient has been linked with, in the order they were.
     *
     * Closed ones included: a link that was undone is still part of the
     * record, and the screens show it greyed rather than dropping it.
     *
     * @return list<array<string, mixed>>
     */
    public function pairsForRecipient(int|string $mrn): array
    {
        return $this->db->table('pairs p')
            ->select('p.*, d.name AS donor_name, d.blood_group AS donor_blood_group, d.status AS donor_status')
            ->join('donors d', 'd.mrn = p.donor_mrn', 'left')
            ->where('p.recipient_mrn', $mrn)
            ->orderBy('p.id')
            ->get()
            ->getResultArray();
    }

    /** The open pair holding this donor, or null when they are free. */
    public function openPairForDonor(int|string $mrn): ?array
    {
        return $this->openPairs()->where('donor_mrn', $mrn)->get()->getRowArray();
    }

    /** The open pair joining these two specifically, or null. */
    public function openPairFor(int|string $recipientMrn, int|string $donorMrn): ?array
    {
        return $this->openPairs()
            ->where('recipient_mrn', $recipientMrn)
            ->where('donor_mrn', $donorMrn)
            ->get()
            ->getRowArray();
    }

    /**
     * Links a recipient to a donor.
     *
     * Refuses when either side is already held by an open pair, which is the
     * rule the database cannot express — MySQL will not take a unique index
     * over a generated column that reads a foreign key with ON UPDATE CASCADE,
     * and the cascade is worth more, so the check lives here.
     *
     * @param array<string, mixed> $attributes
     *
     * Only the donor's half is exclusive. A recipient may hold several links
     * at once — donors are looked at one after another, and sometimes
     * together — but a donor promised to two recipients is a thing the
     * register should not be able to say. The same pair twice is refused
     * either way: that is a duplicate, not a second opinion.
     *
     * @return int|string          the new pair's id
     * @throws \RuntimeException   when the donor is already matched
     */
    public function link(int|string $recipientMrn, int|string $donorMrn, array $attributes = []): int|string
    {
        if ($this->openPairForDonor($donorMrn) !== null) {
            throw new \RuntimeException("Donor {$donorMrn} is already in an open pair.");
        }

        if ($this->openPairFor($recipientMrn, $donorMrn) !== null) {
            throw new \RuntimeException("Recipient {$recipientMrn} and donor {$donorMrn} are already linked.");
        }

        $this->insert(array_merge([
            'status' => 'active',
        ], $attributes, [
            'recipient_mrn' => $recipientMrn,
            'donor_mrn'     => $donorMrn,
        ]));

        return $this->getInsertID();
    }

    /**
     * Closes a pair, releasing both sides back onto their lists.
     *
     * The row stays: a closed pair is history, not a mistake, and the reason
     * is worth keeping.
     */
    public function close(int|string $pairId, ?string $reason = null): bool
    {
        return (bool) $this->update($pairId, [
            'status'        => self::CLOSED,
            'closed_reason' => $reason,
            // A closed pair holds nobody, so it has nobody left to offer.
            'for_exchange'  => 0,
        ]);
    }

    /** Puts a pair forward for a paired exchange, or takes it back. */
    public function offerForExchange(int|string $pairId, bool $offered): bool
    {
        return (bool) $this->update($pairId, ['for_exchange' => $offered ? 1 : 0]);
    }

    /**
     * Pairs with both sides joined, for the pairs screen.
     *
     * @return list<array<string, mixed>>
     */
    public function overview(?string $organCode = null, ?string $status = null, ?string $bloodGroup = null): array
    {
        $builder = $this->db->table('pairs p')
            ->select('p.*, r.organ_code')
            ->select('r.mrn AS r_mrn, r.name AS r_name, r.age AS r_age, r.gender AS r_gender')
            ->select('r.blood_group AS r_blood_group, r.phone AS r_phone')
            ->select('r.entry_date AS r_entry_date, r.dialysis_start AS r_dialysis_start')
            ->select('d.mrn AS d_mrn, d.name AS d_name, d.age AS d_age, d.gender AS d_gender')
            ->select('d.blood_group AS d_blood_group, d.phone AS d_phone, d.donation_type AS d_donation_type')
            ->join('recipients r', 'r.mrn = p.recipient_mrn')
            ->join('donors d', 'd.mrn = p.donor_mrn');

        if ($organCode !== null && $organCode !== '') {
            $builder->where('r.organ_code', $organCode);
        }

        if ($status !== null && $status !== '') {
            $builder->where('p.status', $status);
        }

        // The screen's blood-type filter matches a pair when either side has it.
        if ($bloodGroup !== null && $bloodGroup !== '') {
            $builder->groupStart()
                ->where('r.blood_group', $bloodGroup)
                ->orWhere('d.blood_group', $bloodGroup)
                ->groupEnd();
        }

        return $builder->orderBy('p.status')->orderBy('p.id')->get()->getResultArray();
    }

    /** Builder pre-filtered to open pairs. */
    /**
     * Every donor an open pair of this recipient's holds, as MRNs.
     *
     * A recipient may hold more than one link, so this is a list rather than a
     * row: the exchange has to know *all* of their own donors, because the one
     * thing it must never offer them is somebody they already have.
     *
     * @return list<int>
     */
    public function openDonorMrnsFor(int|string $recipientMrn): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['donor_mrn'],
            $this->openPairs()->select('donor_mrn')->where('recipient_mrn', $recipientMrn)->get()->getResultArray()
        );
    }

    /** The mirror: every recipient an open pair of this donor's holds. */
    public function openRecipientMrnsFor(int|string $donorMrn): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['recipient_mrn'],
            $this->openPairs()->select('recipient_mrn')->where('donor_mrn', $donorMrn)->get()->getResultArray()
        );
    }

    private function openPairs(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table('pairs')->where('status !=', self::CLOSED);
    }
}
