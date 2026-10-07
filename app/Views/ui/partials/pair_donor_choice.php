<?php

use App\Libraries\UiStore;

/**
 * The two ways to put a donor on a pair, as the body of a dialog.
 *
 * A pair is worked up against more than one donor — they are taken on, tested,
 * and let go one by one — so this adds to the pair rather than making another
 * one. Both ways ask the same second question: the word the pair starts this
 * donor on. Active is the donor it is going ahead with and there is one of
 * those, so it is only offered while the pair has nobody holding it.
 *
 * The second choice is a select rather than a page of its own, because the
 * whole of this belongs on the pair's screen — and because what you need in
 * order to choose is the name, the blood group and the age, all of which fit
 * on a line.
 *
 * @var array<string, mixed>       $pair
 * @var string                     $newUrl    Add Donor, entered for this pair
 * @var string                     $addUrl    Where the select posts
 * @var list<array<string, mixed>> $offerable Donors free on the register
 * @var bool                       $hasActive Whether the pair has its donor already
 */
// The words a donor can be put on. Active only while nobody holds it: the pair
// goes ahead with one donor, and saying so twice says nothing.
$offered = $hasActive
    ? array_diff_key(UiStore::PERSON_STATUS_OPTIONS, ['active' => ''])
    : UiStore::PERSON_STATUS_OPTIONS;
?>
<h2 class="link-choice-title">Add a donor to this pair</h2>
<p class="link-choice-sub">
    <?= $hasActive
        ? 'This pair already has the donor it is going ahead with, so a new one starts on hold or declined.'
        : 'This pair has nobody active, so a new donor can be started there straight away.' ?>
</p>

<div class="link-choice-options">
    <a class="link-choice" href="<?= esc($newUrl) ?>">
        <span class="link-choice-icon"><?= ui_icon('plus') ?></span>
        <span>
            <span class="link-choice-label">A new donor</span>
            <span class="link-choice-hint">Link with a new donor
Opens Add Donor page to link this record with a new donor not yet registered in the system.</span>
        </span>
    </a>

    <?php if ($offerable === []): ?>
        <div class="link-choice link-choice--empty">
            <span class="link-choice-icon"><?= ui_icon('link14') ?></span>
            <span>
                <span class="link-choice-label">A donor already registered</span>
                <span class="link-choice-hint">Nobody on the register is free &mdash; everyone is either in a pair or already on this one.</span>
            </span>
        </div>
    <?php else: ?>
        <form class="link-choice link-choice--form" method="post" action="<?= esc($addUrl) ?>">
            <?= csrf_field() ?>
            <span class="link-choice-icon"><?= ui_icon('link14') ?></span>
            <span>
                <label class="link-choice-label" for="add-donor-mrn">A donor already registered</label>
                <span class="link-choice-hint">Choose from the donors on the register who are not in a pair.</span>
                <span class="link-choice-pick">
                    <select id="add-donor-mrn" name="donorMrn" class="input">
                        <?php foreach ($offerable as $donor): ?>
                            <option value="<?= esc($donor['id']) ?>">
                                <?= esc($donor['name']) ?> (<?= esc($donor['id']) ?>)
                                &mdash; <?= esc($donor['bloodType']) ?><?= ($donor['age'] ?? 0) > 0 ? ', ' . esc((string) $donor['age']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="sr-only" for="add-donor-status">Status</label>
                    <select id="add-donor-status" name="status" class="input">
                        <?php foreach ($offered as $value => $label): ?>
                            <option value="<?= esc($value) ?>"<?= $value === 'on_hold' ? ' selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-primary">Add</button>
                </span>
            </span>
        </form>
    <?php endif; ?>
</div>
