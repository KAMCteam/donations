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
 * Nine filters, each a list of checkboxes inside a dropdown, each starting
 * empty — and empty means all, so an untouched filter narrows nothing. They
 * are one GET form: the report is in the address, which is what makes it
 * shareable, bookmarkable, and the same thing the two exports read.
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

// The lab filter's values are comma-joined id lists, so what is "selected" is
// matched on the whole list rather than on one id.
$labOptions  = [];
$labSelected = [];

foreach ($choices['labs'] as $lab) {
    $labOptions[$lab['ids']] = $lab['name'];

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

        <?php $menu('Record type', 'type', $choices['types'], $filters['types']); ?>
        <?php $menu('Organ', 'organ', array_column($choices['organs'], 'label', 'code'), $filters['organs']); ?>
        <?php $menu('Blood group', 'group', array_combine($choices['groups'], $choices['groups']), $filters['groups']); ?>
        <?php $menu('Status', 'status', $choices['statuses'], $filters['statuses']); ?>
        <?php $menu('Labs', 'lab', $labOptions, $labSelected); ?>

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
    </form>

    <div class="card card--scroll">
        <?php if ($rows === []): ?>
            <div class="empty-state">No records match these filters.</div>
        <?php else: ?>
            <table class="table list-table report-table">
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
