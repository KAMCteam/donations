<head>
    <?php $this->load->view('partials/favicon.php') ?>
    <title>التوافقات</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/note.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/table.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/form.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/filters.css') ?>">
</head>

<body>
    <?php $this->load->view('partials/navigation_links.php') ?>

    <div class="container">
        <table class="m15-x w97">
            <thead>
                <tr>
                    <th><?= lang('table_pair_field') ?></th>
                    <th><?= lang('table_pair_patient_1') ?></th>
                    <th><?= lang('table_pair_patient_2') ?></th>
                </tr>
            </thead>

            <tbody>
                <?php 
                $p1 = $pair[0]['pair'][0];
                $p2 = $pair[0]['pair'][1];

                $fields = [
                    lang('table_mrn') => 'mrn',
                    lang('table_name') => 'name',
                    lang('table_age') => 'age',
                    lang('table_relation') => 'relationship',
                    lang('table_type') => 'type',
                    lang('table_blood_group') => 'blood_group',
                    lang('table_status') => 'status',
                    lang('table_mrp') => 'mrp_name',
                    lang('table_gender') => 'gender',
                    lang('table_phone_number') => 'phone_number',
                    lang('table_dialysis') => 'dialysis',
                    lang('table_entry_date') => 'entry_date',
                    lang('table_match_status') => 'match_status',
                    lang('table_lab_results') => 'labs',
                    lang('table_date_committee') => 'matched_on',
                    lang('table_date_crossmatch') => 'surgery_on',
                    lang('table_note') => 'note',
                ];

                ?>

                <?php foreach ($fields as $label => $key): ?>
                    <?php if ($key === 'relationship' || $key === 'match_status' || $key === 'matched_on' || $key === 'surgery_on'): ?>
                        <tr>
                            <th><?= $label ?></th>
                            <td colspan="2"><?php if ($key === 'surgery_on' && $pair[0][$key] === '0000-00-00'): ?><?php else: ?><?= htmlspecialchars($pair[0][$key]) ?><?php endif; ?></td>
                        </tr>
                    <?php elseif ($key === 'labs'):
                        $labels = [
                            'done' => lang('form_lab_status_done'),
                            'not done' => lang('form_lab_status_not_done'),
                            'pending' => lang('form_lab_status_pending'),
                            'na' => lang('form_lab_status_na'),
                            '' => '',
                        ]; 
                        ?>
                        <th><?= $label ?></th>
                        <td class="w500px">
                            
                            <div class="lab-row">
                                <?php foreach ($p1[0][$key] as $lab): ?>
                                    <div class="lab-item">
                                        <label><?= htmlspecialchars($lab['lab_name']) ?>
                                        <input type="text" value="<?= $labels[$lab['result']] ?? $lab['result'] ?>" readonly>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                        </td>
                        <td class="w500px">
                            
                            <div class="lab-row">
                                <?php foreach ($p2[0][$key] as $lab): ?>
                                    <div class="lab-item">
                                        <label><?= htmlspecialchars($lab['lab_name']) ?>
                                        <input type="text" value="<?= $labels[$lab['result']] ?? $lab['result'] ?>" readonly>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                        </td>
                    <?php else: ?>
                        <tr>
                            <th><?= $label ?></th>
                            <td class="w500px <?= ($key === 'note') ? 'tal' : '' ?>">
                                <?php if (($key === 'dialysis' || $key === 'entry_date') && $p1[$key] === '0000-00-00'): ?><?php else: ?>
                                <?= htmlspecialchars($p1[$key]) ?>
                                <?php endif; ?>
                            </td>

                            <td class="w500px <?= ($key === 'note') ? 'tal' : '' ?>">
                                <?php if (($key === 'dialysis' || $key === 'entry_date') && $p2[$key] === '0000-00-00'): ?><?php else: ?>
                                <?= htmlspecialchars($p2[$key]) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>            
            </tbody>
        </table>

    </div>

    <div id="overlay" onclick="closePopUp()"></div>
    <div id="pop-up">
        <div id="pop-up-content"></div>
    </div>
</body>

<script src="<?= base_url('assets/js/note.js') ?>"></script>