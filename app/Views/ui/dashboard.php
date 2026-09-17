<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;

/**
 * Overview. Was `js/pages/dashboard.js`.
 *
 * The prototype rendered empty bars and then filled them in from `paint()`;
 * the widths are written straight into the markup here, which is what the
 * source was imitating anyway (it set them before the first frame so nothing
 * animated on mount). Switching programme is a link rather than a click
 * handler, so the numbers come back already recomputed.
 *
 * @var string                                                     $organ
 * @var array{total: int, unmatched: int, pairs: int, active: int}  $stats
 * @var int                                                        $maxBar
 * @var list<array<string, mixed>>                                 $topUrgent
 */
$rows = [
    ['key' => 'total',     'label' => 'Total Recipients',      'color' => '#15508A'],
    ['key' => 'unmatched', 'label' => 'Waitlist (unmatched)',  'color' => '#2563eb'],
    ['key' => 'pairs',     'label' => 'Linked Pairs',          'color' => '#0f766e'],
    ['key' => 'active',    'label' => 'Active / Scheduled',    'color' => '#b45309'],
];

$actions = [
    ['label' => 'Add Recipient', 'url' => site_url('recipients/new')],
    ['label' => 'Add Donor',     'url' => site_url('donors/new')],
    ['label' => 'Add Pair',      'url' => site_url('pairs/new')],
];
?>
<div class="page">
    <div class="page-header page-header--start">
        <div>
            <div class="eyebrow">Overview</div>
            <h1 class="page-title capitalize"><?= esc($organ) ?> Transplant Program</h1>
        </div>
        <div class="organ-toggle">
            <?php foreach (UiStore::ORGANS as $option): ?>
                <a class="organ-toggle-btn<?= $option === $organ ? ' is-active' : '' ?>" href="<?= site_url('dashboard') . '?organ=' . $option ?>"><?= esc($option) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="quick-actions">
        <?php foreach ($actions as $action): ?>
            <a class="quick-action" href="<?= $action['url'] ?>"><span class="quick-action-plus">+</span><?= esc($action['label']) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="dash-grid">
        <div class="dash-chart card card--pad">
            <div class="dash-card-head">
                <h2 class="dash-card-title">Program statistics</h2>
                <span class="dash-card-meta"><?= esc($organ) ?> program</span>
            </div>
            <div class="stack-5">
                <?php foreach ($rows as $row): ?>
                    <?php $value = $stats[$row['key']]; ?>
                    <div>
                        <div class="stat-row-head">
                            <span class="stat-label"><?= esc($row['label']) ?></span>
                            <span class="stat-value"><?= $value ?></span>
                        </div>
                        <div class="progress">
                            <div class="progress-fill" style="width:<?= (int) round($value / $maxBar * 100) ?>%;background-color:<?= esc($row['color']) ?>"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stack-4">
            <?php if ($topUrgent !== []): ?>
                <div class="card card--pad-sm">
                    <h2 class="dash-card-title dash-card-title--mb4">High-priority waitlist</h2>
                    <div class="stack-3">
                        <?php foreach ($topUrgent as $recipient): ?>
                            <a class="urgent-item" href="<?= site_url('recipients/' . rawurlencode($recipient['id'])) ?>">
                                <div>
                                    <div class="urgent-name"><?= esc($recipient['name']) ?></div>
                                    <div class="urgent-meta"><?= esc($recipient['bloodType']) ?> &middot; <?= esc($recipient['id']) ?></div>
                                </div>
                                <span class="badge <?= ui_tone('urgency', $recipient['urgency']) ?>"><?= esc($recipient['urgency']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
