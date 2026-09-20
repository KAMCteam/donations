<?php

use App\Libraries\UiStore;

/**
 * "Required Lab Tests" card. Was `js/lab-section.js`.
 *
 * Every test is a real form control now: the three status buttons write into a
 * hidden `status` input and the result / date / notes fields live inside the
 * card from the start, marked `hidden` until the pencil is clicked. So the card
 * arrives as finished HTML and posts with the form it sits in, where the
 * prototype had to build the card and its editor from strings on each click.
 *
 * @var list<array<string, mixed>> $tests      Each carries the group it is listed under.
 * @var string                     $field      Form field prefix, e.g. "labs" or "rLabs".
 * @var bool                       $animated   Adds the colour transition (PersonForm / AddPair).
 * @var string|null                $editTitle  Tooltip on the pencil (PersonForm only).
 * @var bool                       $editing    False renders the workup read-only.
 * @var string|null                $editUrl    Where the card's Edit goes; null hides it.
 * @var string|null                $viewUrl    Where Cancel goes.
 * @var string|null                $section    Which card a save is for.
 */
$animated  = $animated ?? false;
$editTitle = $editTitle ?? null;
// The add screens have nothing to view yet, so they default to editable with
// no Edit button of their own.
$editing  = $editing ?? true;
$editUrl  = $editUrl ?? null;
$viewUrl  = $viewUrl ?? null;
$section  = $section ?? null;
$progress = UiStore::labProgress($tests);

// The check list's own groups, in its own order. The index has to keep
// running across them — the form posts one flat array of tests, so a card's
// position in it is what ties its fields together, not the group it is under.
$groups = [];

foreach ($tests as $i => $test) {
    $groups[$test['group'] ?? ''][$i] = $test;
}
?>
<div class="card card--pad" data-lab-section>
    <div class="card-head">
        <div class="lab-head">
            <div>
                <h2 class="card-title">Required Lab Tests</h2>
                <p class="lab-count" data-lab-count><?= $progress['done'] ?> of <?= $progress['total'] ?> completed</p>
            </div>
            <div class="lab-progress">
                <div class="progress">
                    <div class="progress-fill" data-lab-fill style="width:<?= $progress['pct'] ?>%;background-color:#15508A"></div>
                </div>
                <span class="lab-pct" data-lab-pct><?= $progress['pct'] ?>%</span>
            </div>
        </div>
        <?php if (! $editing && $editUrl !== null): ?>
            <a class="btn-edit" href="<?= esc($editUrl) ?>"><?= ui_icon('edit') ?>Edit</a>
        <?php endif; ?>
    </div>

    <fieldset class="card-fields"<?= $editing ? '' : ' disabled' ?>>
    <?php if ($editing && $section !== null): ?>
        <input type="hidden" name="section" value="<?= esc($section) ?>">
    <?php endif; ?>
    <?php foreach ($groups as $groupName => $groupTests): ?>
    <div class="lab-group">
        <?php if ($groupName !== ''): ?>
            <h3 class="lab-group-name"><?= esc($groupName) ?></h3>
        <?php endif; ?>
        <div class="lab-grid">
        <?php foreach ($groupTests as $i => $test): ?>
            <?php // $field and $i are ours, not user input, so the name needs no escaping. ?>
            <?php $base = $field . '[' . $i . ']'; ?>
            <div class="lab-card<?= $animated ? ' lab-card--animated' : '' ?> status-<?= esc($test['status']) ?>" data-idx="<?= $i ?>">
                <input type="hidden" name="<?= $base ?>[id]" value="<?= esc($test['id']) ?>">
                <input type="hidden" name="<?= $base ?>[name]" value="<?= esc($test['name']) ?>">
                <input type="hidden" name="<?= $base ?>[status]" value="<?= esc($test['status']) ?>" data-lab-status-value>

                <div class="lab-card-head">
                    <div class="lab-info">
                        <div class="lab-name"><?= esc($test['name']) ?></div>
                        <div class="lab-result" data-lab-result<?= ($test['result'] ?? '') === '' ? ' hidden' : '' ?>><?= esc($test['result'] ?? '') ?></div>
                        <div class="lab-date" data-lab-date-text<?= ($test['date'] ?? '') === '' ? ' hidden' : '' ?>><?= esc($test['date'] ?? '') ?></div>
                    </div>
                    <span class="lab-pill <?= ui_tone('labStatus', $test['status']) ?>" data-lab-pill><?= esc(UiStore::RESULT_LABEL[$test['status']] ?? $test['status']) ?></span>
                </div>

                <div class="lab-actions">
                    <?php // This test's own answers, from the check list: a serology
                          // offers Positive / Negative, a referral Cleared / not.
                          // The tone and label ride on the button so ui.js can
                          // restyle the card without a copy of every vocabulary. ?>
                    <?php foreach (UiStore::RESULT_OPTIONS[$test['resultType']] ?? UiStore::RESULT_OPTIONS['text'] as $status): ?>
                        <button type="button" class="lab-status-btn<?= $test['status'] === $status ? ' is-active ' . ui_tone('labStatus', $status) : '' ?>" data-lab-status="<?= esc($status) ?>" data-lab-tone="<?= esc(ui_tone('labStatus', $status)) ?>" data-lab-label="<?= esc(UiStore::RESULT_LABEL[$status]) ?>"<?= in_array($status, UiStore::RESULT_UNANSWERED, true) ? ' data-lab-unanswered' : '' ?>><?= esc(UiStore::RESULT_LABEL[$status]) ?></button>
                    <?php endforeach; ?>
                    <button type="button" class="lab-edit-btn" data-lab-edit<?= $editTitle !== null ? ' title="' . esc($editTitle) . '"' : '' ?>><?= ui_icon('edit') ?></button>
                </div>

                <div class="lab-editor stack-2" data-lab-editor hidden>
                    <input type="text" class="lab-editor-field" name="<?= $base ?>[result]" value="<?= esc($test['result'] ?? '') ?>" placeholder="Result / finding">
                    <input type="text" class="lab-editor-field lab-editor-field--mono" name="<?= $base ?>[date]" value="<?= esc($test['date'] ?? '') ?>" placeholder="Date (DD/MM/YYYY)" inputmode="numeric" maxlength="10" autocomplete="off" data-date-text>
                    <textarea class="lab-editor-field" name="<?= $base ?>[notes]" placeholder="Notes (optional)" rows="2"><?= esc($test['notes'] ?? '') ?></textarea>
                    <button type="button" class="lab-editor-save" data-lab-save>Save</button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </fieldset>

    <?php if ($editing && $viewUrl !== null): ?>
        <div class="card-actions">
            <a class="btn-outline" href="<?= esc($viewUrl) ?>">Cancel</a>
            <button type="submit" class="btn-save">Save</button>
        </div>
    <?php endif; ?>
</div>
