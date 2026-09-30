<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The lab catalogue: which tests a workup is made of.
 */
class LabModel extends Model
{
    protected $table         = 'labs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'name', 'lab_parent_id', 'organ_code', 'person_type', 'person_mrn',
        'result_type', 'sort_order', 'is_active',
    ];

    /**
     * The workup for one side of one programme, in the order it is listed.
     *
     * One side only: a test both sheets ask for is a row on each, so there is
     * nothing to union in. This is what a new record's lab cards are built
     * from.
     *
     * @return list<array<string, mixed>>
     */
    /**
     * Every test the catalogue names, once each, for a filter that lists them.
     *
     * Grouped by name because the same test is a row on each sheet and in each
     * programme, and a filter offering "CBC" four times would be asking the
     * same question four ways. Selecting one selects all its rows.
     *
     * @return list<array{ids: string, name: string}>
     */
    public function named(): array
    {
        $rows = $this->db->table('labs')
            ->select('name, GROUP_CONCAT(id) AS ids', false)
            ->where('is_active', 1)
            ->where('person_mrn', null)
            ->groupBy('name')
            ->orderBy('name')
            ->get()
            ->getResultArray();

        return array_map(
            static fn (array $row): array => ['ids' => (string) $row['ids'], 'name' => (string) $row['name']],
            $rows
        );
    }

    /**
     * The id of the group that heads the tests a record adds for itself.
     *
     * Each side has its own row for it, as every group does. Null when the
     * catalogue has never been seeded.
     */
    public function customGroupId(string $groupName, string $personType): ?int
    {
        $row = $this->db->table('lab_parents')
            ->getWhere(['name' => $groupName, 'person_type' => $personType])
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    /** Where the next test added to a sheet goes: after everything on it. */
    public function lastSortOrder(string $organCode, string $personType): int
    {
        $row = $this->db->table('labs')
            ->selectMax('sort_order')
            ->getWhere(['organ_code' => $organCode, 'person_type' => $personType])
            ->getRowArray();

        return (int) ($row['sort_order'] ?? 0);
    }

    public function workupFor(string $organCode, string $personType, int|string|null $mrn = null): array
    {
        $builder = $this->db->table('labs l')
            ->select('l.*, lp.name AS parent_name')
            ->join('lab_parents lp', 'lp.id = l.lab_parent_id', 'left')
            ->where('l.organ_code', $organCode)
            ->where('l.person_type', $personType);

        // Without a record there is only the catalogue: a blank Add Recipient
        // form has nobody whose own tests it could show.
        if ($mrn === null) {
            $builder->where('l.person_mrn', null);
        } else {
            $builder->groupStart()->where('l.person_mrn', null)->orWhere('l.person_mrn', $mrn)->groupEnd();
        }

        return $builder
            ->where('l.is_active', 1)
            ->orderBy('lp.sort_order')
            ->orderBy('l.sort_order')
            ->get()
            ->getResultArray();
    }
}
