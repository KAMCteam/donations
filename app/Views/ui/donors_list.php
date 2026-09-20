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
 * @var list<array<string, mixed>> $donors  Unmatched donors for the current programme.
 */
$headers = ['Name', 'MRN', 'Age', 'Gender', 'Blood Group', 'Type', 'Labs'];
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
