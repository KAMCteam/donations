<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gives each side of the check list its own group headings, and its own rows.
 *
 * The workup used to merge the two sheets: where both asked for CBC there was
 * one `labs` row marked `both`, under one heading spelled the recipient's way.
 * That reads well until the sheets disagree, and they do — "Infectious workup"
 * lists eighteen tests for a recipient and seventeen for a donor, the donor
 * sheet writes Ca/Phos/Mg where the recipient sheet writes it out in full, and
 * the donor's clearances are five of the recipient's eight under a shorter
 * name. One row cannot hold two orders, two spellings and two result lists.
 *
 * So: a heading per side, a row per side, and no `both`.
 *
 * The splitting is the point. A donor's results are attached to the merged row
 * the donor screen was showing, so this makes that row's donor copy first and
 * moves the results onto it — the alternative is a workup that looks untouched
 * because its history stayed behind on the recipient's row.
 */
class SplitLabParentsByPersonType extends Migration
{
    /**
     * Each recipient heading and what the donor sheet calls the same place.
     *
     * Cancer screening and Vaccinations are missing on purpose: the donor
     * sheet has neither, so no donor row can be sitting under them.
     */
    private const DONOR_HEADING = [
        'Immunology tests'         => 'Immunology',
        'Hematology/Biochemistry'  => 'Hematology/Biochem',
        'Infectious workup'        => 'Infectious workup',
        'Urine/stool'              => 'Urine/Stool',
        'Urine/Stool'              => 'Urine/Stool',
        'Imaging'                  => 'Imaging',
        'Referrals and Clearances' => 'Clearances',
    ];

    /** Tests the donor sheet spells its own way. */
    private const DONOR_SPELLING = [
        'Calcium/Phosphorus/Mg' => 'Ca/Phos/Mg',
        'Cr clearance'          => 'Creatinine Clearance',
        'Stool exam'            => 'Stool Exam',
    ];

    public function up(): void
    {
        $this->addPersonTypeToHeadings();
        $this->widenResultTypes();
        $this->splitTheSheets();
        $this->narrowPersonType();
        $this->applyDonorSpellings();
    }

    /**
     * Irreversible by design.
     *
     * Merging the two sides back would mean choosing which sheet's spelling,
     * order and result list to keep and dropping the other — and deciding
     * which of two rows a result belongs to. The Create… migration's own
     * down() still drops the tables.
     */
    public function down(): void
    {
    }

    private function addPersonTypeToHeadings(): void
    {
        if (! $this->hasColumn('lab_parents', 'person_type')) {
            // Everything stored so far was headed the recipient's way.
            $this->db->query('ALTER TABLE ' . $this->table('lab_parents')
                . " ADD COLUMN person_type ENUM('recipient','donor') NOT NULL DEFAULT 'recipient' AFTER name");
        }

        // A heading is now unique per side, not outright.
        if ($this->hasIndex('lab_parents', 'name')) {
            $this->db->query('ALTER TABLE ' . $this->table('lab_parents') . ' DROP INDEX ' . $this->db->protectIdentifiers('name'));
            $this->db->query('ALTER TABLE ' . $this->table('lab_parents') . ' ADD UNIQUE KEY name_person_type (name, person_type)');
        }

        $this->db->query('UPDATE ' . $this->table('lab_parents') . " SET name = 'Urine/Stool' WHERE name = 'Urine/stool'");
    }

    /** The sheet offers Not applicable on some acceptable/abnormal tests. */
    private function widenResultTypes(): void
    {
        if ($this->enumHas('labs', 'result_type', 'acceptable_abnormal_na')) {
            return;
        }

        $this->db->query('ALTER TABLE ' . $this->table('labs')
            . " MODIFY result_type ENUM('text','numeric','blood_group','done','positive_negative',"
            . "'acceptable_abnormal','acceptable_abnormal_na','cleared_not_cleared','given_not_given')"
            . " NOT NULL DEFAULT 'text'");
    }

