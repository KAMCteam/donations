<?php

use App\Libraries\UiStore;

/**
 * What a test somebody added answers, and in what colours.
 *
 * The check list's tests each answer a fixed question, because the sheet they
 * come from asks one. A test added under **Other** has no sheet behind it —
 * only the person adding it knows whether it says Cleared, or Seen, or
 * something the platform has no word for at all. So this is where they say.
 *
 * Every answer the platform has is offered, each already wearing the colour
 * the platform would have given it, because there is nothing to be gained by
 * asking somebody to paint Positive red. Ticking one puts it on the card and
 * leaves it there; the rest disappear when the card is saved, and the saved
 * card then behaves like every other: the answers wear their colours and the
 * one recorded colours the card.
 *
 * Beside each answer is a swatch opening the five colours — Red, Yellow,
 * Green, Blue, Gray — named for themselves rather than for what they are
 * supposed to mean, because what they mean is the test's business.
 *
 * Checkboxes and a `<details>` of radios: ticking an answer and choosing its
 * colour both work with scripting off. The + beside **Add custom result**, and
 * the pencil and bin on a result somebody wrote, are `ui.js`; without it the
 * box at the foot still adds one on save and rubbing a name out still removes
 * it, which is how it worked before there were buttons.
 *
 * @var string                     $base     The field prefix for this test
 * @var string                     $idBase   Unique id stem for its controls
 * @var list<array<string, mixed>> $answers  What it answers now
 * @var array<string, array<string, mixed>> $byKey  The same, keyed
 */
// Ours first, in the order the platform lists them, then any they wrote — so
// the list reads the same on every test and their own are where they left off.
$offered = [];

foreach (UiStore::RESULT_OPTIONS['custom'] as $key) {
    $offered[] = ['key' => $key, 'label' => UiStore::RESULT_LABEL[$key], 'own' => false];
}

foreach ($answers as $answer) {
    if ($answer['own']) {
        $offered[] = ['key' => $answer['key'], 'label' => $answer['label'], 'own' => true];
    }
}

/**
 * The swatch and the five colours behind it, for one answer.
 *
 * "No colour" is first, so a colour can be taken back off again.
 */
$palette = static function (string $name, string $current) {
    $current = UiStore::labTone($current);
    ?>
    <details class="tone-picker">
        <summary class="tone-swatch <?= $current === '' ? 'tone-swatch--none' : esc($current) ?>"
                 title="<?= $current === '' ? 'No colour' : 'Colour: ' . esc(UiStore::LAB_TONES[$current]) ?>">
            <span class="sr-only">Colour for this answer</span>
        </summary>
        <div class="tone-menu">
            <label class="tone-option">
                <input type="radio" name="<?= $name ?>" value=""<?= $current === '' ? ' checked' : '' ?>>
                <span class="tone-dot tone-dot--none"></span>
                <span class="tone-name">No colour</span>
            </label>
            <?php foreach (UiStore::LAB_TONES as $tone => $named): ?>
                <label class="tone-option">
                    <input type="radio" name="<?= $name ?>" value="<?= esc($tone) ?>"<?= $current === $tone ? ' checked' : '' ?>>
                    <span class="tone-dot <?= esc($tone) ?>"></span>
                    <span class="tone-name"><?= esc($named) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </details>
    <?php
};
?>
<div class="lab-answers" data-answer-picker data-base="<?= esc($base) ?>">
    <div class="lab-answers-head">What this test result offers</div>
    <p class="lab-answers-hint">Select the results it offers and assign a colour to each. Only the selected results will appear on the card after it is saved.
</p>

    <div class="lab-answer-list" data-answer-list>
        <?php foreach ($offered as $option): ?>
            <?php
            $key   = (string) $option['key'];
            $on = isset($byKey[$key]);
            // An answer nobody has ticked shows the colour the platform would
            // give it, so the list arrives coloured and there is nothing to
            // paint before ticking. One that is ticked shows what it was given
            // — including no colour at all, if that is what was chosen.
            $tone = $on
                ? UiStore::labTone((string) $byKey[$key]['tone'])
                : UiStore::labTone(UiStore::RESULT_TONE[$key] ?? '');
            $field = $base . '[answers][' . $key . ']';
            $rowId = $idBase . '-ans-' . $key;
            ?>
            <div class="lab-answer<?= $on ? ' is-on' : '' ?>">
                <label class="lab-answer-tick" for="<?= esc($rowId) ?>">
                    <input type="checkbox" id="<?= esc($rowId) ?>" name="<?= $field ?>[on]" value="1"<?= $on ? ' checked' : '' ?>>
                    <?php if ($option['own']): ?>
                        <input type="text" class="lab-answer-name" name="<?= $field ?>[label]"
                               value="<?= esc($option['label']) ?>" maxlength="<?= UiStore::CUSTOM_ANSWER_MAX ?>"
                               aria-label="Name of this result" autocomplete="off">
                    <?php else: ?>
                        <span class="lab-answer-label badge <?= $tone === '' ? 'tone-none' : esc($tone) ?>"><?= esc($option['label']) ?></span>
                        <input type="hidden" name="<?= $field ?>[label]" value="<?= esc($option['label']) ?>">
                    <?php endif; ?>
                </label>
                <?php $palette($field . '[tone]', $tone); ?>
                <?php if ($option['own']): ?>
                    <?php // Theirs to rename and theirs to take away. The
                          // pencil opens the name for typing; the bin takes
                          // the row off the card, and saving is what makes
                          // either of them true. ?>
                    <button type="button" class="lab-answer-act" data-answer-edit title="Rename this result"><?= ui_icon('edit') ?><span class="sr-only">Rename this result</span></button>
                    <button type="button" class="lab-answer-act lab-answer-act--danger" data-answer-remove title="Delete this result"><?= ui_icon('trash') ?><span class="sr-only">Delete this result</span></button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php // The box that adds one. Pressing + puts it in the list above and
          // clears the box, so several can be added before the card is saved;
          // with scripting off the one in the box is added on save, which is
          // how this worked before the button existed. ?>
    <div class="lab-answer-new">
        <label class="lab-answer-new-label" for="<?= esc($idBase) ?>-new">Add custom result</label>
        <input type="text" id="<?= esc($idBase) ?>-new" class="lab-answer-name" name="<?= $base ?>[newAnswer]"
               value="" placeholder="Name of the result" maxlength="<?= UiStore::CUSTOM_ANSWER_MAX ?>" autocomplete="off">
        <?php $palette($base . '[newAnswerTone]', ''); ?>
        <button type="button" class="lab-answer-add" data-answer-add title="Add this result"><?= ui_icon('plus') ?><span class="sr-only">Add this result</span></button>
    </div>
</div>
