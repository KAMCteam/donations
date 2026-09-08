<head>
    <?= $this->include('partials/favicon') ?>
    <title>Choose Organ</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/organs.css') ?>">
</head>

<body>
    <h1><?= esc(lang('Form.filter_organ_welcome')) ?></h1>
    <div class="organs">
        <?php foreach ($organs as $organ): ?>
            <a href="<?= site_url('Organ') . '?selcted_organ=' . urlencode($organ) ?>">
                <img src="<?= base_url('assets/svg/' . $organ . '.svg') ?>"><?= esc(lang('Form.filter_organ_' . $organ)) ?>
            </a>
        <?php endforeach; ?>
    </div>
</body>
