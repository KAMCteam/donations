<link rel="stylesheet" href="<?= base_url('assets/css/navigation_links.css') ?>">
<?php
$links = [
    ['label' => lang('ctrl_patient'),           'url' => base_url('Patient')],
    ['label' => lang('ctrl_mrp'),               'url' => base_url('MRP')],
    ['label' => lang('ctrl_waiting_list'),      'url' => base_url('WaitingList')],
    ['label' => lang('ctrl_update_patient'),    'url' => base_url('UpdatePatient')],
    ['label' => lang('ctrl_pairs'),             'url' => base_url('Pairs')],
    ['label' => lang('ctrl_dashboard'),         'url' => base_url('Dashboard'),],
];
?>

<div class="side-bar">
    <div class="KAMC-logo">
        <img class="KAMC-image" src="<?= base_url('assets/img/KAMC_long.png') ?>" />
    </div>
    <div class="nav-links">
        <?php foreach ($links as $link): ?>
            <a class="nav-link" href="<?= $link['url'] ?>"><?= $link['label'] ?></a>
        <?php endforeach; ?>
    </div>
</div>