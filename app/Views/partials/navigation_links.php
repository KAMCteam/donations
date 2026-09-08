<link rel="stylesheet" href="<?= base_url('assets/css/navigation_links.css') ?>">
<?php
$links = [
    ['label' => lang('Form.ctrl_patient'),        'url' => site_url('Patient')],
    ['label' => lang('Form.ctrl_mrp'),            'url' => site_url('MRP')],
    ['label' => lang('Form.ctrl_waiting_list'),   'url' => site_url('WaitingList')],
    ['label' => lang('Form.ctrl_update_patient'), 'url' => site_url('UpdatePatient')],
    ['label' => lang('Form.ctrl_pairs'),          'url' => site_url('Pairs')],
    ['label' => lang('Form.ctrl_dashboard'),      'url' => site_url('Dashboard')],
];
?>

<div class="side-bar">
    <div class="KAMC-logo">
        <img class="KAMC-image" src="<?= base_url('assets/img/KAMC_long.png') ?>" />
    </div>
    <div class="nav-links">
        <?php foreach ($links as $link): ?>
            <a class="nav-link" href="<?= $link['url'] ?>"><?= esc($link['label']) ?></a>
        <?php endforeach; ?>
    </div>
</div>
