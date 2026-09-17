<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;

/**
 * Donors registry. Was `js/pages/donors-list.js`.
 *
 * @var list<array<string, mixed>> $donors  Unmatched donors for the current programme.
 */
$headers = ['ID', 'Name', 'Age', 'Blood Type', 'Type', 'Relationship', 'Hospital', 'Labs'];
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

    <div class="card card--scroll">
        <?php if ($donors === []): ?>
            <div class="empty-state">No unmatched donors.</div>
        <?php else: ?>
            <table class="table donors-table">
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
                            <td class="cell-id"><a href="<?= $url ?>"><?= esc($donor['id']) ?></a></td>
                            <td class="cell-name"><?= esc($donor['name']) ?></td>
                            <td class="cell-age"><?= esc($donor['age']) ?></td>
                            <td class="cell-blood"><?= esc($donor['bloodType']) ?></td>
                            <td><span class="badge <?= $donor['donationType'] === 'living' ? 'tone-teal-soft' : 'tone-slate' ?>"><?= esc($donor['donationType']) ?></span></td>
                            <td class="cell-muted cell-rel"><?= esc(($donor['relationship'] ?? '') !== '' ? $donor['relationship'] : '—') ?></td>
                            <td class="cell-muted cell-hospital"><?= esc($donor['hospital']) ?></td>
                            <td class="cell-labs"><?= $progress['done'] ?>/<?= $progress['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
