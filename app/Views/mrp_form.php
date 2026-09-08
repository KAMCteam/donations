<head>
    <?= $this->include('partials/favicon') ?>
    <title>إضافة دكتور</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/form.css') ?>">
</head>

<body>
    <?= $this->include('partials/navigation_links') ?>
    <div class="container">
        <?= view('partials/error_handler', [
            'confirmation' => $confirmation,
            'error'        => $error,
        ]) ?>

        <form method="post" action="<?= site_url('MRP/addLab') ?>">
            <?= csrf_field() ?>
            <div id="submit-form-div">
                <h3 class="form-name"><?= esc(lang('Form.form_new_lab')) ?></h3>

                <div class="row">
                    <div class="item">
                        <label><?= esc(lang('Form.form_lab_name')) ?>
                        <input type="text" name="lab_name" required>
                        </label>
                    </div>

                    <div class="item">
                        <label><?= esc(lang('Form.form_lab_shape')) ?>
                        <select name="lab_shape" required>
                            <option value="status"><?= esc(lang('Form.form_mrp_status')) ?></option>
                            <option value="numerical"><?= esc(lang('Form.form_numerical')) ?></option>
                        </select>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="form-submit"><?= esc(lang('Form.form_submit')) ?></button>
        </form>
        <hr>
        <form method="post" action="<?= site_url('MRP/addMRP') ?>">
            <?= csrf_field() ?>
            <div id="submit-form-div">
                <h3 class="form-name"><?= esc(lang('Form.form_new_mrp')) ?></h3>

                <div class="row">
                    <div class="item">
                        <label><?= esc(lang('Form.form_mrp_id')) ?>
                        <input type="text" name="id" required>
                        </label>
                    </div>

                    <div class="item">
                        <label><?= esc(lang('Form.form_name')) ?>
                        <input type="text" name="name" required>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="form-submit"><?= esc(lang('Form.form_submit')) ?></button>
        </form>
    </div>
</body>
