<?php

/**
 * A register as a printed page, and as a PDF.
 *
 * One view for both lists, because the Recipient Waitlist and the Donors List
 * are the same kind of document — a letterhead, the filters that were on, and
 * the rows as the screen had them — differing only in their columns. The
 * caller hands those over as plain text, already formatted, so nothing here
 * has to know what a score or a workup is.
 *
 * A PDF by printing, as every other sheet here is: the browser does Arabic
 * joining and direction itself, and nothing has to be installed on the machine
 * this runs on.
 *
 * @var string                      $sheetTitle  e.g. "Recipient Waitlist"
 * @var string                      $organLabel
 * @var string                      $count       "12 unmatched recipients"
 * @var string                      $filters     What was narrowed to, in words
 * @var string                      $printedOn   DD/MM/YYYY
 * @var list<string>                $headers
 * @var list<list<array{0: string, 1: string}>> $rows  Each cell: text, class
 * @var string                      $empty       What to say when there are none
 * @var string                      $backUrl
 * @var string                      $backLabel
 */
$dash = '—';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($sheetTitle) ?> &mdash; <?= esc($organLabel) ?></title>

    <link rel="icon" type="image/x-icon" href="<?= base_url('assets/img/KAMC.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/sheet.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/print.css') ?>">
</head>

<body>
    <?php // On screen only: paper has no use for a button that reprints it. ?>
    <div class="print-bar">
        <a class="print-bar-back" href="<?= esc($backUrl) ?>">&larr; <?= esc($backLabel) ?></a>
        <button type="button" class="print-bar-print" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <header class="sheet-head">
        <img class="sheet-logo" src="<?= base_url('assets/ui/img/kamc.svg') ?>" alt="King Abdullah Medical City">
        <div class="sheet-titles">
            <h1 class="sheet-title"><?= esc($sheetTitle) ?> &mdash; <?= esc($organLabel) ?> Programme</h1>
            <p class="sheet-meta">
                <?= esc($count) ?>
                &middot; <?= esc($filters) ?>
                &middot; Printed <?= esc($printedOn) ?>
            </p>
        </div>
    </header>

    <?php if ($rows === []): ?>
        <p class="sheet-empty"><?= esc($empty) ?></p>
    <?php else: ?>
        <table class="sheet-table">
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?= esc($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as [$value, $class]): ?>
                            <td class="<?= esc($class) ?>"><?= esc($value !== '' ? $value : $dash) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
