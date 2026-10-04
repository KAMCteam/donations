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
            ->join('pairs p', $selfMrn . ' = t.mrn AND p.status <> ' . $this->db->escape('closed'), 'left')
            ->join($otherTable . ' o', 'o.mrn = ' . $otherMrn, 'left')
            ->select("GROUP_CONCAT(DISTINCT CONCAT(o.name, ' (', o.mrn, ')') ORDER BY o.name SEPARATOR ', ') AS related", false)
            ->select('MAX(p.crossmatch_date) AS crossmatch_date', false)
            ->select("GROUP_CONCAT(DISTINCT p.relationship SEPARATOR ', ') AS pair_relationship", false);

        if ($isRecipient) {
            $builder->select('t.entry_date, t.dialysis_type, t.dialysis_start, NULL AS donation_type, NULL AS own_relationship', false);
        } else {
            $builder->select('t.registered_on AS entry_date, NULL AS dialysis_type, NULL AS dialysis_start, t.donation_type, t.relationship AS own_relationship', false);
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
        foreach ([
            'organs'       => 't.organ_code',
            'groups'       => 't.blood_group',
            'statuses'     => 't.status',
            'mrps'         => 't.mrp_id',
            'coordinators' => 't.coordinator_id',
        ] as $key => $column) {
            if (($f[$key] ?? []) !== []) {
                $builder->whereIn($column, $f[$key]);
            }
        }

        // A date range on the day the record joined the register. Either end
        // may be left open.
        if (($f['from'] ?? '') !== '') {
            $builder->where($dateColumn . ' >=', $f['from']);
        }

        if (($f['to'] ?? '') !== '') {
            $builder->where($dateColumn . ' <=', $f['to']);
        }

        // Having a test means having a result recorded against it. Asking for
        // several asks for any of them, which is what a list of checkboxes
        // reads as.
        if (($f['labs'] ?? []) !== []) {
            $ids = implode(',', array_map('intval', $f['labs']));

            $builder->where(
                'EXISTS (SELECT 1 FROM lab_results lr WHERE lr.person_mrn = t.mrn'
                . ' AND lr.person_type = ' . $this->db->escape($type)
                . ' AND lr.lab_id IN (' . $ids . '))',
                null,
                false
            );
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
