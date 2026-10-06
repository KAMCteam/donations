<?php

use App\Controllers\Reports;
use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * The Reports screen: what the filters select, and what the table shows.
 *
 * Two rules decide everything on that screen, and both are easy to break
 * without noticing. The first is that an untouched filter narrows nothing —
 * nine filters all start empty and empty means all, so a filter that quietly
 * defaults to its first value would cut the report down and say nothing about
 * it. The second is that the record type decides the columns: a donor has no
 * entry date and a recipient has no donor type, so the same report under a
 * different type is a different table.
 *
 * The exports are the same report on paper, so what they must not do is show a
 * record the filters excluded.
 *
 * MySQL/MariaDB only, same as the other database tests.
 *
 * @internal
 */
final class ReportsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private int $mrpId;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db->DBDriver !== 'MySQLi') {
            $this->markTestSkipped('This schema is MySQL-specific; the tests group uses ' . $this->db->DBDriver . '.');
        }

        $this->db->table('mrp')->insert(['code' => 'MRP-001', 'name' => 'Dr. Reports']);
        $this->mrpId = (int) $this->db->insertID();

        // Two recipients and one donor, differing in the things the filters
        // ask about, so every assertion below can name who should survive it.
        $this->db->table('recipients')->insertBatch([
            [
                'mrn' => '7001', 'name' => 'Amal Report', 'organ_code' => 'kidney',
                'blood_group' => 'O', 'gender' => 'female', 'age' => 40,
                'status' => 'active', 'entry_date' => '2024-01-15',
                'dialysis_start' => '2023-06-01', 'mrp_id' => $this->mrpId,
            ],
            [
                'mrn' => '7002', 'name' => 'Badr Report', 'organ_code' => 'kidney',
                'blood_group' => 'A', 'gender' => 'male', 'age' => 55,
                'status' => 'on_hold', 'entry_date' => '2025-03-20',
                // Same keys as the row above: `insertBatch` writes one
                // statement for the set, so a row that leaves a column out
                // does not match the columns the statement names.
                'dialysis_start' => null, 'mrp_id' => null,
            ],
        ]);

        $this->db->table('donors')->insert([
            'mrn' => '7101', 'name' => 'Ziad Report', 'organ_code' => 'kidney',
            'blood_group' => 'O', 'gender' => 'male', 'age' => 33,
            'status' => 'active', 'donation_type' => 'living_related',
            'relationship' => 'Brother', 'registered_on' => '2024-02-10',
        ]);

        $this->withSession(['auth_id' => 1, 'auth_login_id' => '1', 'auth_name' => 'Test User', 'auth_role' => 'admin', 'ui_organ' => 'kidney']);
    }

    /** @param array<string, mixed>|null $params */
    public function get($path, ?array $params = null): \CodeIgniter\Test\TestResponse
    {
        return $this->call('get', $path, $params);
    }

    // ---- Empty means all ---------------------------------------------------

    /**
     * The whole screen rests on this: nothing chosen selects everything, and
     * both registers at once.
     */
    public function testAnUntouchedReportShowsBothRegisters(): void
    {
        $html = $this->get('reports')->getBody();

        $this->assertStringContainsString('Amal Report', $html);
        $this->assertStringContainsString('Badr Report', $html);
        $this->assertStringContainsString('Ziad Report', $html);
        $this->assertStringContainsString('3 records', $html);
    }

    /** And every filter says so on its own button, rather than naming a value. */
    public function testEveryFilterOpensOnAll(): void
    {
        $html = $this->get('reports')->getBody();

        foreach ([
            'Record type', 'Organ', 'Blood group', 'Status',
            'Labs', 'Date', 'Doctor (MRP)', 'Coordinator', 'Columns',
        ] as $label) {
            $this->assertStringContainsString('<span class="filter-title">' . $label . '</span>', $html);
        }

        // Nine filters, nine "All" — the columns one included, because all five
        // of its options start chosen, which selects the same as none of them.
        $this->assertSame(9, substr_count($html, '>All</span>'));
    }

    // ---- One filter at a time ----------------------------------------------

    /**
     * @return list<array{0: string, 1: list<string>, 2: list<string>}>
     */
    public static function filterProvider(): array
    {
        return [
            'record type'  => ['type[]=donor', ['Ziad Report'], ['Amal Report', 'Badr Report']],
            'blood group'  => ['group[]=O', ['Amal Report', 'Ziad Report'], ['Badr Report']],
            'status'       => ['status[]=on_hold', ['Badr Report'], ['Amal Report', 'Ziad Report']],
            'organ'        => ['organ[]=liver', [], ['Amal Report', 'Badr Report', 'Ziad Report']],
            'doctor'       => ['mrp[]=%d', ['Amal Report'], ['Badr Report', 'Ziad Report']],
            'from'         => ['from=2025-01-01', ['Badr Report'], ['Amal Report', 'Ziad Report']],
            'to'           => ['to=2024-12-31', ['Amal Report', 'Ziad Report'], ['Badr Report']],
            'both ends'    => ['from=2024-02-01&to=2024-12-31', ['Ziad Report'], ['Amal Report', 'Badr Report']],
        ];
    }

    /**
     * @param list<string> $expected
     * @param list<string> $excluded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('filterProvider')]
    public function testOneFilterNarrowsToWhatItNames(string $query, array $expected, array $excluded): void
    {
        $html = $this->get('reports?' . sprintf($query, $this->mrpId))->getBody();

        foreach ($expected as $name) {
            $this->assertStringContainsString($name, $html, $query . ' should keep ' . $name);
        }

        foreach ($excluded as $name) {
            $this->assertStringNotContainsString($name, $html, $query . ' should drop ' . $name);
        }
    }

    /** Several values in one filter are an "any of", the way checkboxes read. */
    public function testSeveralValuesInOneFilterAreAnyOfThem(): void
    {
        $html = $this->get('reports?group[]=O&group[]=A')->getBody();

        $this->assertStringContainsString('Amal Report', $html);
        $this->assertStringContainsString('Badr Report', $html);
        $this->assertStringContainsString('Ziad Report', $html);
    }

    // ---- The columns the record type has -----------------------------------

    /**
     * The table is laid out to the width it has, so nothing is read sideways.
     *
     * Fifteen columns at their widest is wider than any screen, and a report
     * somebody has to pan through is a report they read half of.
     */
    public function testTheTableIsLaidOutToFitTheScreen(): void
    {
        $html = $this->get('reports')->getBody();

        // A share per column, as a colgroup — and whichever subset the Columns
        // filter leaves, the shares still add up to the whole table.
        $this->assertStringContainsString('<colgroup>', $html);

        preg_match_all('/<col style="width:([0-9.]+)%">/', $html, $m);
        $this->assertCount(count($this->headings($html)), $m[1], 'one share per column');
        $this->assertEqualsWithDelta(100, array_sum(array_map('floatval', $m[1])), 0.5);

        // And the same holds for a narrower set.
        $donor = $this->get('reports?type=donor')->getBody();
        preg_match_all('/<col style="width:([0-9.]+)%">/', $donor, $d);
        $this->assertCount(count($this->headings($donor)), $d[1]);
        $this->assertEqualsWithDelta(100, array_sum(array_map('floatval', $d[1])), 0.5);
        $this->assertLessThan(count($m[1]), count($d[1]), 'the donor set is the narrower one');
    }

    /** The headings of the table on a page, in order. */
    private function headings(string $html): array
    {
        preg_match('/<thead>.*?<\/thead>/s', $html, $head);
        preg_match_all('/<th>(.*?)<\/th>/s', $head[0] ?? '', $m);

        return $m[1];
    }

    public function testTheRecordTypeDecidesTheColumns(): void
    {
        $mixed = $this->get('reports')->getBody();
        $this->assertStringContainsString('<th>MRN</th>', $mixed);
        $this->assertStringContainsString('<th>Related Donor/Recipient</th>', $mixed);
        // Only the mixed table needs to say which register a row came from.
        $this->assertStringContainsString('<th>Type</th>', $mixed);

        $recipients = $this->get('reports?type[]=recipient')->getBody();
        $this->assertStringContainsString('<th>Recipient MRN</th>', $recipients);
        $this->assertStringContainsString('<th>Related Donor</th>', $recipients);
        $this->assertStringNotContainsString('<th>Donor Type</th>', $recipients);
        // Every row is a recipient; a column saying so would say it 34 times.
        $this->assertStringNotContainsString('<th>Type</th>', $recipients);

        $donors = $this->get('reports?type[]=donor')->getBody();
        $this->assertStringContainsString('<th>Donor MRN</th>', $donors);
        $this->assertStringContainsString('<th>Donor Type</th>', $donors);
        $this->assertStringContainsString('<th>Related Recipient</th>', $donors);
        // A donor has no answer to these, so the set does not carry them.
        $this->assertStringNotContainsString('<th>Type Dialysis</th>', $donors);
        $this->assertStringNotContainsString('<th>Entry Date</th>', $donors);
    }

    /**
     * And it says which, per row, in the same tag the pairs register uses.
     *
     * It is fixed: the Columns filter cannot reach it, because a mixed table
     * with no way of telling a recipient from a donor is not a report.
     */
    public function testTheMixedTableSaysWhichRegisterEachRowCameFrom(): void
    {
        $html = $this->get('reports')->getBody();

        $this->assertStringContainsString('<span class="role-tag tone-blue-soft">recipient</span>', $html);
        $this->assertStringContainsString('<span class="role-tag tone-teal-soft">donor</span>', $html);
        $this->assertStringNotContainsString('name="column[]" value="recordType"', $html);

        // Not even when every optional column is switched off.
        $this->assertStringContainsString('<th>Type</th>', $this->get('reports?applied=1')->getBody());
    }

    /** Both types chosen is the same question as neither, so it is the same table. */
    public function testChoosingBothTypesReadsAsAll(): void
    {
        $html = $this->get('reports?type[]=recipient&type[]=donor')->getBody();

        $this->assertStringContainsString('<th>MRN</th>', $html);
        $this->assertStringContainsString('<th>Type</th>', $html);
        $this->assertStringContainsString('3 records', $html);
    }

    // ---- The column filter -------------------------------------------------

    /** Only the five. Everything else is what a row is. */
    public function testTheColumnFilterOffersOnlyTheOptionalColumns(): void
    {
        $html = $this->get('reports')->getBody();

        foreach (array_keys(Reports::OPTIONAL_COLUMNS) as $key) {
            $this->assertStringContainsString('name="column[]" value="' . $key . '"', $html);
        }

        foreach (['mrn', 'name', 'age', 'bloodGroup', 'status'] as $fixed) {
            $this->assertStringNotContainsString('name="column[]" value="' . $fixed . '"', $html);
        }
    }

    public function testAllFiveColumnsAreOnUntilTheyAreSwitchedOff(): void
    {
        $all = $this->get('reports')->getBody();

        foreach (Reports::OPTIONAL_COLUMNS as $heading) {
            $this->assertStringContainsString('<th>' . $heading . '</th>', $all);
        }

        // `applied` is what says the filters were set on purpose; without it an
        // empty list would read as untouched, which is the opposite.
        $none = $this->get('reports?applied=1')->getBody();

        foreach (Reports::OPTIONAL_COLUMNS as $heading) {
            $this->assertStringNotContainsString('<th>' . $heading . '</th>', $none);
        }

        // What is left is the report still being readable.
        $this->assertStringContainsString('<th>MRN</th>', $none);
        $this->assertStringContainsString('<th>Status</th>', $none);
    }

    public function testAColumnIsKeptOnlyWhereTheRecordTypeHasIt(): void
    {
        $html = $this->get('reports?applied=1&type[]=donor&column[]=entryDate&column[]=crossmatchDate')->getBody();

        $this->assertStringContainsString('<th>Date of Crossmatch</th>', $html);
        // Asked for, but a donor's set has no such column to switch on.
        $this->assertStringNotContainsString('<th>Entry Date</th>', $html);
    }

    // ---- Nothing found -----------------------------------------------------

    /** A sentence, and no offer to undo the filters that are still on screen. */
    public function testAnEmptyReportExplainsItselfAndOffersNoWayBack(): void
    {
        $html = $this->get('reports?organ[]=liver')->getBody();

        $this->assertStringContainsString('No records match these filters.', $html);
        $this->assertStringContainsString('0 records', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    // ---- The exports -------------------------------------------------------

    /** Both flavours are offered, and both carry the filters with them. */
    public function testTheExportsCarryTheFiltersInTheirAddresses(): void
    {
        $html = $this->get('reports?type[]=donor')->getBody();

        $this->assertStringContainsString(site_url('reports/export/general') . '?type%5B0%5D=donor', $html);
        $this->assertStringContainsString(site_url('reports/export/internal') . '?type%5B0%5D=donor', $html);
        $this->assertStringContainsString('Export general record', $html);
        $this->assertStringContainsString('Export internal record', $html);
    }

    public function testTheGeneralExportIsTheFilteredTableAndNothingMore(): void
    {
        $html = $this->get('reports/export/general?status[]=on_hold')->getBody();

        $this->assertStringContainsString('Report &mdash; ', $html);
        $this->assertStringContainsString('Badr Report', $html);
        $this->assertStringNotContainsString('Amal Report', $html);
        $this->assertStringNotContainsString('Ziad Report', $html);
        // No records opened out — that is the other export.
        $this->assertStringNotContainsString('sheet-record', $html);
    }

    public function testTheInternalExportOpensEachFilteredRowOut(): void
    {
        $html = $this->get('reports/export/internal?status[]=on_hold')->getBody();

        $this->assertStringContainsString('Internal Report &mdash; ', $html);
        $this->assertStringContainsString('sheet-record', $html);
        $this->assertStringContainsString('Recipient Details', $html);
        $this->assertStringContainsString('Required Lab Tests', $html);
        // Still only the rows the filters chose.
        $this->assertStringContainsString('Badr Report', $html);
        $this->assertStringNotContainsString('Amal Report', $html);
    }

    /** Anything that is not `internal` is the plain sheet, not an error. */
    public function testAnUnknownExportKindIsTheGeneralOne(): void
    {
        $html = $this->get('reports/export/nonsense')->getBody();

        $this->assertStringContainsString('Report &mdash; ', $html);
        $this->assertStringNotContainsString('sheet-record', $html);
    }

    // ---- The way in --------------------------------------------------------

    public function testTheSidebarOffersReports(): void
    {
        $html = $this->get('reports')->getBody();

        $this->assertStringContainsString('class="nav-item is-active" href="' . site_url('reports') . '"', $html);
    }
}