    /**
     * Moves every donor row under a donor heading, copying the shared ones.
     *
     * Done heading by heading so a row only ever moves to the donor twin of
     * the group it was already in.
     */
    private function splitTheSheets(): void
    {
        if (! $this->enumHas('labs', 'person_type', 'both')) {
            return;
        }

        $parents = $this->db->table('lab_parents')->where('person_type', 'recipient')->get()->getResultArray();

        foreach ($parents as $parent) {
            $donorHeading = self::DONOR_HEADING[$parent['name']] ?? null;

            if ($donorHeading === null) {
                continue;
            }

            $shared = $this->db->table('labs')
                ->where('lab_parent_id', $parent['id'])
                ->whereIn('person_type', ['donor', 'both'])
                ->get()
                ->getResultArray();

            if ($shared === []) {
                continue;
            }

            $donorParentId = $this->donorHeadingId($donorHeading, (int) $parent['sort_order']);

            foreach ($shared as $lab) {
                if ($lab['person_type'] === 'donor') {
                    $this->db->table('labs')->where('id', $lab['id'])->update(['lab_parent_id' => $donorParentId]);

                    continue;
                }

                $this->copyForTheDonorSide($lab, $donorParentId);
            }
        }
    }

    /** The donor's own row for a test both sheets ask for, and its results. */
    private function copyForTheDonorSide(array $lab, int $donorParentId): void
    {
        $this->db->table('labs')->insert([
            'name'          => $lab['name'],
            'lab_parent_id' => $donorParentId,
            'organ_code'    => $lab['organ_code'],
            'person_type'   => 'donor',
            'result_type'   => $lab['result_type'],
            'sort_order'    => $lab['sort_order'],
            'is_active'     => $lab['is_active'],
            'created_at'    => $lab['created_at'],
            'updated_at'    => $lab['updated_at'],
        ]);

        $copyId = (int) $this->db->insertID();

        // What a donor has already recorded belongs to the donor's row.
        $this->db->table('lab_results')
            ->where('lab_id', $lab['id'])
            ->where('person_type', 'donor')
            ->update(['lab_id' => $copyId]);

        $this->db->table('labs')->where('id', $lab['id'])->update(['person_type' => 'recipient']);
    }

    private function donorHeadingId(string $name, int $sortOrder): int
    {
        $parents = $this->db->table('lab_parents');
        $row     = $parents->getWhere(['name' => $name, 'person_type' => 'donor'])->getRowArray();

        if ($row !== null) {
            return (int) $row['id'];
        }

        $parents->insert(['name' => $name, 'person_type' => 'donor', 'sort_order' => $sortOrder]);

        return (int) $this->db->insertID();
    }

    /** Nothing is `both` any more, so the column should not offer it. */
    private function narrowPersonType(): void
    {
        if (! $this->enumHas('labs', 'person_type', 'both')) {
            return;
        }

        // Anything still saying `both` heads a group the donor sheet does not
        // have, so it was only ever the recipient's.
        $this->db->query('UPDATE ' . $this->table('labs') . " SET person_type = 'recipient' WHERE person_type = 'both'");
        $this->db->query('ALTER TABLE ' . $this->table('labs')
            . " MODIFY person_type ENUM('recipient','donor') NOT NULL DEFAULT 'recipient'");
    }

    /** Now that the rows are separate, each can carry its sheet's wording. */
    private function applyDonorSpellings(): void
    {
        foreach (self::DONOR_SPELLING as $shared => $donor) {
            $this->db->table('labs')
                ->where('person_type', 'donor')
                ->where('name', $shared)
                ->update(['name' => $donor]);
        }
    }

    // ---- Asking the database what it already has ---------------------------

    private function table(string $name): string
    {
        return $this->db->protectIdentifiers($this->db->prefixTable($name), true, false, false);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->db->getFieldNames($this->db->prefixTable($table)), true);
    }

    private function hasIndex(string $table, string $index): bool
    {
        return array_key_exists($index, $this->db->getIndexData($this->db->prefixTable($table)));
    }

    /**
     * Whether an ENUM column still offers (or already offers) a value.
     *
     * Read from information_schema rather than getFieldData(), which reports
     * an ENUM's type as the bare word "enum" with the values nowhere in it.
     */
    private function enumHas(string $table, string $column, string $value): bool
    {
        $row = $this->db->query(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->db->prefixTable($table), $column]
        )->getRowArray();

        return $row !== null && str_contains((string) $row['COLUMN_TYPE'], "'" . $value . "'");
    }
}
