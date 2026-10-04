<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\UiStore;

/**
 * "Required Lab Tests" card. Was `js/lab-section.js`.
 *
 * Every test is a real form control now: the status buttons write into a
 * hidden `status` input and the value / date fields live inside the card from
 * the start, marked `hidden` until the pencil is clicked. So the card arrives
 * as finished HTML and posts with the form it sits in, where the prototype had
 * to build the card and its editor from strings on each click.
 *
 * Comment is the exception: the check list prints a comment line under every
 * single test, so it is on the face of the card rather than behind the pencil.
 * Reading a workup, it is where a Positive or an Abnormal says what it was.
 *
 * @var list<array<string, mixed>> $tests      Each carries the group it is listed under.
 * @var string                     $field      Form field prefix, e.g. "labs" or "rLabs".
 * @var bool                       $animated   Adds the colour transition (PersonForm / AddPair).
 * @var bool                       $editing    False renders the workup read-only.
 * @var string|null                $editUrl    Where the card's Edit goes; null hides it.
 * @var string|null                $viewUrl    Where Cancel goes.
 * @var string|null                $section    Which card a save is for.
 * @var string                     $labsTitle  The heading, where more than one workup is on the screen.
 */
$animated = $animated ?? false;
// The add screens have nothing to view yet, so they default to editable with
// no Edit button of their own.
$editing  = $editing ?? true;
// Where Add lab posts and where Remove goes. Null on a screen that has no
// record yet — there is nobody for a test to belong to.
$addLabUrl    = $addLabUrl ?? null;
$removeLabUrl = $removeLabUrl ?? null;
$editUrl  = $editUrl ?? null;
$viewUrl  = $viewUrl ?? null;
$section  = $section ?? null;
// A screen that shows more than one person's workup has to say whose. Named
// `labsTitle` rather than `title`, because CodeIgniter keeps view data between
// `view()` calls and the page's own `$title` would otherwise land here — which
// it did, and the recipient's workup was headed with the recipient's name.
$labsTitle = $labsTitle ?? 'Required Lab Tests';
$progress = UiStore::labProgress($tests);

// The check list's own groups, in its own order. The index has to keep
// running across them — the form posts one flat array of tests, so a card's
// position in it is what ties its fields together, not the group it is under.
$groups = [];

foreach ($tests as $i => $test) {
    $groups[$test['group'] ?? ''][$i] = $test;
}

