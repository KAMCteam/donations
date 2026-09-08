<head>
    <?= $this->include('partials/favicon') ?>
    <title>التوافقات</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/note.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/table.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/form.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/filters.css') ?>">
</head>

<body>
    <?= $this->include('partials/navigation_links') ?>

    <div class="container">
        <?php if (empty($pair)): ?>
            <p><?= esc(lang('Form.ctrl_error_missing_db')) ?></p>
        <?php else: ?>
        <table class="m15-x w97">
            <thead>
                <tr>
                    <th><?= esc(lang('Form.table_pair_field')) ?></th>
                    <th><?= esc(lang('Form.table_pair_patient_1')) ?></th>
                    <th><?= esc(lang('Form.table_pair_patient_2')) ?></th>
                </tr>
            </thead>

            <tbody>
                <?php
                $p1 = $pair[0]['pair'][0];
                $p2 = $pair[0]['pair'][1];

                $fields = [
                    lang('Form.table_mrn')             => 'mrn',
                    lang('Form.table_name')            => 'name',
                    lang('Form.table_age')             => 'age',
                    lang('Form.table_relation')        => 'relationship',
                    lang('Form.table_type')            => 'type',
                    lang('Form.table_blood_group')     => 'blood_group',
                    lang('Form.table_status')          => 'status',
                    lang('Form.table_mrp')             => 'mrp_name',
                    lang('Form.table_gender')          => 'gender',
                    lang('Form.table_phone_number')    => 'phone_number',
                    lang('Form.table_dialysis')        => 'dialysis',
                    lang('Form.table_entry_date')      => 'entry_date',
                    lang('Form.table_match_status')    => 'match_status',
                    lang('Form.table_lab_results')     => 'labs',
                    lang('Form.table_date_committee')  => 'matched_on',
                    lang('Form.table_date_crossmatch') => 'surgery_on',
                    lang('Form.table_note')            => 'note',
                ];

                // How a stored lab result reads on screen.
                $labResultLabels = [
                    'done'     => lang('Form.form_lab_status_done'),
                    'not done' => lang('Form.form_lab_status_not_done'),
                    'pending'  => lang('Form.form_lab_status_pending'),
                    'na'       => lang('Form.form_lab_status_na'),
                    ''         => '',
                ];
                ?>

                <?php foreach ($fields as $label => $key): ?>
                    <?php if (in_array($key, ['relationship', 'match_status', 'matched_on', 'surgery_on'], true)): ?>
                        <tr>
                            <th><?= esc($label) ?></th>
                            <td colspan="2"><?php if (! ($key === 'surgery_on' && ($pair[0][$key] ?? '') === '0000-00-00')): ?><?= esc($pair[0][$key] ?? '') ?><?php endif; ?></td>
                        </tr>
                    <?php elseif ($key === 'labs'): ?>
                        <tr>
                            <th><?= esc($label) ?></th>
                            <?php foreach ([$p1, $p2] as $patient): ?>
                            <td class="w500px">
                                <div class="lab-row">
                                    <?php foreach ($patient[0][$key] ?? [] as $lab): ?>
                                        <div class="lab-item">
                                            <label><?= esc($lab['lab_name']) ?>
                                            <input type="text" value="<?= esc($labResultLabels[$lab['result'] ?? ''] ?? $lab['result'], 'attr') ?>" readonly>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th><?= esc($label) ?></th>
                            <?php foreach ([$p1, $p2] as $patient): ?>
                            <td class="w500px <?= ($key === 'note') ? 'tal' : '' ?>">
                                <?php if (! (in_array($key, ['dialysis', 'entry_date'], true) && ($patient[$key] ?? '') === '0000-00-00')): ?>
                                <?= esc($patient[$key] ?? '') ?>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div id="overlay" onclick="closePopUp()"></div>
    <div id="pop-up">
        <div id="pop-up-content"></div>
    </div>
</body>

<script src="<?= base_url('assets/js/note.js') ?>"></script>
