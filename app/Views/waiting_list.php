<head>
    <?= $this->include('partials/favicon') ?>
    <title>لائحة الانتظار</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/table.css') ?>">
</head>

<body>
    <?= $this->include('partials/navigation_links') ?>
    <div class="container">
        <?= view('partials/filters', [
            'bg'          => 'on',
            'blood_group' => $blood_group,
        ]) ?>

        <table>
            <thead>
                <tr>
                    <th><?= esc(lang('Form.table_no')) ?></th>
                    <th><?= esc(lang('Form.table_name')) ?></th>
                    <th><?= esc(lang('Form.table_mrn')) ?></th>
                    <th><?= esc(lang('Form.table_age')) ?></th>
                    <th><?= esc(lang('Form.table_gender')) ?></th>
                    <th><?= esc(lang('Form.table_blood_group')) ?></th>
                    <th><?= esc(lang('Form.table_score')) ?></th>
                    <th><?= esc(lang('Form.table_urgency')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $count = 1; ?>
                <?php foreach ($unmatchedRecipients ?? [] as $ur): ?>
                <tr class="clickable <?= ((string) $ur['urgency'] === '1') ? 'red' : '' ?>" data-mrn="<?= esc($ur['mrn'], 'attr') ?>">
                    <td><?= esc($count) ?></td>
                    <td><?= esc($ur['name'] ?? '') ?></td>
                    <td><?= esc($ur['mrn'] ?? '') ?></td>
                    <td><?= esc($ur['age'] ?? '') ?></td>
                    <td><?= esc($ur['gender'] ?? '') ?></td>
                    <td><?= esc($ur['blood_group'] ?? '') ?></td>
                    <td><?= esc($ur['score'] ?? '') ?></td>
                    <td><?= esc(lang('Form.table_urgency_' . $ur['urgency'])) ?></td>
                </tr>
                <?php $count++; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

<script>
const clickables = document.querySelectorAll('.clickable');

clickables.forEach(clickable => {
    clickable.addEventListener('click', () => {
        const mrn = clickable.dataset.mrn;
        window.location.href = '<?= site_url('UpdatePatient') ?>/' + mrn;
    });
});
</script>
