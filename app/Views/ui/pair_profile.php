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
 */
?>
<div class="page">
    <div class="page-header page-header--start page-header--wrap">
        <div>
            <a class="back-link" href="<?= site_url('pairs') ?>"><?= ui_icon('back') ?>Back to Pairs List</a>
            <div class="eyebrow"><?= esc($pair['id']) ?></div>
            <h1 class="page-title">Pair Profile</h1>
        </div>
        <button type="submit" form="pair-form" class="btn-save">Save Changes</button>
    </div>

    <form id="pair-form" class="stack-5" method="post" action="<?= current_url() ?>">
        <?= csrf_field() ?>

        <div class="card card--pad">
            <h2 class="card-title card-title--mb5">Pair Details</h2>
            <div class="form-grid-3">
                <div>
                    <label class="field-label" for="f-relationship">Relationship</label>
                    <input type="text" id="f-relationship" name="relationship" class="input" value="<?= esc($v['relationship']) ?>" placeholder="e.g. Sibling, Spouse">
                </div>
                <div>
                    <label class="field-label" for="f-crossmatch">Date of Crossmatch</label>
                    <input type="text" id="f-crossmatch" name="crossmatchDate" class="input" value="<?= esc($v['crossmatchDate']) ?>" placeholder="DD/MM/YYYY">
                </div>
                <div>
                    <label class="field-label" for="f-status">Match Status</label>
                    <select id="f-status" name="pairStatus" class="input">
                        <?php foreach (UiStore::PAIR_STATUS_OPTIONS as $value => $label): ?>
                            <option value="<?= esc($value) ?>"<?= $v['pairStatus'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="card card--pad">
            <div class="section-head">
                <div class="role-badge role-badge--recipient">R</div>
                <h2 class="card-title">Recipient — Personal Information</h2>
            </div>
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
                        <label class="field-label" for="f-r-hospital">Hospital</label>
                        <input type="text" id="f-r-hospital" name="rHospital" class="input" value="<?= esc($v['rHospital']) ?>" placeholder="Hospital">
                    </div>
                </div>

                <div class="form-grid-5">
                    <div>
                        <label class="field-label" for="f-r-diagnosis">Diagnosis</label>
                        <input type="text" id="f-r-diagnosis" name="rDiagnosis" class="input" value="<?= esc($v['rDiagnosis']) ?>" placeholder="Primary diagnosis">
                    </div>
                    <div>
                        <label class="field-label" for="f-r-dialysis">First Dialysis</label>
                        <input type="text" id="f-r-dialysis" name="rFirstDialysis" class="input" value="<?= esc($v['rFirstDialysis']) ?>" placeholder="DD/MM/YYYY">
                    </div>
                    <div>
                        <label class="field-label">Entry Date</label>
                        <input type="text" class="input-ro" value="<?= esc(UiStore::isoToDMY($entryDate)) ?>" readonly>
                    </div>
                    <div>
                        <label class="field-label" for="f-r-urgency">Urgency</label>
                        <select id="f-r-urgency" name="rUrgency" class="input" data-urgency>
                            <?php foreach (UiStore::URGENCY_OPTIONS as $value => $label): ?>
                                <option value="<?= esc($value) ?>"<?= $v['rUrgency'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="f-r-urgent">Urgent?</label>
                        <select id="f-r-urgent" name="rUrgent" class="input" data-urgent>
                            <?php foreach (UiStore::URGENT_OPTIONS as $value => $label): ?>
                                <option value="<?= esc($value) ?>"<?= UiStore::isUrgent($v['rUrgency']) === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <?= view('ui/partials/lab_tests', ['tests' => $rLabTests, 'field' => 'rLabs']) ?>

        <div class="card card--pad">
            <h2 class="card-title card-title--mb4">Recipient — Clinical Notes</h2>
            <textarea class="textarea" name="rNotes" rows="4" placeholder="Add clinical notes, observations, or relevant context..."><?= esc($v['rNotes']) ?></textarea>
        </div>

        <div class="card card--pad">
            <div class="section-head">
                <div class="role-badge role-badge--donor">D</div>
                <h2 class="card-title">Donor — Personal Information</h2>
            </div>
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
        </div>

        <?= view('ui/partials/lab_tests', ['tests' => $dLabTests, 'field' => 'dLabs']) ?>

        <div class="card card--pad">
            <h2 class="card-title card-title--mb4">Donor — Clinical Notes</h2>
            <textarea class="textarea" name="dNotes" rows="4" placeholder="Add clinical notes, observations, or relevant context..."><?= esc($v['dNotes']) ?></textarea>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
