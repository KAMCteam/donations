<?php

/**
 * The two ways to pair somebody, as the body of a dialog.
 *
 * Shared by the record screen — where it sits inside a <dialog> that "Link
 * with …" opens — and by `ui/link_choice`, the page that same button links to
 * so the choice is still reachable with JavaScript off.
 *
 * @var string                $counterpart  "donor" or "recipient"
 * @var string                $newUrl       Add Pair, with this person filled in
 * @var string                $existingUrl  The list of unpaired counterparts
 * @var array<string, mixed>  $person
 */
$label = ucfirst($counterpart);
?>
<h2 class="link-choice-title">Link <?= esc($person['name']) ?></h2>
<p class="link-choice-sub">Choose how to pair this <?= esc($counterpart === 'donor' ? 'recipient' : 'donor') ?>.</p>

<div class="link-choice-options">
    <a class="link-choice" href="<?= esc($newUrl) ?>">
        <span class="link-choice-icon"><?= ui_icon('plus') ?></span>
        <span>
            <span class="link-choice-label">Link with a new <?= esc($counterpart) ?></span>
            <span class="link-choice-hint">Opens Add Pair with this record already filled in — you enter the <?= esc($counterpart) ?>.</span>
        </span>
    </a>

    <a class="link-choice" href="<?= esc($existingUrl) ?>">
        <span class="link-choice-icon"><?= ui_icon('link14') ?></span>
        <span>
            <span class="link-choice-label">Link with an existing <?= esc($counterpart) ?></span>
            <span class="link-choice-hint">Choose from the <?= esc($counterpart) ?>s already registered and not yet paired.</span>
        </span>
    </a>
</div>
