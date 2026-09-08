<head>
    <?= $this->include('partials/favicon') ?>
    <title>تحديث معلومات المريض</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/note.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/table.css') ?>">
</head>

<body>
    <?= $this->include('partials/navigation_links') ?>
    <div class="container">
        <?= view('partials/filters', [
            'bg'          => 'on', // remove a line to disable that filter
            'ty'          => 'on',
            'st'          => 'on',
            'mr'          => 'on',
            'blood_group' => $blood_group,
            'type'        => $type,
            'status'      => $status,
            'mrn'         => $mrn,
        ]) ?>

        <table>
            <thead>
                <tr>
                    <th><?= esc(lang('Form.table_no')) ?></th>
                    <th><?= esc(lang('Form.table_mrn')) ?></th>
                    <th><?= esc(lang('Form.table_name')) ?></th>
                    <th><?= esc(lang('Form.table_age')) ?></th>
                    <th><?= esc(lang('Form.table_type')) ?></th>
                    <th><?= esc(lang('Form.table_blood_group')) ?></th>
                    <th><?= esc(lang('Form.table_status')) ?></th>
                    <th><?= esc(lang('Form.table_mrp')) ?></th>
                    <th><?= esc(lang('Form.table_gender')) ?></th>
                    <th><?= esc(lang('Form.table_phone_number')) ?></th>
                    <th><?= esc(lang('Form.table_dialysis')) ?></th>
                    <th><?= esc(lang('Form.table_entry_date')) ?></th>
                    <th><?= esc(lang('Form.table_note')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $count = 1; ?>
                <?php foreach ($allPatients ?? [] as $patient): ?>
                <tr class="clickable" data-mrn="<?= esc($patient['mrn'], 'attr') ?>">
                    <td><?= esc($count) ?></td>
                    <td><?= esc($patient['mrn'] ?? '') ?></td>
                    <td><?= esc($patient['name'] ?? '') ?></td>
                    <td><?= esc($patient['age'] ?? '') ?></td>
                    <td><?= esc($patient['type'] ?? '') ?></td>
                    <td><?= esc($patient['blood_group'] ?? '') ?></td>
                    <td><?= esc($patient['status'] ?? '') ?></td>
                    <td><?= esc($patient['mrp_name'] ?? '') ?></td>
                    <td><?= esc($patient['gender'] ?? '') ?></td>
                    <td><?= esc($patient['phone_number'] ?? '') ?></td>
                    <td><?= esc(($patient['dialysis'] ?? '0000-00-00') === '0000-00-00' ? lang('Form.table_not_applicable') : $patient['dialysis']) ?></td>
                    <td><?= esc(($patient['entry_date'] ?? '0000-00-00') === '0000-00-00' ? lang('Form.table_not_applicable') : $patient['entry_date']) ?></td>
                    <td class="note green" data-note="<?= esc($patient['note'] ?? '', 'attr') ?>"><?= esc(lang('Form.table_show_note')) ?></td>
                </tr>
                <?php $count++; ?>
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
<script>
const clickables = document.querySelectorAll('.clickable');

clickables.forEach(clickable => {
    clickable.addEventListener('click', () => {
        const mrn = clickable.dataset.mrn;
        window.location.href = '<?= site_url('UpdatePatient') ?>/' + mrn;
    });
});
</script>
