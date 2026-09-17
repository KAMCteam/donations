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
 * @var list<array<string, mixed>> $recipients  Filtered, urgency-sorted.
 * @var string                     $btFilter
 */
$headers  = ['#', 'Name', 'MRN', 'Age', 'Gender', 'Blood Group', 'Score', 'Urgent'];
$scoreMap = ['critical' => 1.8, 'high' => 5.2, 'medium' => 9.5, 'low' => 20.1];
?>
<div class="page">
    <div class="page-header page-header--center">
        <div>
            <div class="eyebrow">Waitlist</div>
            <h1 class="page-title">Recipient Waitlist</h1>
            <p class="page-subtitle"><?= esc(ui_plural(count($recipients), 'unmatched recipient')) ?></p>
        </div>
        <a class="btn-primary" href="<?= site_url('ui/recipients/new') ?>"><?= ui_icon('plus') ?>Add Recipient</a>
    </div>

    <div class="filter-row filter-row--mb">
        <span class="filter-label filter-label--mr">Blood type:</span>
        <a class="chip<?= $btFilter === 'all' ? ' is-active' : '' ?>" href="<?= site_url('ui/recipients') ?>">All</a>
        <?php foreach (UiStore::BLOOD_TYPES as $bloodType): ?>
            <a class="chip chip--mono<?= $btFilter === $bloodType ? ' is-active' : '' ?>" href="<?= site_url('ui/recipients') . '?bt=' . rawurlencode($bloodType) ?>"><?= esc($bloodType) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card card--scroll">
        <?php if ($recipients === []): ?>
            <div class="empty-state">No recipients found.</div>
        <?php else: ?>
            <table class="table waitlist-table">
                <thead>
                    <tr>
                        <?php foreach ($headers as $header): ?>
                            <th><?= esc($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recipients as $index => $recipient): ?>
                        <?php $isUrgent = in_array($recipient['urgency'], ['critical', 'high'], true); ?>
                        <tr class="<?= $isUrgent ? 'is-urgent' : '' ?>" data-href="<?= site_url('ui/recipients/' . rawurlencode($recipient['id'])) ?>">
                            <td><?= $index + 1 ?></td>
                            <td class="cell-name"><a href="<?= site_url('ui/recipients/' . rawurlencode($recipient['id'])) ?>"><?= esc($recipient['name']) ?></a></td>
                            <td class="mono"><?= esc(preg_replace('/\D/', '', (string) $recipient['id'])) ?></td>
                            <td><?= esc($recipient['age']) ?></td>
                            <td>—</td>
                            <td class="mono"><?= esc($recipient['bloodType']) ?></td>
                            <td><?= esc($scoreMap[$recipient['urgency']] ?? '—') ?></td>
                            <td><?= $isUrgent ? 'Urgent' : 'Not Urgent' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
