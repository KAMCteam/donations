<?= $this->extend('ui/layout_bare') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/ui/css/auth.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php

/**
 * A role's own landing screen, shared by all three.
 *
 * One view and not three, because there is one thing on it — who is signed in
 * — and three files saying that would be three files to keep in step for as
 * long as it stays true. The moment a role's dashboard has something of its
 * own on it, it gets a view of its own; until then this is the honest shape.
 *
 * @var string $name
 * @var string $role
 */
?>
<div class="page">
    <div class="page-header page-header--center page-header--wrap">
        <div>
            <div class="eyebrow">Signed in</div>
            <h1 class="page-title"><?= esc(ucfirst($role)) ?> dashboard</h1>
        </div>
        <div class="header-actions">
            <?php // A link and not a form: signing out takes nothing away
                  // that a repeated press could take away twice. ?>
            <a class="btn-outline" href="<?= site_url('logout') ?>"><?= ui_icon('back') ?>Logout</a>
        </div>
    </div>

    <div class="card card--pad">
        <div class="role-home-who">
            <span class="role-home-name"><?= esc($name) ?></span>
            <span class="role-home-role"><?= esc($role) ?></span>
        </div>

        <p class="page-subtitle">
            This dashboard has nothing of its own on it yet. The transplant platform
            &mdash; the waitlist, the donor register, the pairs and the reports &mdash;
            is where the work is done.
        </p>

        <div class="card-actions">
            <a class="btn-primary" href="<?= site_url('organ') ?>">Open the platform</a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
