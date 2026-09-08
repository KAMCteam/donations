<head>
    <?php $this->load->view('partials/favicon.php') ?>
    <title>لوحة التحكم</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/chart.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/form.css') ?>">
</head>

<body>
    <?php $this->load->view('partials/navigation_links.php') ?>
    <div class="container">
        <form method="get" action="<?= base_url('Dashboard/drawGraphs') ?>">
            <div class="chart-filters">
                <?php foreach ($graphs as $graph): ?>
                    <div class="chart-filter" style="background-color: <?= $graph['color'] ?>;">
                        <label><?= htmlspecialchars($graph['label']) ?>
                        <input type="checkbox" name="get_labels[<?= $graph['name'] ?>]" value="<?= $graph['name'] ?>" <?= (false) ? 'checked' : ''?>><!-- TODO: do checked -->
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit"><?= lang('form_submit') ?></button>
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
            labels: <?php echo $labels ?>,
            datasets: [{
                data: <?php echo $datasets ?>,
                backgroundColor: <?php echo $backgrounds ?>,
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