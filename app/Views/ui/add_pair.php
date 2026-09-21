<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;

/**
 * New pair. Was `js/pages/add-pair.js`.
 *
 * Saving still creates three records at once — recipient, donor and the pair
 * linking them — but the browser no longer holds them: the form posts and the
 * controller writes all three.
 *
 * @var array<string, mixed>                  $v          Current field values
 * @var list<array<string, mixed>>            $rLabTests
 * @var list<array<string, mixed>>            $dLabTests
 * @var list<array{id: string, name: string}> $mrps
 * @var string                                $entryDate  ISO, shown as DD/MM/YYYY
 * @var string                                $error      Why the last save bounced, if it did
 * @var string                                $fixedSide  "recipient", "donor" or '' when both are new
 * @var array<string, mixed>|null             $fixed      The person already on the system
 */
// Reached from a record's "Link with a new …": that half is filled in and
// shown read-only, the same disabled fieldset the record screens use, and the
// form collects only the other one. Their MRN rides along in a hidden input,
// since a disabled fieldset posts nothing.
$fixedIs = static fn (string $side): bool => $fixedSide === $side;
// A save that bounced re-renders with what was typed. The keys of $v are the
// field names, which is what makes this one line.
foreach ($v as $field => $value) {
    $v[$field] = old($field, $value);
}
?>
<div class="page">
    <div class="page-header page-header--start page-header--wrap">
        <div>
            <a class="back-link" href="<?= site_url('pairs') ?>"><?= ui_icon('back') ?>Back to Pairs List</a>
            <div class="eyebrow"><?= $fixedSide === '' ? 'New Pair' : 'Link' ?></div>
            <h1 class="page-title"><?= $fixedSide === '' ? 'Add Pair' : 'Link ' . esc($fixed['name']) ?></h1>
            <?php if ($fixedSide !== ''): ?>
                <p class="page-subtitle">Enter the <?= esc($fixedIs('recipient') ? 'donor' : 'recipient') ?> to pair with this record.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <form id="pair-form" class="stack-5" method="post" action="<?= site_url('pairs/new') ?>">
        <?= csrf_field() ?>
        <?php if ($fixedSide !== ''): ?>
            <input type="hidden" name="fixedSide" value="<?= esc($fixedSide) ?>">
            <input type="hidden" name="<?= $fixedIs('recipient') ? 'rMrn' : 'dMrn' ?>" value="<?= esc($fixed['id']) ?>">
        <?php endif; ?>

        <div class="card card--pad">
            <h2 class="card-title card-title--mb5">Pair Details</h2>
            <div class="form-grid-2">
                <div>
                    <label class="field-label" for="f-relationship">Relationship</label>
                    <input type="text" id="f-relationship" name="relationship" class="input" value="<?= esc($v['relationship']) ?>" placeholder="e.g. Sibling, Spouse">
                </div>
                <div>
                    <label class="field-label" for="f-crossmatch">Date of Crossmatch</label>
                    <?= view('ui/partials/date_field', ['id' => 'f-crossmatch', 'name' => 'crossmatchDate', 'value' => $v['crossmatchDate']], ['saveData' => false]) ?>
                </div>
            </div>
        </div>

        <div class="card card--pad">
            <div class="card-head">
                <div class="section-head">
                    <div class="role-badge role-badge--recipient">R</div>
                    <h2 class="card-title">Recipient — Personal Information</h2>
                </div>
                <?php if ($fixedIs('recipient')): ?>
                    <span class="link-fixed-note">Already registered — linking this record</span>
                <?php endif; ?>
            </div>
            <fieldset class="card-fields"<?= $fixedIs('recipient') ? ' disabled' : '' ?>>
            <div class="stack-4">
                <div class="form-grid-5">
                    <div>
                        <label class="field-label" for="f-r-mrn">Recipient MRN</label>
                        <?php if ($fixedIs('recipient')): ?>
                            <input type="text" id="f-r-mrn" class="input-ro input-ro--mono" value="<?= esc($v['rMrn']) ?>" readonly>
                        <?php else: ?>
                            <input type="text" id="f-r-mrn" name="rMrn" class="input input--mono" value="<?= esc($v['rMrn']) ?>" inputmode="numeric" placeholder="From the hospital record" required>
                        <?php endif; ?>
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
                            <option value="">Choose MRP</option>
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
        </div>

        <?= view('ui/partials/lab_tests', [
            'tests'    => $rLabTests,
            'field'    => 'rLabs',
            'animated' => true,
            'editing'  => ! $fixedIs('recipient'),
        ]) ?>

        <div class="card card--pad">
            <h2 class="card-title card-title--mb4">Recipient — Clinical Notes</h2>
            <fieldset class="card-fields"<?= $fixedIs('recipient') ? ' disabled' : '' ?>>
                <textarea class="textarea" name="rNotes" rows="4" placeholder="Add clinical notes, observations, or relevant context..."><?= esc($v['rNotes']) ?></textarea>
            </fieldset>
        </div>

        <div class="card card--pad">
            <div class="card-head">
                <div class="section-head">
                    <div class="role-badge role-badge--donor">D</div>
                    <h2 class="card-title">Donor — Personal Information</h2>
                </div>
                <?php if ($fixedIs('donor')): ?>
                    <span class="link-fixed-note">Already registered — linking this record</span>
                <?php endif; ?>
            </div>
            <fieldset class="card-fields"<?= $fixedIs('donor') ? ' disabled' : '' ?>>
            <div class="stack-4">
                <div class="form-grid-5">
                    <div>
                        <label class="field-label" for="f-d-mrn">Donor MRN</label>
                        <?php if ($fixedIs('donor')): ?>
                            <input type="text" id="f-d-mrn" class="input-ro input-ro--mono" value="<?= esc($v['dMrn']) ?>" readonly>
                        <?php else: ?>
                            <input type="text" id="f-d-mrn" name="dMrn" class="input input--mono" value="<?= esc($v['dMrn']) ?>" inputmode="numeric" placeholder="From the hospital record" required>
                        <?php endif; ?>
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
                            <option value="">Choose MRP</option>
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
                        <?php // The recipient is on this screen, so the finer question can
                              // be asked here: whether a living donor is related to them. ?>
                        <label class="field-label" for="f-d-type">Donor Type</label>
                        <select id="f-d-type" name="dType" class="input">
                            <?php foreach (UiStore::DONATION_TYPES_ON_PAIR as $value): ?>
                                <option value="<?= esc($value) ?>"<?= $v['dType'] === $value ? ' selected' : '' ?>><?= esc(UiStore::DONATION_TYPES[$value]) ?></option>
                            <?php endforeach; ?>
                        </select>
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
        </div>

        <?= view('ui/partials/lab_tests', [
            'tests'    => $dLabTests,
            'field'    => 'dLabs',
            'animated' => true,
            'editing'  => ! $fixedIs('donor'),
        ]) ?>

        <div class="card card--pad">
            <h2 class="card-title card-title--mb4">Donor — Clinical Notes</h2>
            <fieldset class="card-fields"<?= $fixedIs('donor') ? ' disabled' : '' ?>>
                <textarea class="textarea" name="dNotes" rows="4" placeholder="Add clinical notes, observations, or relevant context..."><?= esc($v['dNotes']) ?></textarea>
            </fieldset>
        </div>

        <?php // At the foot of the form, below everything it saves. ?>
        <div class="form-actions">
            <a class="btn-outline" href="<?= site_url('pairs') ?>">Cancel</a>
            <button type="submit" class="btn-save">Save Pair</button>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
