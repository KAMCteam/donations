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
 * Every answer the platform has is offered; ticking one puts it on the card as
 * a button and leaves it there, and the rest disappear when the card is saved.
 * Beside each is a swatch that opens the colours the check list's own answers
 * use — a colour on a medical record means something, so the palette names
 * what each is for rather than offering a wheel, and an answer carries none
 * until one is picked.
 *
 * Checkboxes and a `<details>` of radios: choosing an answer, choosing its
 * colour and adding one of your own all work with scripting off.
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
 * The swatch and the palette behind it, for one answer.
 *
 * An answer starts with no colour at all, and the swatch is empty until one is
 * picked: a colour on a medical record means something, so it is said on
 * purpose or not said. "No colour" is the first thing in the palette, so a
 * colour can be taken back off again.
 */
$palette = static function (string $name, string $id, string $current) {
    $current = isset(UiStore::LAB_TONES[$current]) ? $current : '';
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
            <?php foreach (UiStore::LAB_TONES as $tone => $means): ?>
                <label class="tone-option">
                    <input type="radio" name="<?= $name ?>" value="<?= esc($tone) ?>"<?= $current === $tone ? ' checked' : '' ?>>
                    <span class="tone-dot <?= esc($tone) ?>"></span>
                    <span class="tone-name"><?= esc($means) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </details>
    <?php
};
?>
<div class="lab-answers">
    <div class="lab-answers-head">What this test answers</div>
    <p class="lab-answers-hint">Tick the answers it offers and give each a colour. Only the ticked ones stay on the card after it is saved.</p>

    <div class="lab-answer-list">
        <?php foreach ($offered as $option): ?>
            <?php
            $key    = (string) $option['key'];
            $on     = isset($byKey[$key]);
            $tone   = (string) ($byKey[$key]['tone'] ?? '');
            $field  = $base . '[answers][' . $key . ']';
            $rowId  = $idBase . '-ans-' . $key;
            ?>
            <div class="lab-answer<?= $on ? ' is-on' : '' ?>">
                <label class="lab-answer-tick" for="<?= esc($rowId) ?>">
                    <input type="checkbox" id="<?= esc($rowId) ?>" name="<?= $field ?>[on]" value="1"<?= $on ? ' checked' : '' ?>>
                    <?php if ($option['own']): ?>
                        <?php // Theirs to rename, and theirs to take away: a
                              // name rubbed out is an answer removed, which is
                              // the same gesture as unticking it and reads
                              // better than a second button to press. ?>
                        <input type="text" class="lab-answer-name" name="<?= $field ?>[label]"
                               value="<?= esc($option['label']) ?>" maxlength="<?= UiStore::CUSTOM_ANSWER_MAX ?>"
                               aria-label="Name of this answer" autocomplete="off">
                    <?php else: ?>
                        <span class="lab-answer-label badge <?= $tone === '' ? 'tone-none' : esc($tone) ?>"><?= esc($option['label']) ?></span>
                        <input type="hidden" name="<?= $field ?>[label]" value="<?= esc($option['label']) ?>">
                    <?php endif; ?>
                </label>
                <?php $palette($field . '[tone]', $rowId . '-tone', (string) $tone); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php // One box, always on the card: adding an answer is typing a name and
          // saving, with no step of its own before the thing you are already
          // saving. ?>
    <div class="lab-answer-new">
        <label class="sr-only" for="<?= esc($idBase) ?>-new">Add an answer of your own</label>
        <input type="text" id="<?= esc($idBase) ?>-new" class="lab-answer-name" name="<?= $base ?>[newAnswer]"
               value="" placeholder="+ Add an answer of your own" maxlength="<?= UiStore::CUSTOM_ANSWER_MAX ?>" autocomplete="off">
        <?php $palette($base . '[newAnswerTone]', $idBase . '-new-tone', ''); ?>
    </div>
</div>
