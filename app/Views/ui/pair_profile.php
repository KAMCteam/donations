<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;
use App\Models\PairModel;

/**
 * Existing pair. Was `js/pages/pair-profile.js`.
 *
 * Same layout as Add Pair with the values filled in, plus the Match Status
 * dropdown. The fields the source left unbound — gender, MRP, coordinator,
 * first dialysis, donor status — are bound here: they now have columns behind
 * them, and a control that shows a stored value but throws an edit away is a
 * way to lose a record, not fidelity to the design.
 *
 * The donors are tabs rather than one card: a pair is worked up against more
 * than one, and all of them belong to the same pair — the one it is going
 * ahead with, the ones it is holding, and the ones it has finished with.
 * {@see ui/partials/donor_tabs} is that half of the screen.
 *
 * @var array<string, mixed>                  $pair
 * @var array<string, mixed>|null             $recipient
 * @var array<string, mixed>|null             $donor      The open tab's donor
 * @var array<string, mixed>                  $v
 * @var list<array<string, mixed>>            $rLabTests
 * @var list<array<string, mixed>>            $dLabTests  The open tab's workup
 * @var list<array<string, mixed>>            $tabs       Every donor of this pair
 * @var int                                   $openTab    1-based, or 0 for none
 * @var list<array<string, mixed>>            $offerable  Donors the Add dialog can offer
 * @var list<array{id: string, name: string}> $mrps
 * @var string                                $entryDate
 * @var string                                $editing    The card open for editing, '' for none
 */
