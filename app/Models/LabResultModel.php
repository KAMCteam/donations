<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * A person's lab results, in the role they were worked up for.
 */
class LabResultModel extends Model
{
    protected $table         = 'lab_results';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'person_mrn', 'person_type', 'lab_id', 'status', 'value', 'taken_on', 'notes',
    ];

    /**
     * The whole workup for one person: every test their programme and role
     * calls for, left-joined with whatever has been recorded so far — so a
     * test with no result yet still comes back, as a pending card.
     *
     * @return list<array<string, mixed>>
     */
    public function workupFor(int|string $mrn, string $personType, string $organCode): array
    {
        return $this->db->table('labs l')
            ->select('l.id AS lab_id, l.name AS lab_name, l.result_type, lp.name AS parent_name')
            ->select('lr.id AS result_id, COALESCE(lr.status, \'not_done\') AS status, lr.value, lr.taken_on, lr.notes', false)
            ->join('lab_parents lp', 'lp.id = l.lab_parent_id', 'left')
            ->join(
                'lab_results lr',
                'lr.lab_id = l.id AND lr.person_mrn = ' . $this->db->escape($mrn)
                    . ' AND lr.person_type = ' . $this->db->escape($personType),
                'left'
            )
            ->where('l.organ_code', $organCode)
            ->whereIn('l.person_type', [$personType, 'both'])
            ->where('l.is_active', 1)
            ->orderBy('lp.sort_order')
            ->orderBy('l.sort_order')
            ->get()
            ->getResultArray();
    }

    /**
     * Completed out of total, which is what the progress bar shows.
     *
     * @return array{done: int, total: int, pct: int}
     */
    public function progressFor(int|string $mrn, string $personType): array
    {
        $row = $this->db->table('lab_results')
            // Answered, which is every answer but the two that mean nobody
            // has looked yet. UiStore::RESULT_UNANSWERED is the same list.
            ->select('COUNT(*) AS total, SUM(status NOT IN (\'not_done\', \'pending\')) AS done', false)
            ->where('person_mrn', $mrn)
            ->where('person_type', $personType)
            ->get()
            ->getRowArray();

        $total = (int) ($row['total'] ?? 0);
        $done  = (int) ($row['done'] ?? 0);

        return ['done' => $done, 'total' => $total, 'pct' => (int) round($done / max($total, 1) * 100)];
    }

    /** Records or replaces one result, keeping one row per person per test. */
    public function record(int|string $mrn, string $personType, int $labId, array $attributes): void
    {
        $existing = $this->where([
            'person_mrn'  => $mrn,
            'person_type' => $personType,
            'lab_id'      => $labId,
        ])->first();

        if ($existing !== null) {
            $this->update($existing['id'], $attributes);

            return;
        }

        $this->insert(array_merge($attributes, [
            'person_mrn'  => $mrn,
            'person_type' => $personType,
            'lab_id'      => $labId,
        ]));
    }
}
