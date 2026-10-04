<?php

use App\Libraries\UiStore;

/**
 * What a folded personal-details card still says about itself.
 *
 * A card shut to get it out of the way should not take the two things anybody
 * scans it for with it, so the summary keeps the name and where the person
 * stands. Only while it is shut: open, the card says both in its own fields.
 *
 * @var string $name       The person's, as the card has it
 * @var string $statusKey  on_hold | active | declined, or '' for none
 */
$statusKey = UiStore::personStatusFromUi($statusKey) ?: $statusKey;
$label     = UiStore::STATUS_OPTIONS[$statusKey] ?? '';
?>
<span class="card-fold-facts">
    <?php if (trim($name) !== ''): ?>
        <span class="card-fold-name"><?= esc($name) ?></span>
    <?php endif; ?>
    <?php if ($label !== ''): ?>
        <span class="badge <?= esc(ui_tone('status', $statusKey)) ?>"><?= esc($label) ?></span>
    <?php endif; ?>
</span>
