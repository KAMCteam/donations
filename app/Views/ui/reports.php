<?= $this->extend('ui/layout') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/ui/css/reports.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php

use App\Controllers\Reports;
use App\Libraries\UiStore;

/**
 * Reports: the register read across, under whatever the filters say.
 *
 * Eleven filters, most of them a list of checkboxes inside a dropdown, and
 * most of them starting empty — empty means all, so an untouched filter
 * narrows nothing. They are one GET form: the report is in the address, which
 * is what makes it shareable, bookmarkable, and the same thing the two exports
 * read.
 *
 * **Record type is not one of the dropdowns.** It decides which columns the
 * table has — a donor has no entry date, a recipient has no donor type — so it
 * is not a filter among filters but the thing the rest are read inside. It
 * sits on its own line above them, as three words to choose between.
 *
 * Two others have a default that is not "all". **Organ** opens on the
 * programme the session is in, because a report is read inside a programme
 * like every other screen. **Labs** asks its tests as Completed, which is the
 * question somebody opening it has.
 *
 * The dropdowns are `<details>`, so they open and close without scripting.
 *
 * @var array<string, mixed>              $filters
 * @var array<string, mixed>              $choices
 * @var list<array<string, mixed>>        $rows
 * @var list<array{0: string, 1: string}> $columns
 * @var string                            $query
 */
$dash = '—';

/**
 * How many of a filter's values are chosen, for the count on its button.
 *
 * Nothing chosen and everything chosen both read "All", because they select
 * the same records — which is what the number is there to tell you.
 */
$chosen = static fn (array $values, int $total): string => $values === [] || count($values) >= $total
    ? 'All'
    : (string) count($values);

/** One dropdown: a summary that opens it, and the checkboxes inside. */
$menu = static function (string $label, string $name, array $options, array $selected) use ($chosen): void { ?>
    <details class="filter">
        <summary class="filter-button">
            <span class="filter-title"><?= esc($label) ?></span>
            <?php $count = $chosen($selected, count($options)); ?>
            <span class="filter-count<?= $count === 'All' ? '' : ' is-set' ?>"><?= esc($count) ?></span>
        </summary>
        <div class="filter-menu">
            <?php foreach ($options as $value => $text): ?>
                <label class="filter-option">
                    <input type="checkbox" name="<?= esc($name) ?>[]" value="<?= esc((string) $value) ?>"
                           <?= in_array((string) $value, array_map('strval', $selected), true) ? 'checked' : '' ?>>
                    <span><?= esc($text) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </details>
<?php };

/** The same dropdown for a question with one answer: radios, not checkboxes. */
$pick = static function (string $label, string $name, array $options, string $chosenValue, string $allLabel): void { ?>
    <details class="filter">
        <summary class="filter-button">
            <span class="filter-title"><?= esc($label) ?></span>
            <span class="filter-count<?= $chosenValue === '' ? '' : ' is-set' ?>"><?= esc($chosenValue === '' ? 'All' : $options[$chosenValue]) ?></span>
        </summary>
        <div class="filter-menu">
            <?php // The empty answer is an option like the other two, so it
                  // can be chosen back: a radio row with no way to unset it
                  // is a filter somebody is stuck inside. ?>
            <label class="filter-option">
                <input type="radio" name="<?= esc($name) ?>" value=""<?= $chosenValue === '' ? ' checked' : '' ?>>
                <span><?= esc($allLabel) ?></span>
            </label>
            <?php foreach ($options as $value => $text): ?>
                <label class="filter-option">
                    <input type="radio" name="<?= esc($name) ?>" value="<?= esc((string) $value) ?>"<?= $chosenValue === (string) $value ? ' checked' : '' ?>>
                    <span><?= esc($text) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </details>
<?php };

// The lab filter's values are comma-joined id lists, so what is "selected" is
// matched on the whole list rather than on one id — and the list is kept in
// the headings the workup lists it under, because a hundred test names in one
// alphabetical column is a list nobody finds anything in.
$labGroups   = [];
$labSelected = [];

