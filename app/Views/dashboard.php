<head>
    <?= $this->include('partials/favicon') ?>
    <title>لوحة التحكم</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/chart.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/form.css') ?>">
</head>

<body>
    <?= $this->include('partials/navigation_links') ?>
    <div class="container">
        <form method="get" action="<?= site_url('Dashboard/drawGraphs') ?>">
            <div class="chart-filters">
                <?php foreach ($graphs as $graph): ?>
                    <div class="chart-filter" style="background-color: <?= esc($graph['color'], 'attr') ?>;">
                        <label><?= esc($graph['label']) ?>
                        <input type="checkbox" name="get_labels[<?= esc($graph['name'], 'attr') ?>]" value="<?= esc($graph['name'], 'attr') ?>"><!-- TODO: remember the previous selection -->
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit"><?= esc(lang('Form.form_submit')) ?></button>
        </form>

        <div class="chart-container">
            <canvas id="chart"></canvas>
        </div>
    </div>
</body>

<script src="<?= base_url('assets/js/chart.umd.js') ?>"></script>
<script>
    const ctx = document.getElementById('chart');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= $labels ?>,
            datasets: [{
                data: <?= $datasets ?>,
                backgroundColor: <?= $backgrounds ?>,
                barPercentage: 0.6,
                categoryPercentage: 0.8
            }]
        },
        options: {
            plugins: {
                legend: {
                    display: false
                },
            }
        }
    })
</script>
