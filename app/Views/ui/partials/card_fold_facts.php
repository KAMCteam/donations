<?php

use App\Libraries\UiStore;

/**
 * What a folded personal-details card still says about itself.
 *
 * A card shut to get it out of the way should not take with it the three
 * things anybody scans it for, so the summary keeps the name, the blood group
 * and where the person stands — and keeps them *as fields*: the label above
 * and the value in the same read-only box the card's own fields wear while
 * they are not being edited, so the shut card reads like the open one rather
 * than like a caption of it.
 *
 * Text, not controls. The card's real fields are a few lines below, and a
 * second set carrying the same names would post the same answers twice; these
 * are the values, shaped like the fields they come from.
 *
 * Only while it is shut: open, the card says all three in its own fields.
 *
 * @var string $person     "Recipient" or "Donor" — the word its labels use
 * @var string $name       The person's, as the card has it
 * @var string $bloodType
 * @var string $statusKey  on_hold | active | declined, or '' for none
 */
$statusKey   = UiStore::personStatusFromUi($statusKey) ?: $statusKey;
$statusLabel = UiStore::STATUS_OPTIONS[$statusKey] ?? '';

/**
 * One fact, in the shape of the field it came from.
 *
 * Nothing recorded shows the dash the rest of the platform shows for nothing
 * recorded, rather than an empty box that reads as a field left out.
 */
$fact = static function (string $modifier, string $label, string $value, bool $mono = false): void {
    $empty = trim($value) === '';
    ?>
    <span class="card-fold-field card-fold-field--<?= $modifier ?>">
        <span class="field-label"><?= esc($label) ?></span>
        <span class="input-ro<?= $mono ? ' input-ro--mono' : '' ?><?= $empty ? ' input-ro--faint' : '' ?>"><?= $empty ? '&mdash;' : esc($value) ?></span>
    </span>
    <?php
};
?>
<span class="card-fold-facts">
    <?php $fact('name', $person . ' Name', $name); ?>
    <?php $fact('blood', 'Blood Group', $bloodType, true); ?>
    <?php $fact('status', $person . ' Status', $statusLabel); ?>
</span>
