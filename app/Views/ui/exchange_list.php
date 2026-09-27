<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;

/**
 * The pairs a paired exchange can be built from.
 *
 * Only the pairs whose own screen has had Pair Exchange pressed on it, and
 * that an exchange can still move — open, not already transplanted. Pressing
 * that button is the filter; the list is short by construction, so the file
 * number is all it asks for.
 *
 * It carried the Pairs List's blood-type and status chips as well. Neither
 * belonged here: a blood group narrows a register you are reading, but an
 * exchange is built by matching groups to each other, so hiding all but one of
 * them hides exactly what the screen is for. And a pair is on this list only
 * while its status is one an exchange can move, which left a status filter
 * choosing between two or three words that were all already true.
 *
 * @var list<array<string, mixed>> $rows      Joined pairs, already filtered
 * @var string                     $query
 * @var bool                       $hasDraft  An exchange already part-built
 * @var string                     $error
 */
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
        <form class="search-row" method="get" action="<?= site_url('exchange') ?>">
            <label class="filter-label filter-label--mr" for="ex-q">File number:</label>
            <input type="search" id="ex-q" name="q" class="input search-input" value="<?= esc($query) ?>" placeholder="MRN or name" inputmode="search">
            <button type="submit" class="btn-outline">Search</button>
            <?php if ($query !== ''): ?>
                <a class="stat-link" href="<?= site_url('exchange') ?>">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card card--scroll">
        <?php if ($rows === []): ?>
            <?php // Empty is the normal starting state, so say what fills it. ?>
            <div class="empty-state">
                No pairs have been put forward for exchange.<br>
                Open a pair from the <a class="stat-link" href="<?= site_url('pairs') ?>">Pairs List</a> and press <strong>Pair Exchange</strong> to offer it here.
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
