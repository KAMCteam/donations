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
 * @var string                     $backUrl
 * @var string                     $backLabel
 */
$dash = '—';
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
        <?php foreach ($blocks as $block): ?>
            <section class="rec-block">
                <h2 class="rec-block-title">
                    <?= esc($block['title']) ?>
                    <?php if (($block['subtitle'] ?? '') !== ''): ?>
                        <small><?= esc($block['subtitle']) ?></small>
                    <?php endif; ?>
                </h2>

                <?php if ($block['kind'] === 'fields'): ?>
                    <?php
                    // The grid is three across and a wide field takes two of
                    // them, so rows do not fill evenly: a wide field with only
                    // one column left is pushed to the next row and leaves a
                    // hole behind it, and the last row usually ends short.
                    // Every gap is filled with an empty cell, because an
                    // unfilled one leaves the block's border stopping halfway
                    // and the box reads as torn rather than ended.
                    //
                    // Walking the row here rather than leaving it to the
                    // browser is what makes the holes findable at all: laid
                    // out, they are between cells, not after them.
                    $cells  = [];
                    $column = 0;

                    foreach ($block['fields'] as $field) {
                        $width = ($field['wide'] ?? false) ? 2 : 1;

                        if ($column + $width > 3) {
                            for (; $column < 3; $column++) {
                                $cells[] = null;
                            }

                            $column = 0;
                        }

                        $cells[] = $field;
                        $column  = ($column + $width) % 3;
                    }

                    for (; $column > 0 && $column < 3; $column++) {
                        $cells[] = null;
                    }
                    ?>
                    <div class="rec-fields">
                        <?php foreach ($cells as $field): ?>
                            <?php if ($field === null): ?>
                                <div class="rec-field rec-field--filler"></div>
                            <?php else: ?>
                                <div class="rec-field<?= ($field['wide'] ?? false) ? ' rec-field--wide' : '' ?>">
                                    <div class="rec-label"><?= esc($field['label']) ?></div>
                                    <div class="rec-value<?= ($field['mono'] ?? false) ? ' rec-value--mono' : '' ?><?= ($field['strong'] ?? false) ? ' rec-value--strong' : '' ?>"><?= esc($field['value'] !== '' ? $field['value'] : $dash) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($block['kind'] === 'text'): ?>
                    <?php $text = trim((string) $block['text']); ?>
                    <div class="rec-text<?= $text === '' ? ' rec-text--empty' : '' ?>"><?= $text === '' ? 'Nothing recorded.' : esc($text) ?></div>

                <?php else: ?>
                    <?php
                    // The workup, in the check list's own groups and order. The
                    // progress line is the same count the screen shows, so a
                    // printed sheet and the record it came from agree.
                    $progress = UiStore::labProgress($block['tests']);
                    $groups   = [];

                    foreach ($block['tests'] as $test) {
                        $groups[$test['group'] ?? ''][] = $test;
                    }
                    ?>
                    <p class="rec-progress"><?= $progress['done'] ?> of <?= $progress['total'] ?> completed &middot; <?= $progress['pct'] ?>%</p>

                    <?php foreach ($groups as $groupName => $groupTests): ?>
                        <?php if ($groupName !== ''): ?>
                            <h3 class="rec-group-name"><?= esc($groupName) ?></h3>
                        <?php endif; ?>
                        <table class="rec-labs">
                            <thead>
                                <tr>
                                    <th class="l-test">Test</th>
                                    <th class="l-answer">Result</th>
                                    <th class="l-value">Value / finding</th>
                                    <th class="l-date">Date</th>
                                    <th class="l-comment">Comment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupTests as $test): ?>
                                    <?php
                                    // "Other" has no answer to give — it is the
                                    // sheet's blank line — so its row carries
                                    // only what was written in it.
                                    $freeText   = in_array($test['resultType'], UiStore::FREE_TEXT_TYPES, true);
                                    $status     = (string) $test['status'];
                                    $unanswered = in_array($status, UiStore::RESULT_UNANSWERED, true);
                                    $flag       = (UiStore::RESULT_TONE[$status] ?? '') === 'tone-red';
                                    // A test that does not offer the answer it
                                    // holds has not been answered at all, and
                                    // the sheet says so the way an empty field
                                    // does — with a dash, not with a word the
                                    // screen never showed.
                                    $blank      = $freeText || ! UiStore::offersAnswer($test['resultType'], $status);
                                    ?>
                                    <tr>
                                        <td class="l-test"><?= esc($test['name']) ?></td>
                                        <td class="l-answer<?= $unanswered ? ' l-answer--unanswered' : '' ?><?= $flag ? ' l-answer--flag' : '' ?>"><?= $blank ? $dash : esc(UiStore::RESULT_LABEL[$status] ?? $status) ?></td>
                                        <td class="l-value"><?= esc(($test['result'] ?? '') !== '' ? $test['result'] : $dash) ?></td>
                                        <td class="l-date"><?= esc(($test['date'] ?? '') !== '' ? $test['date'] : $dash) ?></td>
                                        <td class="l-comment"><?= esc(($test['notes'] ?? '') !== '' ? $test['notes'] : $dash) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <footer class="sheet-foot">
        King Abdullah Medical City &mdash; National Transplant Registry. Confidential patient information.
    </footer>

    <?php // Straight to the dialog, so the button behaves like a download did. ?>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>

</html>
