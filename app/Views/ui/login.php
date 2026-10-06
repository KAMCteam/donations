<?= $this->extend('ui/layout_bare') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/ui/css/auth.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/ui/js/login.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php

/**
 * User login. Was `js/pages/login.js`.
 *
 * The prototype validated in the browser and then swapped the rendered page;
 * this is a real form post, and the error paragraph below is rendered only
 * when the controller sends one back — the same `{error && <p>}` behaviour,
 * decided server-side. {@see \App\Controllers\Auth} is what it decides with.
 *
 * The screen is the one that was designed: the same panel, the same two
 * fields, the same button. What was added is what a form that can now refuse
 * somebody needs — the message, the number kept in the box so it is not
 * retyped, a way to read the password back, and a button that says it is
 * working. The last two are `login.js`, which only ever adds; with scripting
 * off the field is an ordinary password box and the button an ordinary submit.
 *
 * @var string|null $error    Why the last attempt was refused
 * @var string|null $loginId  What was typed into User ID, to type it once
 * @var string|null $notice   Why somebody was sent here from a locked screen
 */
?>
<div class="login">
    <div class="login-brand">
        <img class="login-brand-logo" src="<?= base_url('assets/ui/img/kamc.svg') ?>" alt="King Abdullah Medical City">
        <?php // The panel is the logo and the name of what this is, and nothing
              // else: the slogan, the paragraph under it and the notice along
              // the foot have all gone. ?>
        <h1 class="login-headline">Organ Donation &amp; Transplantation</h1>
    </div>

    <div class="login-panel">
        <div class="login-box">
            <div class="login-heading-block">
                <div class="login-mobile-logo"><img src="<?= base_url('assets/ui/img/kamc.svg') ?>" alt="King Abdullah Medical City"></div>
                <h2 class="login-title">User login</h2>
                <p class="login-sub">Enter your credentials to access the platform.</p>
            </div>

            <form class="stack-5" method="post" action="<?= site_url('login') ?>" novalidate data-login-form>
                <?= csrf_field() ?>
                <div>
                    <label class="login-label" for="user-id">User ID</label>
                    <?php // `inputmode` and not `type="number"`: a staff number
                          // is digits, but a number box comes with spinners and
                          // drops a leading zero. ?>
                    <input type="text" id="user-id" name="login_id" class="input" value="<?= esc($loginId ?? '') ?>"
                           placeholder="e.g. 1042" inputmode="numeric" autocomplete="username" autofocus
                           style="font-family:&quot;DM Mono&quot;, monospace">
                </div>
                <div>
                    <label class="login-label" for="user-password">Password</label>
                    <?php // The field is the field. The button is placed over
                          // it rather than beside it, so the row keeps the
                          // width and the shape every other box on this screen
                          // has. `login.js` adds it; it is not in the markup. ?>
                    <div class="login-field">
                        <input type="password" id="user-password" name="password" class="input" placeholder="••••••••" autocomplete="current-password">
                    </div>
                </div>
                <?php if (! empty($notice)): ?>
                    <p class="login-notice"><?= esc($notice) ?></p>
                <?php endif; ?>
                <?php if (! empty($error)): ?>
                    <p class="login-error" role="alert"><?= esc($error) ?></p>
                <?php endif; ?>
                <button type="submit" class="login-submit" data-login-submit data-busy-label="Signing in&hellip;">Sign in</button>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
