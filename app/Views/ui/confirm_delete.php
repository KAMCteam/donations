<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

/**
 * "Are you sure?" as a page.
 *
 * Where the delete button in a list goes with JavaScript off, and where the
 * question is actually defined — the dialog on the list is a copy of it, not
 * the other way round. The button that does the deleting is a POST either way,
 * because a GET that deletes fires on a prefetch.
 *
 * @var string $name     What is being deleted, as the screens name it
 * @var string $kind     recipient | donor | pair
 * @var string $detail   Exactly what goes, and what stays
 * @var string $action   Where the POST goes
 * @var string $backUrl  The list it came from
 */
?>
<div class="page">
    <div class="page-header page-header--plain">
        <a class="back-link" href="<?= esc($backUrl) ?>"><?= ui_icon('back') ?>Back</a>
        <div class="eyebrow">Delete</div>
        <h1 class="page-title">Delete <?= esc($name) ?>?</h1>
    </div>

    <div class="card card--pad">
        <form method="post" action="<?= esc($action) ?>" class="confirm">
            <?= csrf_field() ?>
            <p class="confirm-detail"><?= esc($detail) ?></p>
            <div class="confirm-actions">
                <a class="btn-outline" href="<?= esc($backUrl) ?>">Cancel</a>
                <button type="submit" class="btn-danger"><?= ui_icon('trash') ?>Delete <?= esc($kind) ?></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
