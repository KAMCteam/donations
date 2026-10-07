<?php

namespace App\Controllers;

use App\Libraries\RecordBlocks;
use App\Libraries\UiStore;
use App\Models\LabModel;
use App\Models\ReportModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Reports: the register read across, under whatever the filters say.
 *
 * The other screens each answer one question — who is waiting, who is free,
 * which pairs there are. This one answers whatever is asked of it: nine
 * filters over both registers at once, and two ways of taking the answer away.
 *
 * Every filter is a list of checkboxes and every list starts empty, which
 * means "all" — an untouched filter narrows nothing, so the query does not
 * mention it. That is one rule for all nine rather than a default per filter.
 *
 * Which columns the table has is the record type's to decide, not the user's:
 * a donor has no entry date and a recipient has no donor type. The column
 * filter chooses among the five that a report might or might not want, and
 * those only where the chosen record type has them at all.
 */
class Reports extends BaseController
{
    /** @var list<string> */
    protected $helpers = ['url', 'form', 'auth', 'lang', 'ui'];

    /**
     * The columns each record type shows, in the order the report asks for.
     *
     * A column is [key, heading]. The headings differ by type where the same
     * value is called something else — a recipient's MRN and a donor's — which
     * is why this is three lists rather than one with exceptions.
     */
    public const COLUMNS = [
        'recipient' => [
            ['mrn', 'Recipient MRN'], ['name', 'Recipient Name'], ['age', 'Age'],
            ['bloodGroup', 'Blood Group'], ['mrp', 'MRP'], ['gender', 'Gender'],
            ['phone', 'Phone Number'], ['dialysisType', 'Type Dialysis'],
            ['firstDialysis', 'First Dialysis'], ['entryDate', 'Entry Date'],
            ['related', 'Related Donor'], ['relationship', 'Relationship'],
            ['status', 'Status'], ['crossmatchDate', 'Date of Crossmatch'],
        ],
        'donor' => [
            ['mrn', 'Donor MRN'], ['name', 'Donor Name'], ['age', 'Age'],
            ['bloodGroup', 'Blood Group'], ['mrp', 'MRP'], ['gender', 'Gender'],
            ['phone', 'Phone Number'], ['donationType', 'Donor Type'],
            ['related', 'Related Recipient'], ['status', 'Status'],
            ['crossmatchDate', 'Date of Crossmatch'],
        ],
        'all' => [
            // A mixed table is the one place a row does not say what it is:
            // the recipient set and the donor set each name it in their MRN
            // and Name headings, and this set cannot. So it says so outright,
            // and always — nobody reading both registers at once can do
            // without it, which is why it is not among the optional five.
            ['mrn', 'MRN'], ['name', 'Name'], ['age', 'Age'], ['recordType', 'Type'],
            ['bloodGroup', 'Blood Group'], ['mrp', 'MRP'], ['gender', 'Gender'],
            ['phone', 'Phone Number'], ['dialysisType', 'Type Dialysis'],
            ['firstDialysis', 'First Dialysis'], ['entryDate', 'Entry Date'],
            ['related', 'Related Donor/Recipient'], ['relationship', 'Relationship'],
            ['status', 'Status'], ['crossmatchDate', 'Date of Crossmatch'],
        ],
    ];

    /**
     * The only columns the column filter offers.
     *
     * Everything else is what a row is — a number, a name, a blood group —
     * and turning those off would leave a table nobody could read. These five
     * are the ones a report may or may not be about.
     */
    public const OPTIONAL_COLUMNS = [
        'dialysisType'   => 'Type Dialysis',
        'firstDialysis'  => 'First Dialysis',
        'entryDate'      => 'Entry Date',
        'relationship'   => 'Relationship',
        'crossmatchDate' => 'Date of Crossmatch',
    ];

