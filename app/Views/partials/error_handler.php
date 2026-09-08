<head>
    <link rel="stylesheet" href="<?= base_url('assets/css/error_handler.css') ?>">
</head>

<?php if (! empty($confirmation)): ?>
    <div class="confirmation"><?= esc($confirmation) ?></div>
<?php endif; ?>

<?php if (! empty($error)): ?>
    <div class="error"><?= esc($error) ?></div>
<?php endif; ?>

<script src="<?= base_url('assets/js/error_handler.js') ?>"></script>
