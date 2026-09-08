<head>
    <?= $this->include('partials/favicon') ?>
    <title>التوافقات</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/note.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/table.css') ?>">
</head>

<body>
    <?= $this->include('partials/navigation_links') ?>

    <div class="container">
        <?= view('partials/filters', [
            'bg'           => 'on',
            'ms'           => 'on',
            'blood_group'  => $blood_group,
            'match_status' => $match_status,
        ]) ?>

        <table class="m15-x w97">
            <thead>
                <tr>
                    <th><?= esc(lang('Form.table_pair_no')) ?></th>
                    <th><?= esc(lang('Form.table_mrn')) ?></th>
                    <th><?= esc(lang('Form.table_name')) ?></th>
                    <th><?= esc(lang('Form.table_age')) ?></th>
                    <th><?= esc(lang('Form.table_type')) ?></th>
                    <th><?= esc(lang('Form.table_relation')) ?></th>
                    <th><?= esc(lang('Form.table_blood_group')) ?></th>
                    <th><?= esc(lang('Form.table_status')) ?></th>
                    <th><?= esc(lang('Form.table_mrp')) ?></th>
                    <th><?= esc(lang('Form.table_gender')) ?></th>
                    <th><?= esc(lang('Form.table_phone_number')) ?></th>
                    <th><?= esc(lang('Form.table_dialysis')) ?></th>
                    <th><?= esc(lang('Form.table_entry_date')) ?></th>
                    <th><?= esc(lang('Form.table_match_status')) ?></th>
                    <th><?= esc(lang('Form.table_date_committee')) ?></th>
                    <th><?= esc(lang('Form.table_date_crossmatch')) ?></th>
                    <th><?= esc(lang('Form.table_note')) ?></th>
                </tr>
            </thead>
            <?php $count = 1; ?>
            <?php $flag = true; ?>
            <?php foreach ($allPairs ?? [] as $pair): ?>
            <tbody class="pair <?= ($count % 2 === 0) ? 'grey' : '' ?>">
                <?php foreach ($pair['pair'] as $patient): ?>
                <tr class="pair">
                    <?php if ($flag): ?>
                        <td rowspan="2">
                            <a class="pair-link <?= ($count > 9) ? 'larger' : '' ?>" href="<?= site_url('Pairs/' . ($patient['mrn'] ?? '')) ?>"><?= esc($count) ?></a>
                        </td>
                    <?php endif; ?>
                    <td><?= esc($patient['mrn'] ?? '') ?></td>
                    <td><?= esc($patient['name'] ?? '') ?></td>
                    <td><?= esc($patient['age'] ?? '') ?></td>
                    <td><?= esc($patient['type'] ?? '') ?></td>
                    <?php if ($flag): ?><td rowspan="2"><?= esc($pair['relationship'] ?? '') ?></td><?php endif; ?>
                    <td><?= esc($patient['blood_group'] ?? '') ?></td>
                    <td><?= esc($patient['status'] ?? '') ?></td>
                    <td><?= esc($patient['mrp_name'] ?? '') ?></td>
                    <td><?= esc($patient['gender'] ?? '') ?></td>
                    <td><?= esc($patient['phone_number'] ?? '') ?></td>
                    <td><?= esc(($patient['dialysis'] ?? '0000-00-00') === '0000-00-00' ? lang('Form.table_not_applicable') : $patient['dialysis']) ?></td>
                    <td><?= esc(($patient['entry_date'] ?? '0000-00-00') === '0000-00-00' ? lang('Form.table_not_applicable') : $patient['entry_date']) ?></td>
                    <?php if ($flag): ?><td rowspan="2"><?= esc(lang('Form.table_match_status_' . $pair['match_status'])) ?></td><?php endif; ?>
                    <?php if ($flag): ?><td rowspan="2"><?= esc(substr((string) ($pair['matched_on'] ?? ''), 0, 10)) ?></td><?php endif; ?>
                    <?php if ($flag): ?><td rowspan="2"><?= esc(substr((string) (($pair['surgery_on'] ?? '') === '0000-00-00' ? '' : ($pair['surgery_on'] ?? '')), 0, 10)) ?></td><?php endif; ?>
                    <td class="note green" data-note="<?= esc($patient['note'] ?? '', 'attr') ?>"><?= esc(lang('Form.table_show_note')) ?></td>
                </tr>
                <?php $flag = ! $flag; ?>
                <?php endforeach; ?>
            </tbody>
            <?php $count++; ?>
            <?php endforeach; ?>
        </table>
    </div>

    <div id="overlay" onclick="closePopUp()"></div>
    <div id="pop-up">
        <div id="pop-up-content"></div>
    </div>
</body>

<script src="<?= base_url('assets/js/note.js') ?>"></script>