// The group that takes the tests a record adds is the one group that has to
// be there when it is empty — it is where the button to add the first one
// lives, and nothing is under it until that button is pressed. Last, as the
// check list had it.
if (($addLabUrl ?? null) !== null && ! isset($groups[DatabaseSeeder::CUSTOM_GROUP])) {
    $groups[DatabaseSeeder::CUSTOM_GROUP] = [];
}
?>
<div class="card card--pad" data-lab-section>
    <div class="card-head">
        <div class="lab-head">
            <div>
                <h2 class="card-title"><?= $labsTitle ?></h2>
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
    <?php // The one group the check list seeds nothing under: it heads the
          // tests this record adds for itself, so it is the one with a button. ?>
    <?php $isCustomGroup = $groupName === DatabaseSeeder::CUSTOM_GROUP; ?>
    <div class="lab-group">
        <?php if ($groupName !== ''): ?>
            <div class="lab-group-head">
                <h3 class="lab-group-name"><?= esc($groupName) ?></h3>
                <?php if ($isCustomGroup && $addLabUrl !== null): ?>
                    <?php // A plain post: it creates the test and comes back to
                          // this card, so it needs nothing from the browser. ?>
                    <button type="submit" class="btn-add-lab" formaction="<?= esc($addLabUrl) ?>" formnovalidate><?= ui_icon('plus') ?>Add lab</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($isCustomGroup && $groupTests === []): ?>
            <p class="lab-group-empty">No tests added. Use <strong>Add lab</strong> for anything the check list has no line for.</p>
        <?php endif; ?>
        <div class="lab-grid">
        <?php foreach ($groupTests as $i => $test): ?>
            <?php // $field and $i are ours, not user input, so the name needs no escaping. ?>
            <?php $base = $field . '[' . $i . ']'; ?>
            <?php // "Other" is the sheet's blank line: no answer to give, so no
                  // buttons and no pill — the card is its box and nothing else. ?>
            <?php $freeText = in_array($test['resultType'], UiStore::FREE_TEXT_TYPES, true); ?>
            <?php // A test whose answer list has no "Not done" — a vaccination —
                  // starts with nothing on it rather than with a word it cannot
                  // offer. The pill is in the markup all the same, so pressing
                  // an answer has something to fill. ?>
            <?php
            // This test's own answers, with the colour each carries on it.
            $answers  = $test['answers'] ?? [];
            $byKey    = array_column($answers, null, 'key');
            $answered = isset($byKey[$test['status']]);
            $custom   = (bool) ($test['custom'] ?? false);
            $nameId   = $field . '-' . $i . '-name';
            // A test the record added is anchored, so its own Edit can bring
            // the page back to this card rather than to the top of the workup.
            $cardId   = 'lab-' . $test['id'];
            // What the card calls this answer and what colour it is — its own
            // word for it when somebody wrote one, ours otherwise.
            $label = static fn (string $key): string => $byKey[$key]['label'] ?? UiStore::RESULT_LABEL[$key] ?? $key;
            // A test the record added wears only the colours somebody chose
            // for it: '' means none was, and the answer shows plain. The check
            // list's own tests keep the colours the sheet gives them.
            $tone  = static fn (string $key): string => isset($byKey[$key])
                ? (string) $byKey[$key]['tone']
                : ui_tone('labStatus', $key);
            ?>
            <div class="lab-card<?= $animated ? ' lab-card--animated' : '' ?><?= $freeText ? ' lab-card--free' : '' ?> status-<?= esc($test['status']) ?>"<?= $custom ? ' id="' . esc($cardId) . '"' : '' ?> data-idx="<?= $i ?>">
                <input type="hidden" name="<?= $base ?>[id]" value="<?= esc($test['id']) ?>">
                <?php if (! $custom): ?>
                    <input type="hidden" name="<?= $base ?>[name]" value="<?= esc($test['name']) ?>">
                <?php endif; ?>
                <input type="hidden" name="<?= $base ?>[status]" value="<?= esc($test['status']) ?>" data-lab-status-value>

                <div class="lab-card-head">
                    <div class="lab-info">
                        <?php if ($custom): ?>
                            <?php // Its name is typed where it is read, and saved
                                  // with the answer — the check list did not
                                  // supply it, so the record does. ?>
                            <label class="sr-only" for="<?= $nameId ?>">Test name</label>
                            <input type="text" id="<?= $nameId ?>" class="lab-name-field" name="<?= $base ?>[name]"
                                   value="<?= esc($test['name']) ?>" placeholder="Name of the test" maxlength="150" autocomplete="off">
                        <?php else: ?>
                            <div class="lab-name"><?= esc($test['name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (! $freeText): ?>
                        <?php $pillTone = $tone($test['status']); ?>
                        <span class="lab-pill <?= $pillTone === '' ? 'tone-none' : esc($pillTone) ?>" data-lab-pill<?= $answered ? '' : ' hidden' ?>><?= $answered ? esc($label($test['status'])) : '' ?></span>
                    <?php endif; ?>
                    <?php if ($custom && ! $editing && $editUrl !== null): ?>
                        <?php // The saved test's own Edit: the workup's pencil
                              // opens the whole card, and this one opens it at
                              // this test, where the answer list it was given
                              // is waiting with its words and its colours. ?>
                        <a class="lab-edit-answers" href="<?= esc($editUrl) ?>#<?= esc($cardId) ?>"><?= ui_icon('edit') ?>Edit results</a>
                    <?php endif; ?>
                </div>

                <?php if (! $freeText): ?>
                <div class="lab-actions">
                    <?php // This test's own answers: a serology offers Positive /
                          // Negative, a referral Cleared / not, and a test the
                          // record added offers whatever it was given. The tone
                          // and label ride on the button so ui.js can restyle
                          // the card without a copy of every vocabulary. ?>
                    <?php foreach ($answers as $answer): ?>
                        <?php // A colour somebody chose shows on every answer
                              // that has one, not only on the one recorded:
                              // choosing them was the point. An answer nobody
                              // coloured is plain, recorded or not. ?>
                        <?php $answerTone = (string) $answer['tone']; ?>
                        <button type="button" class="lab-status-btn<?= $custom && $answerTone !== '' ? ' lab-status-btn--tinted ' . esc($answerTone) : '' ?><?= $test['status'] === $answer['key'] ? ' is-active' . ($answerTone === '' ? '' : ' ' . esc($answerTone)) : '' ?>" data-lab-status="<?= esc($answer['key']) ?>" data-lab-tone="<?= esc($answerTone) ?>" data-lab-label="<?= esc($answer['label']) ?>"<?= in_array($answer['key'], UiStore::RESULT_UNANSWERED, true) ? ' data-lab-unanswered' : '' ?>><?= esc($answer['label']) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($custom && $editing): ?>
                    <?php // What this test answers is its own question, asked
                          // once, on the card — because only the person adding
                          // the test knows what it asks. Ticked answers are the
                          // buttons above; the rest are offered and nothing
                          // more, and vanish from the card once it is saved.
                          //
                          // Checkboxes and a `<details>` of radios, so choosing
                          // an answer and choosing its colour both work with
                          // scripting off. ?>
                    <?= view('ui/partials/lab_answer_picker', [
                        'base'    => $base,
                        'idBase'  => $field . '-' . $i,
                        'answers' => $answers,
                        'byKey'   => $byKey,
                    ], ['saveData' => false]) ?>
                <?php endif; ?>

                <?php // The sheet's comment line. Free text on every test, and
                      // on HLA typing the loci it asks to be filled in with. ?>
                <?php if ($editing || $freeText || ($test['notes'] ?? '') !== ''): ?>
                    <?php
                    $hint      = UiStore::COMMENT_HINT[$test['name']] ?? null;
                    $commentId = $field . '-' . $i . '-comment';
                    ?>
                    <div class="lab-comment">
                        <label class="lab-comment-label" for="<?= $commentId ?>"><?= $freeText ? 'Notes' : 'Comment' ?></label>
                        <textarea id="<?= $commentId ?>" class="lab-comment-field" name="<?= $base ?>[notes]"
                                  rows="<?= $freeText ? 3 : ($hint['rows'] ?? 1) ?>"<?= $hint === null ? ($freeText ? ' placeholder="Anything the workup has no line for"' : '') : ' placeholder="' . esc($hint['placeholder']) . '"' ?>><?= esc($test['notes'] ?? '') ?></textarea>
                    </div>
                <?php endif; ?>

                <?php if ($custom && $removeLabUrl !== null): ?>
                    <?php // Theirs to add, theirs to take away — at the foot of
                          // the card, after everything it holds. The question is
                          // asked on the page it leads to, so this is a link. ?>
                    <div class="lab-remove">
                        <a class="lab-remove-link" href="<?= esc($removeLabUrl) ?>/<?= esc($test['id']) ?>/delete"><?= ui_icon('trash') ?>Remove this test</a>
                    </div>
                <?php endif; ?>
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
