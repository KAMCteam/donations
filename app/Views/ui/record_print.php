<?php

use App\Libraries\UiStore;

/**
 * One record as a printed page, and as a PDF.
 *
 * The same document for a recipient, a donor and a pair: they differ only in
 * which blocks go on it and in what order, so the controller builds that list
 * and this renders it. A pair is simply the pair's own block followed by both
 * people's.
 *
 * A standalone document rather than a screen in the shell, for the reasons the
 * Pairs List sheet gives: nothing here is a card, a chip or a sidebar, and the
 * browser's own print dialog is what makes the PDF — it is the one thing on a
 * plain XAMPP that renders an Arabic name with its joining and its direction
 * intact, and there is nothing to install.
 *
 * @var string                     $sheetTitle  e.g. "Recipient Record"
 * @var string                     $subject     Whose record it is, for the <title>
 * @var list<string>               $meta        MRN, programme, printed on …
 * @var list<array<string, mixed>> $blocks      What goes on the sheet, in order
 * @var bool                       $labsSummary Workups as group headings only
 * @var string                     $backUrl
 * @var string                     $backLabel
 */
$dash = '—';
$labsSummary ??= false;
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($sheetTitle) ?> &mdash; <?= esc($subject) ?></title>

    <link rel="icon" type="image/x-icon" href="<?= base_url('assets/img/KAMC.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/sheet.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/record-print.css') ?>">
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
            <h1 class="sheet-title"><?= esc($sheetTitle) ?> &mdash; <?= esc($subject) ?></h1>
            <p class="sheet-meta"><?= implode(' &middot; ', array_map('esc', $meta)) ?></p>
        </div>
    </header>

    <div class="sheet-body">
        <?= view('ui/partials/record_blocks', [
            'blocks'      => $blocks,
            'labsSummary' => $labsSummary,
        ], ['saveData' => false]) ?>
    </div>

    <footer class="sheet-foot">
        King Abdullah Medical City &mdash; National Transplant Registry. Confidential patient information.
    </footer>

    <?php // Straight to the dialog, so the button behaves like a download did. ?>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>

</html>
