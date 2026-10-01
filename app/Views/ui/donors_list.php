<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

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
 * @var string                     $statusFilter  The blood type chosen, or "all".
 */
$headers = ['Name', 'MRN', 'Age', 'Gender', 'Blood Group', 'Type', 'Labs', ''];

/**
 * Rebuilds the address with one filter swapped out.
 *
 * A filter sitting on its own default — `all`, for both of them — drops out of
 * the URL, so a plain list has a plain address and the chips still say what is
 * being asked for.
 */
$filterUrl = static function (string $key, string $value) use ($btFilter, $statusFilter): string {
    $query = ['bt' => $btFilter, 'status' => $statusFilter];
    $query[$key] = $value;

    // A filter on its own default drops out, so a plain list has a plain
    // address and the chips still say exactly what is being asked for.
    $query = array_filter($query, static fn (string $v): bool => $v !== 'all');

    return site_url('donors') . ($query === [] ? '' : '?' . http_build_query($query));
};
?>
<div class="page">
    <div class="page-header page-header--center">
        <div>
            <div class="eyebrow">Registry</div>
            <h1 class="page-title">Donors List</h1>
            <p class="page-subtitle"><?= esc(ui_plural(count($donors), 'unmatched donor')) ?></p>
        </div>
        <a class="btn-primary" href="<?= site_url('donors/new') ?>"><?= ui_icon('plus') ?>Add Donor</a>
    </div>

    <?php // Narrowed in SQL, as the waitlist's are: the chips are links, so the
          // page works with scripting off and the choice is in the address. ?>
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
        <?php if ($donors === []): ?>
            <?php // One sentence for both filters rather than one about blood
                  // type and silence about the other. ?>
            <div class="empty-state">
                <?= $btFilter === 'all' && $statusFilter === 'all'
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
                        $progress = UiStore::labProgress($donor['labTests']);
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
                            <td class="cell-action"><?= view('ui/partials/delete_cell', [
                                'url'    => site_url('donors/' . rawurlencode($donor['id']) . '/delete'),
                                'name'   => $donor['name'],
                                'kind'   => 'donor',
                                'detail' => 'MRN ' . $donor['id'] . '. The record and its whole lab workup will be removed. This cannot be undone.',
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
