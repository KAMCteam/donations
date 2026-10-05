<?= $this->extend('ui/layout') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/ui/css/exchange.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php

/**
 * The review before saving, as a page.
 *
 * Where Review and save goes with JavaScript off; with it on the builder opens
 * the same summary as a dialog and never leaves the screen. The summary itself
 * is one partial, so the two cannot drift apart, and confirming from either
 * posts to the same place.
 *
 * @var array<string, mixed> $state
 */
?>
<div class="page">
    <div class="page-header page-header--plain">
        <a class="back-link" href="<?= site_url('exchange/build') ?>"><?= ui_icon('back') ?>Back to the exchange</a>
        <div class="eyebrow">Exchange</div>
        <h1 class="page-title">Review the exchange</h1>
    </div>

    <div class="card card--pad">
        <?php // One form around the whole summary, because the summary asks
              // for each new pair's own details now and they travel with the
              // answer. ?>
        <form method="post" action="<?= site_url('exchange/build') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="confirm">
            <?= view('ui/partials/exchange_summary', ['state' => $state], ['saveData' => false]) ?>

            <div class="confirm-actions">
                <a class="btn-outline" href="<?= site_url('exchange/build') ?>">Back</a>
                <button type="submit" class="btn-save"<?= $state['complete'] ? '' : ' disabled title="The chain is not finished yet"' ?>>Save the exchange</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
