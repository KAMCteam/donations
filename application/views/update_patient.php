<head>
    <?php $this->load->view('partials/favicon.php') ?>
    <title>تحديث معلومات المريض</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/form.css') ?>">
</head>

<body>
    <?php $this->load->view('partials/navigation_links.php') ?>
    
    <div class="container">
        <?php 
        $messages = [
            'confirmation' => $confirmation,
            'error' => $error,
        ];
        $this->load->view('partials/error_handler.php', $messages);
        ?>

    <form method="post" action="<?= base_url('UpdatePatient/update') ?>">
        <div id="submit-form-div">            
            <h3 class="form-name"><?= lang('form_update_patient') ?></h3>
            
            <!-------------------------------->
            <!-------- Update Patient -------->
            <!-------------------------------->
            <div class="row">
                <div class="item">
                    <label><?= lang('form_mrn') ?>
                    <input type="text" name="mrn" required value="<?= $patientInfo['mrn'] ?>">
                    </label>
                </div>

                <div class="item">
                    <label><?= lang('form_name') ?>
                    <input type="text" name="name" required value="<?= $patientInfo['name'] ?>">
                    </label>
                </div>

                <div class="item">
                    <label><?= lang('form_city') ?>
                    <input type="text" name="city" required value="<?= $patientInfo['city'] ?>">
                    </label>
                </div>

                <div class="item">
                    <label><?= lang('form_phone_number') ?>
                    <input type="text" name="phone_number" required value="<?= $patientInfo['phone_number'] ?>">
                    </label>
                </div>

                <div class="item">
                    <label><?= lang('form_gender') ?>
                    <select name="gender" required>
                        <option value="M" <?= ($patientInfo['gender'] === 'M') ? 'selected' : '' ?>><?= lang('form_male') ?></option>
                        <option value="F" <?= ($patientInfo['gender'] === 'F') ? 'selected' : '' ?>><?= lang('form_female') ?></option>
                    </select>
                    </label>
                </div>
            </div>

            <div class="row">
                <div class="item">
                    <label><?= lang('form_age') ?>
                    <input type="text" name="age" required value="<?= $patientInfo['age'] ?>">
                    </label>
                </div>
            
                <div class="item">
                    <label><?= lang('form_blood_group') ?>
                    <select name="blood_group" required>
                        <option value="A" <?= ($patientInfo['blood_group'] === 'A') ? 'selected' : '' ?>>A</option>
                        <option value="B" <?= ($patientInfo['blood_group'] === 'B') ? 'selected' : '' ?>>B</option>
                        <option value="AB" <?= ($patientInfo['blood_group'] === 'AB') ? 'selected' : '' ?>>AB</option>
                        <option value="O" <?= ($patientInfo['blood_group'] === 'O') ? 'selected' : '' ?>>O</option>
                    </select>
                    </label>
                </div>

                <div class="item">
                    <label><?= lang('form_mrp') ?>
                    <select name="mrp">
                        <option value=""><?= lang('form_choose_mrp') ?></option>
                        <?php foreach ($mrps as $mrp): ?>
                            <option value="<?= $mrp['id'] ?>" <?= ($patientInfo['mrp_id'] === $mrp['id']) ? 'selected' : '' ?>><?= $mrp['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    </label>
                </div>

                <div class="item">
                    <label><?= lang('form_status') ?>
                    <select name="status" required>
                        <option value="pending" <?= ($patientInfo['status'] === 'pending') ? 'selected' : '' ?>><?= lang('form_pending') ?></option>
                        <option value="ready" <?= ($patientInfo['status'] === 'ready') ? 'selected' : '' ?>><?= lang('form_ready') ?></option>
                    </select>
                    </label>
                </div>
            </div>
            <hr>
            <!----------------------------->
            <!-------- Lab Results -------->
            <!----------------------------->
            <h3><?= lang('form_labs_header') ?></h3>
            <div class="lab-row">
                <?php foreach ($patientInfo[0]['labs'] as $result): ?>
                    <div class="lab-item">
                        <label><?= htmlspecialchars($result['lab_name']) ?>
                        <?php $flag = (bool) !empty($result['result']); ?>
                        <?php if ($result['result_shape'] === 'numerical'): ?>
                            <input type="text" name="<?= $result['actual_id'] ?>" value="<?= ($flag) ? htmlspecialchars($result['result']) : '' ?>">
                        <?php elseif ($result['result_shape'] === 'status'): ?>
                            <select name="<?= $result['actual_id'] ?>">
                                <option value=""><?= lang('form_lab_status_none') ?></option>
                                <option value="done" <?= ($flag && $result['result'] === 'done') ? 'selected' : '' ?>><?= lang('form_lab_status_done') ?></option>
                                <option value="not done" <?= ($flag && $result['result'] === 'not done') ? 'selected' : '' ?>><?= lang('form_lab_status_not_done') ?></option>
                                <option value="pending" <?= ($flag && $result['result'] === 'pending') ? 'selected' : '' ?>><?= lang('form_lab_status_pending') ?></option>
                                <option value="na" <?= ($flag && $result['result'] === 'na') ? 'selected' : '' ?>><?= lang('form_lab_status_na') ?></option>
                            </select>
                        <?php endif; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div>
                <label><?= lang('form_note') ?>
                <textarea name="note"><?= $patientInfo['note'] ?></textarea>
                </label>
            </div>

            <!----------------------------------------->
            <!-------- Recipient/Donor Buttons -------->
            <!----------------------------------------->
            <div class="radio-box-container">
                <div class="radio-box rtl" id="recipient-box">
                    <label><?= lang('form_recipient') ?>
                    <input type="radio" name="patient_type" id="recipient-radio" value="recipient" required <?= ($patientInfo['type'] === 'recipient') ? 'checked' : '' ?>>
                    </label>
                </div>

                <div class="radio-box rtl" id="donor-box">
                    <label><?= lang('form_donor') ?>
                    <input type="radio" name="patient_type" id="donor-radio" value="donor" required <?= ($patientInfo['type'] === 'donor') ? 'checked' : '' ?>>
                    </label>
                </div>
            </div>

            <!---------------------------------->
            <!-------- Recipient Chosen -------->
            <!---------------------------------->
            <div id="recipient" class="nodis">
                <div class="row">
                    <div class="item">
                        <label><?= lang('form_dialysis') ?>
                        <input type="date" name="dialysis" class="recipient-hidden" value="<?= $patientInfo['dialysis'] ?>">
                        </label>
                    </div>

                    <div class="item">
                        <label><?= lang('form_waiting_since') ?>
                        <input type="date" name="entry_date" class="recipient-hidden" value="<?= $patientInfo['entry_date'] ?>">
                        </label>
                    </div>
                    
                    <div class="item">
                        <label><?= lang('form_urgency') ?>
                        <select name="urgency" required>
                            <option value="0" <?= ($patientInfo['urgency'] === '0') ? 'selected' : '' ?>><?= lang('form_not_urgent') ?></option>
                            <option value="1" <?= ($patientInfo['urgency'] === '1') ? 'selected' : '' ?>><?= lang('form_urgent') ?></option>
                        </select>
                        </label>
                    </div>
                </div>

                <div class="radio-box-container">
                    <div class="radio-box rtl" id="donor-box-true">
                        <label><?= lang('form_donor_true') ?>
                        <input type="radio" name="has_donor" id="donor-radio-true" class="recipient-hidden" value="true" <?= (!empty($hasDonor)) ? 'checked' : '' ?>>
                        </label>
                    </div>

                    <div class="radio-box rtl" id="donor-box-false">
                        <label><?= lang('form_donor_false') ?>
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
                if (!empty($myRecipient)) {
                    $unmatchedRecipients = array_merge($myRecipient, $unmatchedRecipients);
                }
                ?>

                <?php if (!empty($hasRecipient)): ?>
                    <div class="row">
                        <div class="item">
                            <label><?= lang('form_choose_recipient') ?>
                            <select name="recipient_pair" class="choose-recipient-mrn">
                                <option value=""><?= lang('form_recipient_false') ?></option>
                                <?php foreach ($unmatchedRecipients as $ur): ?>
                                    <option value="<?= $ur['mrn'] ?>" <?= ($ur['mrn'] === $hasRecipient['recipient_mrn']) ? 'selected' : '' ?>><?= htmlspecialchars($ur['name']) . (($ur['mrn'] === $hasRecipient['recipient_mrn']) ? lang('form_assigned_recipient') : '') ?></option>
                                <?php endforeach; ?>
                            </select>
                            </label>
                        </div>

                        <div class="item">
                            <label><?= lang('form_match_status') ?>
                            <select name="match_status_recipient" required>
                                <option value="pending" <?= ($hasRecipient['match_status'] === 'pending') ? 'selected' : '' ?>><?= lang('form_pending') ?></option>
                                <option value="confirmed" <?= ($hasRecipient['match_status'] === 'confirmed') ? 'selected' : '' ?>><?= lang('form_confirmed') ?></option>
                                <option value="closed" <?= ($hasRecipient['match_status'] === 'closed') ? 'selected' : '' ?>><?= lang('form_closed') ?></option>
                                <option value="completed" <?= ($hasRecipient['match_status'] === 'completed') ? 'selected' : '' ?>><?= lang('form_completed') ?></option>
                                <option value="paired_exchange" <?= ($hasRecipient['match_status'] === 'paired_exchange') ? 'selected' : '' ?>><?= lang('form_paired_exchange') ?></option>
                            </select>
                            </label>
                        </div>

                        <div class="item">
                            <label><?= lang('form_relationship') ?>
                            <input type="text" name="relationship_recipient" id="relationship-recipient" value="<?= $hasRecipient['relationship'] ?>">
                            </label>
                        </div>
                    
                        <div class="item">
                            <label><?= lang('form_committee_date') ?>
                            <input type="date" name="recipient_committee_date" value="<?= $hasRecipient['matched_on'] ?>">
                            </label>
                        </div>

                        <div class="item">
                            <label><?= lang('form_crossmatch_date') ?>
                            <input type="date" name="recipient_crossmatch_date" value="<?= $hasRecipient['surgery_on'] ?>">
                            </label>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <div class="item">
                            <label><?= lang('form_choose_recipient') ?>
                            <select name="recipient_pair" class="choose-recipient-mrn">
                                <option value=""><?= lang('form_recipient_false') ?></option>
                                <?php foreach ($unmatchedRecipients as $ur): ?>
                                    <option value="<?= $ur['mrn'] ?>"><?= $ur['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            </label>
                        </div>

                        <div class="item">
                            <label><?= lang('form_match_status') ?>
                            <select name="match_status_recipient" required>
                                <option value="pending"><?= lang('form_pending') ?></option>
                                <option value="confirmed"><?= lang('form_confirmed') ?></option>
                                <option value="closed"><?= lang('form_closed') ?></option>
                                <option value="completed"><?= lang('form_completed') ?></option>
                                <option value="paired_exchange"><?= lang('form_paired_exchange') ?></option>
                            </select>
                            </label>
                        </div>

                        <div class="item">
                            <label><?= lang('form_relationship') ?>
                            <input type="text" name="relationship_recipient">
                            </label>
                        </div>
                    
                        <div class="item">
                            <label><?= lang('form_committee_date') ?>
                            <input type="date" name="recipient_committee_date">
                            </label>
                        </div>

                        <div class="item">
                            <label><?= lang('form_crossmatch_date') ?>
                            <input type="date" name="recipient_crossmatch_date">
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!------------------------------------------------>
            <!-------- Recipient Chosen and has donor -------->
            <!------------------------------------------------>
            <div id="donor-true" class="nodis">
                <?php 
                if (!empty($myDonor)) {
                    $unmatchedDonors = array_merge($myDonor, $unmatchedDonors);
                }
                ?>
                <?php if (empty($unmatchedDonors)): ?>
                    <?= lang('form_donors_null') ?>
                <?php else: ?>
                    <?php if (!empty($hasDonor)): ?>
                        <div class="row">
                            <div class="item">
                                <label><?= lang('form_choose_donor') ?>
                                <select name="donor_pair" class="choose-donor-mrn" required>
                                    <?php foreach ($unmatchedDonors as $ud): ?>
                                        <option value="<?= $ud['mrn'] ?>" <?= ($ud['mrn'] === $hasDonor['donor_mrn']) ? 'selected' : '' ?>><?= htmlspecialchars($ud['name']) . (($ud['mrn'] === $hasDonor['donor_mrn']) ? lang('form_assigned_donor') : '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                </label>
                            </div>

                            <div class="item">
                                <label><?= lang('form_match_status') ?>
                                <select name="match_status_donor" required>
                                    <option value="pending" <?= ($hasDonor['match_status'] === 'pending') ? 'selected' : '' ?>><?= lang('form_pending') ?></option>
                                    <option value="confirmed" <?= ($hasDonor['match_status'] === 'confirmed') ? 'selected' : '' ?>><?= lang('form_confirmed') ?></option>
                                    <option value="closed" <?= ($hasDonor['match_status'] === 'closed') ? 'selected' : '' ?>><?= lang('form_closed') ?></option>
                                    <option value="completed" <?= ($hasDonor['match_status'] === 'completed') ? 'selected' : '' ?>><?= lang('form_completed') ?></option>
                                    <option value="paired_exchange" <?= ($hasDonor['match_status'] === 'paired_exchange') ? 'selected' : '' ?>><?= lang('form_paired_exchange') ?></option>
                                </select>
                            </div>

                            <div class="item">
                                <label><?= lang('form_relationship') ?>
                                <input type="text" name="relationship_donor" value="<?= $hasDonor['relationship'] ?>">
                                </label>
                            </div>
                        
                            <div class="item">
                                <label><?= lang('form_committee_date') ?>
                                <input type="date" name="donor_committee_date" value="<?= $hasDonor['matched_on'] ?>">
                                </label>
                            </div>

                            <div class="item">
                                <label><?= lang('form_crossmatch_date') ?>
                                <input type="date" name="donor_crossmatch_date" value="<?= $hasDonor['surgery_on'] ?>">
                                </label>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="item">
                                <label><?= lang('form_choose_donor') ?>
                                <select name="donor_pair" class="choose-donor-mrn" required>
                                    <?php foreach ($unmatchedDonors as $ud): ?>
                                        <option value="<?= $ud['mrn'] ?>"><?= htmlspecialchars($ud['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                </label>
                            </div>

                            <div class="item">
                                <label><?= lang('form_match_status') ?>
                                <select name="match_status_donor" required>
                                    <option value="pending"><?= lang('form_pending') ?></option>
                                    <option value="confirmed"><?= lang('form_confirmed') ?></option>
                                    <option value="closed"><?= lang('form_closed') ?></option>
                                    <option value="completed"><?= lang('form_completed') ?></option>
                                    <option value="paired_exchange"><?= lang('form_paired_exchange') ?></option>
                                </select>
                            </div>

                            <div class="item">
                                <label><?= lang('form_relationship') ?>
                                <input type="text" name="relationship_donor">
                                </label>
                            </div>
                        
                            <div class="item">
                                <label><?= lang('form_committee_date') ?>
                                <input type="date" name="donor_committee_date">
                                </label>
                            </div>

                            <div class="item">
                                <label><?= lang('form_crossmatch_date') ?>
                                <input type="date" name="donor_crossmatch_date">
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                </label>
            </div>

            <!-- <div id="donor-false" class="nodis">
                <label>false:
                <input type="text" name="" class="donor-false-hidden">
                </label>
            </div> -->
        </div>

        <button type="submit" class="form-submit"><?= lang('form_submit') ?></button>
    </form>
    </div>
</body>

<script src="<?= base_url('assets/js/radio_buttons_popup.js') ?>"></script>