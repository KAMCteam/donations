<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;

/**
 * Recipient waitlist. Was `js/pages/recipient-waitlist.js`.
 *
 * The blood-type chips filtered an in-memory array and re-rendered the table;
 * they are links carrying `?bt=` now, and the controller hands back the rows
 * already filtered and sorted, so the table below is a plain `<table>`.
 *
 * @var list<array<string, mixed>> $recipients  Unpaired, urgent-first then score-sorted.
 * @var string                     $btFilter
 * @var string                     $statusFilter
 * @var string                     $searchQuery  What the search above the list is narrowing to
 */
$headers = ['#', 'Name', 'MRN', 'Age', 'Gender', 'Blood Group', 'Score', 'Urgent', ''];
// The score is a real number now: a tenth of a point per month waiting plus a
// tenth per month on dialysis, computed by the query. It is NULL for a
// recipient with no dialysis date, which shows as a dash rather than as zero.
$score = static fn (?float $value): string => $value === null ? '—' : number_format($value, 1);

/**
 * Rebuilds the address with one filter swapped out.
 *
 * A filter sitting on its own default — `all`, for both of them — drops out of
 * the URL, so a plain list has a plain address and the chips still say what is
 * being asked for.
 */
$filterUrl = static function (string $key, string $value) use ($btFilter, $statusFilter, $searchQuery): string {
    $query = ['bt' => $btFilter, 'status' => $statusFilter];
    $query[$key] = $value;

    // A filter on its own default drops out, so a plain list has a plain
    // address and the chips still say exactly what is being asked for.
    $query = array_filter($query, static fn (string $v): bool => $v !== 'all');

    // The search above the list is a filter like the chips are, so pressing
    // one keeps it rather than clearing it.
    if ($searchQuery !== '') {
        $query['q'] = $searchQuery;
    }

    return site_url('recipients') . ($query === [] ? '' : '?' . http_build_query($query));
};

// The same two as a bare query string, for the sheet: it is another address
// under this one, not a replacement for it.
$filterQuery = static function () use ($btFilter, $statusFilter, $searchQuery): string {
    $query = array_filter(
        ['bt' => $btFilter, 'status' => $statusFilter],
        static fn (string $v): bool => $v !== 'all'
    );

    if ($searchQuery !== '') {
        $query['q'] = $searchQuery;
    }

    return $query === [] ? '' : '?' . http_build_query($query);
};
?>
<div class="page">
    <div class="page-header page-header--center">
        <div>
            <div class="eyebrow">Waitlist</div>
            <h1 class="page-title">Recipient Waitlist</h1>
            <p class="page-subtitle"><?= esc(ui_plural(count($recipients), 'unmatched recipient')) ?></p>
        </div>
        <?php // The filtered list as a printable sheet, as the Pairs List
              // has: what is on the screen is what comes out. ?>
        <div class="header-actions">
            <a class="btn-outline" href="<?= site_url('recipients/print') . $filterQuery() ?>" target="_blank" rel="noopener"><?= ui_icon('printer') ?>Export PDF</a>
            <a class="btn-primary" href="<?= site_url('recipients/new') ?>"><?= ui_icon('plus') ?>Add Recipient</a>
        </div>
    </div>

    <?php // Two rows, as the Pairs List has: each narrows on its own and the
          // two narrow together, so the address carries both. ?>
    <div class="filter-stack">
        <div class="filter-row">
            <span class="filter-label filter-label--mr">Blood type:</span>
            <a class="chip<?= $btFilter === 'all' ? ' is-active' : '' ?>" href="<?= $filterUrl('bt', 'all') ?>">All</a>
            <?php foreach (UiStore::BLOOD_TYPES as $bloodType): ?>
                <a class="chip chip--mono<?= $btFilter === $bloodType ? ' is-active' : '' ?>" href="<?= $filterUrl('bt', $bloodType) ?>"><?= esc($bloodType) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="filter-row">
            <span class="filter-label filter-label--mr">Status:</span>
            <a class="chip<?= $statusFilter === 'all' ? ' is-active' : '' ?>" href="<?= $filterUrl('status', 'all') ?>">All</a>
            <?php // The three a record is ever set to. The rest of
                  // `STATUS_OPTIONS` belongs to a pair or is retired, so
                  // offering them would be offering empty lists. ?>
            <?php foreach (UiStore::PERSON_STATUS_OPTIONS as $value => $label): ?>
                <a class="chip<?= $statusFilter === $value ? ' is-active' : '' ?>" href="<?= $filterUrl('status', $value) ?>"><?= esc($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card--scroll">
        <?php if ($recipients === []): ?>
            <div class="empty-state">No recipients found.</div>
        <?php else: ?>
            <table class="table list-table waitlist-table">
                <thead>
                    <tr>
                        <?php foreach ($headers as $header): ?>
                            <th><?= esc($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recipients as $index => $recipient): ?>
                        <tr class="<?= $recipient['urgent'] ? 'is-urgent' : '' ?>" data-href="<?= site_url('recipients/' . rawurlencode($recipient['id'])) ?>">
                            <td><?= $index + 1 ?></td>
                            <td class="cell-name"><a href="<?= site_url('recipients/' . rawurlencode($recipient['id'])) ?>"><?= esc($recipient['name']) ?></a></td>
                            <td class="mono"><?= esc($recipient['id']) ?></td>
                            <td><?= esc($recipient['age']) ?></td>
                            <td><?= esc($recipient['gender']) ?></td>
                            <td class="mono"><?= esc($recipient['bloodType']) ?></td>
                            <td class="mono"><?= esc($score($recipient['score'] ?? null)) ?></td>
                            <td><?= $recipient['urgent'] ? 'Urgent' : 'Not Urgent' ?></td>
                            <td class="cell-action"><?= view('ui/partials/delete_cell', [
                                'url'    => site_url('recipients/' . rawurlencode($recipient['id']) . '/delete'),
                                'name'   => $recipient['name'],
                                'kind'   => 'recipient',
                                'detail' => 'MRN ' . $recipient['id'] . '. The record and its whole lab workup will be removed. This cannot be undone.',
                            ], ['saveData' => false]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?= view('ui/partials/delete_dialog', [], ['saveData' => false]) ?>
<?= $this->endSection() ?>
