<head>
    <?php $this->load->view('partials/favicon.php') ?>
    <title>إضافة دكتور</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/form.css') ?>">
</head>

<body>
    <?php $this->load->view('partials/navigation_links.php') ?>
    <div class="container">
        <?php 
        $messages = [
            'confirmation' => $confirmation,
            'error' => $error,
        ];
        $this->load->view('partials/error_handler.php', $messages);
        ?>
        
        <form method="post" action="<?= base_url('MRP/addLab') ?>">
            <div id="submit-form-div">            
                <h3 class="form-name"><?= lang('form_new_lab') ?></h3>
                
                <div class="row">
                    <div class="item">
                        <label><?= lang('form_lab_name') ?>
                        <input type="text" name="lab_name" required>
                        </label>
                    </div>

                    <div class="item">
                        <label><?= lang('form_lab_shape') ?>
                        <select type="text" name="lab_shape" required>
                            <option value="status"><?= lang('form_mrp_status') ?></option>
                            <option value="numerical"><?= lang('form_numerical') ?></option>
                        </select>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="form-submit"><?= lang('form_submit') ?></button>
        </form>
        <hr>
        <form method="post" action="<?= base_url('MRP/addMRP') ?>">
            <div id="submit-form-div">            
                <h3 class="form-name"><?= lang('form_new_mrp') ?></h3>
                
                <div class="row">
                    <div class="item">
                        <label><?= lang('form_mrp_id') ?>
                        <input type="text" name="id" required>
                        </label>
                    </div>

                    <div class="item">
                        <label><?= lang('form_name') ?>
                        <input type="text" name="name" required>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="form-submit"><?= lang('form_submit') ?></button>
        </form>
    </div>
</body>