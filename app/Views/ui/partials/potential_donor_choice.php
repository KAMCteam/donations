<?php

/**
 * The two ways to add a potential donor, as the body of a dialog.
 *
 * A recipient is worked up against several donors at once, so this is a list
 * being added to rather than a pair being made: neither choice creates a pair
 * and neither puts anybody on the Donors List. The pair comes later, from one
 * tab, when somebody presses Pair up.
 *
 * The second choice is a select rather than a page of its own, because the
 * whole of this belongs on the recipient's record — and because what you need
 * in order to choose is the name, the blood group and the age, all of which
 * fit on a line.
 *
 * @var array<string, mixed>       $person      The recipient
 * @var string                     $newUrl      Add Donor, entered for this recipient
 * @var string                     $considerUrl Where the select posts
 * @var list<array<string, mixed>> $candidates  Donors on the register, unpaired
 */
?>
<h2 class="link-choice-title">Add a potential donor for <?= esc($person['name']) ?></h2>
<p class="link-choice-sub">No pair is made yet, and nobody joins the Donors List. This only says the two are being looked at together.</p>

<div class="link-choice-options">
    <a class="link-choice" href="<?= esc($newUrl) ?>">
        <span class="link-choice-icon"><?= ui_icon('plus') ?></span>
        <span>
            <span class="link-choice-label">A new donor</span>
            <span class="link-choice-hint">Enter somebody who is not on the system yet. They are stored against this recipient, with a workup of their own.</span>
        </span>
    </a>

    <?php if ($candidates === []): ?>
        <div class="link-choice link-choice--empty">
            <span class="link-choice-icon"><?= ui_icon('link14') ?></span>
            <span>
                <span class="link-choice-label">A donor already registered</span>
                <span class="link-choice-hint">Nobody on the register is free to be considered — everyone is either in a pair or already on this list.</span>
            </span>
        </div>
    <?php else: ?>
        <form class="link-choice link-choice--form" method="post" action="<?= esc($considerUrl) ?>">
            <?= csrf_field() ?>
            <span class="link-choice-icon"><?= ui_icon('link14') ?></span>
            <span>
                <label class="link-choice-label" for="consider-donor">A donor already registered</label>
                <span class="link-choice-hint">Choose from the donors on the register who are not in a pair.</span>
                <span class="link-choice-pick">
                    <select id="consider-donor" name="donorMrn" class="input">
                        <?php foreach ($candidates as $donor): ?>
                            <option value="<?= esc($donor['id']) ?>">
                                <?= esc($donor['name']) ?> (<?= esc($donor['id']) ?>)
                                &mdash; <?= esc($donor['bloodType']) ?><?= ($donor['age'] ?? 0) > 0 ? ', ' . esc((string) $donor['age']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-primary">Add</button>
                </span>
            </span>
        </form>
    <?php endif; ?>
</div>