foreach ($choices['labs'] as $lab) {
    $labGroups[$lab['group']][$lab['ids']] = $lab['name'];

    if (array_intersect(array_map('intval', explode(',', $lab['ids'])), $filters['labs']) !== []) {
        $labSelected[] = $lab['ids'];
    }
}
?>
<div class="page">
    <div class="page-header page-header--center page-header--wrap">
        <div>
            <div class="eyebrow">Reports</div>
            <h1 class="page-title">Reports</h1>
            <p class="page-subtitle"><?= esc(ui_plural(count($rows), 'record')) ?></p>
        </div>
        <div class="header-actions">
            <?php // Two ways of taking the same rows away. A menu rather than
                  // two buttons: they are one action with a choice in it. ?>
            <details class="export">
                <summary class="btn-primary export-button"><?= ui_icon('printer') ?>Export PDF</summary>
                <div class="export-menu">
                    <a class="export-option" href="<?= site_url('reports/export/general') . $query ?>" target="_blank" rel="noopener">
                        <span class="export-option-name">Export general record</span>
                        <span class="export-option-note">The table as it stands, with the columns showing now.</span>
                    </a>
                    <a class="export-option" href="<?= site_url('reports/export/internal') . $query ?>" target="_blank" rel="noopener">
                        <span class="export-option-name">Export internal record</span>
                        <span class="export-option-note">Each row opened out: the whole record and its workup.</span>
                    </a>
                </div>
            </details>
        </div>
    </div>

    <form class="report-filters" method="get" action="<?= site_url('reports') ?>">
        <?php // Says the filters were set on purpose, so an empty column list
              // reads as "none of them" rather than as untouched. ?>
        <input type="hidden" name="applied" value="1">

        <?php // Record type, on its own line above the rest and as words
              // rather than a dropdown: it is what the table's columns are
              // decided by, so the report is read differently depending on it
              // and it should not have to be opened to be seen.
              //
              // One answer, where the others take several: both types at once
              // is the mixed table, which is what All already means, so a
              // third tick would have been a third way of saying it. ?>
        <div class="report-type" role="group" aria-label="Record type">
            <span class="report-type-label">Record type</span>
            <?php $type = count($filters['types']) === 1 ? $filters['types'][0] : ''; ?>
            <label class="report-type-option<?= $type === '' ? ' is-chosen' : '' ?>">
                <input type="radio" name="type[]" value=""<?= $type === '' ? ' checked' : '' ?>>
                <span>All records</span>
            </label>
            <?php foreach ($choices['types'] as $value => $text): ?>
                <label class="report-type-option<?= $type === (string) $value ? ' is-chosen' : '' ?>">
                    <input type="radio" name="type[]" value="<?= esc((string) $value) ?>"<?= $type === (string) $value ? ' checked' : '' ?>>
                    <span><?= esc($text) ?>s</span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="report-filter-row">
        <?php $menu('Organ', 'organ', array_column($choices['organs'], 'label', 'code'), $filters['organs']); ?>
        <?php $menu('Blood group', 'group', array_combine($choices['groups'], $choices['groups']), $filters['groups']); ?>
        <?php $menu('Status', 'status', $choices['statuses'], $filters['statuses']); ?>
        <?php $pick('In a pair?', 'paired', $choices['paired'], $filters['paired'], 'All'); ?>

        <?php // The tests, in the headings the workup lists them under, and
              // asked as one of two questions. Which question is at the top,
              // because it is what the ticks below it mean — and it is one or
              // the other: a record cannot have both completed and not
              // completed the same test. ?>
        <details class="filter">
            <summary class="filter-button">
                <span class="filter-title">Labs</span>
                <?php $labCount = $chosen($labSelected, count($choices['labs'])); ?>
                <span class="filter-count<?= $labCount === 'All' ? '' : ' is-set' ?>"><?= esc($labCount) ?></span>
            </summary>
            <div class="filter-menu filter-menu--labs">
                <div class="filter-modes">
                    <?php foreach ($choices['labModes'] as $value => $text): ?>
                        <label class="filter-mode<?= $filters['labMode'] === $value ? ' is-chosen' : '' ?>">
                            <input type="radio" name="labMode" value="<?= esc($value) ?>"<?= $filters['labMode'] === $value ? ' checked' : '' ?>>
                            <span><?= esc($text) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="filter-modes-hint">Tick the tests to ask about. The report keeps the records that have <?= $filters['labMode'] === 'missing' ? 'not completed' : 'completed' ?> any of them.</p>

                <?php foreach ($labGroups as $groupName => $groupLabs): ?>
                    <?php if ((string) $groupName !== ''): ?>
                        <h4 class="filter-group-name"><?= esc($groupName) ?></h4>
                    <?php endif; ?>
                    <?php foreach ($groupLabs as $value => $text): ?>
                        <label class="filter-option">
                            <input type="checkbox" name="lab[]" value="<?= esc((string) $value) ?>"
                                   <?= in_array((string) $value, $labSelected, true) ? 'checked' : '' ?>>
                            <span><?= esc($text) ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </details>

        <details class="filter">
            <summary class="filter-button">
                <span class="filter-title">Date</span>
                <span class="filter-count<?= $filters['from'] === '' && $filters['to'] === '' ? '' : ' is-set' ?>"><?= $filters['from'] === '' && $filters['to'] === '' ? 'All' : 'Set' ?></span>
            </summary>
            <div class="filter-menu filter-menu--dates">
                <label class="filter-date">
                    <span>From</span>
                    <?= view('ui/partials/date_field', ['id' => 'report-from', 'name' => 'from', 'value' => $filters['from'] === '' ? '' : UiStore::isoToDMY($filters['from'])], ['saveData' => false]) ?>
                </label>
                <label class="filter-date">
                    <span>To</span>
                    <?= view('ui/partials/date_field', ['id' => 'report-to', 'name' => 'to', 'value' => $filters['to'] === '' ? '' : UiStore::isoToDMY($filters['to'])], ['saveData' => false]) ?>
                </label>
            </div>
        </details>

        <?php $menu('Doctor (MRP)', 'mrp', array_column($choices['mrps'], 'name', 'id'), $filters['mrps']); ?>
        <?php $menu('Coordinator', 'coordinator', array_column($choices['coordinators'], 'name', 'id'), $filters['coordinators']); ?>
        <?php $menu('Columns', 'column', $choices['columns'], $filters['columnsTouched'] ? $filters['columns'] : array_keys(Reports::OPTIONAL_COLUMNS)); ?>

        <button type="submit" class="btn-primary report-apply">Apply filters</button>
        <?php if ($query !== ''): ?>
            <a class="stat-link" href="<?= site_url('reports') ?>">Clear</a>
        <?php endif; ?>
        </div>
    </form>

    <div class="card card--scroll">
        <?php if ($rows === []): ?>
            <div class="empty-state">No records match these filters.</div>
        <?php else: ?>
            <?php
            // The table is laid out rather than left to find its own width:
            // fifteen columns at their widest is wider than any screen, and a
            // report somebody has to pan sideways through is a report they
            // read half of. Each column is given a share of the width here, so
            // what is long gets room and what is four characters wide does not
            // take any — and whichever subset the Columns filter leaves, the
            // shares still add up to the whole table.
            // Wide enough for the longest word in the heading as well as for
            // what is under it: a column narrower than its own name reads as a
            // mistake however little is in it.
            $share = [
                'mrn' => 7, 'name' => 9, 'age' => 4, 'recordType' => 7,
                'bloodGroup' => 5, 'mrp' => 5, 'gender' => 6, 'phone' => 8,
                'dialysisType' => 7, 'firstDialysis' => 8, 'entryDate' => 8,
                'related' => 11, 'relationship' => 9, 'status' => 8,
                'donationType' => 8, 'crossmatchDate' => 9, 'organ' => 6,
            ];
            $total = 0;

            foreach ($columns as [$key, $heading]) {
                $total += $share[$key] ?? 6;
            }
            ?>
            <table class="table list-table report-table">
                <colgroup>
                    <?php foreach ($columns as [$key, $heading]): ?>
                        <col style="width:<?= round((($share[$key] ?? 6) / max($total, 1)) * 100, 2) ?>%">
                    <?php endforeach; ?>
                </colgroup>
                <thead>
                    <tr>
                        <?php foreach ($columns as [$key, $heading]): ?>
                            <th><?= esc($heading) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $url = site_url(($row['type'] === 'recipient' ? 'recipients/' : 'donors/') . rawurlencode($row['mrn'])); ?>
                        <tr data-href="<?= $url ?>">
                            <?php foreach ($columns as [$key, $heading]): ?>
                                <?php
                                $value = match ($key) {
                                    'mrn'            => $row['mrn'],
                                    'name'           => $row['name'],
                                    'recordType'     => $row['type'] === 'recipient' ? 'recipient' : 'donor',
                                    'related'        => $row['related'],
                                    'status'         => UiStore::STATUS_OPTIONS[$row['status']] ?? $row['status'],
                                    'donationType'   => UiStore::DONATION_TYPES[$row['donationType']] ?? $row['donationType'],
                                    'entryDate'      => $row['entryDate'] === '' ? '' : UiStore::isoToDMY($row['entryDate']),
                                    'firstDialysis'  => $row['firstDialysis'] === '' ? '' : UiStore::isoToDMY($row['firstDialysis']),
                                    'crossmatchDate' => $row['crossmatchDate'] === '' ? '' : UiStore::isoToDMY($row['crossmatchDate']),
                                    default          => (string) ($row[$key] ?? ''),
                                };
                                $mono = in_array($key, ['mrn', 'bloodGroup', 'phone', 'entryDate', 'firstDialysis', 'crossmatchDate'], true);
                                ?>
                                <td class="<?= $key === 'name' ? 'cell-name' : ($mono ? 'mono' : '') ?>">
                                    <?php if ($key === 'name'): ?>
                                        <a href="<?= $url ?>"><?= esc($value) ?></a>
                                    <?php elseif ($key === 'status'): ?>
                                        <span class="badge <?= ui_tone('status', $row['status']) ?>"><?= esc($value) ?></span>
                                    <?php elseif ($key === 'recordType'): ?>
                                        <?php // The same tag, in the same two colours, as the pairs register. ?>
                                        <span class="role-tag <?= $row['type'] === 'recipient' ? 'tone-blue-soft' : 'tone-teal-soft' ?>"><?= esc($value) ?></span>
                                    <?php else: ?>
                                        <?= esc($value !== '' ? $value : $dash) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
