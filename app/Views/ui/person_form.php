<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;

/**
 * Recipient / donor record. Was `js/pages/person-form.js`.
 *
 * One view covers all four combinations the prototype's `opts` did — add or
 * view, recipient or donor — but the fields are literal HTML rather than
 * `U.field(...)` calls, and Save is a submit button wired to the form by its
 * `form` attribute, so it can sit in the header exactly where it did before
 * without the header having to be inside the form.
 *
 * @var string                     $mode        "add" or "view"
 * @var string                     $personType  "recipient" or "donor"
 * @var array<string, mixed>       $v           Current field values
 * @var array<string, mixed>|null  $person      The stored record, when viewing
 * @var array<string, mixed>|null  $linked      The paired counterpart, if any
 * @var list<array<string, mixed>> $labTests
 * @var list<array{id: string, name: string}> $mrps
 */
$isRecipient = $personType === 'recipient';
$backUrl     = site_url($isRecipient ? 'recipients' : 'donors');
$backLabel   = $isRecipient ? 'Back to Recipient Waitlist' : 'Donors List';
$title       = $mode === 'add'
    ? ($isRecipient ? 'Add Recipient' : 'Add Donor')
    : ($person['name'] ?? ($isRecipient ? 'Recipient Profile' : 'Donor Profile'));
