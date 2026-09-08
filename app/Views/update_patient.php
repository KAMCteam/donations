<head>
    <?= $this->include('partials/favicon') ?>
    <title>تحديث معلومات المريض</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/form.css') ?>">
</head>

<body>
    <?= $this->include('partials/navigation_links') ?>

    <div class="container">
        <?= view('partials/error_handler', [
            'confirmation' => $confirmation,
            'error'        => $error,
        ]) ?>

    <form method="post" action="<?= site_url('UpdatePatient/update') ?>">
        <?= csrf_field() ?>
        <div id="submit-form-div">
            <h3 class="form-name"><?= esc(lang('Form.form_update_patient')) ?></h3>

            <!-------------------------------->
            <!-------- Update Patient -------->
            <!-------------------------------->
            <div class="row">
                <div class="item">
                    <label><?= esc(lang('Form.form_mrn')) ?>
                    <input type="text" name="mrn" required value="<?= esc($patientInfo['mrn'] ?? '', 'attr') ?>">
                    </label>
                </div>

                <div class="item">
                    <label><?= esc(lang('Form.form_name')) ?>
                    <input type="text" name="name" required value="<?= esc($patientInfo['name'] ?? '', 'attr') ?>">
                    </label>
                </div>

                <div class="item">
                    <label><?= esc(lang('Form.form_city')) ?>
                    <input type="text" name="city" required value="<?= esc($patientInfo['city'] ?? '', 'attr') ?>">
                    </label>
                </div>

                <div class="item">
                    <label><?= esc(lang('Form.form_phone_number')) ?>
                    <input type="text" name="phone_number" required value="<?= esc($patientInfo['phone_number'] ?? '', 'attr') ?>">
                    </label>
                </div>

                <div class="item">
                    <label><?= esc(lang('Form.form_gender')) ?>
                    <select name="gender" required>
                        <option value="M" <?= (($patientInfo['gender'] ?? '') === 'M') ? 'selected' : '' ?>><?= esc(lang('Form.form_M')) ?></option>
                        <option value="F" <?= (($patientInfo['gender'] ?? '') === 'F') ? 'selected' : '' ?>><?= esc(lang('Form.form_F')) ?></option>
                    </select>
                    </label>
                </div>
            </div>

            <div class="row">
                <div class="item">
                    <label><?= esc(lang('Form.form_age')) ?>
                    <input type="text" name="age" required value="<?= esc($patientInfo['age'] ?? '', 'attr') ?>">
                    </label>
                </div>

                <div class="item">
                    <label><?= esc(lang('Form.form_blood_group')) ?>
                    <select name="blood_group" required>
                        <?php foreach (['A', 'B', 'AB', 'O'] as $bloodGroup): ?>
                            <option value="<?= $bloodGroup ?>" <?= (($patientInfo['blood_group'] ?? '') === $bloodGroup) ? 'selected' : '' ?>><?= $bloodGroup ?></option>
                        <?php endforeach; ?>
                    </select>
                    </label>
                </div>

                <div class="item">
                    <label><?= esc(lang('Form.form_mrp')) ?>
                    <select name="mrp">
                        <option value=""><?= esc(lang('Form.form_choose_mrp')) ?></option>
                        <?php foreach ($mrps as $mrp): ?>
                            <option value="<?= esc($mrp['id'], 'attr') ?>" <?= ((string) ($patientInfo['mrp_id'] ?? '') === (string) $mrp['id']) ? 'selected' : '' ?>><?= esc($mrp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    </label>
                </div>

                <div class="item">
                    <label><?= esc(lang('Form.form_status')) ?>
                    <select name="status" required>
                        <option value="pending" <?= (($patientInfo['status'] ?? '') === 'pending') ? 'selected' : '' ?>><?= esc(lang('Form.form_pending')) ?></option>
                        <option value="ready" <?= (($patientInfo['status'] ?? '') === 'ready') ? 'selected' : '' ?>><?= esc(lang('Form.form_ready')) ?></option>
                    </select>
                    </label>
                </div>
            </div>
            <hr>
            <!----------------------------->
            <!-------- Lab Results -------->
            <!----------------------------->
            <h3><?= esc(lang('Form.form_labs_header')) ?></h3>
            <div class="lab-row">
                <?php foreach ($patientInfo[0]['labs'] ?? [] as $result): ?>
                    <div class="lab-item">
                        <label><?= esc($result['lab_name']) ?>
                        <?php $flag = ! empty($result['result']); ?>
                        <?php if ($result['result_shape'] === 'numerical'): ?>
                            <input type="text" name="<?= esc($result['actual_id'], 'attr') ?>" value="<?= $flag ? esc($result['result'], 'attr') : '' ?>">
                        <?php elseif ($result['result_shape'] === 'status'): ?>
                            <select name="<?= esc($result['actual_id'], 'attr') ?>">
                                <option value=""><?= esc(lang('Form.form_lab_status_none')) ?></option>
                                <?php
                                $labStatuses = [
                                    'done'     => lang('Form.form_lab_status_done'),
                                    'not done' => lang('Form.form_lab_status_not_done'),
                                    'pending'  => lang('Form.form_lab_status_pending'),
                                    'na'       => lang('Form.form_lab_status_na'),
                                ];
                                ?>
                                <?php foreach ($labStatuses as $value => $label): ?>
                                    <option value="<?= esc($value, 'attr') ?>" <?= ($flag && $result['result'] === $value) ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div>
                <label><?= esc(lang('Form.form_note')) ?>
                <textarea name="note"><?= esc($patientInfo['note'] ?? '') ?></textarea>
                </label>
            </div>

            <!----------------------------------------->
            <!-------- Recipient/Donor Buttons -------->
            <!----------------------------------------->
            <div class="radio-box-container">
                <div class="radio-box rtl" id="recipient-box">
                    <label><?= esc(lang('Form.form_recipient')) ?>
                    <input type="radio" name="patient_type" id="recipient-radio" value="recipient" required <?= (($patientInfo['type'] ?? '') === 'recipient') ? 'checked' : '' ?>>
                    </label>
                </div>

                <div class="radio-box rtl" id="donor-box">
                    <label><?= esc(lang('Form.form_donor')) ?>
                    <input type="radio" name="patient_type" id="donor-radio" value="donor" required <?= (($patientInfo['type'] ?? '') === 'donor') ? 'checked' : '' ?>>
                    </label>
                </div>
            </div>

            <!---------------------------------->
            <!-------- Recipient Chosen -------->
            <!---------------------------------->
            <div id="recipient" class="nodis">
                <div class="row">
                    <div class="item">
                        <label><?= esc(lang('Form.form_dialysis')) ?>
                        <input type="date" name="dialysis" class="recipient-hidden" value="<?= esc($patientInfo['dialysis'] ?? '', 'attr') ?>">
                        </label>
                    </div>

                    <div class="item">
                        <label><?= esc(lang('Form.form_waiting_since')) ?>
                        <input type="date" name="entry_date" class="recipient-hidden" value="<?= esc($patientInfo['entry_date'] ?? '', 'attr') ?>">
                        </label>
                    </div>

                    <div class="item">
                        <label><?= esc(lang('Form.form_urgency')) ?>
                        <select name="urgency" required>
                            <option value="0" <?= ((string) ($patientInfo['urgency'] ?? '') === '0') ? 'selected' : '' ?>><?= esc(lang('Form.form_urgent_0')) ?></option>
                            <option value="1" <?= ((string) ($patientInfo['urgency'] ?? '') === '1') ? 'selected' : '' ?>><?= esc(lang('Form.form_urgent_1')) ?></option>
                        </select>
                        </label>
                    </div>
                </div>

                <div class="radio-box-container">
                    <div class="radio-box rtl" id="donor-box-true">
                        <label><?= esc(lang('Form.form_donor_true')) ?>
                        <input type="radio" name="has_donor" id="donor-radio-true" class="recipient-hidden" value="true" <?= (! empty($hasDonor)) ? 'checked' : '' ?>>
                        </label>
                    </div>

                    <div class="radio-box rtl" id="donor-box-false">
                        <label><?= esc(lang('Form.form_donor_false')) ?>
                        <input type="radio" name="has_donor" id="donor-radio-false" class="recipient-hidden" value="false" <?= (empty($hasDonor)) ? 'checked' : '' ?>>
                        </label>
                    </div>
                </div>
            </div>

            <!------------------------------>
            <!-------- Donor Chosen -------->
            <!------------------------------>
            <div id="donor" class="nodis">
                <?php
                // The recipient this donor is already paired with is not in the
                // unmatched list, so put them at the front of the options.
                if (! empty($myRecipient)) {
                    $unmatchedRecipients = array_merge($myRecipient, $unmatchedRecipients);
                }
                ?>

                <div class="row">
                    <div class="item">
                        <label><?= esc(lang('Form.form_choose_recipient')) ?>
                        <select name="recipient_pair" class="choose-recipient-mrn">
                            <option value=""><?= esc(lang('Form.form_recipient_false')) ?></option>
                            <?php foreach ($unmatchedRecipients as $ur): ?>
                                <?php $isAssigned = ! empty($hasRecipient) && (string) $ur['mrn'] === (string) $hasRecipient['recipient_mrn']; ?>
                                <option value="<?= esc($ur['mrn'], 'attr') ?>" <?= $isAssigned ? 'selected' : '' ?>><?= esc($ur['name']) . ($isAssigned ? esc(lang('Form.form_assigned_recipient')) : '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        </label>
                    </div>

                    <div class="item">
                        <label><?= esc(lang('Form.form_match_status')) ?>
                        <select name="match_status_recipient" required>
                            <?php
                            $matchStatuses = [
                                'pending'         => lang('Form.form_pending'),
                                'confirmed'       => lang('Form.form_confirmed'),
                                'closed'          => lang('Form.form_closed'),
                                'completed'       => lang('Form.form_completed'),
                                'paired_exchange' => lang('Form.form_paired_exchange'),
                            ];
                            ?>
                            <?php foreach ($matchStatuses as $value => $label): ?>
                                <option value="<?= esc($value, 'attr') ?>" <?= (($hasRecipient['match_status'] ?? '') === $value) ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </label>
                    </div>

                    <div class="item">
                        <label><?= esc(lang('Form.form_relationship')) ?>
                        <input type="text" name="relationship_recipient" id="relationship-recipient" value="<?= esc($hasRecipient['relationship'] ?? '', 'attr') ?>">
                        </label>
                    </div>

                    <div class="item">
                        <label><?= esc(lang('Form.form_committee_date')) ?>
                        <input type="date" name="recipient_committee_date" value="<?= esc($hasRecipient['matched_on'] ?? '', 'attr') ?>">
                        </label>
                    </div>

                    <div class="item">
                        <label><?= esc(lang('Form.form_crossmatch_date')) ?>
                        <input type="date" name="recipient_crossmatch_date" value="<?= esc($hasRecipient['surgery_on'] ?? '', 'attr') ?>">
                        </label>
                    </div>
                </div>
            </div>

            <!------------------------------------------------>
            <!-------- Recipient Chosen and has donor -------->
            <!------------------------------------------------>
            <div id="donor-true" class="nodis">
                <?php
                // Same idea for the donor this recipient is already paired with.
                if (! empty($myDonor)) {
                    $unmatchedDonors = array_merge($myDonor, $unmatchedDonors);
                }
                ?>
                <?php if (empty($unmatchedDonors)): ?>
                    <?= esc(lang('Form.form_donors_null')) ?>
                <?php else: ?>
                    <div class="row">
                        <div class="item">
                            <label><?= esc(lang('Form.form_choose_donor')) ?>
                            <select name="donor_pair" class="choose-donor-mrn" required>
                                <?php foreach ($unmatchedDonors as $ud): ?>
                                    <?php $isAssigned = ! empty($hasDonor) && (string) $ud['mrn'] === (string) $hasDonor['donor_mrn']; ?>
                                    <option value="<?= esc($ud['mrn'], 'attr') ?>" <?= $isAssigned ? 'selected' : '' ?>><?= esc($ud['name']) . ($isAssigned ? esc(lang('Form.form_assigned_donor')) : '') ?></option>
                                <?php endforeach; ?>
                            </select>
                            </label>
                        </div>

                        <div class="item">
                            <label><?= esc(lang('Form.form_match_status')) ?>
                            <select name="match_status_donor" required>
                                <?php foreach ($matchStatuses as $value => $label): ?>
                                    <option value="<?= esc($value, 'attr') ?>" <?= (($hasDonor['match_status'] ?? '') === $value) ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            </label>
                        </div>

                        <div class="item">
                            <label><?= esc(lang('Form.form_relationship')) ?>
                            <input type="text" name="relationship_donor" value="<?= esc($hasDonor['relationship'] ?? '', 'attr') ?>">
                            </label>
                        </div>

                        <div class="item">
                            <label><?= esc(lang('Form.form_committee_date')) ?>
                            <input type="date" name="donor_committee_date" value="<?= esc($hasDonor['matched_on'] ?? '', 'attr') ?>">
                            </label>
                        </div>

                        <div class="item">
                            <label><?= esc(lang('Form.form_crossmatch_date')) ?>
                            <input type="date" name="donor_crossmatch_date" value="<?= esc($hasDonor['surgery_on'] ?? '', 'attr') ?>">
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <button type="submit" class="form-submit"><?= esc(lang('Form.form_submit')) ?></button>
    </form>
    </div>
</body>

<script src="<?= base_url('assets/js/radio_buttons_popup.js') ?>"></script>
