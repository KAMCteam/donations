<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\LabProgress;
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
 * @var bool                       $foldable   Render it as a card that opens and closes.
 * @var bool                       $open       Start it open; an edited card always does.
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
// The figure at the top of the card weighs the groups by what they are worth;
// each group's own bar below is that group and nothing else.
$progress = LabProgress::weighted($tests);

// A workup is seventy-odd cards long, which is a screenful and a half between
// whoever is reading and whatever comes after it. On a screen where what comes
// after matters — the pair, where the donors are below — the card folds, and
// the one line that says how the workup is going stays on the screen either
// way. `<details>` does it, so it works with scripting off.
//
// Folded shut unless it is the card being edited: a card you came here to
// change has to be open when you arrive.
$foldable = $foldable ?? false;
$open     = $editing || ($open ?? false);
// One body, two wrappers. The alternative is writing the whole card twice and
// letting the copies drift.
$box  = $foldable ? 'details' : 'div';
$head = $foldable ? 'summary' : 'div';

// The custom tests on this card that can be taken off it, and the question
// each one asks first. The dialogs are rendered under the card: they cannot
// go inside the fieldset, because a control in a disabled one is disabled
// whatever form it belongs to, and the card is disabled while it is read.
$removable = [];

// The check list's own groups, in its own order. The index has to keep
// running across them — the form posts one flat array of tests, so a card's
// position in it is what ties its fields together, not the group it is under.
$groups = [];

foreach ($tests as $i => $test) {
    $groups[$test['group'] ?? ''][$i] = $test;
}

// The group that takes the tests a record adds is the one group that has to
// be there when it is empty — it is where a test gets added, and nothing is
// under it until one is. Last, as the check list had it.
//
// On every screen that can be written on, Add screens included. It used to
// appear only where there was a record to add a test to, which meant the one
// group somebody might actually need while entering a patient was the one
// group they could not see: the sheet in front of them had no line for the
// test their consultant had asked for, and nothing on the screen said there
// would ever be one.
if (($editing || $addLabUrl !== null) && ! isset($groups[DatabaseSeeder::CUSTOM_GROUP])) {
    $groups[DatabaseSeeder::CUSTOM_GROUP] = [];
}

// How each group is going, the same count as the card's own. A workup is read
// group by group — immunology is somebody's morning and serology is somebody
// else's — so "28%" against the whole sheet says less than the groups do.
//
// A group of nothing but free-text lines has nothing to complete and gets no
// bar: `labProgress` leaves those out, so its total is zero and a percentage
// of them would be a number about nothing.
$groupProgress = array_map(
    static fn (array $groupTests): array => LabProgress::counted($groupTests),
    $groups
);

// What each group is worth towards the figure at the top. On the markup so
// that `ui.js` can keep that figure right as answers are pressed, without a
// second copy of the table living in the browser. {@see LabProgress}
$side       = LabProgress::sideOf($tests);
$groupWeight = [];

foreach (array_keys($groups) as $groupName) {
    $groupWeight[$groupName] = LabProgress::weightFor($side, (string) $groupName);
}

// A screen with no record yet cannot post "add a test" anywhere — there is
// nobody for it to belong to until Save. So the group arrives with one blank
// card instead of a button: type a name into it and the test is created with
// the record, leave it alone and nothing is. `ui.js` turns the line under it
// into an Add lab button that lays out another, which is an addition — with
// scripting off the one card is still there and still works.
//
// After the counts, and never in them: an unnamed card is not a test, and
// "0 of 75" on an Add screen would be counting a box nobody has filled in.
if ($editing && $addLabUrl === null) {
    $groups[DatabaseSeeder::CUSTOM_GROUP][count($tests)] = UiStore::blankCustomLab($side);
}

// The groups are numbered, and a group's bar, its cards and its line in the
// folded summary all carry the number. Names would do it too, until one of
// them has a slash in it — "Hematology/Biochemistry" — and the script has to
// start quoting. The numbers are ours.
$groupNumber = [];

