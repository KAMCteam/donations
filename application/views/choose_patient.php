<head>
    <?php $this->load->view('partials/favicon.php') ?>
    <title>تحديث معلومات المريض</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/note.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/table.css') ?>">
</head>

<body>
    <?php $this->load->view('partials/navigation_links.php') ?>
    <div class="container">
        <?php 
        $filters = [
            'bg' => 'on', // remove this line to disable filter
            'ty' => 'on',
            'st' => 'on',
            'mr' => 'on',
            'blood_group'   => $blood_group,
            'type'          => $type,
            'status'        => $status,
            'mrn'           => $mrn,
        ]; 
        $this->load->view('partials/filters.php', $filters); 
        ?>

        <table>
            <thead>
                <tr>
                    <th><?= lang('table_no') ?></th>
                    <th><?= lang('table_mrn') ?></th>
                    <th><?= lang('table_name') ?></th>
                    <th><?= lang('table_age') ?></th>
                    <th><?= lang('table_type') ?></th>
                    <th><?= lang('table_blood_group') ?></th>
                    <th><?= lang('table_status') ?></th>
                    <th><?= lang('table_mrp') ?></th>
                    <th><?= lang('table_gender') ?></th>
                    <th><?= lang('table_phone_number') ?></th>
                    <th><?= lang('table_dialysis') ?></th>
                    <th><?= lang('table_entry_date') ?></th>
                    <th><?= lang('table_note') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($allPatients)): ?>
                <?php $count = 1;
                foreach ($allPatients as $patient): ?>
                <tr class="clickable" data-mrn="<?= $patient['mrn'] ?>">
                    <td><?= htmlspecialchars($count) ?></td>
                    <td><?= htmlspecialchars($patient['mrn']) ?></td>
                    <td><?= htmlspecialchars($patient['name']) ?></td>
                    <td><?= htmlspecialchars($patient['age']) ?></td>
                    <td><?= htmlspecialchars($patient['type']) ?></td>
                    <td><?= htmlspecialchars($patient['blood_group']) ?></td>
                    <td><?= htmlspecialchars($patient['status']) ?></td>
                    <td><?= htmlspecialchars($patient['mrp_name']) ?></td>
                    <td><?= htmlspecialchars($patient['gender']) ?></td>
                    <td><?= htmlspecialchars($patient['phone_number']) ?></td>
                    <td><?= htmlspecialchars(($patient['dialysis'] === '0000-00-00') ? lang('table_not_applicable') : $patient['dialysis']) ?></td>
                    <td><?= htmlspecialchars(($patient['entry_date'] === '0000-00-00') ? lang('table_not_applicable') : $patient['entry_date']) ?></td>
                    <td class="note green" data-note="<?= htmlspecialchars($patient['note']) ?>"><?= lang('table_show_note') ?></td>
                </tr>
                <?php 
                $count++;
                endforeach; ?>
                <?php endif; ?>
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
        window.location.href = '<?= base_url('UpdatePatient') ?>/' + mrn;
    });
});
</script>