    private UiStore $store;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $this->store = new UiStore($this->session);
    }

    public function index(): string
    {
        [$filters, $rows, $columns] = $this->report();

        return view('ui/reports', [
            'title'    => 'Reports',
            'searchQuery'  => $filters['search'],
            'searchPlaceholder' => 'Search these results by MRN or name',
            'navPage'  => 'reports',
            'organ'    => $this->store->organ(),
            'filters'  => $filters,
            'choices'  => $this->choices(),
            'rows'     => $rows,
            'columns'  => $columns,
            'query'    => $this->queryString($filters),
        ]);
    }

    /**
     * The report on paper.
     *
     * `general` is the table as it stands — the same rows, the same columns,
     * nothing the screen was not already showing. `internal` is each of those
     * rows opened out: the whole record and the whole workup. Both take the
     * rows the filters chose and no others, which is the point of exporting
     * from here rather than from the register.
     */
    public function export(string $kind = 'general'): string
    {
        [$filters, $rows, $columns] = $this->report();
        $internal                   = $kind === 'internal';

        return view('ui/reports_print', [
            'kind'      => $internal ? 'internal' : 'general',
            'rows'      => $rows,
            'columns'   => $columns,
            'records'   => $internal ? $this->records($rows) : [],
            'organLabel' => $this->store->organLabel(),
            'summary'   => $this->summary($filters),
            'printedOn' => date('d/m/Y'),
            'backUrl'   => site_url('reports') . $this->queryString($filters),
        ]);
    }

    /**
     * The filters, the rows they select, and the columns to show them in.
     *
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: list<array{0: string, 1: string}>}
     */
    private function report(): array
    {
        $filters = $this->filters();
        $rows    = model(ReportModel::class)->rows($filters);

        return [$filters, $rows, $this->columns($filters)];
    }

    /**
     * What the address is asking for.
     *
     * Every list defaults to empty, and empty means all. The dates are the one
     * filter that is not a list, because a range is two ends rather than a
     * choice among values.
     *
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        $list = fn (string $key): array => array_values(array_filter(
            (array) ($this->request->getGet($key) ?? []),
            static fn ($v): bool => is_string($v) && $v !== ''
        ));

        // Was this address written by the form, or is it the screen opening?
        // Several filters have a default that is not "all", and the only way
        // to tell an untouched one from one somebody emptied is whether the
        // form put its own marker in the address.
        $applied = $this->request->getGet('applied') !== null;

        $organs = $list('organ');

        return [
            'types'        => array_values(array_intersect($list('type'), ['recipient', 'donor'])),
            // The programme the session is in, until somebody says otherwise.
            // A report is read inside a programme — the sidebar, the lists and
            // the dashboard are all that programme's — so opening Reports on
            // every organ at once answered a question nobody had asked.
            'organs'       => $organs !== [] || $applied ? $organs : [$this->store->organ()],
            'groups'       => array_values(array_intersect($list('group'), UiStore::BLOOD_TYPES)),
            'statuses'     => array_values(array_intersect($list('status'), array_keys(UiStore::STATUS_OPTIONS))),
            // In a pair, not in a pair, or not asked. '' is the default and
            // the third answer: a report about everybody.
            'paired'       => in_array((string) ($this->request->getGet('paired') ?? ''), ['yes', 'no'], true)
                ? (string) $this->request->getGet('paired')
                : '',
            // A test is offered by name and carries every id that name has —
            // one per sheet, one per programme — so a choice arrives as a
            // comma-joined list and is flattened here.
            'labs'         => array_values(array_unique(array_map(
                'intval',
                array_merge(...array_map(static fn (string $v): array => explode(',', $v), $list('lab')) ?: [[]])
            ))),
            // Which question the chosen tests are being asked: completed, or
            // still outstanding. One or the other and never both — a record
            // cannot have both completed and not completed the same test, so
            // offering the two lists side by side would be offering an empty
            // report.
            'labMode'      => (string) ($this->request->getGet('labMode') ?? '') === 'missing' ? 'missing' : 'done',
            'from'         => $this->date((string) ($this->request->getGet('from') ?? '')),
            'to'           => $this->date((string) ($this->request->getGet('to') ?? '')),
            'mrps'         => array_map('intval', $list('mrp')),
            'coordinators' => array_map('intval', $list('coordinator')),
            'columns'      => array_values(array_intersect($list('column'), array_keys(self::OPTIONAL_COLUMNS))),
            // Nothing chosen is every column chosen, as everywhere else here.
            'columnsTouched' => $applied,
            // The box above the report, which narrows what the filters chose
            // rather than being a tenth filter of its own.
            'search'         => trim((string) ($this->request->getGet('q') ?? '')),
        ];
    }

    /** DD/MM/YYYY off the screen, ISO for the query, '' when it is neither. */
    private function date(string $value): string
    {
        $iso = UiStore::dmyToIso($value);

        return $iso === '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : $iso;
    }

    /**
     * The columns this report shows.
     *
     * The record type decides the set; the column filter may switch off the
     * five it is allowed to, and only where the set has them. A donor's set
     * has no dialysis and no entry date, so those stay off whatever the filter
     * says — there is nothing to show.
     *
     * @param array<string, mixed> $filters
     *
     * @return list<array{0: string, 1: string}>
     */
    private function columns(array $filters): array
    {
        $types = $filters['types'];
        $set   = count($types) === 1 ? self::COLUMNS[$types[0]] : self::COLUMNS['all'];

        // Untouched, every optional column is on.
        $wanted = $filters['columnsTouched'] ? $filters['columns'] : array_keys(self::OPTIONAL_COLUMNS);

        return array_values(array_filter(
            $set,
            static fn (array $column): bool => ! isset(self::OPTIONAL_COLUMNS[$column[0]])
                || in_array($column[0], $wanted, true)
        ));
    }

    /**
     * Everything the filters can be set to.
     *
     * @return array<string, mixed>
     */
    private function choices(): array
    {
        return [
            'types'        => ['recipient' => 'Recipient', 'donor' => 'Donor'],
            'organs'       => $this->store->organs(),
            'groups'       => UiStore::BLOOD_TYPES,
            // Every word the platform uses, a pair's as well as a person's.
            // The four a record holds are a pair's first four as well, and the
            // other two are only ever a pair's — so the filter asks the
            // question of both: a record matches when its own word is asked
            // for, or when an open pair holding it wears one that is. It
            // offered the person's three alone before, which made a report
            // about the cases in a paired exchange impossible to ask for.
            'statuses'     => UiStore::PAIR_STATUS_OPTIONS,
            'paired'       => ['yes' => 'In a pair', 'no' => 'Not in a pair'],
            'labs'         => model(LabModel::class)->named(),
            'labModes'     => ['done' => 'Completed', 'missing' => 'Not completed'],
            'mrps'         => $this->store->mrps(),
            'coordinators' => $this->store->coordinators(),
            'columns'      => self::OPTIONAL_COLUMNS,
        ];
    }

    /**
     * The filtered rows opened out into printable records.
     *
     * The blocks are the same ones a single record's own sheet is made of —
     * `RecordBlocks` decides what a recipient and a donor consist of on paper,
     * so the bulk sheet and the single one cannot drift apart. A row whose
     * record has since gone is left out rather than printed empty.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{title: string, subtitle: string, blocks: list<array<string, mixed>>}>
     */
    private function records(array $rows): array
    {
        $blocks  = new RecordBlocks($this->store);
        $records = [];

        foreach ($rows as $row) {
            $recipient = $row['type'] === 'recipient';
            $person    = $recipient
                ? $this->store->findRecipient($row['mrn'])
                : $this->store->findDonor($row['mrn']);

            if ($person === null) {
                continue;
            }

            if ($recipient) {
                $own = [$blocks->recipient($person)];

                // Every donor they have been linked with, the declined ones
                // too: one who was considered and set aside is part of the
                // record.
                if (($person['donors'] ?? []) !== []) {
                    $own[] = $blocks->donors($person['donors']);
                }
            } else {
                $own = [$blocks->donor($person, $this->store->findRecipient($person['pairedRecipientId'] ?? null))];
            }

            $records[] = [
                'title'    => (string) ($person['name'] ?? ''),
                'subtitle' => ($recipient ? 'Recipient' : 'Donor') . ' · MRN ' . $row['mrn'],
                'blocks'   => array_merge($own, $blocks->workup($person, 'Required Lab Tests', 'Clinical Notes')),
            ];
        }

        return $records;
    }

    /**
     * What was asked for, in words, for the line under the sheet's title.
     *
     * @param array<string, mixed> $filters
     */
    private function summary(array $filters): string
    {
        $choices = $this->choices();
        $applied = [];

        $named = static function (array $keys, array $from): string {
            return implode(', ', array_map(static fn ($k): string => (string) ($from[$k] ?? $k), $keys));
        };

        if ($filters['types'] !== []) {
            $applied[] = $named($filters['types'], $choices['types']);
        }

        if ($filters['organs'] !== []) {
            $applied[] = $named($filters['organs'], array_column($choices['organs'], 'label', 'code'));
        }

        if ($filters['groups'] !== []) {
            $applied[] = 'Blood ' . implode(', ', $filters['groups']);
        }

        if ($filters['statuses'] !== []) {
            $applied[] = $named($filters['statuses'], $choices['statuses']);
        }

        if ($filters['paired'] !== '') {
            $applied[] = (string) $choices['paired'][$filters['paired']];
        }

        if ($filters['labs'] !== []) {
            $applied[] = ui_plural(count($filters['labs']), 'test')
                . ($filters['labMode'] === 'missing' ? ' not completed' : ' completed');
        }

        if ($filters['from'] !== '' || $filters['to'] !== '') {
            $applied[] = 'Entered '
                . ($filters['from'] === '' ? 'up to' : UiStore::isoToDMY($filters['from']))
                . ($filters['from'] !== '' && $filters['to'] !== '' ? ' to ' : ' ')
                . ($filters['to'] === '' ? 'onwards' : UiStore::isoToDMY($filters['to']));
        }

        if ($filters['search'] !== '') {
            $applied[] = 'Matching “' . $filters['search'] . '”';
        }

        if ($filters['mrps'] !== []) {
            $applied[] = $named(array_map('strval', $filters['mrps']), array_column($choices['mrps'], 'name', 'id'));
        }

        if ($filters['coordinators'] !== []) {
            $applied[] = $named(array_map('strval', $filters['coordinators']), array_column($choices['coordinators'], 'name', 'id'));
        }

        return $applied === [] ? 'No filters applied' : implode(' · ', $applied);
    }

    /**
     * The filters as a query string, so an export and a Back go on showing the
     * same report.
     *
     * @param array<string, mixed> $filters
     */
    private function queryString(array $filters): string
    {
        $query = [];

        foreach ([
            'type' => 'types', 'organ' => 'organs', 'group' => 'groups', 'status' => 'statuses',
            'lab' => 'labs', 'mrp' => 'mrps', 'coordinator' => 'coordinators', 'column' => 'columns',
        ] as $param => $key) {
            if ($filters[$key] !== []) {
                $query[$param] = $filters[$key];
            }
        }

        foreach (['from', 'to', 'search'] as $end) {
            if ($filters[$end] !== '') {
                $query[$end === 'search' ? 'q' : $end] = $filters[$end];
            }
        }

        if ($filters['paired'] !== '') {
            $query['paired'] = $filters['paired'];
        }

        // Only where it changes the reading: the mode is about the tests
        // chosen, and with none chosen it is about nothing.
        if ($filters['labs'] !== [] && $filters['labMode'] !== 'done') {
            $query['labMode'] = $filters['labMode'];
        }

        // `applied` is only worth carrying where it changes the reading, and
        // it changes it in two places. With every optional column chosen,
        // touched and untouched mean the same thing. And an empty organ list
        // means every programme only if somebody emptied it — untouched, it
        // means the one the session is in — so the marker is what tells an
        // address asking for all of them from one asking for nothing in
        // particular.
        if (($filters['columnsTouched'] && count($filters['columns']) < count(self::OPTIONAL_COLUMNS))
            || $filters['organs'] === []) {
            $query['applied'] = '1';
        }

        return $query === [] ? '' : '?' . http_build_query($query);
    }
}
