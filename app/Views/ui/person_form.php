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
 * @var string                     $forPair     The pair this new donor is being entered for
 * @var string                     $pairUrl     This recipient's pair, '' when they have none
 * @var bool                       $pairHasActive  Whether that pair already has its donor
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
// Entered from a pair's screen, Back belongs to that pair rather than to the
// register the donor has not been read on yet.
$backUrl     = $forPair !== ''
    ? site_url('recipients/' . rawurlencode($forPair))
    : site_url($isRecipient ? 'recipients' : 'donors');
$backLabel   = $isRecipient ? 'Back to Recipient Waitlist' : 'Donors List';
$title       = $mode === 'add'
    ? ($isRecipient ? 'Add Recipient' : 'Add Donor')
    : ($person['name'] ?? ($isRecipient ? 'Recipient Profile' : 'Donor Profile'));
$eyebrow = $mode === 'add' ? 'New' : ($person['id'] ?? '');

// A saved record opens read-only and is edited one card at a time. Each Edit
// is a link back to this screen with the card named, so the card returns as a
// form and the screen still works with JavaScript off. A new record has
// nothing to read yet, so every card starts editable and one Save at the foot
// of the form commits the lot — below the fields it saves, not above them.
// The age is not asked for any more: the date of birth is, and the age is
// what that comes to today. It is shown beside the field's own label rather
// than in a box of its own, because it is not a second answer — it is the
// same answer, read out. A record entered before birth dates were collected
// has only the number, and that is what shows.
$ageNote = static function (string $birthDate, string $storedAge): string {
    $age = $birthDate === '' ? $storedAge : (string) UiStore::ageFrom($birthDate);

    return $age === '' || $age === '0' ? '' : 'Age ' . $age;
};

