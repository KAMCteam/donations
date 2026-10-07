<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;

/**
 * Pairs register. Was `js/pages/pairs-list.js`.
 *
 * Each pair still occupies two rows sharing a rowspan'd pair number, the
 * alternating row background is still written inline, and the export still
 * covers the filtered set — as a printable sheet at `pairs/print` now rather
 * than a CSV, built from the same rows the table shows rather than re-derived
 * in the browser.
 *
 * @var list<array{pair: array<string, mixed>, recipient: array<string, mixed>|null, donor: array<string, mixed>|null}> $rows
 * @var list<string> $btFilter      The blood groups the chips are set to; empty is all
 * @var list<string> $statusFilter  The statuses they are set to; empty is all
 * @var list<array{id: string, name: string}> $mrps
 * @var string $searchQuery  What the search above the list is narrowing to
 */
$headers = [
    'Pair #', 'MRN', 'Name', 'Age', 'Type', 'Relationship',
    'Blood Group', 'MRP', 'Gender', 'Phone Number',
    'Type Dialysis', 'First Dialysis', 'Entry Date', 'Status', 'Date of Crossmatch', '',
];

$dash = '—';

// Dates are DD/MM/YYYY everywhere the screens show one; entry_date arrives as
// the ISO the column holds, the rest are already formatted.
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

// What each row may hold, in the order the row lists them, so the address
// reads the same whichever order the chips were pressed.
$allowed = ['bt' => UiStore::BLOOD_TYPES, 'status' => array_keys(UiStore::PAIR_STATUS_OPTIONS)];

/**
 * Rebuilds the current query string with one chip pressed.
 *
 * Pressed, not chosen: a chip is in the set or out of it, so this list can be
 * asked for Active *and* Paired Exchange at once. `all` is not a value in the
 * set — it empties the row.
 *
 * A filter sitting on its own default is left out of the URL — every blood
 * group for one, Active alone for the other, which is what the screen opens
 * on. That is why `status=all` has to be written out: leaving it off would
 * mean Active.
 */
