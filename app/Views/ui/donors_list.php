<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\LabProgress;
use App\Libraries\UiStore;

/**
 * Donors registry. Was `js/pages/donors-list.js`.
 *
 * Same table as the Recipient Waitlist — the shared `list-table` style, the
 * row itself a link to the record — because they are the same kind of screen.
 * Relationship and Hospital were dropped from the columns: neither identifies
 * a donor at a glance, and both are on the record.
 *
 * @var list<array<string, mixed>> $donors    Unmatched donors for the current programme.
 * @var string                     $btFilter
 * @var string                     $statusFilter
 * @var string                     $searchQuery  What the search above the list is narrowing to  The blood type chosen, or "all".
 */
$headers = ['Name', 'MRN', 'Age', 'Gender', 'Blood Group', 'Type', 'Labs', 'Status', ''];

/**
 * Rebuilds the address with one filter swapped out.
 *
 * A filter sitting on its own default — `all`, for both of them — drops out of
 * the URL, so a plain list has a plain address and the chips still say what is
 * being asked for.
 */
$allowed = ['bt' => UiStore::BLOOD_TYPES, 'status' => array_keys(UiStore::PERSON_STATUS_OPTIONS)];

$filterUrl = static function (string $key, string $value) use ($btFilter, $statusFilter, $searchQuery, $allowed): string {
    $query = ['bt' => $btFilter, 'status' => $statusFilter];
    // Pressed, not chosen: a chip goes into the row's set or comes out of it,
    // so this list can be asked for A and O at once. `all` empties the row.
    $query[$key] = $value === 'all' ? [] : ui_filter_toggle($query[$key], $value, $allowed[$key]);

    // An empty row narrows nothing, so it drops out: a plain list has a plain
    // address and the chips still say exactly what is being asked for.
    $query = array_filter(array_map('ui_filter_param', $query), static fn (string $v): bool => $v !== '');

    // The search above the list is a filter like the chips are, so pressing
    // one keeps it rather than clearing it.
    if ($searchQuery !== '') {
        $query['q'] = $searchQuery;
    }

    return site_url('donors') . ($query === [] ? '' : '?' . http_build_query($query));
};

// The same two as a bare query string, for the sheet: it is another address
// under this one, not a replacement for it.
$filterQuery = static function () use ($btFilter, $statusFilter, $searchQuery): string {
    $query = array_filter(
        ['bt' => ui_filter_param($btFilter), 'status' => ui_filter_param($statusFilter)],
        static fn (string $v): bool => $v !== ''
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
            <div class="eyebrow">Registry</div>
            <h1 class="page-title">Donors List</h1>
            <p class="page-subtitle"><?= esc(ui_plural(count($donors), 'unmatched donor')) ?></p>
        </div>
        <?php // The filtered list as a printable sheet, as the Pairs List
              // has: what is on the screen is what comes out. ?>
        <div class="header-actions">
            <a class="btn-outline" href="<?= site_url('donors/print') . $filterQuery() ?>" target="_blank" rel="noopener"><?= ui_icon('printer') ?>Export PDF</a>
            <a class="btn-primary" href="<?= site_url('donors/new') ?>"><?= ui_icon('plus') ?>Add Donor</a>
        </div>
    </div>

    <?php // Narrowed in SQL, as the waitlist's are: the chips are links, so the
          // page works with scripting off and the choice is in the address. ?>
    <?php // Two rows, as the Pairs List has: each narrows on its own and the
          // two narrow together, so the address carries both. ?>
    <div class="filter-stack">
        <div class="filter-row">
            <span class="filter-label filter-label--mr">Blood type:</span>
            <a class="chip<?= $btFilter === [] ? ' is-active' : '' ?>" href="<?= $filterUrl('bt', 'all') ?>">All</a>
            <?php foreach (UiStore::BLOOD_TYPES as $bloodType): ?>
                <a class="chip chip--mono<?= in_array($bloodType, $btFilter, true) ? ' is-active' : '' ?>" href="<?= $filterUrl('bt', $bloodType) ?>"><?= esc($bloodType) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="filter-row">
            <span class="filter-label filter-label--mr">Status:</span>
            <a class="chip<?= $statusFilter === [] ? ' is-active' : '' ?>" href="<?= $filterUrl('status', 'all') ?>">All</a>
            <?php // The three a record is ever set to. The rest of
                  // `STATUS_OPTIONS` belongs to a pair or is retired, so
                  // offering them would be offering empty lists. ?>
            <?php foreach (UiStore::PERSON_STATUS_OPTIONS as $value => $label): ?>
                <a class="chip<?= in_array($value, $statusFilter, true) ? ' is-active' : '' ?>" href="<?= $filterUrl('status', $value) ?>"><?= esc($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card--scroll">
        <?php if ($donors === []): ?>
            <?php // One sentence for both filters rather than one about blood
                  // type and silence about the other. ?>
            <div class="empty-state">
                <?= $btFilter === [] && $statusFilter === []
                    ? 'No unmatched donors.'
                    : 'No unmatched donors match these filters.' ?>
            </div>
        <?php else: ?>
            <table class="table list-table">
                <thead>
                    <tr>
                        <?php foreach ($headers as $header): ?>
                            <th><?= esc($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donors as $donor): ?>
                        <?php
                        $progress = LabProgress::counted($donor['labTests']);
                        $url      = site_url('donors/' . rawurlencode($donor['id']));
                        ?>
                        <tr data-href="<?= $url ?>">
                            <td class="cell-name"><a href="<?= $url ?>"><?= esc($donor['name']) ?></a></td>
                            <td class="mono"><?= esc($donor['id']) ?></td>
                            <td><?= esc($donor['age']) ?></td>
                            <td><?= esc($donor['donorGender']) ?></td>
                            <td class="mono"><?= esc($donor['bloodType']) ?></td>
                            <td><span class="badge <?= str_starts_with($donor['donationType'], 'living') ? 'tone-teal-soft' : 'tone-slate' ?>"><?= esc(UiStore::DONATION_TYPES[$donor['donationType']] ?? $donor['donationType']) ?></span></td>
                            <td class="mono"><?= $progress['done'] ?>/<?= $progress['total'] ?></td>
                            <?php // The donor's own status, the one the chips
                                  // above already narrow the list by. It is
                                  // kept on the record as the words the
                                  // control shows, so it is turned back into
                                  // the key the colours are listed under. ?>
                            <?php $statusKey = UiStore::personStatusFromUi((string) $donor['donorStatus']); ?>
                            <td><span class="badge <?= esc(ui_tone('status', $statusKey)) ?>"><?= esc(UiStore::STATUS_OPTIONS[$statusKey] ?? $donor['donorStatus']) ?></span></td>
                            <td class="cell-action"><?= view('ui/partials/delete_cell', [
                                'url'    => site_url('donors/' . rawurlencode($donor['id']) . '/delete'),
                                'name'   => $donor['name'],
                                'kind'   => 'donor',
                                'detail' => 'MRN ' . $donor['id'] . '. The record, its whole lab workup and any pair it has already been through will be removed. This cannot be undone.',
                            ], ['saveData' => false]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
