<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The waiting list, and the score it is ordered by.
 */
class RecipientModel extends Model
{
    protected $table         = 'recipients';
    protected $primaryKey    = 'mrn';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'mrn', 'name', 'organ_code', 'blood_group', 'gender', 'age', 'city', 'phone',
        'entry_date', 'dialysis_start', 'is_urgent', 'status',
        'mrp_id', 'coordinator_id', 'notes',
    ];

    /**
     * The waiting-list score, unchanged from the original system: a tenth of a
     * point per month waiting, plus a tenth per month on dialysis.
     *
     * Computed at read time, never stored, so it keeps counting up on its own
     * and cannot go stale. `dialysis_start` is nullable and NULL plus a number
     * is NULL in SQL, so a recipient with no dialysis date scores NULL rather
     * than counting only the waiting time — the original behaviour, kept.
     * `score_waiting_only` is the waiting half on its own for anyone who wants
     * a number in that case.
     */
    public const SCORE_CALC = '
            (
                (0.1 * TIMESTAMPDIFF(MONTH, entry_date, CURDATE())) +
                (0.1 * TIMESTAMPDIFF(MONTH, dialysis_start, CURDATE()))
            ) AS score,
            (0.1 * TIMESTAMPDIFF(MONTH, entry_date, CURDATE())) AS score_waiting_only
        ';

    /**
     * Recipients not held by an open pair, urgent first, then by score.
     *
     * "Open" is every pair status except `closed`, so closing a pair puts both
     * sides back on their lists.
     *
     * @return list<array<string, mixed>>
     */
    public function waitingList(?string $organCode = null, ?string $bloodGroup = null): array
    {
        $builder = $this->db->table('recipients r')
            ->select('r.*, ' . self::SCORE_CALC, false)
            ->select('op.label AS program_label, m.name AS mrp_name, c.name AS coordinator_name')
            ->join('organ_programs op', 'op.code = r.organ_code', 'left')
            ->join('mrp m', 'm.id = r.mrp_id', 'left')
            ->join('coordinators c', 'c.id = r.coordinator_id', 'left')
            ->where('NOT EXISTS (SELECT 1 FROM pairs p WHERE p.recipient_mrn = r.mrn AND p.status <> \'closed\')', null, false);

        if ($organCode !== null && $organCode !== '') {
            $builder->where('r.organ_code', $organCode);
        }

        if ($bloodGroup !== null && $bloodGroup !== '') {
            $builder->where('r.blood_group', $bloodGroup);
        }

        return $builder->orderBy('r.is_urgent', 'DESC')
            ->orderBy('score', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** One recipient with their score, whether or not they are paired. */
    public function withScore(int|string $mrn): ?array
    {
        return $this->db->table('recipients r')
            ->select('r.*, ' . self::SCORE_CALC, false)
            ->where('r.mrn', $mrn)
            ->get()
            ->getRowArray();
    }

    /** True when an open pair already holds this recipient. */
    public function isMatched(int|string $mrn): bool
    {
        return $this->db->table('pairs')
            ->where('recipient_mrn', $mrn)
            ->where('status !=', 'closed')
            ->countAllResults() > 0;
    }
}
