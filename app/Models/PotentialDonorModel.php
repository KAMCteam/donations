<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Who is being considered for a recipient, before any pair exists.
 *
 * A recipient is worked up against several donors at once and only one of them
 * becomes the pair. These are the others — and, until the moment somebody
 * presses Pair up, that one as well. The row stays after a pair is made,
 * because who else was looked at is part of what happened.
 */
class PotentialDonorModel extends Model
{
    protected $table         = 'potential_donors';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['recipient_mrn', 'donor_mrn', 'status', 'aside_reason'];

    public const DECLINED = 'declined';

    /** Set aside by hand: a decision about this donor. The tab freezes. */
    public const DELINKED = 'delinked';

    /**
     * Set aside because somebody else was paired: a decision about them, not
     * about this one. The pair can be switched back here later.
     */
    public const SUPERSEDED = 'superseded';

    /** What a candidate's status can be. The donor's own record says the same. */
    public const STATUSES = ['active', 'on_hold', 'declined'];

    /**
     * One recipient's candidates, oldest first, each with their donor.
     *
     * In the order they were added, because that is what numbers the tabs: a
     * candidate is donor-1 because they were the first to be considered, and
     * stays donor-1 whatever happens to the rest.
     *
     * @return list<array<string, mixed>>
     */
    public function forRecipient(int|string $recipientMrn): array
    {
        return $this->db->table('potential_donors pd')
            ->select('pd.*, d.name AS donor_name, d.blood_group AS donor_blood_group, d.status AS donor_status')
            // The pair this candidate became, if they became one. Nothing is
            // joined for the others, which is the difference.
            ->select('(SELECT p.id FROM pairs p WHERE p.recipient_mrn = pd.recipient_mrn'
                . ' AND p.donor_mrn = pd.donor_mrn AND p.status <> \'closed\' LIMIT 1) AS pair_id', false)
            ->join('donors d', 'd.mrn = pd.donor_mrn', 'left')
            ->where('pd.recipient_mrn', $recipientMrn)
            ->orderBy('pd.id')
            ->get()
            ->getResultArray();
    }

    /** One candidate, checked against the recipient it is supposed to belong to. */
    public function forRecipientById(int|string $recipientMrn, int|string $id): ?array
    {
        return $this->where('recipient_mrn', $recipientMrn)->find((int) $id);
    }

    /** Whether this donor is already somebody's candidate — anybody's. */
    public function spokenFor(int|string $donorMrn): bool
    {
        return $this->where('donor_mrn', $donorMrn)
            ->where('status !=', self::DECLINED)
            ->countAllResults() > 0;
    }

    /**
     * Adds a candidate, or does nothing if that pairing is already on the list.
     *
     * Considering somebody twice is considering them once.
     */
    public function consider(int|string $recipientMrn, int|string $donorMrn): void
    {
        $existing = $this->where('recipient_mrn', $recipientMrn)
            ->where('donor_mrn', $donorMrn)
            ->first();

        if ($existing === null) {
            $this->insert(['recipient_mrn' => $recipientMrn, 'donor_mrn' => $donorMrn, 'status' => 'active']);

            return;
        }

        // Considered before and set aside: adding them again takes them back
        // up rather than leaving a declined tab that cannot be reopened.
        if ($existing['status'] === self::DECLINED) {
            $this->update((int) $existing['id'], ['status' => 'active', 'aside_reason' => null]);
        }
    }

    /**
     * Sets every other candidate of this recipient aside, as superseded.
     *
     * Nothing was decided about them: one of the others was paired, and that
     * is all this says. Which is why the reason is recorded — a superseded
     * candidate can be switched back to, and a delinked one cannot.
     *
     * A candidate already set aside by hand keeps that reason: being passed
     * over does not undo having been declined.
     */
    public function declineOthers(int|string $recipientMrn, int|string $keepId): void
    {
        $this->db->table('potential_donors')
            ->where('recipient_mrn', $recipientMrn)
            ->where('id !=', (int) $keepId)
            ->groupStart()
                ->where('aside_reason', null)
                ->orWhere('aside_reason', self::SUPERSEDED)
            ->groupEnd()
            ->update([
                'status'       => self::DECLINED,
                'aside_reason' => self::SUPERSEDED,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
    }
}
