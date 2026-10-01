<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;

/**
 * The pairs a paired exchange can be built from.
 *
 * Only the pairs whose own screen has had Pair Exchange pressed on it, and
 * that an exchange can still move — open, not already transplanted. Pressing
 * that button is the filter, and the list is short by construction.
 *
 * It had a file-number box of its own. That went to the bar at the top of
 * every screen, which asks the same question of every register at once: one
 * place to type an MRN is better than a different one per screen. `?q=` still
 * narrows this list — the top bar's results link here — so what is left on the
 * screen is the way out of that, not a second box to type into.
 *
 * It carried the Pairs List's status chips as well, which did not belong: a
 * pair is on this list only while its status is one an exchange can move, so
 * the filter chose between two or three words that were all already true.
 *
 * The blood-type row came back, but asking a different question. The Pairs
 * List's matched a pair when either side had the group; here it is the
 * **recipient's** group alone. An exchange exists because a donor cannot give
 * to their own recipient, so matching on either side would hide the very pairs
 * that make one work — what somebody narrowing this list wants is the
 * recipients who need a group they have somewhere to place.
 *
 * @var list<array<string, mixed>> $rows      Joined pairs, already filtered
 * @var string                     $query
 * @var string                     $btFilter  The recipient blood group, or "all"
 * @var bool                       $hasDraft  An exchange already part-built
 * @var string                     $error
 */
/**
 * Rebuilds the address with the blood type swapped, keeping the search.
 *
 * A filter on its own default drops out, so a plain list has a plain address.
 */
$filterUrl = static function (string $value) use ($query): string {
    $params = array_filter(
        ['bt' => $value === 'all' ? '' : $value, 'q' => $query],
        static fn (string $v): bool => $v !== ''
    );

    return site_url('exchange') . ($params === [] ? '' : '?' . http_build_query($params));
};
$headers = ['Pair #', 'Recipient', 'MRN', 'Blood', 'Donor', 'MRN', 'Blood', 'Status', ''];
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
        <div class="filter-row">
            <?php // Said in the label, because this row means something else
                  // here than it does on the Pairs List. ?>
            <span class="filter-label filter-label--mr">Recipient blood type:</span>
            <a class="chip<?= $btFilter === 'all' ? ' is-active' : '' ?>" href="<?= $filterUrl('all') ?>">All</a>
            <?php foreach (UiStore::BLOOD_TYPES as $bloodType): ?>
                <a class="chip chip--mono<?= $btFilter === $bloodType ? ' is-active' : '' ?>" href="<?= $filterUrl($bloodType) ?>"><?= esc($bloodType) ?></a>
            <?php endforeach; ?>
        </div>

        <?php // The file-number box that used to sit here has gone to the bar
              // at the top of every screen, which asks the same question of
              // every register at once. What is left when a search is still
              // narrowing this list is the way out of it. ?>
        <?php if ($query !== ''): ?>
            <div class="filter-row">
                <span class="filter-label filter-label--mr">File number:</span>
                <span class="chip is-active"><?= esc($query) ?></span>
                <a class="stat-link" href="<?= $filterUrl($btFilter) ?>">Clear</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="card card--scroll">
        <?php if ($rows === []): ?>
            <div class="empty-state">
                <?php if ($btFilter !== 'all' || $query !== ''): ?>
                    No pairs on the exchange list match these filters.
                <?php else: ?>
                    <?php // Empty is the normal starting state, so say what fills it. ?>
                    No pairs have been put forward for exchange.<br>
                    Open a pair from the <a class="stat-link" href="<?= site_url('pairs') ?>">Pairs List</a> and press <strong>Pair Exchange</strong> to offer it here.
                <?php endif; ?>
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
                    <?php foreach ($rows as $row): ?>
                        <?php $pairUrl = site_url('pairs/' . rawurlencode((string) $row['id'])); ?>
                        <?php // The row opens the pair, as every other list opens
                              // the record it lists — these rows already had the
                              // pointer cursor for it. The names still go to the
                              // two people, and Create exchange still posts. ?>
                        <tr data-href="<?= $pairUrl ?>">
                            <td class="mono"><a href="<?= $pairUrl ?>"><?= esc($row['id']) ?></a></td>
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
