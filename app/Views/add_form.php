<head>
    <?= $this->include('partials/favicon') ?>
    <title>إضافة مريض \ متبرع</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/form.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/programs.css') ?>">
</head>

<body>
    <?= $this->include('partials/navigation_links') ?>

    <div class="container">
        <div class="program-container">
            <div class="programs"><?= esc(lang('Form.filter_recipient')) ?>
                <?php foreach ($programs['recipients'] as $pr): ?>
                    <?php if ($pr['program'] === 'R_PE') {
                        continue;
                    } ?>
                    <a class="program <?= ($program === $pr['program']) ? 'active' : '' ?>" href="<?= site_url('Patient') . '?patient_type=' . urlencode($pr['patient_type']) . '&program=' . urlencode($pr['program']) ?>"><?= esc(lang('Form.filter_program_' . $pr['program'])) ?></a>
                <?php endforeach; ?>
            </div>

            <div class="programs"><?= esc(lang('Form.filter_donor')) ?>
                <?php foreach ($programs['donors'] as $pr): ?>
                    <a class="program <?= ($program === $pr['program']) ? 'active' : '' ?>" href="<?= site_url('Patient') . '?patient_type=' . urlencode($pr['patient_type']) . '&program=' . urlencode($pr['program']) ?>"><?= esc(lang('Form.filter_program_' . $pr['program'])) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?= view('partials/error_handler', [
            'confirmation' => $confirmation,
            'error'        => $error,
        ]) ?>

        <form method="post" action="<?= site_url('Patient/add') ?>">
            <?= csrf_field() ?>

            <!------------------------------->
            <!-------- First Patient -------->
            <!------------------------------->
            <?= view('partials/form_fields', ['index' => 'recipient']) ?>

            <!------------------------------------------------>
            <!---------------- Second Patient ---------------->
            <!------------------------------------------------>
            <?= view('partials/form_fields', ['index' => 'donor']) ?>

            <?= view('partials/form_fields', ['index' => 'pair']) ?>

            <input type="hidden" name="program" value="<?= esc($program ?? '', 'attr') ?>">
            <button type="submit" class="form-submit"><?= esc(lang('Form.form_submit')) ?></button>
        </form>
    </div>
</body>

<script>
// Each group is [mrn, name, city, phone, gender, age]; a program only renders
// some of them, so every group is wired up only when its MRN field is present.
const lookupUrl = '<?= site_url('Patient/getByMRN') ?>';

['', '_2', '_3'].forEach(suffix => {
    const mrn = document.getElementById('mrn' + suffix);

    if (!mrn) {
        return;
    }

    mrn.addEventListener('change', () => {
        fetch(`${lookupUrl}?mrn=${encodeURIComponent(mrn.value)}`)
            .then(r => r.json())
            .then(data => {
                if (!data || data.error) {
                    return;
                }

                const fields = {
                    name: 'name',
                    city: 'city',
                    phone_number: 'phone_number',
                    gender: 'gender',
                    age: 'age',
                };

                Object.entries(fields).forEach(([key, id]) => {
                    const el = document.getElementById(id + suffix);
                    if (el) {
                        el.value = data[key] ?? '';
                    }
                });
            });
    });
});
</script>