$viewUrl  = $mode === 'add' ? null : site_url(($isRecipient ? 'recipients/' : 'donors/') . rawurlencode($person['id']));
$editable = static fn (string $section): bool => $mode === 'add' || $editing === $section;
$editUrl  = static fn (string $section): string => $viewUrl . '?edit=' . $section;
?>
<div class="page">
    <div class="page-header page-header--start page-header--wrap">
        <div>
            <a class="back-link" href="<?= $backUrl ?>"><?= ui_icon('back') ?>Back to <?= esc($forPair !== '' ? 'the pair' : ($isRecipient ? 'Recipient Waitlist' : 'Donors List')) ?></a>
            <div class="eyebrow"><?= esc($eyebrow) ?></div>
            <h1 class="page-title"><?= esc($title) ?></h1>
            <?php if ($forPair !== ''): ?>
                <p class="page-subtitle">Being entered for a pair. Saving adds them to it on the status chosen below &mdash; Active is only open to them when the pair has nobody active.</p>
            <?php endif; ?>
        </div>
        <div class="header-actions">
            <?php // A record has nothing to print until it has been saved. ?>
            <?php if ($mode === 'view'): ?>
                <a class="btn-outline" href="<?= site_url(($isRecipient ? 'recipients/' : 'donors/') . rawurlencode($person['id'])) ?>/print" target="_blank" rel="noopener"><?= ui_icon('printer') ?>Export PDF</a>
            <?php endif; ?>
            <?php // One link to a side, and the button goes once it is made.
                  // Making the pair is one step again: choose a new donor or a
                  // registered one and the pair exists. A pair's further
                  // donors are added on the pair's own screen, which is where
                  // the button turns into a link. ?>
            <?php if ($mode === 'view' && $linked !== null): ?>
                <?php // The recipient's goes to the pair, because that is
                      // where their donors are: the one it is going ahead
                      // with, and any it is holding in reserve. ?>
                <a class="btn-outline" href="<?= esc($isRecipient && $pairUrl !== '' ? $pairUrl : site_url('recipients/' . rawurlencode($linked['id']))) ?>">Linked: <?= esc($linked['name']) ?></a>
            <?php elseif ($mode === 'view'): ?>
                <?php // A real link to the choice at its own URL; ui.js opens
                      // the dialog below instead when it can. ?>
                <a class="btn-outline" href="<?= esc($linkUrl) ?>" data-dialog="link-choice"><?= ui_icon('link14') ?>Link with <?= esc($isRecipient ? 'Donor' : 'Recipient') ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <form id="person-form" class="stack-5" method="post" action="<?= current_url() ?>">
        <?= csrf_field() ?>
        <?php // Opened from a pair's screen: saving adds this donor to that
              // pair, and comes back to it with their tab open. ?>
        <?php if ($forPair !== ''): ?>
            <input type="hidden" name="pair" value="<?= esc($forPair) ?>">
        <?php endif; ?>

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
                            <label class="field-label" for="f-birth">
                                Date of Birth
                                <span class="field-note" data-age-note="f-birth"><?= esc($ageNote($v['birthDate'], $v['age'])) ?></span>
                            </label>
                            <?php // The number still travels, so a record saved
                                  // without a birth date keeps the age it was
                                  // entered with instead of dropping to zero. ?>
                            <input type="hidden" name="age" value="<?= esc($v['age']) ?>">
                            <?= view('ui/partials/date_field', ['id' => 'f-birth', 'name' => 'birthDate', 'value' => $v['birthDate'], 'past' => true], ['saveData' => false]) ?>
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
                            <?php // Before the date, because it decides whether
                                  // there is a date to give at all. ?>
                            <label class="field-label" for="f-dialysis-type">Type Dialysis</label>
                            <select id="f-dialysis-type" name="dialysisType" class="input" data-closes="f-dialysis" data-closes-when="<?= UiStore::DIALYSIS_PREEMPTIVE ?>">
                                <option value="">Not recorded</option>
                                <?php foreach (UiStore::DIALYSIS_TYPES as $value => $label): ?>
                                    <option value="<?= esc($value) ?>"<?= $v['dialysisType'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="f-dialysis">First Dialysis</label>
                            <?php // Pre-emptive means a transplant before
                                  // dialysis ever starts, so there is no first
                                  // one: the field closes rather than waiting
                                  // for a date that cannot exist. ?>
                            <?= view('ui/partials/date_field', [
                                'id'       => 'f-dialysis',
                                'name'     => 'firstDialysis',
                                'value'    => $v['firstDialysis'],
                                'past'     => true,
                                'disabled' => $v['dialysisType'] === UiStore::DIALYSIS_PREEMPTIVE,
                            ], ['saveData' => false]) ?>
                        </div>
                        <div>
                            <label class="field-label" for="f-entry">Entry Date</label>
                            <?php // Today on a new record, and still a field:
                                  // somebody entering a patient who arrived
                                  // last week should not have to leave it
                                  // saying they arrived now. ?>
                            <?= view('ui/partials/date_field', ['id' => 'f-entry', 'name' => 'dateRegistered', 'value' => UiStore::isoToDMY($v['dateRegistered']), 'past' => true], ['saveData' => false]) ?>
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
                            <?php // The person's own, and nobody else's. It used
                                  // to be the same value as the pair's Match
                                  // Status, and setting either set the other;
                                  // it cannot be, now that a recipient may hold
                                  // several donors — there would be no saying
                                  // which of them Declined meant. The pair's
                                  // status and the donor's are each their own
                                  // too, on their own cards. ?>
                            <label class="field-label" for="f-status">Recipient Status</label>
                            <select id="f-status" name="status" class="input">
                                <?php foreach (UiStore::PERSON_STATUS_OPTIONS as $value => $label): ?>
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
                            <label class="field-label" for="f-birth">
                                Date of Birth
                                <span class="field-note" data-age-note="f-birth"><?= esc($ageNote($v['birthDate'], $v['age'])) ?></span>
                            </label>
                            <input type="hidden" name="age" value="<?= esc($v['age']) ?>">
                            <?= view('ui/partials/date_field', ['id' => 'f-birth', 'name' => 'birthDate', 'value' => $v['birthDate'], 'past' => true], ['saveData' => false]) ?>
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
                            <?php // Living or deceased is all this screen can ask: whether a
                                  // living donor is related is a question about them and a
                                  // recipient, and there is no recipient here. A saved record
                                  // offers the full list, so a "Living Related" set on a pair
                                  // screen is not silently downgraded by opening this one. ?>
                            <label class="field-label" for="f-donation-type">Donor Type</label>
                            <select id="f-donation-type" name="donationType" class="input">
                                <?php foreach ($mode === 'add' ? UiStore::DONATION_TYPES_ON_REGISTER : array_keys(UiStore::DONATION_TYPES) as $value): ?>
                                    <option value="<?= esc($value) ?>"<?= $v['donationType'] === $value ? ' selected' : '' ?>><?= esc(UiStore::DONATION_TYPES[$value]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <?php // The donor's own. A pair's status and the
                                  // recipient's are theirs, on their own cards;
                                  // none of the three follows another.
                                  //
                                  // Entered for a pair that already has the
                                  // donor it is going ahead with, Active is
                                  // left off: there is one of those, and this
                                  // form is a slower way of pressing the same
                                  // control the pair's tab carries. ?>
                            <label class="field-label" for="f-donor-status">Donor Status</label>
                            <select id="f-donor-status" name="donorStatus" class="input">
                                <?php foreach (UiStore::DONOR_STATUS_OPTIONS as $value => $label): ?>
                                    <?php if ($value === 'Active' && $pairHasActive) { continue; } ?>
                                    <option value="<?= esc($value) ?>"<?= $v['donorStatus'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-5">
                        <div>
                            <?php // The register has always dated a donor; this
                                  // is the first screen to show it, and to let
                                  // it be corrected. ?>
                            <label class="field-label" for="f-entry">Entry Date</label>
                            <?= view('ui/partials/date_field', ['id' => 'f-entry', 'name' => 'dateRegistered', 'value' => UiStore::isoToDMY($v['dateRegistered']), 'past' => true], ['saveData' => false]) ?>
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
            'editing'   => $editable('labs'),
            'editUrl'   => $mode === 'add' ? null : $editUrl('labs'),
            'viewUrl'   => $mode === 'add' ? null : $viewUrl,
            'section'   => $mode === 'add' ? null : 'labs',
            // Nothing to add a test to until the record exists.
            'addLabUrl'    => $mode === 'add' ? null : site_url(($isRecipient ? 'recipients/' : 'donors/') . rawurlencode($person['id']) . '/labs'),
            'removeLabUrl' => $mode === 'add' ? null : site_url(($isRecipient ? 'recipients/' : 'donors/') . rawurlencode($person['id']) . '/labs'),
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

        <?php // A new record saves once, at the end. A saved one has a Save per
              // card instead, because it is edited a card at a time. ?>
        <?php if ($mode === 'add'): ?>
            <div class="form-actions">
                <a class="btn-outline" href="<?= $backUrl ?>">Cancel</a>
                <button type="submit" class="btn-save">Save <?= esc($isRecipient ? 'Recipient' : 'Donor') ?></button>
            </div>
        <?php endif; ?>
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
                ], ['saveData' => false]) ?>
            </div>
        </dialog>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
