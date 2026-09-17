<?= $this->extend('ui/layout_bare') ?>

<?= $this->section('content') ?>
<?php

/**
 * Staff login. Was `js/pages/login.js`.
 *
 * The prototype validated in the browser and then swapped the rendered page;
 * this is a real form post, and the error paragraph below is rendered only
 * when the controller sends one back — the same `{error && <p>}` behaviour,
 * decided server-side.
 *
 * @var string|null $error
 */
?>
<div class="login">
    <div class="login-brand">
        <img class="login-brand-logo" src="<?= base_url('assets/ui/img/kamc-big.png') ?>" alt="King Abdullah Medical City">
        <div>
            <div class="login-tagline">Organ Donation &amp; Transplant Platform</div>
            <h1 class="login-headline">Every transplant<br>begins with a match.</h1>
            <p class="login-lede">Secure access for medical staff. Manage recipients, donors, and transplant pairs across Kidney and Liver programs.</p>
        </div>
        <div class="login-copy">&copy; 2026 National Transplant Registry &mdash; Restricted Access</div>
    </div>

    <div class="login-panel">
        <div class="login-box">
            <div class="login-heading-block">
                <div class="login-mobile-logo"><img src="<?= base_url('assets/ui/img/kamc-big.png') ?>" alt="King Abdullah Medical City"></div>
                <h2 class="login-title">Staff login</h2>
                <p class="login-sub">Enter your credentials to access the platform.</p>
            </div>

            <form class="stack-5" method="post" action="<?= site_url('ui/login') ?>" novalidate>
                <?= csrf_field() ?>
                <div>
                    <label class="login-label" for="staff-id">Staff ID</label>
                    <input type="text" id="staff-id" name="id" class="input" value="<?= esc($id ?? '') ?>" placeholder="e.g. DR-00421" style="font-family:&quot;DM Mono&quot;, monospace">
                </div>
                <div>
                    <label class="login-label" for="staff-password">Password</label>
                    <input type="password" id="staff-password" name="password" class="input" placeholder="••••••••">
                </div>
                <?php if (! empty($error)): ?>
                    <p class="login-error"><?= esc($error) ?></p>
                <?php endif; ?>
                <button type="submit" class="login-submit">Sign in</button>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
