<?php

use App\Libraries\UiStore;

/**
 * One of a pair's donors, as the cards the rest of the platform uses.
 *
 * The pair reads top to bottom — the pair's own details, the recipient, their
 * workup, their notes, then the donors — and a donor inside it has to read the
 * same way, or the screen changes shape halfway down. So this is the pair
 * profile's donor half, card for card: personal details, workup, notes, each
 * opened for editing on its own and each saving on its own.
 *
 * Every card is its own form, because the pair's own form is already on the
 * page and forms do not nest. The `section` each posts names the link as well
 * as the card, so the controller knows which of several donors it is being
 * asked about.
 *
 * An archived donor is shown and not edited: no Edit links, and the fieldsets
 * stay closed. The word they were given still shows, because archiving is the
 * pair's doing and says nothing about them.
 *
 * @var array<string, mixed>       $donor      The donor record
 * @var array<string, mixed>       $tab        Their link to this pair
 * @var array<string, mixed>       $v          The donor's fields, d-prefixed
 * @var list<array<string, mixed>> $labTests
 * @var string                     $editing    Which card is open, '' for none
 * @var string                     $viewUrl    This tab, read-only
 * @var string                     $labUrl     Where Add lab posts and Remove goes
 * @var bool                       $hasActive  Whether the pair has its donor already
 * @var list<array{id: string, name: string}> $mrps
 */
// Each card is named for its link as well as for itself, so a screen holding
// three donors knows which one a save is about.
$card     = static fn (string $name): string => 'pd' . $tab['id'] . '-' . $name;
$editable = fn (string $name): bool => ! $tab['archived'] && $editing === $card($name);
$editUrl  = fn (string $name): string => $viewUrl . '&edit=' . $card($name) . '#card-' . $card($name);
// Where Cancel goes: the card, not the top of the pair.
$closeUrl = fn (string $name): string => $viewUrl . '#card-' . $card($name);
?>
<?php // Folded away when it is in the road, and open when the screen arrives.
      // Shut, the summary keeps the name and the status, which are what
      // anybody scans this card for.
      //
      // The summary has to be the first thing in the card, so the form starts
      // under it rather than around it — the head holds a link and no fields,
      // so there is nothing in it the form wants. ?>
