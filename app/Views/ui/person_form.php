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
 * @var string                     $error       Why the last save bounced, if it did
 * @var string                     $editing     The card open for editing, '' for none
 * @var string                     $linkUrl     The pairing choice, as a page
 * @var string                     $linkNewUrl
 * @var string                     $linkExistingUrl
 */
// A save that bounced re-renders with what was typed rather than with what the
// record held, so a rejected MRN does not cost the rest of the form. The keys
// of $v are the field names, which is what makes this one line.
foreach ($v as $field => $value) {
    $v[$field] = old($field, $value);
}

$isRecipient = $personType === 'recipient';
$backUrl     = site_url($isRecipient ? 'recipients' : 'donors');
$backLabel   = $isRecipient ? 'Back to Recipient Waitlist' : 'Donors List';
$title       = $mode === 'add'
    ? ($isRecipient ? 'Add Recipient' : 'Add Donor')
    : ($person['name'] ?? ($isRecipient ? 'Recipient Profile' : 'Donor Profile'));
$eyebrow = $mode === 'add' ? 'New' : ($person['id'] ?? '');

// A saved record opens read-only and is edited one card at a time. Each Edit
// is a link back to this screen with the card named, so the card returns as a
// form and the screen still works with JavaScript off. A new record has
// nothing to read yet, so every card starts editable and the header keeps its
// single Save.
$viewUrl  = $mode === 'add' ? null : site_url(($isRecipient ? 'recipients/' : 'donors/') . rawurlencode($person['id']));
$editable = static fn (string $section): bool => $mode === 'add' || $editing === $section;
$editUrl  = static fn (string $section): string => $viewUrl . '?edit=' . $section;
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
                <?php // A real link to the choice at its own URL; ui.js opens
                      // the dialog below instead when it can. ?>
                <a class="btn-outline" href="<?= esc($linkUrl) ?>" data-dialog="link-choice"><?= ui_icon('link14') ?>Link with <?= esc($isRecipient ? 'Donor' : 'Recipient') ?></a>
            <?php endif; ?>
            <?php if ($mode === 'add'): ?>
                <button type="submit" form="person-form" class="btn-save">Save</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <form id="person-form" class="stack-5" method="post" action="<?= current_url() ?>">
        <?= csrf_field() ?>

        <div class="card card--pad">
            <div class="card-head">
                <h2 class="card-title">Personal Information</h2>
                <?php if (! $editable('personal')): ?>
                    <a class="btn-edit" href="<?= esc($editUrl('personal')) ?>"><?= ui_icon('edit') ?>Edit</a>
                <?php endif; ?>
            </div>
            <fieldset class="card-fields"<?= $editable('personal') ? '' : ' disabled' ?>>
            <?php if ($mode !== 'add' && $editable('personal')): ?>
                <input type="hidden" name="section" value="personal">
            <?php endif; ?>

            <?php if ($isRecipient): ?>
                <div class="stack-4">
                    <div class="form-grid-5">
                        <div>
                            <label class="field-label" for="f-mrn">Recipient MRN</label>
                            <?php if ($mode === 'add'): ?>
                                <input type="text" id="f-mrn" name="mrn" class="input input--mono" value="<?= esc($v['mrn']) ?>" inputmode="numeric" placeholder="From the hospital record" required>
                            <?php else: ?>
                                <input type="text" id="f-mrn" class="input-ro input-ro--mono" value="<?= esc($person['id']) ?>" readonly>
                            <?php endif; ?>
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
                            <label class="field-label" for="f-coordinator">Recipient Coordinator</label>
                            <input type="text" id="f-coordinator" name="coordinator" class="input" value="<?= esc($v['coordinator']) ?>" placeholder="Choose Coordinator">
                        </div>
                    </div>

                    <div class="form-grid-5">
                        <div>
                            <label class="field-label" for="f-dialysis">First Dialysis</label>
                            <?= view('ui/partials/date_field', ['id' => 'f-dialysis', 'name' => 'firstDialysis', 'value' => $v['firstDialysis']], ['saveData' => false]) ?>
                        </div>
                        <div>
                            <label class="field-label" for="f-entry">Entry Date</label>
                            <?= view('ui/partials/date_field', ['id' => 'f-entry', 'name' => null, 'value' => UiStore::isoToDMY($v['dateRegistered'])], ['saveData' => false]) ?>
                        </div>
                        <div>
                            <?php // The whole of it is one yes/no; there is no
                                  // scale behind it any more. A checkbox posts
                                  // nothing when it is off, so a hidden 0 goes
                                  // first and the box overrides it when ticked. ?>
                            <label class="field-label" for="f-urgent">Urgent?</label>
                            <input type="hidden" name="urgent" value="0">
                            <label class="check">
                                <input type="checkbox" id="f-urgent" name="urgent" value="1"<?= $v['urgent'] ? ' checked' : '' ?>>
                                <span>This case is urgent</span>
                            </label>
                        </div>
                        <div>
                            <?php // The same list, and the same value, as the pair's
                                  // Match Status: setting either sets the other. ?>
                            <label class="field-label" for="f-status">Recipient Status</label>
                            <select id="f-status" name="status" class="input">
                                <?php foreach (UiStore::STATUS_OPTIONS as $value => $label): ?>
                                    <option value="<?= esc($value) ?>"<?= $v['status'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="stack-4">
                    <div class="form-grid-5">
                        <div>
                            <label class="field-label" for="f-mrn">Donor MRN</label>
                            <?php if ($mode === 'add'): ?>
                                <input type="text" id="f-mrn" name="mrn" class="input input--mono" value="<?= esc($v['mrn']) ?>" inputmode="numeric" placeholder="From the hospital record" required>
                            <?php else: ?>
                                <input type="text" id="f-mrn" class="input-ro input-ro--mono" value="<?= esc($person['id']) ?>" readonly>
                            <?php endif; ?>
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
                            <label class="field-label" for="f-donor-coordinator">Donor Coordinator</label>
                            <input type="text" id="f-donor-coordinator" name="donorCoordinator" class="input" value="<?= esc($v['donorCoordinator']) ?>" placeholder="Choose Coordinator">
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
            </fieldset>

            <?php if ($mode !== 'add' && $editable('personal')): ?>
                <div class="card-actions">
                    <a class="btn-outline" href="<?= esc($viewUrl) ?>">Cancel</a>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            <?php endif; ?>
        </div>

        <?= view('ui/partials/lab_tests', [
            'tests'     => $labTests,
            'field'     => 'labs',
            'animated'  => true,
            'editTitle' => 'Add result',
            'editing'   => $editable('labs'),
            'editUrl'   => $mode === 'add' ? null : $editUrl('labs'),
            'viewUrl'   => $mode === 'add' ? null : $viewUrl,
            'section'   => $mode === 'add' ? null : 'labs',
        ]) ?>

        <div class="card card--pad">
            <div class="card-head">
                <h2 class="card-title">Clinical Notes</h2>
                <?php if (! $editable('notes')): ?>
                    <a class="btn-edit" href="<?= esc($editUrl('notes')) ?>"><?= ui_icon('edit') ?>Edit</a>
                <?php endif; ?>
            </div>
            <fieldset class="card-fields"<?= $editable('notes') ? '' : ' disabled' ?>>
                <?php if ($mode !== 'add' && $editable('notes')): ?>
                    <input type="hidden" name="section" value="notes">
                <?php endif; ?>
                <textarea class="textarea" name="notes" rows="5" placeholder="Add clinical notes, observations, or relevant context..."><?= esc($v['notes']) ?></textarea>
            </fieldset>

            <?php if ($mode !== 'add' && $editable('notes')): ?>
                <div class="card-actions">
                    <a class="btn-outline" href="<?= esc($viewUrl) ?>">Cancel</a>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($mode === 'view' && $linked === null): ?>
        <dialog id="link-choice" class="dialog">
            <div class="dialog-body">
                <form method="dialog" class="dialog-close-form">
                    <button class="dialog-close" aria-label="Close">&times;</button>
                </form>
                <?= view('ui/partials/link_choice', [
                    'counterpart' => $isRecipient ? 'donor' : 'recipient',
                    'newUrl'      => $linkNewUrl,
                    'existingUrl' => $linkExistingUrl,
                    'person'      => $person,
                ]) ?>
            </div>
        </dialog>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
