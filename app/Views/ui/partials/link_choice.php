<?php

/**
 * The two ways to pair somebody, as the body of a dialog.
 *
 * A recipient is paired with a donor who is already on the register, or with
 * one who is being entered now, and that is the whole of the choice. It used
 * to be a choice of *pages*: the second option led to a screen listing every
 * unpaired counterpart in a table, a row at a time, with Pair Details above
 * it. That screen is gone. Choosing somebody already registered needs their
 * name, their blood group and their age, which fit on a line, so the list is a
 * select and the choice is made without leaving the record.
 *
 * Nothing else is asked here. The relationship and the crossmatch date used to
 * be filled in on the way through; they belong to the pair and the pair's own
 * screen is where they are entered, which is where this lands.
 *
 * Shared by the record screen — where it sits inside a <dialog> that "Link
 * with …" opens — and by `ui/link_choice`, the page that same button links to
 * so the choice is still reachable with JavaScript off. The select is a plain
 * form either way: it posts, it makes the pair, it opens it.
 *
 * @var string                     $counterpart  "donor" or "recipient"
 * @var string                     $newUrl       Add Pair, with this person filled in
 * @var string                     $existingUrl  Where the select posts
 * @var array<string, mixed>       $person
 * @var list<array<string, mixed>> $candidates   The counterparts free to pair
 */
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

    <?php if ($candidates === []): ?>
        <div class="link-choice link-choice--empty">
            <span class="link-choice-icon"><?= ui_icon('link14') ?></span>
            <span>
                <span class="link-choice-label">Link with an existing <?= esc($counterpart) ?></span>
                <span class="link-choice-hint">Nobody on the <?= esc($counterpart) ?> list is free to pair.</span>
            </span>
        </div>
    <?php else: ?>
        <form class="link-choice link-choice--form" method="post" action="<?= esc($existingUrl) ?>">
            <?= csrf_field() ?>
            <span class="link-choice-icon"><?= ui_icon('link14') ?></span>
            <span>
                <label class="link-choice-label" for="link-choice-mrn">Link with an existing <?= esc($counterpart) ?></label>
                <span class="link-choice-hint">Choose from the <?= esc($counterpart) ?>s on the list who are free to pair.</span>
                <span class="link-choice-pick">
                    <select id="link-choice-mrn" name="mrn" class="input">
                        <?php foreach ($candidates as $candidate): ?>
                            <option value="<?= esc($candidate['id']) ?>">
                                <?= esc($candidate['name']) ?> (<?= esc($candidate['id']) ?>)
                                &mdash; <?= esc($candidate['bloodType']) ?><?= ($candidate['age'] ?? 0) > 0 ? ', ' . esc((string) $candidate['age']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-primary"><?= ui_icon('link14') ?>Link</button>
                </span>
            </span>
        </form>
    <?php endif; ?>
</div>