// A pair opens read-only and is edited one card at a time: each Edit is a link
// back here with the card named, so the card returns as a form and the screen
// works with JavaScript off. Only the open card's fieldset is enabled, so only
// its fields post — saving the notes cannot disturb the crossmatch date.
$viewUrl  = site_url('pairs/' . rawurlencode($pair['id']));
$editable = static fn (string $section): bool => $editing === $section;
// Both carry the card's own anchor, so opening one for editing and closing it
// again land on the card rather than at the top of the pair.
$editUrl  = static fn (string $section): string => $viewUrl . '?edit=' . $section . '#card-' . $section;
$closeUrl = static fn (string $section): string => $viewUrl . '#card-' . $section;
?>
<div class="page">
    <div class="page-header page-header--start page-header--wrap">
        <div>
            <?php $back = ui_back(site_url('pairs'), 'Pairs List'); ?>
            <a class="back-link" href="<?= esc($back['url']) ?>"><?= ui_icon('back') ?>Back to <?= esc($back['label']) ?></a>
            <div class="eyebrow"><?= esc($pair['id']) ?></div>
            <h1 class="page-title">Pair Profile</h1>
        </div>
        <?php // A pair reaches the exchange screen two ways: this button, or
              // its status being set to Paired Exchange — which says the same
              // thing, so it does the same thing. While the status says it,
              // there is nothing to press: taking it back means saying
              // something else on the card below. ?>
        <div class="header-actions">
            <a class="btn-outline" href="<?= site_url('pairs/' . rawurlencode($pair['id'])) ?>/print" target="_blank" rel="noopener"><?= ui_icon('printer') ?>Export PDF</a>
            <?php if ($pair['status'] === PairModel::EXCHANGE): ?>
                <span class="badge tone-teal-soft">On the exchange list</span>
            <?php elseif ($pair['forExchange']): ?>
                <span class="badge tone-teal-soft">Offered for exchange</span>
                <form method="post" action="<?= esc($viewUrl) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="exchange">
                    <input type="hidden" name="forExchange" value="0">
                    <button type="submit" class="btn-outline">Withdraw from exchange</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= esc($viewUrl) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="exchange">
                    <input type="hidden" name="forExchange" value="1">
                    <button type="submit" class="btn-outline"><?= ui_icon('shuffle14') ?>Pair Exchange</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <form id="pair-form" class="stack-5" method="post" action="<?= current_url() ?>">
        <?= csrf_field() ?>

        <div class="card card--pad" id="card-pair">
            <div class="card-head">
                <h2 class="card-title">Pair Details</h2>
                <?php if (! $editable('pair')): ?>
                    <a class="btn-edit" href="<?= esc($editUrl('pair')) ?>"><?= ui_icon('edit') ?>Edit</a>
                <?php endif; ?>
            </div>
            <fieldset class="card-fields"<?= $editable('pair') ? '' : ' disabled' ?>>
                <?php if ($editable('pair')): ?>
                    <input type="hidden" name="section" value="pair">
                <?php endif; ?>
            <div class="form-grid-3">
                <div>
                    <label class="field-label" for="f-relationship">Relationship</label>
                    <input type="text" id="f-relationship" name="relationship" class="input" value="<?= esc($v['relationship']) ?>" placeholder="e.g. Sibling, Spouse">
                </div>
                <div>
                    <label class="field-label" for="f-crossmatch">Date of Crossmatch</label>
                    <?= view('ui/partials/date_field', ['id' => 'f-crossmatch', 'name' => 'crossmatchDate', 'value' => $v['crossmatchDate']], ['saveData' => false]) ?>
                </div>
                <div>
                    <label class="field-label" for="f-status">Status</label>
                    <select id="f-status" name="pairStatus" class="input" data-reveal="closed-reason,transplant-date" data-reveal-when="closed,transplanted">
                        <?php foreach (UiStore::PAIR_STATUS_OPTIONS as $value => $label): ?>
                            <option value="<?= esc($value) ?>"<?= $v['pairStatus'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php // Said before the save, not after it: four of these
                          // six words are the recipient's and the donor's own
                          // as well, and saving the card writes them there
                          // too. Somebody setting a pair Transplanted should
                          // know the two registers change with it. ?>
                    <p class="field-hint">
                        <?= esc(implode(', ', array_slice(UiStore::PERSON_STATUS_OPTIONS, 0, -1))) ?>
                        and <?= esc(array_values(UiStore::PERSON_STATUS_OPTIONS)[count(UiStore::PERSON_STATUS_OPTIONS) - 1]) ?>
                        are the recipient's and the donor's status too: saving this card sets theirs to the same word.
                        Paired Exchange and Closed are the pair's alone and leave both as they are.
                    </p>
                </div>
            </div>

            <?php // A transplanted pair has a day it happened on, and no other
                  // status has one — so the field is asked for beside the word
                  // rather than sitting empty on every pair. Hidden by ui.js
                  // until Transplanted is chosen; without scripting it is
                  // simply always there, and the server drops a date that
                  // belongs to no transplant. ?>
            <div class="stack-4 reveal" id="transplant-date"<?= $v['pairStatus'] === 'transplanted' ? '' : ' hidden' ?>>
                <div class="form-grid-3">
                    <div>
                        <label class="field-label" for="f-transplant">Date of Transplant</label>
                        <?= view('ui/partials/date_field', ['id' => 'f-transplant', 'name' => 'transplantDate', 'value' => $v['transplantDate'], 'past' => true], ['saveData' => false]) ?>
                    </div>
                </div>
            </div>

            <?php // Closing a pair is the one status that means something
                  // beyond its label — both sides go back on their lists — so
                  // it is the one that asks why. Hidden by ui.js until Closed
                  // is chosen; without scripting it is simply always there,
                  // and an empty reason on any other status is discarded. ?>
            <div class="stack-4 reveal" id="closed-reason"<?= $v['pairStatus'] === 'closed' ? '' : ' hidden' ?>>
                <div>
                    <label class="field-label" for="f-closed-reason">Why was it closed?</label>
                    <textarea class="textarea" id="f-closed-reason" name="closedReason" rows="3"
                              placeholder="The reason this pair was closed"><?= esc($v['closedReason']) ?></textarea>
                </div>
            </div>
            </fieldset>

            <?php if ($editable('pair')): ?>
                <div class="card-actions">
                    <a class="btn-outline" href="<?= esc($closeUrl('pair')) ?>">Cancel</a>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            <?php endif; ?>
        </div>

        <?php // Folded away when it is in the road, and open when the screen
              // arrives. Shut, the summary keeps the name and the status,
              // which are what anybody scans this card for. ?>
        <details class="card card--pad card-fold" id="card-recipient" open>
            <summary class="card-head card-fold-head">
                <span class="card-fold-mark" aria-hidden="true"><?= ui_icon('chevron') ?></span>
                <div class="section-head">
                    <div class="role-badge role-badge--recipient">R</div>
                    <h2 class="card-title">Recipient — Personal Information</h2>
                </div>
                <?= view('ui/partials/card_fold_facts', [
                    'person'    => 'Recipient',
                    'name'      => (string) $v['rName'],
                    'bloodType' => (string) $v['rBloodType'],
                    'statusKey' => (string) $v['rStatus'],
                ], ['saveData' => false]) ?>
                <?php if (! $editable('recipient')): ?>
                    <a class="btn-edit" href="<?= esc($editUrl('recipient')) ?>"><?= ui_icon('edit') ?>Edit</a>
                <?php endif; ?>
            </summary>
            <fieldset class="card-fields"<?= $editable('recipient') ? '' : ' disabled' ?>>
                <?php if ($editable('recipient')): ?>
                    <input type="hidden" name="section" value="recipient">
                <?php endif; ?>
            <div class="stack-4">
                <div class="form-grid-5">
                    <div>
                        <label class="field-label">Recipient MRN</label>
                        <input type="text" class="input-ro input-ro--mono" value="<?= esc($recipient['id'] ?? '—') ?>" readonly>
                    </div>
                    <div class="span-lg-2">
                        <label class="field-label" for="f-r-name">Recipient Name</label>
                        <input type="text" id="f-r-name" name="rName" class="input" value="<?= esc($v['rName']) ?>" placeholder="Full name">
                    </div>
                    <div>
                        <label class="field-label" for="f-r-city">Recipient City</label>
                        <input type="text" id="f-r-city" name="rCity" class="input" value="<?= esc($v['rCity']) ?>" placeholder="City">
                    </div>
                    <div>
                        <label class="field-label" for="f-r-phone">Phone Number</label>
                        <input type="tel" id="f-r-phone" name="rPhone" class="input" value="<?= esc($v['rPhone']) ?>" placeholder="+966 5x xxx xxxx">
                    </div>
                </div>

                <div class="form-grid-5">
                    <div>
                        <label class="field-label" for="f-r-gender">Recipient Gender</label>
                        <select id="f-r-gender" name="rGender" class="input">
                            <?php foreach (UiStore::GENDER_OPTIONS as $value => $label): ?>
                                <option value="<?= esc($value) ?>"<?= $v['rGender'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <?= view('ui/partials/birth_date_field', [
                            'id' => 'f-r-birth', 'name' => 'rBirthDate', 'value' => $v['rBirthDate'],
                            'ageName' => 'rAge', 'age' => $v['rAge'],
                        ], ['saveData' => false]) ?>
                    </div>
                    <div>
                        <label class="field-label" for="f-r-blood">Blood Group</label>
                        <select id="f-r-blood" name="rBloodType" class="input input--mono">
                            <?php foreach (UiStore::BLOOD_TYPES as $bloodType): ?>
                                <option value="<?= esc($bloodType) ?>"<?= $v['rBloodType'] === $bloodType ? ' selected' : '' ?>><?= esc($bloodType) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="f-r-mrp">Recipient MRP</label>
                        <select id="f-r-mrp" name="rMrp" class="input">
                            <option value=""<?= $v['rMrp'] === '' ? ' selected' : '' ?>>Choose MRP</option>
                            <?php foreach ($mrps as $mrp): ?>
                                <option value="<?= esc($mrp['id']) ?>"<?= $v['rMrp'] === $mrp['id'] ? ' selected' : '' ?>><?= esc($mrp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="f-r-coordinator">Recipient Coordinator</label>
                        <?= view('ui/partials/coordinator_field', ['id' => 'f-r-coordinator', 'name' => 'rCoordinator', 'value' => $v['rCoordinator'], 'coordinators' => $coordinators], ['saveData' => false]) ?>
                    </div>
                </div>

                <div class="form-grid-5">
                    <div>
                        <?php // Before the date, because it decides whether there
                              // is a date to give: pre-emptive means a transplant
                              // before dialysis ever starts. ?>
                        <label class="field-label" for="f-r-dialysis-type">Type Dialysis</label>
                        <select id="f-r-dialysis-type" name="rDialysisType" class="input" data-closes="f-r-dialysis" data-closes-when="<?= UiStore::DIALYSIS_PREEMPTIVE ?>">
                            <option value="">Not recorded</option>
                            <?php foreach (UiStore::DIALYSIS_TYPES as $value => $label): ?>
                                <option value="<?= esc($value) ?>"<?= $v['rDialysisType'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="f-r-dialysis">First Dialysis</label>
                        <?= view('ui/partials/date_field', [
                            'id'       => 'f-r-dialysis',
                            'name'     => 'rFirstDialysis',
                            'value'    => $v['rFirstDialysis'],
                            'past'     => true,
                            'disabled' => $v['rDialysisType'] === UiStore::DIALYSIS_PREEMPTIVE,
                        ], ['saveData' => false]) ?>
                    </div>
                    <div>
                        <label class="field-label" for="f-r-entry">Entry Date</label>
                        <?= view('ui/partials/date_field', ['id' => 'f-r-entry', 'name' => 'rEntryDate', 'value' => UiStore::isoToDMY($entryDate), 'past' => true], ['saveData' => false]) ?>
                    </div>
                    <div>
                        <label class="field-label" for="f-r-urgent">Urgent?</label>
                        <input type="hidden" name="rUrgent" value="0">
                        <label class="check">
                            <input type="checkbox" id="f-r-urgent" name="rUrgent" value="1"<?= $v['rUrgent'] ? ' checked' : '' ?>>
                            <span>This case is urgent</span>
                        </label>
                    </div>
                    <div>
                        <?php // The person's own three. Saving this card moves
                              // the pair's Status with it where the pair can
                              // hold the same word — they are one fact, and
                              // this is the half the person's record keeps. ?>
                        <label class="field-label" for="f-r-status">Recipient Status</label>
                        <select id="f-r-status" name="rStatus" class="input">
                            <?php foreach (UiStore::PERSON_STATUS_OPTIONS as $value => $label): ?>
                                <option value="<?= esc($value) ?>"<?= $v['rStatus'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            </fieldset>

            <?php if ($editable('recipient')): ?>
                <div class="card-actions">
                    <a class="btn-outline" href="<?= esc($closeUrl('recipient')) ?>">Cancel</a>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            <?php endif; ?>
        </details>

        <?php // Open when the screen arrives: the recipient's workup is what
              // somebody opens a pair to read, and arriving at one line that
              // has to be pressed before it says anything was making them
              // press it every time. It still folds — the pair's donors are
              // below it and a workup is a screenful and a half to scroll
              // past — and `ui.js` remembers it shut for anybody who shuts it. ?>
        <?= view('ui/partials/lab_tests', [
            'tests'    => $rLabTests,
            'field'    => 'rLabs',
            'foldable' => true,
            'open'     => true,
            'editing'  => $editable('rlabs'),
            // Bare: the workup card adds the fragment itself.
            'editUrl' => $viewUrl . '?edit=rlabs',
            'viewUrl' => $viewUrl,
            'section' => 'rlabs',
            // The recipient's own tests, added and removed from this screen but
            // belonging to their record wherever it is opened.
            'addLabUrl'    => site_url('pairs/' . rawurlencode($pair['id']) . '/labs/recipient'),
            'removeLabUrl' => site_url('pairs/' . rawurlencode($pair['id']) . '/labs/recipient'),
            // `saveData` false, like every other partial here: CodeIgniter
            // keeps view data between `view()` calls, and a flag this one
            // reads with `??` would otherwise be the last caller's.
        ], ['saveData' => false]) ?>

        <div class="card card--pad" id="card-rnotes">
            <div class="card-head">
                <h2 class="card-title">Recipient — Clinical Notes</h2>
                <?php if (! $editable('rnotes')): ?>
                    <a class="btn-edit" href="<?= esc($editUrl('rnotes')) ?>"><?= ui_icon('edit') ?>Edit</a>
                <?php endif; ?>
            </div>
            <fieldset class="card-fields"<?= $editable('rnotes') ? '' : ' disabled' ?>>
                <?php if ($editable('rnotes')): ?>
                    <input type="hidden" name="section" value="rnotes">
                <?php endif; ?>
                <textarea class="textarea" name="rNotes" rows="4" placeholder="Add clinical notes, observations, or relevant context..."><?= esc($v['rNotes']) ?></textarea>
            </fieldset>

            <?php if ($editable('rnotes')): ?>
                <div class="card-actions">
                    <a class="btn-outline" href="<?= esc($closeUrl('rnotes')) ?>">Cancel</a>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            <?php endif; ?>
        </div>

    </form>

    <?php // The pair's donors, each a tab: the one it is going ahead with, the
          // ones it is holding, and the ones it has finished with. Below the
          // recipient, because reading a donor means having read who they are
          // being worked up for. ?>
    <?= view('ui/partials/donor_tabs', [
        'pair'      => $pair,
        'tabs'      => $tabs,
        'openTab'   => $openTab,
        'donor'     => $donor,
        'v'         => $v,
        'labTests'  => $dLabTests,
        'editing'   => $editing,
        'offerable' => $offerable,
        'mrps'      => $mrps,
        'coordinators' => $coordinators,
    ], ['saveData' => false]) ?>
</div>
<?= $this->endSection() ?>