$filterUrl = static function (string $key, string $value) use ($btFilter, $statusFilter, $searchQuery, $allowed): string {
    $query = ['bt' => $btFilter, 'status' => $statusFilter];
    $query[$key] = $value === 'all' ? [] : ui_filter_toggle($query[$key], $value, $allowed[$key]);

    $query = array_filter([
        'bt'     => $query['bt'] === [] ? null : ui_filter_param($query['bt']),
        'status' => $query['status'] === [UiStore::PAIRS_DEFAULT_STATUS]
            ? null
            : ($query['status'] === [] ? 'all' : ui_filter_param($query['status'])),
        // The search above the list is a filter like the chips are, so pressing
        // one keeps it rather than clearing it.
        'q'      => $searchQuery === '' ? null : $searchQuery,
    ], static fn (?string $v): bool => $v !== null);

    return site_url('pairs') . ($query === [] ? '' : '?' . http_build_query($query));
};
?>
<div class="page">
    <div class="page-header page-header--center page-header--wrap">
        <div>
            <div class="eyebrow">Pairs</div>
            <h1 class="page-title">Pairs List</h1>
            <p class="page-subtitle"><?= esc(ui_plural(count($rows), 'pair')) ?></p>
        </div>
        <div class="header-actions">
            <a class="btn-outline" href="<?= site_url('pairs/print') . '?' . http_build_query(array_filter(['bt' => ui_filter_param($btFilter), 'status' => $statusFilter === [] ? 'all' : ui_filter_param($statusFilter), 'q' => $searchQuery])) ?>" target="_blank" rel="noopener"><?= ui_icon('printer') ?>Export PDF</a>
            <a class="btn-primary" href="<?= site_url('pairs/new') ?>"><?= ui_icon('plus') ?>Add Pair</a>
        </div>
    </div>

    <div class="filter-stack">
        <div class="filter-row">
            <span class="filter-label">Blood Group:</span>
            <a class="chip<?= $btFilter === [] ? ' is-active' : '' ?>" href="<?= $filterUrl('bt', 'all') ?>">All</a>
            <?php foreach (UiStore::BLOOD_TYPES as $bloodType): ?>
                <a class="chip chip--mono<?= in_array($bloodType, $btFilter, true) ? ' is-active' : '' ?>" href="<?= $filterUrl('bt', $bloodType) ?>"><?= esc($bloodType) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="filter-row">
            <span class="filter-label">Status:</span>
            <a class="chip<?= $statusFilter === [] ? ' is-active' : '' ?>" href="<?= $filterUrl('status', 'all') ?>">All</a>
            <?php // All six the pair's own card offers, Closed among them: it
                  // is a word a pair can be on, not an ending, so a pair
                  // wearing it is still here to be narrowed down to. What is
                  // not on this list is a pair that has been *ended*. ?>
            <?php foreach (UiStore::PAIR_STATUS_OPTIONS as $status => $statusLabel): ?>
                <a class="chip<?= in_array($status, $statusFilter, true) ? ' is-active' : '' ?>" href="<?= $filterUrl('status', $status) ?>"><?= esc($statusLabel) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card--scroll">
        <?php if ($rows === []): ?>
            <?php // One sentence and nothing else, as on every other list. The
                  // chips are right above it and already say what is being
                  // asked for, so a link back to All was a second way to press
                  // a button that is already on the screen. ?>
            <div class="empty-state">No pairs found.</div>
        <?php else: ?>
            <table class="table pairs-table">
                <thead>
                    <tr>
                        <?php foreach ($headers as $header): ?>
                            <th><?= esc($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <?php
                        $pair        = $row['pair'];
                        $recipient   = $row['recipient'];
                        $donor       = $row['donor'];
                        $rowBg       = $index % 2 === 0 ? '#ffffff' : '#f8fafc';
                        $statusLabel = UiStore::STATUS_OPTIONS[$pair['status']] ?? $pair['status'];
                        $url         = site_url('pairs/' . rawurlencode($pair['id']));
                        ?>
                        <tr class="row-recipient" data-href="<?= $url ?>" style="background-color:<?= $rowBg ?>">
                            <td rowspan="2" class="cell-pairno" style="background-color:<?= $rowBg ?>">
                                <div class="pair-number"><a href="<?= $url ?>"><?= $index + 1 ?></a></div>
                            </td>
                            <td class="t-mono t-500"><?= esc($recipient['id'] ?? $dash) ?></td>
                            <td class="t-medium t-800"><?= esc($recipient['name'] ?? $dash) ?></td>
                            <td class="t-700"><?= esc($recipient['age'] ?? $dash) ?></td>
                            <td><span class="role-tag tone-blue-soft">recipient</span></td>
                            <?php // Relationship is the pair's own column. It used to print
                                  // the note here, which put the same text in two columns
                                  // and left Relationship blank whenever nobody wrote a note. ?>
                            <td rowspan="2" class="cell-span cell-rel"><?= esc($orDash((string) ($pair['relationship'] ?? ''))) ?></td>
                            <td class="t-mono t-semibold t-700"><?= esc($recipient['bloodType'] ?? $dash) ?></td>
                            <td class="t-600"><?= esc($mrpName($recipient['selectedMrp'] ?? '')) ?></td>
                            <td class="t-600"><?= esc($recipient['gender'] ?? $dash) ?></td>
                            <td class="t-mono t-600 cell-phone"><?= esc($recipient['phone'] ?? $dash) ?></td>
                            <?php // Which kind, then when it began. Pre-emptive
                                  // has no when: the transplant comes first. ?>
                            <td class="t-500"><?= esc(UiStore::DIALYSIS_TYPES[$recipient['dialysisType'] ?? ''] ?? $dash) ?></td>
                            <td class="t-mono t-500"><?= esc($orDash($recipient['firstDialysis'] ?? '')) ?></td>
                            <td class="t-mono t-500"><?= esc($entry($recipient['dateRegistered'] ?? '')) ?></td>
                            <td rowspan="2" class="cell-span">
                                <span class="status-tag <?= ui_tone('status', $pair['status']) ?>"><?= esc($statusLabel) ?></span>
                            </td>
                            <?php // No Note column: it held a "Show Note" that opened
                                  // nothing, and the note itself is on the pair's own
                                  // screen and on its printed sheet. ?>
                            <td class="t-mono t-500 cell-last"><?= esc(($pair['scheduledDate'] ?? '') !== '' ? $pair['scheduledDate'] : $dash) ?></td>
                            <?php // One control for the pair, not one per row: the two rows
                                  // are one record, and deleting it unmakes the link only. ?>
                            <td rowspan="2" class="cell-action" style="background-color:<?= $rowBg ?>"><?= view('ui/partials/delete_cell', [
                                'url'    => site_url('pairs/' . rawurlencode($pair['id']) . '/delete'),
                                'name'   => 'Pair #' . ($index + 1),
                                'kind'   => 'pair',
                                'detail' => 'The link between ' . ($recipient['name'] ?? 'MRN ' . $pair['recipientId'])
                                    . ' and ' . ($donor['name'] ?? 'MRN ' . $pair['donorId'])
                                    . ' will be removed. Both records stay on the register, with their workups, and each can be matched again.',
                            ], ['saveData' => false]) ?></td>
                        </tr>
                        <tr class="row-donor" data-href="<?= $url ?>" style="background-color:<?= $rowBg ?>">
                            <td class="t-mono t-500"><?= esc($donor['id'] ?? $dash) ?></td>
                            <td class="t-medium t-800"><?= esc($donor['name'] ?? $dash) ?></td>
                            <td class="t-700"><?= esc($donor['age'] ?? $dash) ?></td>
                            <td><span class="role-tag tone-teal-soft">donor</span></td>
                            <td class="t-mono t-semibold t-700"><?= esc($donor['bloodType'] ?? $dash) ?></td>
                            <td class="t-600"><?= esc($mrpName($donor['donorMrp'] ?? '')) ?></td>
                            <td class="t-600"><?= esc($donor['donorGender'] ?? $dash) ?></td>
                            <td class="t-mono t-600 cell-phone"><?= esc($donor['phone'] ?? $dash) ?></td>
                            <?php // Dialysis and Entry Date are the recipient's
                                  // questions; a donor has no answer to give,
                                  // which is not the same as having none yet. ?>
                            <td class="t-500 cell-na">Not applicable</td>
                            <td class="t-500 cell-na">Not applicable</td>
                            <td class="t-500 cell-na">Not applicable</td>
                            <td class="t-mono t-500 cell-last"><?= esc(($pair['scheduledDate'] ?? '') !== '' ? $pair['scheduledDate'] : $dash) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
