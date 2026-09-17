<?= $this->extend('ui/layout_bare') ?>

<?= $this->section('content') ?>
<?php

/**
 * Programme picker. Was `js/pages/organ-selector.js`.
 *
 * The two cards were `<button>`s that mutated client state; they are links
 * now. `.organ-options` is a flex container, so its children are blockified
 * either way and the cards render identically.
 *
 * @var list<array{organ: string, label: string, desc: string, icon: string}> $organs
 */
?>
<div class="organ-page">
    <div class="organ-intro">
        <div class="organ-brand">
            <div class="organ-brand-mark"><?= ui_icon('pulse') ?></div>
            <span class="organ-brand-name">TransplantOS</span>
        </div>
        <h1 class="organ-title">Select organ program</h1>
        <p class="organ-sub">Choose the transplant program you are managing this session.</p>
    </div>

    <div class="organ-options">
        <?php foreach ($organs as $organ): ?>
            <a class="organ-card" href="<?= site_url('organ/' . $organ['organ']) ?>">
                <div class="organ-icon"><img src="<?= base_url('assets/ui/img/' . $organ['icon']) ?>" alt="<?= esc($organ['label']) ?>" width="30" height="30"></div>
                <div class="organ-name"><?= esc($organ['label']) ?></div>
                <div class="organ-desc"><?= esc($organ['desc']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
