<?php

use App\Libraries\UiStore;

/**
 * The Pairs List as a printed page, and as a PDF.
 *
 * A standalone document rather than a screen in the shell: no sidebar, no top
 * bar, no filter chips, nothing that would come out on paper without meaning
 * anything there. It carries its own stylesheet, and opens the print dialog on
 * load — where Chrome's destination is Save as PDF, so the same page is the
 * file and the printout.
 *
 * Printing is what makes the PDF rather than a library because the names in
 * this register are Arabic as often as not, and a PDF writer has to do the
 * joining and the right-to-left itself. The browser already does both, for the
 * same reason it does on screen. It also keeps the app running on a plain
 * XAMPP with nothing installed, which is why the framework is committed here
 * in the first place.
 *
 * @var list<array{pair: array<string, mixed>, recipient: array<string, mixed>|null, donor: array<string, mixed>|null}> $rows
 * @var string $organLabel  The programme this list is for
 * @var string $filters     What the chips were narrowed to, in words
 * @var string $printedOn   DD/MM/YYYY
 * @var list<array{id: string, name: string}> $mrps
 */
$headers = [
    'Pair #', 'MRN', 'Name', 'Age', 'Type', 'Relationship',
    'Blood Group', 'MRP', 'Gender', 'Phone Number',
    'Dialysis', 'Entry Date', 'Status', 'Date of Crossmatch', 'Note',
];

$dash = '—';

$orDash  = static fn (string $value): string => $value === '' ? '—' : $value;
$entry   = static fn (string $iso): string => $iso === '' ? '—' : UiStore::isoToDMY($iso);
$mrpName = static function (string $id) use ($mrps): string {
    foreach ($mrps as $mrp) {
        if ($mrp['id'] === $id) {
            return $mrp['name'];
        }
    }

    return '—';
};
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Pairs List — <?= esc($organLabel) ?></title>

    <link rel="icon" type="image/x-icon" href="<?= base_url('assets/img/KAMC.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/print.css') ?>">
</head>

<body>
    <?php // On screen only: paper has no use for a button that reprints it. ?>
    <div class="print-bar">
        <a class="print-bar-back" href="<?= esc($backUrl) ?>">&larr; Back to Pairs List</a>
        <button type="button" class="print-bar-print" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <header class="sheet-head">
        <img class="sheet-logo" src="<?= base_url('assets/ui/img/kamc.svg') ?>" alt="King Abdullah Medical City">
        <div class="sheet-titles">
            <h1 class="sheet-title">Pairs List &mdash; <?= esc($organLabel) ?> Programme</h1>
            <p class="sheet-meta">
                <?= esc(ui_plural(count($rows), 'pair')) ?>
                &middot; <?= esc($filters) ?>
                &middot; Printed <?= esc($printedOn) ?>
            </p>
        </div>
    </header>

    <?php if ($rows === []): ?>
        <p class="sheet-empty">No pairs found.</p>
    <?php else: ?>
        <table class="sheet-table">
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?= esc($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <?php // A tbody per pair, so a page break never falls between a
                  // recipient and the donor they are matched to. ?>
            <?php foreach ($rows as $index => $row): ?>
                <?php
                $pair        = $row['pair'];
                $recipient   = $row['recipient'];
                $donor       = $row['donor'];
                $statusLabel = UiStore::STATUS_OPTIONS[$pair['status']] ?? $pair['status'];
                $note        = (string) ($pair['notes'] ?? '');
                ?>
                <tbody class="pair">
                    <tr>
                        <td rowspan="2" class="c-pairno"><?= $index + 1 ?></td>
                        <td class="c-mono"><?= esc($recipient['id'] ?? $dash) ?></td>
                        <td class="c-name"><?= esc($recipient['name'] ?? $dash) ?></td>
                        <td><?= esc($recipient['age'] ?? $dash) ?></td>
                        <td class="c-role">recipient</td>
                        <td rowspan="2" class="c-span"><?= esc($orDash((string) ($pair['relationship'] ?? ''))) ?></td>
                        <td class="c-mono"><?= esc($recipient['bloodType'] ?? $dash) ?></td>
                        <td><?= esc($mrpName($recipient['selectedMrp'] ?? '')) ?></td>
                        <td><?= esc($recipient['gender'] ?? $dash) ?></td>
                        <td class="c-mono"><?= esc($recipient['phone'] ?? $dash) ?></td>
                        <td class="c-mono"><?= esc($orDash($recipient['firstDialysis'] ?? '')) ?></td>
                        <td class="c-mono"><?= esc($entry($recipient['dateRegistered'] ?? '')) ?></td>
                        <td rowspan="2" class="c-span"><?= esc($statusLabel) ?></td>
                        <td class="c-mono"><?= esc($orDash((string) ($pair['scheduledDate'] ?? ''))) ?></td>
                        <td rowspan="2" class="c-span c-note"><?= esc($note !== '' ? $note : $dash) ?></td>
                    </tr>
                    <tr>
                        <td class="c-mono"><?= esc($donor['id'] ?? $dash) ?></td>
                        <td class="c-name"><?= esc($donor['name'] ?? $dash) ?></td>
                        <td><?= esc($donor['age'] ?? $dash) ?></td>
                        <td class="c-role">donor</td>
                        <td class="c-mono"><?= esc($donor['bloodType'] ?? $dash) ?></td>
                        <td><?= esc($mrpName($donor['donorMrp'] ?? '')) ?></td>
                        <td><?= esc($donor['donorGender'] ?? $dash) ?></td>
                        <td class="c-mono"><?= esc($donor['phone'] ?? $dash) ?></td>
                        <td>N/A</td>
                        <td>N/A</td>
                        <td class="c-mono"><?= esc($orDash((string) ($pair['scheduledDate'] ?? ''))) ?></td>
                    </tr>
                </tbody>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <footer class="sheet-foot">
        King Abdullah Medical City &mdash; National Transplant Registry. Confidential patient information.
    </footer>

    <?php // Straight to the dialog, so the link behaves like the download did.
          // Wrapped in onload: Chrome prints a blank sheet if the logo has not
          // arrived yet. ?>
    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
</body>

</html>
