<?= $this->extend('ui/layout_bare') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/ui/css/auth.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php

/**
 * Signed in, but not for this.
 *
 * Rendered by {@see \App\Filters\RoleFilter} when somebody with a session
 * reaches a screen their role is not listed for — a bookmark kept after a role
 * changed, a link passed along by a colleague, an address typed from memory.
 *
 * It is the login screen's panel with a sentence in it instead of a form, for
 * the same reason the login screen looks the way it does: these are the two
 * screens somebody meets outside the platform, and they should look like one
 * thing rather than like the platform having broken.
 *
 * It says what is the matter and where they can go, and it does not say what
 * is on the screen they asked for. Nothing is lost by that — somebody who
 * should see it will be told by whoever sent the link — and naming it would
 * make this page a way of reading the map of a system you are locked out of.
 *
 * @var string $role     The role they are signed in as
 * @var string $name     Their name, for the line that says who they are
 * @var string $homeUrl  Their own dashboard
 */
?>
<div class="login">
    <div class="login-brand">
        <img class="login-brand-logo" src="<?= base_url('assets/ui/img/kamc.svg') ?>" alt="King Abdullah Medical City">
        <h1 class="login-headline">Organ Donation &amp; Transplantation</h1>
    </div>

    <div class="login-panel">
        <div class="login-box auth-message">
            <div class="login-heading-block">
                <div class="login-mobile-logo"><img src="<?= base_url('assets/ui/img/kamc.svg') ?>" alt="King Abdullah Medical City"></div>
                <p class="auth-message-code">403 &middot; Forbidden</p>
                <h2 class="login-title">Not your screen</h2>
                <p class="auth-message-body">
                    <?php if ($name !== ''): ?>
                        You are signed in as <strong><?= esc($name) ?></strong><?= $role === '' ? '' : ', a ' . esc($role) ?>,
                        and this screen is not open to that role.
                    <?php else: ?>
                        This screen is not open to your role.
                    <?php endif; ?>
                    If you need it, ask an administrator to change what your account can reach.
                </p>
            </div>

            <div class="auth-message-actions">
                <a class="login-submit" href="<?= esc($homeUrl) ?>">Go to the platform</a>
                <a class="auth-link" href="<?= site_url('logout') ?>">Sign out</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