foreach (array_keys($groups) as $n => $groupName) {
    $groupNumber[$groupName] = $n;
}

/** One group's bar and percentage, the card's own in miniature. */
$groupBar = static function (array $p): void {
    if ($p['total'] === 0) {
        return;
    }
    ?>
    <span class="lab-group-progress">
        <span class="progress"><span class="progress-fill" data-lab-group-fill style="width:<?= $p['pct'] ?>%;background-color:#15508A"></span></span>
        <span class="lab-pct" data-lab-group-pct><?= $p['pct'] ?>%</span>
    </span>
    <?php
};
?>
<?php // Named, so Edit and Cancel can send the browser straight back to it
      // rather than to the top of a seventy-card screen. ?>
<<?= $box ?> class="card card--pad<?= $foldable ? ' card-fold' : '' ?>"<?= $section === null ? '' : ' id="card-' . esc($section, 'attr') . '"' ?> data-lab-section<?= $foldable && $open ? ' open' : '' ?>>
    <<?= $head ?> class="card-head<?= $foldable ? ' card-fold-head' : '' ?>">
        <?php if ($foldable): ?>
            <span class="card-fold-mark" aria-hidden="true"><?= ui_icon('chevron') ?></span>
        <?php endif; ?>
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
            <?php // To this card, so pressing Edit on a workup two screens
                  // down does not send somebody back to the top of the page. ?>
            <a class="btn-edit" href="<?= esc($editUrl . ($section === null ? '' : '#card-' . $section)) ?>"><?= ui_icon('edit') ?>Edit</a>
        <?php endif; ?>
        <?php // Shut, the card is one line — so the line carries the groups:
              // the names and how far each has got, which is what somebody
              // shuts a seventy-card workup and still wants to know. Open,
              // they go: each group says it over its own tests. ?>
        <?php if ($foldable): ?>
            <div class="lab-fold-groups">
                <?php foreach ($groups as $groupName => $groupTests): ?>
                    <?php if ($groupName === '' || $groupProgress[$groupName]['total'] === 0) { continue; } ?>
                    <span class="lab-fold-group" data-lab-sum="<?= (int) $groupNumber[$groupName] ?>">
                        <span class="lab-fold-group-name"><?= esc($groupName) ?></span>
                        <?php $groupBar($groupProgress[$groupName]); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </<?= $head ?>>

    <fieldset class="card-fields"<?= $editing ? '' : ' disabled' ?>>
    <?php if ($editing && $section !== null): ?>
        <input type="hidden" name="section" value="<?= esc($section) ?>">
    <?php endif; ?>
    <?php if ($editing && $addLabUrl !== null): ?>
        <?php // The card's default button, so that Enter in one of its boxes
              // saves the card. Without it the first submit button in the form
              // is Add lab, and answering a box with Enter added a test. ?>
        <button type="submit" class="offscreen-submit" tabindex="-1" aria-hidden="true">Save</button>
    <?php endif; ?>
    <?php foreach ($groups as $groupName => $groupTests): ?>
    <?php // The one group the check list seeds nothing under: it heads the
          // tests this record adds for itself, so it is the one with a button. ?>
    <?php $isCustomGroup = $groupName === DatabaseSeeder::CUSTOM_GROUP; ?>
    <?php if ($isCustomGroup && $addLabUrl !== null): ?>
        <?php // Out of the fieldset, which is disabled while the card is being
              // read: adding a test is not editing this one, so it should not
              // need the pencil pressed first. It is the last group, so this
              // closes the fields above it and opens another for its own. ?>
        </fieldset>
        <div class="lab-group lab-group--add" data-lab-group-head="<?= (int) $groupNumber[$groupName] ?>">
            <div class="lab-group-add-head">
                <h3 class="lab-group-name"><?= esc($groupName) ?></h3>
                <?php $groupBar($groupProgress[$groupName]); ?>
            </div>
            <?php // While the card is open it belongs to the card's own form,
                  // so pressing it keeps whatever has been typed into the
                  // tests above. While the card is being read there is nothing
                  // to keep, and it belongs to the page's own `lab-add` form
                  // instead — which is what lets it be pressed at all, the
                  // card's fields being disabled.
                  //
                  // Either way it is not what Enter presses: open, the hidden
                  // Save above it is the card's default button; closed, this
                  // one answers to another form altogether. A text box
                  // answered with Enter used to add a test nobody asked for. ?>
            <button type="submit" class="btn-add-lab"<?= $editing ? '' : ' form="lab-add"' ?> formaction="<?= esc($addLabUrl) ?>" formnovalidate><?= ui_icon('plus') ?>Add lab</button>
        </div>
        <fieldset class="card-fields"<?= $editing ? '' : ' disabled' ?>>
    <?php elseif ($isCustomGroup && $editing): ?>
        <?php // The same shape on a screen with no record yet: the heading, and
              // Add lab under it on its own. The button is `ui.js` — there is
              // nowhere to post an added test until Save, so what it does is
              // lay out another blank card in the form — and it is rendered
              // hidden, because a button that cannot work is worse than none.
              //
              // That file also takes the blank card below away, so the group
              // arrives as it does on a record: a heading and a button, and a
              // card only once one has been asked for. With the file blocked
              // the card stays and the button never appears, which is the one
              // shape that still works with no script at all.
              //
              // Inside the fieldset, unlike the record screens' button: nothing
              // is disabled on an Add screen, so there is nothing to step out
              // of. ?>
        <div class="lab-group lab-group--add" data-lab-group-head="<?= (int) $groupNumber[$groupName] ?>" data-lab-add-blank="<?= esc($field) ?>">
            <div class="lab-group-add-head">
                <h3 class="lab-group-name"><?= esc($groupName) ?></h3>
                <?php $groupBar($groupProgress[$groupName]); ?>
            </div>
            <button type="button" class="btn-add-lab" data-lab-add-more hidden><?= ui_icon('plus') ?>Add lab</button>
        </div>
    <?php endif; ?>
    <div class="lab-group">
        <?php // Its heading is above, with the button, except where there is
              // no button — a group nobody can add to. ?>
        <?php if ($groupName !== '' && ! ($isCustomGroup && ($addLabUrl !== null || $editing))): ?>
            <div class="lab-group-head" data-lab-group-head="<?= (int) $groupNumber[$groupName] ?>">
                <h3 class="lab-group-name"><?= esc($groupName) ?></h3>
                <?php $groupBar($groupProgress[$groupName]); ?>
            </div>
        <?php endif; ?>
        <div class="lab-grid" data-lab-group="<?= (int) $groupNumber[$groupName] ?>" data-lab-weight="<?= (int) ($groupWeight[$groupName] ?? 0) ?>">
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
            // Every card is named, not only the ones a record added: a card
            // that cannot be linked to cannot be pointed at, and "Edit
            // results" has been linking to its own card from the start.
            // A test that has not been saved has no id to be named after, so
            // it is named for where it sits in the form — unique either way,
            // and two blank cards with the same id would be one anchor.
            $cardId   = $test['id'] === '' ? 'lab-new-' . $field . '-' . $i : 'lab-' . $test['id'];
            // What the card calls this answer and what colour it is — its own
            // word for it when somebody wrote one, ours otherwise.
            $label = static fn (string $key): string => $byKey[$key]['label'] ?? UiStore::RESULT_LABEL[$key] ?? $key;
            // A test the record added wears only the colours somebody chose
            // for it: '' means none was, and the answer shows plain. The check
            // list's own tests keep the colours the sheet gives them.
            $tone  = static fn (string $key): string => isset($byKey[$key])
                ? (string) $byKey[$key]['tone']
                : ui_tone('labStatus', $key);
            // The answer a test was given colours the card it is on, not only
            // the pill: a workup is read by running down it, and a colour you
            // have to look twice for is not read at all.
            //
            // Only an answer that says something, though. Grey is what every
            // card would be — "not done", "not applicable", "not required" are
            // the resting state, and a workup washed grey top to bottom says
            // nothing at all.
            $cardTone = '';

            if ($answered && ! in_array($tone($test['status']), ['', UiStore::LAB_TONE_DEFAULT], true)) {
                $cardTone = ' lab-card--toned lab-card--' . substr($tone($test['status']), strlen('tone-'));
            }
            ?>
            <div class="lab-card<?= $animated ? ' lab-card--animated' : '' ?><?= $freeText ? ' lab-card--free' : '' ?><?= $cardTone ?> status-<?= esc($test['status']) ?>" id="<?= esc($cardId) ?>" data-idx="<?= $i ?>">
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
                        <?php // Every answer that has a colour wears it, not
                              // only the one recorded: the colours are how a
                              // workup is read at a glance. The check list
                              // gives its own tests theirs; a test the record
                              // added wears the ones it was given, and an
                              // answer nobody coloured is plain. ?>
                        <?php $answerTone = (string) $answer['tone']; ?>
                        <button type="button" class="lab-status-btn<?= $answerTone === '' ? '' : ' ' . esc($answerTone) ?><?= $test['status'] === $answer['key'] ? ' is-active' : '' ?>" data-lab-status="<?= esc($answer['key']) ?>" data-lab-tone="<?= esc($answerTone) ?>" data-lab-label="<?= esc($answer['label']) ?>"<?= in_array($answer['key'], UiStore::RESULT_UNANSWERED, true) ? ' data-lab-unanswered' : '' ?>><?= esc($answer['label']) ?></button>
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

                <?php if ($custom && $test['id'] === ''): ?>
                    <?php // A card that has never been saved. There is nothing
                          // to delete — no row behind it — so taking it away is
                          // taking it off the form, which is `ui.js`, which is
                          // also what put it there. Hidden until that file
                          // unhides it, for the same reason Add lab is.
                          //
                          // No question asked first: a test added by mistake
                          // has written nothing down, and the thing being
                          // undone is a press from a moment ago. ?>
                    <div class="lab-remove">
                        <button type="button" class="lab-remove-link" data-lab-drop hidden><?= ui_icon('trash') ?>Remove this test</button>
                    </div>
                <?php elseif ($custom && $removeLabUrl !== null): ?>
                    <?php // Theirs to add, theirs to take away — at the foot of
                          // the card, after everything it holds. The question is
                          // asked over the card rather than on a screen of its
                          // own, so this opens the dialog under it. ?>
                    <?php
                    $removeAction = $removeLabUrl . '/' . $test['id'] . '/delete';
                    $removeId     = 'confirm-' . substr(sha1($removeAction), 0, 10);

                    $removable[$removeId] = ['action' => $removeAction, 'name' => (string) $test['name']];
                    ?>
                    <div class="lab-remove">
                        <a class="lab-remove-link" href="#<?= esc($removeId) ?>" data-dialog="<?= esc($removeId) ?>"><?= ui_icon('trash') ?>Remove this test</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </fieldset>

    <?php foreach ($removable as $removeId => $removeThis): ?>
        <?= view('ui/partials/confirm_dialog', [
            'id'     => $removeId,
            'title'  => 'Remove ' . ($removeThis['name'] === '' ? 'this test' : $removeThis['name']) . '?',
            'detail' => 'This test was added to this record, so only this record has it. It will be '
                . 'removed along with the answer and the comment on it. This cannot be undone.',
            'action' => $removeThis['action'],
            'confirmVerb' => 'Remove test',
        ], ['saveData' => false]) ?>
    <?php endforeach; ?>

    <?php if ($editing && $viewUrl !== null): ?>
        <div class="card-actions">
            <a class="btn-outline" href="<?= esc($viewUrl . ($section === null ? '' : '#card-' . $section)) ?>">Cancel</a>
            <button type="submit" class="btn-save">Save</button>
        </div>
    <?php endif; ?>
</<?= $box ?>>
