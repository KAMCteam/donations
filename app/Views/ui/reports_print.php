<?php

use App\Libraries\UiStore;

/**
 * A report as a printed page, and as a PDF.
 *
 * Two documents from one view, because they are two depths of the same report
 * rather than two reports. `general` is the table as the screen has it — the
 * rows the filters chose, in the columns the filters left on. `internal` is
 * that same table followed by each of its rows opened out: the whole record and
 * the whole workup, one per page, built from the blocks a single record's own
 * sheet uses.
 *
 * Neither prints anything the filters excluded. That is the point of exporting
 * from Reports rather than from a register.
 *
 * A PDF by printing, as every other sheet here is: the browser does Arabic
 * joining and direction itself, and nothing has to be installed on the machine
 * this runs on.
 *
 * @var string                            $kind        general | internal
 * @var list<array<string, mixed>>        $rows
 * @var list<array{0: string, 1: string}> $columns
 * @var list<array{title: string, subtitle: string, blocks: list<array<string, mixed>>}> $records
 * @var string                            $organLabel
 * @var string                            $summary     What was asked for, in words
 * @var string                            $printedOn   DD/MM/YYYY
 * @var string                            $backUrl
 */
$dash     = '—';
$internal = $kind === 'internal';
$title    = $internal ? 'Internal Report' : 'Report';

/** One cell's text, formatted as the screen formats it. */
$cell = static function (array $row, string $key): string {
    return match ($key) {
        'related'        => (string) $row['related'],
        'status'         => UiStore::STATUS_OPTIONS[$row['status']] ?? (string) $row['status'],
        'donationType'   => UiStore::DONATION_TYPES[$row['donationType']] ?? (string) $row['donationType'],
        'entryDate', 'firstDialysis', 'crossmatchDate' => $row[$key] === '' ? '' : UiStore::isoToDMY((string) $row[$key]),
        default          => (string) ($row[$key] ?? ''),
    };
};

$mono = ['mrn', 'bloodGroup', 'phone', 'entryDate', 'firstDialysis', 'crossmatchDate'];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($title) ?> &mdash; <?= esc($organLabel) ?></title>

    <link rel="icon" type="image/x-icon" href="<?= base_url('assets/img/KAMC.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/sheet.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/print.css') ?>">
    <?php // The detailed half is made of record blocks, so it needs the
          // stylesheet that draws them. ?>
    <?php if ($internal): ?>
        <link rel="stylesheet" href="<?= base_url('assets/ui/css/record-print.css') ?>">
    <?php endif; ?>
    <?php // Last either way: it is what settles the two halves against each
          // other, and it has the report's own table to set up besides. ?>
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/report-print.css') ?>">
</head>

<body>
    <?php // On screen only: paper has no use for a button that reprints it. ?>
    <div class="print-bar">
        <a class="print-bar-back" href="<?= esc($backUrl) ?>">&larr; Back to Reports</a>
        <button type="button" class="print-bar-print" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <header class="sheet-head">
        <img class="sheet-logo" src="<?= base_url('assets/ui/img/kamc.svg') ?>" alt="King Abdullah Medical City">
        <div class="sheet-titles">
            <h1 class="sheet-title"><?= esc($title) ?> &mdash; <?= esc($organLabel) ?> Programme</h1>
            <p class="sheet-meta">
                <?= esc(ui_plural(count($rows), 'record')) ?>
                &middot; <?= esc($summary) ?>
                &middot; Printed <?= esc($printedOn) ?>
            </p>
        </div>
    </header>

    <?php if ($rows === []): ?>
        <p class="sheet-empty">No records match these filters.</p>
    <?php else: ?>
        <table class="sheet-table">
            <thead>
                <tr>
                    <?php foreach ($columns as [$key, $heading]): ?>
                        <th><?= esc($heading) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($columns as [$key, $heading]): ?>
                            <?php $value = $cell($row, $key); ?>
                            <td class="<?= $key === 'name' ? 'c-name' : (in_array($key, $mono, true) ? 'c-mono' : '') ?>"><?= esc($value !== '' ? $value : $dash) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($internal): ?>
            <?php // Each row in full, one record per page, so a record is never
                  // split across a break and the table above stays a contents
                  // page for what follows. ?>
            <?php foreach ($records as $record): ?>
                <section class="sheet-record">
                    <h2 class="sheet-record-title">
                        <?= esc($record['title']) ?>
                        <small><?= esc($record['subtitle']) ?></small>
                    </h2>
                    <?= view('ui/partials/record_blocks', ['blocks' => $record['blocks']], ['saveData' => false]) ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <footer class="sheet-foot">
        King Abdullah Medical City &mdash; National Transplant Registry. Confidential patient information.
    </footer>

    <?php // Straight to the dialog, so the link behaves like a download did.
          // Wrapped in onload: Chrome prints a blank sheet if the logo has not
          // arrived yet. ?>
    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
</body>

</html>
