<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;

/**
 * Existing pair. Was `js/pages/pair-profile.js`.
 *
 * Same layout as Add Pair with the values filled in, plus the Match Status
 * dropdown. The fields the source left unbound — gender, MRP, coordinator,
 * first dialysis, donor status — are bound here: they now have columns behind
 * them, and a control that shows a stored value but throws an edit away is a
 * way to lose a record, not fidelity to the design.
 *
 * @var array<string, mixed>                  $pair
 * @var array<string, mixed>|null             $recipient
 * @var array<string, mixed>|null             $donor
 * @var array<string, mixed>                  $v
 * @var list<array<string, mixed>>            $rLabTests
 * @var list<array<string, mixed>>            $dLabTests
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
$editUrl  = static fn (string $section): string => $viewUrl . '?edit=' . $section;
?>
<div class="page">
    <div class="page-header page-header--start page-header--wrap">
        <div>
            <a class="back-link" href="<?= site_url('pairs') ?>"><?= ui_icon('back') ?>Back to Pairs List</a>
            <div class="eyebrow"><?= esc($pair['id']) ?></div>
            <h1 class="page-title">Pair Profile</h1>
        </div>
    </div>

    <form id="pair-form" class="stack-5" method="post" action="<?= current_url() ?>">
        <?= csrf_field() ?>

        <div class="card card--pad">
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
                    <label class="field-label" for="f-status">Match Status</label>
                    <select id="f-status" name="pairStatus" class="input">
                        <?php foreach (UiStore::STATUS_OPTIONS as $value => $label): ?>
                            <option value="<?= esc($value) ?>"<?= $v['pairStatus'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            </fieldset>

            <?php if ($editable('pair')): ?>
                <div class="card-actions">
                    <a class="btn-outline" href="<?= esc($viewUrl) ?>">Cancel</a>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            <?php endif; ?>
        </div>

        <div class="card card--pad">
            <div class="card-head">
                <div class="section-head">
                    <div class="role-badge role-badge--recipient">R</div>
                    <h2 class="card-title">Recipient — Personal Information</h2>
                </div>
                <?php if (! $editable('recipient')): ?>
                    <a class="btn-edit" href="<?= esc($editUrl('recipient')) ?>"><?= ui_icon('edit') ?>Edit</a>
                <?php endif; ?>
            </div>
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
                        <label class="field-label" for="f-r-age">Recipient Age</label>
                        <input type="number" id="f-r-age" name="rAge" class="input" value="<?= esc($v['rAge']) ?>" placeholder="Age">
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
                        <input type="text" id="f-r-coordinator" name="rCoordinator" class="input" value="<?= esc($v['rCoordinator']) ?>" placeholder="Choose Coordinator">
                    </div>
                </div>

                <div class="form-grid-5">
                    <div>
                        <label class="field-label" for="f-r-dialysis">First Dialysis</label>
                        <?= view('ui/partials/date_field', ['id' => 'f-r-dialysis', 'name' => 'rFirstDialysis', 'value' => $v['rFirstDialysis']], ['saveData' => false]) ?>
                    </div>
                    <div>
                        <label class="field-label" for="f-r-entry">Entry Date</label>
                        <?= view('ui/partials/date_field', ['id' => 'f-r-entry', 'name' => null, 'value' => UiStore::isoToDMY($entryDate)], ['saveData' => false]) ?>
                    </div>
                    <div>
                        <label class="field-label" for="f-r-urgent">Urgent?</label>
                        <input type="hidden" name="rUrgent" value="0">
                        <label class="check">
                            <input type="checkbox" id="f-r-urgent" name="rUrgent" value="1"<?= $v['rUrgent'] ? ' checked' : '' ?>>
                            <span>This case is urgent</span>
                        </label>
                    </div>
                </div>
            </div>
            </fieldset>

            <?php if ($editable('recipient')): ?>
                <div class="card-actions">
                    <a class="btn-outline" href="<?= esc($viewUrl) ?>">Cancel</a>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            <?php endif; ?>
        </div>

        <?= view('ui/partials/lab_tests', [
            'tests'   => $rLabTests,
            'field'   => 'rLabs',
            'editing' => $editable('rlabs'),
            'editUrl' => $editUrl('rlabs'),
            'viewUrl' => $viewUrl,
            'section' => 'rlabs',
        ]) ?>

        <div class="card card--pad">
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
                    <a class="btn-outline" href="<?= esc($viewUrl) ?>">Cancel</a>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            <?php endif; ?>
        </div>

        <div class="card card--pad">
            <div class="card-head">
                <div class="section-head">
                    <div class="role-badge role-badge--donor">D</div>
                    <h2 class="card-title">Donor — Personal Information</h2>
                </div>
                <?php if (! $editable('donor')): ?>
                    <a class="btn-edit" href="<?= esc($editUrl('donor')) ?>"><?= ui_icon('edit') ?>Edit</a>
                <?php endif; ?>
            </div>
            <fieldset class="card-fields"<?= $editable('donor') ? '' : ' disabled' ?>>
                <?php if ($editable('donor')): ?>
                    <input type="hidden" name="section" value="donor">
                <?php endif; ?>
            <div class="stack-4">
                <div class="form-grid-5">
                    <div>
                        <label class="field-label">Donor MRN</label>
                        <input type="text" class="input-ro input-ro--mono" value="<?= esc($donor['id'] ?? '—') ?>" readonly>
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
                        <label class="field-label" for="f-d-age">Donor Age</label>
                        <input type="number" id="f-d-age" name="dAge" class="input" value="<?= esc($v['dAge']) ?>" placeholder="Age">
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
                        <input type="text" id="f-d-coordinator" name="dCoordinator" class="input" value="<?= esc($v['dCoordinator']) ?>" placeholder="Choose Coordinator">
                    </div>
                    <div>
                        <label class="field-label" for="f-d-status">Donor Status</label>
                        <select id="f-d-status" name="dStatus" class="input">
                            <?php foreach (UiStore::DONOR_STATUS_OPTIONS as $value => $label): ?>
                                <option value="<?= esc($value) ?>"<?= $v['dStatus'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            </fieldset>

            <?php if ($editable('donor')): ?>
                <div class="card-actions">
                    <a class="btn-outline" href="<?= esc($viewUrl) ?>">Cancel</a>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            <?php endif; ?>
        </div>

        <?= view('ui/partials/lab_tests', [
            'tests'   => $dLabTests,
            'field'   => 'dLabs',
            'editing' => $editable('dlabs'),
            'editUrl' => $editUrl('dlabs'),
            'viewUrl' => $viewUrl,
            'section' => 'dlabs',
        ]) ?>

        <div class="card card--pad">
            <div class="card-head">
                <h2 class="card-title">Donor — Clinical Notes</h2>
                <?php if (! $editable('dnotes')): ?>
                    <a class="btn-edit" href="<?= esc($editUrl('dnotes')) ?>"><?= ui_icon('edit') ?>Edit</a>
                <?php endif; ?>
            </div>
            <fieldset class="card-fields"<?= $editable('dnotes') ? '' : ' disabled' ?>>
                <?php if ($editable('dnotes')): ?>
                    <input type="hidden" name="section" value="dnotes">
                <?php endif; ?>
                <textarea class="textarea" name="dNotes" rows="4" placeholder="Add clinical notes, observations, or relevant context..."><?= esc($v['dNotes']) ?></textarea>
            </fieldset>

            <?php if ($editable('dnotes')): ?>
                <div class="card-actions">
                    <a class="btn-outline" href="<?= esc($viewUrl) ?>">Cancel</a>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