$eyebrow = $mode === 'add' ? 'New' : ($person['id'] ?? '');
?>
<div class="page">
    <div class="page-header page-header--start page-header--wrap">
        <div>
            <a class="back-link" href="<?= $backUrl ?>"><?= ui_icon('back') ?>Back to <?= esc($isRecipient ? 'Recipient Waitlist' : 'Donors List') ?></a>
            <div class="eyebrow"><?= esc($eyebrow) ?></div>
            <h1 class="page-title"><?= esc($title) ?></h1>
        </div>
        <div class="header-actions">
            <?php if ($mode === 'view' && $linked !== null): ?>
                <a class="btn-outline" href="<?= site_url(($isRecipient ? 'donors/' : 'recipients/') . rawurlencode($linked['id'])) ?>">Linked: <?= esc($linked['name']) ?></a>
            <?php elseif ($mode === 'view'): ?>
                <a class="btn-outline" href="<?= site_url($isRecipient ? 'donors' : 'recipients') ?>"><?= ui_icon('link14') ?>Link with <?= esc($isRecipient ? 'Donor' : 'Recipient') ?></a>
            <?php endif; ?>
            <button type="submit" form="person-form" class="btn-save">Save</button>
        </div>
    </div>

    <form id="person-form" class="stack-5" method="post" action="<?= current_url() ?>">
        <?= csrf_field() ?>

        <div class="card card--pad">
            <h2 class="card-title card-title--mb5">Personal Information</h2>

            <?php if ($isRecipient): ?>
                <div class="stack-4">
                    <div class="form-grid-5">
                        <div>
                            <label class="field-label">Recipient MRN</label>
                            <input type="text" class="input-ro input-ro--faint input-ro--mono" value="<?= esc($person['id'] ?? 'Auto') ?>" readonly>
                        </div>
                        <div class="span-lg-2">
                            <label class="field-label" for="f-name">Recipient Name</label>
                            <input type="text" id="f-name" name="name" class="input" value="<?= esc($v['name']) ?>" placeholder="Full name">
                        </div>
                        <div>
                            <label class="field-label" for="f-address">Recipient City</label>
                            <input type="text" id="f-address" name="address" class="input" value="<?= esc($v['address']) ?>" placeholder="City">
                        </div>
                        <div>
                            <label class="field-label" for="f-phone">Phone Number</label>
                            <input type="tel" id="f-phone" name="phone" class="input" value="<?= esc($v['phone']) ?>" placeholder="+966 5x xxx xxxx">
                        </div>
                    </div>

                    <div class="form-grid-5">
                        <div>
                            <label class="field-label" for="f-gender">Recipient Gender</label>
                            <select id="f-gender" name="gender" class="input">
                                <?php foreach (UiStore::GENDER_OPTIONS as $value => $label): ?>
                                    <option value="<?= esc($value) ?>"<?= $v['gender'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="f-age">Recipient Age</label>
                            <input type="number" id="f-age" name="age" class="input" value="<?= esc($v['age']) ?>" placeholder="Age">
                        </div>
                        <div>
                            <label class="field-label" for="f-blood">Blood Group</label>
                            <select id="f-blood" name="bloodType" class="input input--mono">
                                <?php foreach (UiStore::BLOOD_TYPES as $bloodType): ?>
                                    <option value="<?= esc($bloodType) ?>"<?= $v['bloodType'] === $bloodType ? ' selected' : '' ?>><?= esc($bloodType) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="f-mrp">Recipient MRP</label>
                            <select id="f-mrp" name="selectedMrp" class="input">
                                <option value="">Choose MRP</option>
                                <?php foreach ($mrps as $mrp): ?>
                                    <option value="<?= esc($mrp['id']) ?>"<?= $v['selectedMrp'] === $mrp['id'] ? ' selected' : '' ?>><?= esc($mrp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="f-hospital">Hospital</label>
                            <input type="text" id="f-hospital" name="hospital" class="input" value="<?= esc($v['hospital']) ?>" placeholder="Hospital">
                        </div>
                    </div>

                    <div class="form-grid-5">
                        <div>
                            <label class="field-label" for="f-diagnosis">Diagnosis</label>
                            <input type="text" id="f-diagnosis" name="diagnosis" class="input" value="<?= esc($v['diagnosis']) ?>" placeholder="Primary diagnosis">
                        </div>
                        <div>
                            <label class="field-label" for="f-dialysis">First Dialysis</label>
                            <input type="text" id="f-dialysis" name="firstDialysis" class="input" value="<?= esc($v['firstDialysis']) ?>" placeholder="DD/MM/YYYY">
                        </div>
                        <div>
                            <label class="field-label">Entry Date</label>
                            <input type="text" class="input-ro" value="<?= esc(UiStore::isoToDMY($v['dateRegistered'])) ?>" readonly>
                        </div>
                        <div>
                            <label class="field-label" for="f-urgency">Urgency</label>
                            <select id="f-urgency" name="urgency" class="input" data-urgency>
                                <?php foreach (UiStore::URGENCY_OPTIONS as $value => $label): ?>
                                    <option value="<?= esc($value) ?>"<?= $v['urgency'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="f-urgent">Urgent?</label>
                            <select id="f-urgent" name="urgent" class="input" data-urgent>
                                <?php foreach (UiStore::URGENT_OPTIONS as $value => $label): ?>
                                    <option value="<?= esc($value) ?>"<?= UiStore::isUrgent($v['urgency']) === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="stack-4">
                    <div class="form-grid-5">
                        <div>
                            <label class="field-label">Donor MRN</label>
                            <input type="text" class="input-ro input-ro--faint input-ro--mono" value="<?= esc($person['id'] ?? 'Auto') ?>" readonly>
                        </div>
                        <div>
                            <label class="field-label" for="f-name">Donor Name</label>
                            <input type="text" id="f-name" name="name" class="input" value="<?= esc($v['name']) ?>" placeholder="Full name">
                        </div>
                        <div>
                            <label class="field-label" for="f-address">Donor City</label>
                            <input type="text" id="f-address" name="address" class="input" value="<?= esc($v['address']) ?>" placeholder="City">
                        </div>
                        <div>
                            <label class="field-label" for="f-phone">Donor Phone Number</label>
                            <input type="tel" id="f-phone" name="phone" class="input" value="<?= esc($v['phone']) ?>" placeholder="+966 5x xxx xxxx">
                        </div>
                        <div>
                            <label class="field-label" for="f-donor-gender">Donor Gender</label>
                            <select id="f-donor-gender" name="donorGender" class="input">
                                <?php foreach (UiStore::GENDER_OPTIONS as $value => $label): ?>
                                    <option value="<?= esc($value) ?>"<?= $v['donorGender'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-5">
                        <div>
                            <label class="field-label" for="f-age">Donor Age</label>
                            <input type="number" id="f-age" name="age" class="input" value="<?= esc($v['age']) ?>" placeholder="Age">
                        </div>
                        <div>
                            <label class="field-label" for="f-blood">Donor Blood Group</label>
                            <select id="f-blood" name="bloodType" class="input input--mono">
                                <?php foreach (UiStore::BLOOD_TYPES as $bloodType): ?>
                                    <option value="<?= esc($bloodType) ?>"<?= $v['bloodType'] === $bloodType ? ' selected' : '' ?>><?= esc($bloodType) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="f-donor-mrp">Donor MRP</label>
                            <select id="f-donor-mrp" name="donorMrp" class="input">
                                <option value="">Choose MRP</option>
                                <?php foreach ($mrps as $mrp): ?>
                                    <option value="<?= esc($mrp['id']) ?>"<?= $v['donorMrp'] === $mrp['id'] ? ' selected' : '' ?>><?= esc($mrp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="f-coordinator">Donor Coordinator</label>
                            <input type="text" id="f-coordinator" name="donorCoordinator" class="input" value="<?= esc($v['donorCoordinator']) ?>" placeholder="Choose Coordinator">
                        </div>
                        <div>
                            <label class="field-label" for="f-donor-status">Donor Status</label>
                            <select id="f-donor-status" name="donorStatus" class="input">
                                <?php foreach (UiStore::DONOR_STATUS_OPTIONS as $value => $label): ?>
                                    <option value="<?= esc($value) ?>"<?= $v['donorStatus'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?= view('ui/partials/lab_tests', ['tests' => $labTests, 'field' => 'labs', 'animated' => true, 'editTitle' => 'Add result']) ?>

        <div class="card card--pad">
            <h2 class="card-title card-title--mb4">Clinical Notes</h2>
            <textarea class="textarea" name="notes" rows="5" placeholder="Add clinical notes, observations, or relevant context..."><?= esc($v['notes']) ?></textarea>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
