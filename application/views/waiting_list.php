<head>
    <?php $this->load->view('partials/favicon.php') ?>
    <title>لائحة الانتظار</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/table.css') ?>">
</head>

<body>
    <?php $this->load->view('partials/navigation_links.php') ?>
    <div class="container">
        <?php 
        $filters = [
            'bg' => 'on',
            'blood_group' => $blood_group
        ]; 
        $this->load->view('partials/filters.php', $filters); 
        ?>

        <table>
            <thead>
                <tr>
                    <th><?= lang('table_no') ?></th>
                    <th><?= lang('table_name') ?></th>
                    <th><?= lang('table_mrn') ?></th>
                    <th><?= lang('table_age') ?></th>
                    <th><?= lang('table_gender') ?></th>
                    <th><?= lang('table_blood_group') ?></th>
                    <th><?= lang('table_score') ?></th>
                    <th><?= lang('table_urgency') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($unmatchedRecipients)): ?>
                <?php $count = 1;
                foreach ($unmatchedRecipients as $ur): ?>
                <tr class="clickable <?= $ur['urgency'] === '1' ? 'red' : '' ?>" data-mrn="<?= $ur['mrn'] ?>">
                    <td><?= htmlspecialchars($count) ?></td>
                    <td><?= htmlspecialchars($ur['name']) ?></td>
                    <td><?= htmlspecialchars($ur['mrn']) ?></td>
                    <td><?= htmlspecialchars($ur['age']) ?></td>
                    <td><?= htmlspecialchars($ur['gender']) ?></td>
                    <td><?= htmlspecialchars($ur['blood_group']) ?></td>
                    <td><?= htmlspecialchars($ur['score']) ?></td>
                    <td><?= htmlspecialchars(lang('table_urgency_' . $ur['urgency'])) ?></td>
                </tr>
                <?php 
                $count++;
                endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>

<script>
const clickables = document.querySelectorAll('.clickable');

clickables.forEach(clickable => {
    clickable.addEventListener('click', () => {
        const mrn = clickable.dataset.mrn;
        window.location.href = '<?= base_url('UpdatePatient') ?>/' + mrn;
    });
});
</script>