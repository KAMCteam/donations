<head>
    <?php $this->load->view('partials/favicon.php') ?>
    <title>Choose Organ</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/organs.css') ?>">
</head>

<body>
    <h1><?= lang('filter_organ_welcome') ?></h1>
    <div class="organs">
        <?php foreach($organs as $organ): ?>
            <a href="<?= base_url('Organ?selcted_organ=' . $organ) ?>"><img src="<?= base_url('assets/svg/' . $organ . '.svg') ?>"><?= lang('filter_organ_' . $organ) ?></a>
        <?php endforeach; ?>
    </div>
</body>