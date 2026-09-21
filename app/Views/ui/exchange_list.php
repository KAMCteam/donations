<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;

/**
 * The pairs a paired exchange can be built from.
 *
 * Only the ones an exchange can move: open, and not already transplanted. The
 * search box takes a file number — either side's — and the two chip rows are
 * the Pairs List's own filters, so this reads as that screen narrowed to the
 * question being asked.
 *
 * @var list<array<string, mixed>> $rows          Joined pairs, already filtered
 * @var string                     $query
 * @var string                     $statusFilter
 * @var string                     $btFilter
 * @var bool                       $hasDraft      An exchange already part-built
 * @var string                     $error
 */
$headers = ['Pair #', 'Recipient', 'MRN', 'Blood', 'Donor', 'MRN', 'Blood', 'Status', ''];

/** Rebuilds the current query string with one filter swapped out. */
$filterUrl = static function (string $key, string $value) use ($query, $btFilter, $statusFilter): string {
    $params        = ['q' => $query, 'bt' => $btFilter, 'status' => $statusFilter];
    $params[$key]  = $value;
    $params        = array_filter($params, static fn (string $v): bool => $v !== '' && $v !== 'all');

    return site_url('exchange') . ($params === [] ? '' : '?' . http_build_query($params));
};
?>
<div class="page">
    <div class="page-header page-header--center page-header--wrap">
        <div>
            <div class="eyebrow">Exchange</div>
            <h1 class="page-title">Paired Exchange</h1>
            <p class="page-subtitle"><?= esc(ui_plural(count($rows), 'pair')) ?> available to exchange</p>
        </div>
        <?php if ($hasDraft): ?>
            <a class="btn-primary" href="<?= site_url('exchange/build') ?>"><?= ui_icon('link14') ?>Resume exchange</a>
        <?php endif; ?>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <div class="filter-stack">
        <form class="search-row" method="get" action="<?= site_url('exchange') ?>">
            <label class="filter-label filter-label--mr" for="ex-q">File number:</label>
            <input type="search" id="ex-q" name="q" class="input search-input" value="<?= esc($query) ?>" placeholder="MRN or name" inputmode="search">
            <?php // The chips are query parameters too, so searching keeps them. ?>
            <input type="hidden" name="bt" value="<?= esc($btFilter) ?>">
            <input type="hidden" name="status" value="<?= esc($statusFilter) ?>">
            <button type="submit" class="btn-outline">Search</button>
            <?php if ($query !== ''): ?>
                <a class="stat-link" href="<?= $filterUrl('q', '') ?>">Clear</a>
            <?php endif; ?>
        </form>

        <div class="filter-row">
            <span class="filter-label">Blood type:</span>
            <a class="chip<?= $btFilter === 'all' ? ' is-active' : '' ?>" href="<?= $filterUrl('bt', 'all') ?>">All</a>
            <?php foreach (UiStore::BLOOD_TYPES as $bloodType): ?>
                <a class="chip chip--mono<?= $btFilter === $bloodType ? ' is-active' : '' ?>" href="<?= $filterUrl('bt', $bloodType) ?>"><?= esc($bloodType) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="filter-row">
            <span class="filter-label">Status:</span>
            <a class="chip<?= $statusFilter === 'all' ? ' is-active' : '' ?>" href="<?= $filterUrl('status', 'all') ?>">All</a>
            <?php foreach (UiStore::STATUS_OPTIONS as $status => $statusLabel): ?>
                <?php if (! App\Libraries\ExchangeDraft::isExchangeable($status)) { continue; } ?>
                <a class="chip<?= $statusFilter === $status ? ' is-active' : '' ?>" href="<?= $filterUrl('status', $status) ?>"><?= esc($statusLabel) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card--scroll">
        <?php if ($rows === []): ?>
            <div class="empty-state">No pairs available to exchange.</div>
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
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="mono"><?= esc($row['id']) ?></td>
                            <td class="cell-name"><a href="<?= site_url('recipients/' . rawurlencode($row['r_mrn'])) ?>"><?= esc($row['r_name']) ?></a></td>
                            <td class="mono"><?= esc($row['r_mrn']) ?></td>
                            <td class="mono"><?= esc($row['r_blood_group']) ?></td>
                            <td class="cell-name"><a href="<?= site_url('donors/' . rawurlencode($row['d_mrn'])) ?>"><?= esc($row['d_name']) ?></a></td>
                            <td class="mono"><?= esc($row['d_mrn']) ?></td>
                            <td class="mono"><?= esc($row['d_blood_group']) ?></td>
                            <td><span class="badge <?= ui_tone('status', $row['status']) ?>"><?= esc(UiStore::STATUS_OPTIONS[$row['status']] ?? $row['status']) ?></span></td>
                            <td class="cell-action">
                                <?php // A post, not a link: it replaces whatever draft is open. ?>
                                <form method="post" action="<?= site_url('exchange/start/' . rawurlencode($row['id'])) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-edit"><?= ui_icon('link14') ?>Create exchange</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
