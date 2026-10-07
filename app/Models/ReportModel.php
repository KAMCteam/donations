<?php

namespace App\Models;

use App\Libraries\UiStore;
use CodeIgniter\Model;

/**
 * The Reports screen's one query.
 *
 * Recipients and donors are two tables with different columns, and the screen
 * shows either or both in one table, so each side is read on its own terms and
 * the two are mapped to a common row before they are merged. Nothing here
 * writes; it is a reading of the register under whatever the filters say.
 *
 * Every filter is a list. An empty list means "all", which is what an
 * untouched filter sends, so a filter nobody has touched narrows nothing and
 * the query says so by not mentioning it.
 */
class ReportModel extends Model
{
    protected $table      = 'recipients';
    protected $returnType = 'array';

    /**
     * The rows a set of filters selects, in one shape whichever side they came
     * from.
     *
     * @param array{types?: list<string>, organs?: list<string>, groups?: list<string>,
     *              statuses?: list<string>, labs?: list<int>, from?: string, to?: string,
     *              mrps?: list<int>, coordinators?: list<int>} $f
     *
     * @return list<array<string, mixed>>
     */
    public function rows(array $f): array
    {
        $types = $f['types'] ?? [];
        $rows  = [];

        if ($types === [] || in_array('recipient', $types, true)) {
            $rows = array_merge($rows, $this->side('recipient', $f));
        }

        if ($types === [] || in_array('donor', $types, true)) {
            $rows = array_merge($rows, $this->side('donor', $f));
        }

        // The box above the report, which narrows what the filters chose by
        // the two things written on the paperwork: a number and a name.
        if (($f['search'] ?? '') !== '') {
            $search = (string) $f['search'];
            $rows   = array_values(array_filter(
                $rows,
                static fn (array $row): bool => stripos($row['mrn'], $search) !== false
                    || stripos($row['name'], $search) !== false
            ));
        }

        // One order for the merged set, so a mixed table does not read as two
        // tables stacked: by name, which is what somebody scanning it reads.
        usort($rows, static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']));

        return $rows;
    }

    /**
     * One side of the register, filtered.
     *
     * @param array<string, mixed> $f
     *
     * @return list<array<string, mixed>>
     */
    private function side(string $type, array $f): array
    {
        $isRecipient = $type === 'recipient';
        $table       = $isRecipient ? 'recipients' : 'donors';
        $selfMrn     = $isRecipient ? 'p.recipient_mrn' : 'p.donor_mrn';
        $otherMrn    = $isRecipient ? 'p.donor_mrn' : 'p.recipient_mrn';
        $otherTable  = $isRecipient ? 'donors' : 'recipients';

        $builder = $this->db->table($table . ' t')
            ->select('t.mrn, t.name, t.age, t.blood_group, t.gender, t.phone, t.status, t.organ_code')
            ->select('m.name AS mrp_name, c.name AS coordinator_name')
            ->join('mrp m', 'm.id = t.mrp_id', 'left')
            ->join('coordinators c', 'c.id = t.coordinator_id', 'left');

        // Who this person is linked to. A recipient may have several donors
        // now, so the other side is gathered rather than joined one-to-one:
        // one cell listing them all, rather than the same record repeated once
        // per link. Aggregating also keeps one row per MRN whatever the joins
        // multiply.
        $builder
            ->join('pairs p', $selfMrn . ' = t.mrn AND ' . PairModel::openSql(), 'left')
            ->join($otherTable . ' o', 'o.mrn = ' . $otherMrn, 'left')
            ->select("GROUP_CONCAT(DISTINCT CONCAT(o.name, ' (', o.mrn, ')') ORDER BY o.name SEPARATOR ', ') AS related", false)
            ->select('MAX(p.crossmatch_date) AS crossmatch_date', false)
            ->select("GROUP_CONCAT(DISTINCT p.relationship SEPARATOR ', ') AS pair_relationship", false);

        if ($isRecipient) {
            $builder->select('t.entry_date, t.dialysis_type, t.dialysis_start, NULL AS donation_type, NULL AS own_relationship', false);
        } else {
            // No entry date: a donor's is the register's own bookkeeping and
            // is not one of their details, so the mixed table leaves the cell
            // empty for them the way it leaves the dialysis ones. The column
            // is still what a date range narrows a donor report by.
            $builder->select('NULL AS entry_date, NULL AS dialysis_type, NULL AS dialysis_start, t.donation_type, t.relationship AS own_relationship', false);
        }

        $this->narrow($builder, $f, $isRecipient ? 't.entry_date' : 't.registered_on', $type);

        return array_map(
            fn (array $row): array => $this->toRow($row, $type),
            $builder->groupBy('t.mrn')->get()->getResultArray()
        );
    }

    /**
     * The filters that read the same on both sides.
     *
     * @param array<string, mixed> $f
     */
    private function narrow(\CodeIgniter\Database\BaseBuilder $builder, array $f, string $dateColumn, string $type): void
    {
        $isSelfRecipient = $type === 'recipient';

        foreach ([
            'organs'       => 't.organ_code',
            'groups'       => 't.blood_group',
            'mrps'         => 't.mrp_id',
            'coordinators' => 't.coordinator_id',
        ] as $key => $column) {
            if (($f[$key] ?? []) !== []) {
                $builder->whereIn($column, $f[$key]);
            }
        }

        // Status, on either of the two things that hold one. Four of the six
        // words are a person's own and a pair's alike; the other two —
        // Paired Exchange and Closed — are only ever a pair's, so asking for
        // one of those and matching `t.status` alone would answer every time
        // with nothing. A record matches if its own word is asked for, or if
        // an open pair holding it wears one that is.
        if (($f['statuses'] ?? []) !== []) {
            $words = implode(',', array_map(fn (string $s): string => $this->db->escape($s), $f['statuses']));

            $builder->groupStart()
                ->where('t.status IN (' . $words . ')', null, false)
                ->orWhere(
                    'EXISTS (SELECT 1 FROM pairs ps WHERE ps.' . ($isSelfRecipient ? 'recipient_mrn' : 'donor_mrn')
                    . ' = t.mrn AND ' . PairModel::openSql('ps') . ' AND ps.status IN (' . $words . '))',
                    null,
                    false
                )
            ->groupEnd();
        }

        // In a pair, or not in one — an open pair, which is the only kind that
        // holds anybody. The question a report asks about somebody who is
        // still waiting, and the one it asks about a case already under way.
        if (($f['paired'] ?? '') !== '') {
            $holds = 'SELECT 1 FROM pairs pp WHERE pp.' . ($isSelfRecipient ? 'recipient_mrn' : 'donor_mrn')
                . ' = t.mrn AND ' . PairModel::openSql('pp');

            $builder->where(
                ($f['paired'] === 'yes' ? 'EXISTS (' : 'NOT EXISTS (') . $holds . ')',
                null,
                false
            );
        }

        // A date range on the day the record joined the register. Either end
        // may be left open.
        if (($f['from'] ?? '') !== '') {
            $builder->where($dateColumn . ' >=', $f['from']);
        }

        if (($f['to'] ?? '') !== '') {
            $builder->where($dateColumn . ' <=', $f['to']);
        }

        // A test is **completed** when a result is recorded against it that
        // says something: a row whose status is one of the words the workup
        // counts, which is every word but the two that mean nobody has looked
        // yet. Not completed is the other side of the same line, and it has to
        // be a NOT EXISTS rather than a status test, because the commonest way
        // of not having completed a test is having no row for it at all.
        //
        // Asking for several asks for any of them, which is what a list of
        // checkboxes reads as: any one of these completed, or any one of these
        // still outstanding.
        if (($f['labs'] ?? []) !== []) {
            $ids    = implode(',', array_map('intval', $f['labs']));
            $unsaid = implode(',', array_map(fn (string $s): string => $this->db->escape($s), UiStore::RESULT_UNANSWERED));

            $done = 'SELECT 1 FROM lab_results lr WHERE lr.person_mrn = t.mrn'
                . ' AND lr.person_type = ' . $this->db->escape($type)
                . ' AND lr.lab_id IN (' . $ids . ')'
                . ' AND lr.status NOT IN (' . $unsaid . ')';

            if (($f['labMode'] ?? 'done') === 'missing') {
                // Any of the chosen tests this record has not completed. Each
                // id is asked about on its own, because "none of them done"
                // and "one of them not done" are different questions and the
                // checkbox list reads as the second.
                $builder->where(
                    // The chosen name is every row the catalogue has under it
                    // — one per side, one per programme — and only the row on
                    // this record's own sheet is a test this record has. Asked
                    // about the others, a kidney recipient would be missing
                    // the liver sheet's copy of a test they had completed.
                    'EXISTS (SELECT 1 FROM labs lx WHERE lx.id IN (' . $ids . ')'
                    . ' AND lx.person_type = ' . $this->db->escape($type)
                    . ' AND lx.organ_code = t.organ_code'
                    . ' AND lx.is_active = 1 AND lx.person_mrn IS NULL'
                    . ' AND NOT EXISTS (SELECT 1 FROM lab_results lr WHERE lr.person_mrn = t.mrn'
                    . ' AND lr.person_type = ' . $this->db->escape($type)
                    . ' AND lr.lab_id = lx.id'
                    . ' AND lr.status NOT IN (' . $unsaid . ')))',
                    null,
                    false
                );
            } else {
                $builder->where('EXISTS (' . $done . ')', null, false);
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function toRow(array $row, string $type): array
    {
        return [
            'type'           => $type,
            'mrn'            => (string) $row['mrn'],
            'name'           => (string) $row['name'],
            'age'            => (string) $row['age'],
            'bloodGroup'     => (string) $row['blood_group'],
            'mrp'            => (string) ($row['mrp_name'] ?? ''),
            'coordinator'    => (string) ($row['coordinator_name'] ?? ''),
            'gender'         => (string) $row['gender'],
            'phone'          => (string) $row['phone'],
            'dialysisType'   => UiStore::DIALYSIS_TYPES[$row['dialysis_type'] ?? ''] ?? '',
            'firstDialysis'  => (string) ($row['dialysis_start'] ?? ''),
            'entryDate'      => (string) ($row['entry_date'] ?? ''),
            'donationType'   => (string) ($row['donation_type'] ?? ''),
            // Everyone on the other side of an open link, already written out
            // as "Name (MRN)" — a recipient may have more than one donor.
            'related'        => (string) ($row['related'] ?? ''),
            // A donor's own relationship to their recipient is the record's;
            // a recipient has none of their own, so the pair's stands in.
            'relationship'   => (string) ($row['own_relationship'] ?: ($row['pair_relationship'] ?? '')),
            'status'         => (string) $row['status'],
            'crossmatchDate' => (string) ($row['crossmatch_date'] ?? ''),
            'organ'          => (string) $row['organ_code'],
        ];
    }
}
