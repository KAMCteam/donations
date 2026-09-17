<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

/**
 * Register a Medical Responsible Person. Was `js/pages/add-mrp.js`.
 *
 * The success banner used to be a hidden node toggled by the submit handler;
 * it is now rendered only after a successful post, off a flash message.
 *
 * @var bool                                  $saved
 * @var list<array{id: string, name: string}> $mrps
 */
?>
<div class="page">
    <div class="page-header page-header--plain">
        <div class="eyebrow">New</div>
        <h1 class="page-title">Add MRP</h1>
        <p class="page-subtitle">Register a new Medical Responsible Person.</p>
    </div>

    <div class="mrp-wrap">
        <?php if ($saved): ?>
            <div class="mrp-success"><?= ui_icon('check') ?>MRP added successfully.</div>
        <?php endif; ?>

        <form class="card card--pad stack-5" method="post" action="<?= site_url('mrp') ?>" novalidate>
            <?= csrf_field() ?>
            <div>
                <label class="mrp-label" for="mrp-id">MRP ID</label>
                <input type="text" id="mrp-id" name="id" class="input" placeholder="e.g. MRP-004" style="font-family:&quot;DM Mono&quot;, monospace">
            </div>
            <div>
                <label class="mrp-label" for="mrp-name">Full name</label>
                <input type="text" id="mrp-name" name="name" class="input" placeholder="e.g. Dr. Amira Hassan">
            </div>
            <button type="submit" class="mrp-submit">Add MRP</button>
        </form>

        <?php if ($mrps !== []): ?>
            <div class="mrp-list-block">
                <h2 class="mrp-list-title">Registered MRPs</h2>
                <div class="card card--clip">
                    <?php foreach ($mrps as $mrp): ?>
                        <div class="mrp-item">
                            <div>
                                <div class="mrp-name"><?= esc($mrp['name']) ?></div>
                                <div class="mrp-id"><?= esc($mrp['id']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
