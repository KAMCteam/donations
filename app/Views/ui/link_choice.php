<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

/**
 * "Link with a donor / recipient", as a page.
 *
 * The record screen shows these two options in a dialog. This is the same
 * choice at its own URL, which is where the button points, so it works with
 * JavaScript switched off and can be linked to or bookmarked.
 *
 * @var string               $personType
 * @var array<string, mixed> $person
 * @var string               $newUrl
 * @var string               $existingUrl
 * @var string               $backUrl
 */
$counterpart = $personType === 'recipient' ? 'donor' : 'recipient';
?>
<div class="page">
    <div class="page-header page-header--plain">
        <a class="back-link" href="<?= esc($backUrl) ?>"><?= ui_icon('back') ?>Back to <?= esc($person['name']) ?></a>
        <div class="eyebrow">Link</div>
        <h1 class="page-title">Link with a <?= esc($counterpart) ?></h1>
    </div>

    <div class="link-choice-wrap card card--pad">
        <?= view('ui/partials/link_choice', [
            'counterpart' => $counterpart,
            'newUrl'      => $newUrl,
            'existingUrl' => $existingUrl,
            'person'      => $person,
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
