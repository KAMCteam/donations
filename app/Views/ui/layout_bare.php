<?php

/**
 * Shell without the sidebar, for the two screens that sit outside the app:
 * login and the organ picker. In the prototype these were the `NO_SIDEBAR`
 * pages of `js/app.js`; the only difference is a `.app-main` without the
 * `has-sidebar` modifier and no top bar.
 *
 * @var string $title
 */
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Streamline organ donation and transplant processes with a user-friendly platform for managing recipients, donors, and pairs, enhancing efficiency for healthcare professionals.">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($title ?? 'Transplant Program') ?></title>

    <link rel="icon" type="image/x-icon" href="<?= base_url('assets/img/KAMC.png') ?>">

    <link rel="stylesheet" href="<?= base_url('assets/ui/css/base.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/layout.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/components.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/pages.css') ?>">
</head>

<body>
    <div id="app">
        <div class="app">
            <main class="app-main">
                <div id="page" class="page-host">
                    <?= $this->renderSection('content') ?>
                </div>
            </main>
        </div>
    </div>

    <script src="<?= base_url('assets/ui/js/ui.js') ?>"></script>
</body>

</html>
