<head>
    <?php $this->load->view('partials/favicon.php') ?>
    <title>التوافقات</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/note.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/table.css') ?>">
</head>

<body>
    <?php $this->load->view('partials/navigation_links.php') ?>

    <div class="container">
        <?php 
        $filters = [
            'bg' => 'on',
            'ms' => 'on',
            'blood_group' => $blood_group,
            'match_status' => $match_status
        ]; 
        $this->load->view('partials/filters.php', $filters); 
        ?>

        <table class="m15-x w97">
            <thead>
                <tr>
                    <th><?= lang('table_pair_no') ?></th>
                    <th><?= lang('table_mrn') ?></th>
                    <th><?= lang('table_name') ?></th>
                    <th><?= lang('table_age') ?></th>
                    <th><?= lang('table_type') ?></th>
                    <th><?= lang('table_relation') ?></th>
                    <th><?= lang('table_blood_group') ?></th>
                    <th><?= lang('table_status') ?></th>
                    <th><?= lang('table_mrp') ?></th>
                    <th><?= lang('table_gender') ?></th>
                    <th><?= lang('table_phone_number') ?></th>
                    <th><?= lang('table_dialysis') ?></th>
                    <th><?= lang('table_entry_date') ?></th>
                    <th><?= lang('table_match_status') ?></th>
                    <th><?= lang('table_date_committee') ?></th>
                    <th><?= lang('table_date_crossmatch') ?></th>
                    <th><?= lang('table_note') ?></th>
                </tr>
            </thead>
            <?php if (!empty($allPairs)): ?>
            <?php $count = 1;
            $flag = true;
            foreach ($allPairs as $pair): ?>
            <tbody class="pair <?= ($count % 2 == 0) ? 'grey' : '' ?>">
                <?php foreach ($pair['pair'] as $patient): ?>
                <tr class="pair">
                    <?php if ($flag): ?>
                        <td rowspan="2">
                            <a class="pair-link <?= ($count > 9) ? 'larger' : '' ?>" href="<?= base_url('Pairs/' . $patient['mrn']) ?>"><?= htmlspecialchars($count) ?></a>
                        </td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($patient['mrn']) ?></td>
                    <td><?= htmlspecialchars($patient['name']) ?></td>
                    <td><?= htmlspecialchars($patient['age']) ?></td>
                    <td><?= htmlspecialchars($patient['type']) ?></td>
                    <?php if ($flag): ?><td rowspan="2"><?= htmlspecialchars($pair['relationship']) ?></td><?php endif; ?>
                    <td><?= htmlspecialchars($patient['blood_group']) ?></td>
                    <td><?= htmlspecialchars($patient['status']) ?></td>
                    <td><?= htmlspecialchars($patient['mrp_name']) ?></td>
                    <td><?= htmlspecialchars($patient['gender']) ?></td>
                    <td><?= htmlspecialchars($patient['phone_number']) ?></td>
                    <td><?= htmlspecialchars(($patient['dialysis'] === '0000-00-00') ? lang('table_not_applicable') : $patient['dialysis']) ?></td>
                    <td><?= htmlspecialchars(($patient['entry_date'] === '0000-00-00') ? lang('table_not_applicable') : $patient['entry_date']) ?></td>
                    <?php if ($flag): ?><td rowspan="2"><?= lang('table_match_status_' . $pair['match_status']) ?></td><?php endif; ?>
                    <?php if ($flag): ?><td rowspan="2"><?= htmlspecialchars(substr($pair['matched_on'], 0, 10)) ?></td><?php endif; ?>
                    <?php if ($flag): ?><td rowspan="2"><?= htmlspecialchars(substr(($pair['surgery_on'] === '0000-00-00') ? '' : $pair['surgery_on'], 0, 10)) ?></td><?php endif; ?>
                    <td class="note green" data-note="<?= htmlspecialchars($patient['note']) ?>"><?= lang('table_show_note') ?></td>
                </tr>
                <?php $flag = !$flag; endforeach; ?>
            </tbody>
            <?php 
            $count++;
            endforeach; ?>
            <?php endif; ?>
        </table>
    </div>

    <div id="overlay" onclick="closePopUp()"></div>
    <div id="pop-up">
        <div id="pop-up-content"></div>
    </div>
</body>

<script src="<?= base_url('assets/js/note.js') ?>"></script>