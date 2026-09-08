<head>
    <?php $this->load->view('partials/favicon.php') ?>
    <title>إضافة مريض \ متبرع</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/form.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/programs.css') ?>">
</head>

<body>
    <?php $this->load->view('partials/navigation_links.php') ?>
    
    <div class="container">
        <div class="program-container">
            <div class="programs"><?= lang('filter_recipient') ?>
                <?php foreach($programs['recipients'] as $pr): if ($pr['program'] === 'R_PE') {continue;} ?>
                        <a class="program <?= ($program === $pr['program']) ? 'active' : '' ?>" href="<?= base_url('Patient?patient_type=' . $pr['patient_type'] . '&program=' . $pr['program']) ?>"><?= lang('filter_program_' . $pr['program']) ?></a>
                <?php endforeach; ?>
            </div>
            
            <div class="programs"><?= lang('filter_donor') ?>
                <?php foreach($programs['donors'] as $pr): ?>
                        <a class="program <?= ($program === $pr['program']) ? 'active' : '' ?>" href="<?= base_url('Patient?patient_type=' . $pr['patient_type'] . '&program=' . $pr['program']) ?>"><?= lang('filter_program_' . $pr['program']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php 
        $messages = [
            'confirmation' => $confirmation,
            'error' => $error,
        ];
        $this->load->view('partials/error_handler.php', $messages);
        ?>

        <form method="post" action="<?= base_url('Patient/add') ?>">
            <!------------------------------->
            <!-------- First Patient -------->
            <!------------------------------->
            <?php 
            $page['index'] = 'recipient';
            $this->load->view('partials/form_fields.php', $page); 
            ?>
                
            <!------------------------------------------------>
            <!---------------- Second Patient ---------------->
            <!------------------------------------------------>
            <?php 
            $page['index'] = 'donor';
            $this->load->view('partials/form_fields.php', $page); 
            ?>

            <?php 
            $page['index'] = 'pair';
            $this->load->view('partials/form_fields.php', $page); 
            ?>

            <input type="hidden" name="program" value="<?= $program ?>">
            <button type="submit" class="form-submit"><?= lang('form_submit') ?></button>
        </form>
    </div>
</body>

<!-- <script src="<?= base_url('assets/js/radio_buttons_popup.js') ?>"></script> -->
<script>
const  mrn = document.getElementById('mrn');
const  name = document.getElementById('name');
const  city = document.getElementById('city');
const  phoneNumber = document.getElementById('phone_number');
const  gender = document.getElementById('gender');
const  age = document.getElementById('age');

mrn.addEventListener('change', () => {
    fetch(`<?= base_url('Patient/getByMRN') ?>?mrn=${encodeURIComponent(mrn.value)}`)
        .then(r => r.json())
        .then(data => {
            name.value = data.name;
            city.value = data.city;
            phoneNumber.value = data.phone_number;
            gender.value = data.gender;
            age.value = data.age;
        });
});

const  mrn2 = document.getElementById('mrn_2');
const  name2 = document.getElementById('name_2');
const  city2 = document.getElementById('city_2');
const  phoneNumber2 = document.getElementById('phone_number_2');
const  gender2 = document.getElementById('gender_2');
const  age2 = document.getElementById('age_2');

mrn2.addEventListener('change', () => {
    fetch(`<?= base_url('Patient/getByMRN') ?>?mrn=${encodeURIComponent(mrn2.value)}`)
        .then(r => r.json())
        .then(data => {
            name2.value = data.name;
            city2.value = data.city;
            phoneNumber2.value = data.phone_number;
            gender2.value = data.gender;
            age2.value = data.age;
        });
});

const  mrn3 = document.getElementById('mrn_3');
const  name3 = document.getElementById('name_3');
const  city3 = document.getElementById('city_3');
const  phoneNumber3 = document.getElementById('phone_number_3');
const  gender3 = document.getElementById('gender_3');
const  age3 = document.getElementById('age_3');

mrn3.addEventListener('change', () => {
    fetch(`<?= base_url('Patient/getByMRN') ?>?mrn=${encodeURIComponent(mrn3.value)}`)
        .then(r => r.json())
        .then(data => {
            name3.value = data.name;
            city3.value = data.city;
            phoneNumber3.value = data.phone_number;
            gender3.value = data.gender;
            age3.value = data.age;
        });
});

</script>