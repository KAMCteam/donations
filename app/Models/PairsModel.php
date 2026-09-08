<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Migrated from the CodeIgniter 3 Pairs_model.
 *
 * The raw-SQL bits (the score expression and the NOT IN sub-selects) are kept
 * verbatim; in CI4 the "do not escape this" flag is the third argument to
 * where() and the second to select(), same as CI3.
 */
class PairsModel extends Model
{
    protected $table         = 'pairs';
    protected $primaryKey    = 'pair_id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'recipient_mrn', 'donor_mrn', 'match_status', 'relationship', 'matched_on', 'surgery_on',
    ];

    /**
     * Waiting-list score: months since entry plus months on dialysis.
     */
    private const SCORE_CALC = '
            (
                (0.1 * TIMESTAMPDIFF(MONTH, entry_date, CURDATE())) +
                (0.1 * TIMESTAMPDIFF(MONTH, dialysis, CURDATE()))
            ) AS score
        ';

    /**
     * @param array<string, mixed> $pair
     */
    public function insert_pair(array $pair): bool
    {
        return $this->db->table('pairs')->insert($pair);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_unmatched_recipients(): array
    {
        return $this->db->table('patients')
            ->select('patients.*, ' . self::SCORE_CALC, false)
            ->where('type', 'recipient')
            ->where("mrn NOT IN (SELECT recipient_mrn FROM pairs WHERE match_status NOT IN ('closed'))", null, false)
            ->orderBy('urgency', 'DESC')
            ->orderBy('score', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_some_unmatched_recipients(?string $blood_group): array
    {
        if (empty($blood_group)) {
            return $this->get_unmatched_recipients();
        }

        return $this->db->table('patients')
            ->select('patients.*, ' . self::SCORE_CALC, false)
            ->where('type', 'recipient')
            ->where("mrn NOT IN (SELECT recipient_mrn FROM pairs WHERE match_status NOT IN ('closed'))", null, false)
            ->where('blood_group', $blood_group)
            ->orderBy('urgency', 'DESC')
            ->orderBy('score', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_unmatched_donors(): array
    {
        return $this->db->table('patients')
            ->select('*')
            ->where('type', 'donor')
            ->where('mrn NOT IN (SELECT donor_mrn FROM pairs)', null, false)
            ->get()
            ->getResultArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function has_donor(int|string|null $patient_mrn): ?array
    {
        return $this->db->table('pairs')
            ->select('donor_mrn, match_status, relationship, matched_on, surgery_on')
            ->where('recipient_mrn', $patient_mrn)
            ->orderBy('pair_id', 'desc')
            ->get()
            ->getRowArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function has_recipient(int|string|null $patient_mrn): ?array
    {
        return $this->db->table('pairs')
            ->select('recipient_mrn, match_status, relationship, matched_on, surgery_on')
            ->where('donor_mrn', $patient_mrn)
            ->orderBy('pair_id', 'desc')
            ->get()
            ->getRowArray();
    }

    /**
     * The id of the open pair joining these two, or null when there is none.
     */
    public function pair_exists(int|string|null $recipient_mrn, int|string|null $donor_mrn): int|string|null
    {
        $row = $this->db->table('pairs')
            ->where('recipient_mrn', $recipient_mrn)
            ->where('donor_mrn', $donor_mrn)
            ->where("match_status not in ('closed')", null, false)
            ->get()
            ->getRowArray();

        return $row['pair_id'] ?? null;
    }

    /**
     * @param array<string, mixed> $pair
     */
    public function update_pair(int|string $pair_id, array $pair): bool
    {
        return $this->db->table('pairs')
            ->where('pair_id', $pair_id)
            ->update($pair);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_all_pairs(): array
    {
        return $this->db->table('pairs')
            ->select('*')
            ->orderBy('match_status')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_all_pairs_info(): array
    {
        return $this->expand_pairs($this->get_all_pairs());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_custom_pairs(?string $blood_group, ?string $match_status, int|string|null $recipient_mrn = null): array
    {
        $builder = $this->db->table('pairs as p')->select('p.*');

        if (! empty($blood_group)) {
            $builder->join('patients as pa', 'p.recipient_mrn = pa.mrn');
            $builder->where('blood_group', $blood_group);
        }

        if (! empty($match_status)) {
            $builder->where('match_status', $match_status);
        }

        if (! empty($recipient_mrn)) {
            $builder->where('p.recipient_mrn', $recipient_mrn);
        }

        return $builder->orderBy('match_status')->get()->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get_custom_pairs_info(?string $blood_group, ?string $match_status, int|string|null $recipient_mrn = null): array
    {
        return $this->expand_pairs($this->get_custom_pairs($blood_group, $match_status, $recipient_mrn));
    }

    /**
     * Replaces the two MRNs on each pair row with the full patient records.
     *
     * @param list<array<string, mixed>> $pairs
     *
     * @return list<array<string, mixed>>
     */
    private function expand_pairs(array $pairs): array
    {
        $patients = model(PatientModel::class);
        $myPairs  = [];

        foreach ($pairs as $pair) {
            $myPairs[] = [
                'relationship' => $pair['relationship'],
                'match_status' => $pair['match_status'],
                'matched_on'   => $pair['matched_on'] ?? null,
                'surgery_on'   => $pair['surgery_on'] ?? null,
                'pair'         => [
                    $patients->get_patient_info_modified($pair['recipient_mrn']),
                    $patients->get_patient_info_modified($pair['donor_mrn']),
                ],
            ];
        }

        return $myPairs;
    }
}