<details class="card card--pad card-fold" id="card-<?= esc($card('personal')) ?>" open>
    <summary class="card-head card-fold-head">
        <span class="card-fold-mark" aria-hidden="true"><?= ui_icon('chevron') ?></span>
        <div class="section-head">
            <div class="role-badge role-badge--donor">D</div>
            <h2 class="card-title">Donor &mdash; Personal Information</h2>
        </div>
        <?= view('ui/partials/card_fold_facts', [
            'person'    => 'Donor',
            'name'      => (string) $v['dName'],
            'bloodType' => (string) $v['dBloodType'],
            'statusKey' => (string) $v['dStatus'],
        ], ['saveData' => false]) ?>
        <?php if (! $editable('personal') && ! $tab['archived']): ?>
            <a class="btn-edit" href="<?= esc($editUrl('personal')) ?>"><?= ui_icon('edit') ?>Edit</a>
        <?php endif; ?>
    </summary>
    <form method="post" action="<?= esc($viewUrl) ?>">
        <?= csrf_field() ?>
        <fieldset class="card-fields"<?= $editable('personal') ? '' : ' disabled' ?>>
            <?php if ($editable('personal')): ?>
                <input type="hidden" name="section" value="<?= esc($card('personal')) ?>">
            <?php endif; ?>
            <div class="stack-4">
                <div class="form-grid-5">
                    <div>
                        <label class="field-label">Donor MRN</label>
                        <input type="text" class="input-ro input-ro--mono" value="<?= esc($donor['id']) ?>" readonly>
                    </div>
                    <div>
                        <label class="field-label" for="f-d-name">Donor Name</label>
                        <input type="text" id="f-d-name" name="dName" class="input" value="<?= esc($v['dName']) ?>" placeholder="Full name">
                    </div>
                    <div>
                        <label class="field-label" for="f-d-city">Donor City</label>
                        <input type="text" id="f-d-city" name="dCity" class="input" value="<?= esc($v['dCity']) ?>" placeholder="City">
                    </div>
                    <div>
                        <label class="field-label" for="f-d-phone">Donor Phone Number</label>
                        <input type="tel" id="f-d-phone" name="dPhone" class="input" value="<?= esc($v['dPhone']) ?>" placeholder="+966 5x xxx xxxx">
                    </div>
                    <div>
                        <label class="field-label" for="f-d-gender">Donor Gender</label>
                        <select id="f-d-gender" name="dGender" class="input">
                            <?php foreach (UiStore::GENDER_OPTIONS as $value => $label): ?>
                                <option value="<?= esc($value) ?>"<?= $v['dGender'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid-5">
                    <div>
                        <?= view('ui/partials/birth_date_field', [
                            'id' => 'f-d-birth', 'name' => 'dBirthDate', 'value' => $v['dBirthDate'],
                            'ageName' => 'dAge', 'age' => $v['dAge'],
                        ], ['saveData' => false]) ?>
                    </div>
                    <div>
                        <label class="field-label" for="f-d-blood">Donor Blood Group</label>
                        <select id="f-d-blood" name="dBloodType" class="input input--mono">
                            <?php foreach (UiStore::BLOOD_TYPES as $bloodType): ?>
                                <option value="<?= esc($bloodType) ?>"<?= $v['dBloodType'] === $bloodType ? ' selected' : '' ?>><?= esc($bloodType) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="f-d-mrp">Donor MRP</label>
                        <select id="f-d-mrp" name="dMrp" class="input">
                            <option value=""<?= $v['dMrp'] === '' ? ' selected' : '' ?>>Choose MRP</option>
                            <?php foreach ($mrps as $mrp): ?>
                                <option value="<?= esc($mrp['id']) ?>"<?= $v['dMrp'] === $mrp['id'] ? ' selected' : '' ?>><?= esc($mrp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="f-d-coordinator">Donor Coordinator</label>
                        <?= view('ui/partials/coordinator_field', ['id' => 'f-d-coordinator', 'name' => 'dCoordinator', 'value' => $v['dCoordinator'], 'coordinators' => $coordinators], ['saveData' => false]) ?>
                    </div>
                    <div>
                        <?php // The recipient is on this screen, so the finer
                              // question can be asked here: whether a living
                              // donor is related to them. ?>
                        <label class="field-label" for="f-d-type">Donor Type</label>
                        <select id="f-d-type" name="dType" class="input">
                            <?php foreach (UiStore::DONATION_TYPES_ON_PAIR as $value): ?>
                                <option value="<?= esc($value) ?>"<?= $v['dType'] === $value ? ' selected' : '' ?>><?= esc(UiStore::DONATION_TYPES[$value]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid-5">
                    <div>
                        <label class="field-label" for="f-d-relationship">Relationship</label>
                        <input type="text" id="f-d-relationship" name="dRelationship" class="input" value="<?= esc($v['dRelationship']) ?>" placeholder="e.g. Brother">
                    </div>
                    <div>
                        <?php // The donor's own, and the same fact the tab
                              // above sets: a pair goes ahead with one donor,
                              // so Active is left off while somebody else
                              // holds it, here as there. ?>
                        <label class="field-label" for="f-d-status">Donor Status</label>
                        <select id="f-d-status" name="dStatus" class="input">
                            <?php foreach (UiStore::DONOR_STATUS_OPTIONS as $value => $label): ?>
                                <?php if ($value === 'Active' && $hasActive && ! $tab['isActive']) { continue; } ?>
                                <option value="<?= esc($value) ?>"<?= $v['dStatus'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </fieldset>

        <?php if ($editable('personal')): ?>
            <div class="card-actions">
                <a class="btn-outline" href="<?= esc($closeUrl('personal')) ?>">Cancel</a>
                <button type="submit" class="btn-save">Save</button>
            </div>
        <?php endif; ?>
    </form>
</details>

<form method="post" action="<?= esc($viewUrl) ?>">
    <?= csrf_field() ?>
    <?php if ($editable('labs')): ?>
        <input type="hidden" name="section" value="<?= esc($card('labs')) ?>">
    <?php endif; ?>
    <?php // Folded shut, as the recipient's is: this donor's notes are under
          // it, and the next donor's tab is a press away. ?>
    <?= view('ui/partials/lab_tests', [
        'tests'        => $labTests,
        'field'        => 'dLabs',
        'foldable'     => true,
        'animated'     => true,
        'editing'      => $editable('labs'),
        // Bare: the workup card adds the fragment itself.
        'editUrl'      => $tab['archived'] ? null : $viewUrl . '&edit=' . $card('labs'),
        'viewUrl'      => $viewUrl,
        'section'      => $card('labs'),
        'labsTitle'    => 'Donor &mdash; Required Lab Tests',
        // Adding a test is not editing this card, so it does not wait for
        // the pencil; removing one is, because it takes something away.
        'addLabUrl'    => $tab['archived'] ? null : $labUrl,
        'removeLabUrl' => $editable('labs') ? $labUrl : null,
    ], ['saveData' => false]) ?>
</form>

<div class="card card--pad" id="card-<?= esc($card('notes')) ?>">
    <form method="post" action="<?= esc($viewUrl) ?>">
        <?= csrf_field() ?>
        <div class="card-head">
            <h2 class="card-title">Donor &mdash; Clinical Notes</h2>
            <?php if (! $editable('notes') && ! $tab['archived']): ?>
                <a class="btn-edit" href="<?= esc($editUrl('notes')) ?>"><?= ui_icon('edit') ?>Edit</a>
            <?php endif; ?>
        </div>
        <fieldset class="card-fields"<?= $editable('notes') ? '' : ' disabled' ?>>
            <?php if ($editable('notes')): ?>
                <input type="hidden" name="section" value="<?= esc($card('notes')) ?>">
            <?php endif; ?>
            <textarea class="textarea" name="dNotes" rows="4" placeholder="Add clinical notes, observations, or relevant context..."><?= esc($v['dNotes']) ?></textarea>
        </fieldset>

        <?php if ($editable('notes')): ?>
            <div class="card-actions">
                <a class="btn-outline" href="<?= esc($closeUrl('notes')) ?>">Cancel</a>
                <button type="submit" class="btn-save">Save</button>
            </div>
        <?php endif; ?>
    </form>
</div>
