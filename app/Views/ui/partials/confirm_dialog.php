<?php

/**
 * "Are you sure?", wherever something is about to be taken away.
 *
 * It used to be a page: pressing a bin left the list, the record or the pair
 * and loaded a screen with one question on it. Pressing it by accident meant
 * finding the way back. The question is the same question; it is asked here,
 * over the screen it was asked from, and answering it either way leaves that
 * screen where it was.
 *
 * The Delete button belongs to `confirm-post`, the empty form in the layout,
 * and carries its own `formaction`. That is what lets this sit anywhere —
 * inside the card's form, inside a table cell — because a form cannot be
 * nested in another and a dialog has to be where the thing it is about is.
 *
 * With scripting off the opener is a link to this dialog's own id, and
 * `:target` shows it: the panel appears over the screen, the button posts,
 * Cancel clears the address. So nothing here waits on JavaScript; the script
 * only makes it modal.
 *
 * @var string $id      This dialog's own id, which its opener links to
 * @var string $title   The question
 * @var string $detail  Exactly what goes, and what stays
 * @var string $action  Where the answer posts
 * @var string $confirmVerb  What the button says. "Delete" unless told otherwise
 * @var string $confirmIcon  The button's icon
 * @var list<array{value: string, label: string, hint: string, select?: array}> $confirmChoices
 *      A question with more than one answer — delinking the donor a pair is
 *      going ahead with. Empty for the ordinary "yes or no". An answer that
 *      needs to know *which* carries a `select`: {name, label, options}, each
 *      option a {value, label}.
 *
 * The three that have defaults are named for this partial and nothing else,
 * because CodeIgniter keeps view data between `view()` calls: a `$choices` of
 * ours would be whatever the last screen to use that word had in it, which is
 * how the Reports filters once ended up in a delete dialog.
 */
$confirmChoices ??= [];
$confirmVerb    ??= 'Delete';
$confirmIcon    ??= 'trash';
?>
<dialog class="dialog" id="<?= esc($id) ?>">
    <div class="confirm">
        <h2 class="confirm-title"><?= esc($title) ?></h2>
        <p class="confirm-detail"><?= esc($detail) ?></p>
        <?php if ($confirmChoices !== []): ?>
            <div class="confirm-choices">
                <?php foreach ($confirmChoices as $n => $choice): ?>
                    <?php // The radios belong to the same form as the button,
                          // so they travel with it. The safer answer is the one
                          // already chosen. ?>
                    <label class="confirm-choice">
                        <input type="radio" name="outcome" value="<?= esc($choice['value']) ?>" form="confirm-post"<?= $n === 0 ? ' checked' : '' ?>>
                        <span>
                            <span class="confirm-choice-label"><?= esc($choice['label']) ?></span>
                            <span class="confirm-choice-hint"><?= esc($choice['hint']) ?></span>
                            <?php if (isset($choice['select'])): ?>
                                <?php // Which one, asked where the answer is
                                      // given. It posts whatever is chosen;
                                      // the answer beside it is what decides
                                      // whether anybody reads it. ?>
                                <span class="confirm-choice-pick">
                                    <label class="sr-only" for="<?= esc($id . '-' . $choice['value']) ?>"><?= esc($choice['select']['label']) ?></label>
                                    <select id="<?= esc($id . '-' . $choice['value']) ?>" name="<?= esc($choice['select']['name']) ?>" class="input" form="confirm-post">
                                        <?php foreach ($choice['select']['options'] as $option): ?>
                                            <option value="<?= esc($option['value']) ?>"><?= esc($option['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="confirm-actions">
            <a class="btn-outline" href="#" data-dialog-close>Cancel</a>
            <button type="submit" class="btn-danger" form="confirm-post" formaction="<?= esc($action) ?>"><?= ui_icon($confirmIcon) ?><?= esc($confirmVerb) ?></button>
        </div>
    </div>
</dialog>
