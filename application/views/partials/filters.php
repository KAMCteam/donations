<head>
    <link rel="stylesheet" href="<?= base_url('assets/css/filters.css') ?>">
</head>

<form method="get" aciton="" id="filters-form">
    <div class="filters-container">

        <!----- Match Status ----->
        <?php if (!empty($ms)): ?>
            <div class="filters">
                <button type="submit" name="set_match_status" value="pending" class="filter-button <?= ($match_status === 'pending') ? 'active' : '' ?>"><?= lang('filter_status_pending') ?></button>
                <button type="submit" name="set_match_status" value="confirmed" class="filter-button <?= ($match_status === 'confirmed') ? 'active' : '' ?>"><?= lang('filter_status_confirmed') ?></button>
                <button type="submit" name="set_match_status" value="closed" class="filter-button <?= ($match_status === 'closed') ? 'active' : '' ?>"><?= lang('filter_status_closed') ?></button>
                <button type="submit" name="set_match_status" value="completed" class="filter-button <?= ($match_status === 'completed') ? 'active' : '' ?>"><?= lang('filter_status_completed') ?></button>
                <button type="submit" name="set_match_status" value="paired_exchange" class="filter-button <?= ($match_status === 'paired_exchange') ? 'active' : '' ?>"><?= lang('filter_status_paired_exchange') ?></button>
            </div>

            <input type="hidden" name="match_status" value="<?= $match_status ?>">
        <?php endif; ?>

        <!----- Recipient/Donor ----->
        <?php if (!empty($ty)): ?>
            <div class="filters">
                <button type="submit" name="set_type" value="recipient" class="filter-button <?= ($type === 'recipient') ? 'active' : '' ?>"><?= lang('filter_recipient') ?></button>
                <button type="submit" name="set_type" value="donor" class="filter-button <?= ($type === 'donor') ? 'active' : '' ?>"><?= lang('filter_donor') ?></button>
            </div>

            <input type="hidden" name="type" value="<?= $type ?>">
        <?php endif; ?>

        <!----- Pending/Ready ----->
        <?php if (!empty($st)): ?>
            <div class="filters">
                <button type="submit" name="set_status" value="pending" class="filter-button <?= ($status === 'pending') ? 'active' : '' ?>"><?= lang('filter_pending') ?></button>
                <button type="submit" name="set_status" value="ready" class="filter-button <?= ($status === 'ready') ? 'active' : '' ?>"><?= lang('filter_ready') ?></button>
            </div>

            <input type="hidden" name="status" value="<?= $status ?>">
        <?php endif; ?>

        <!----- Blood Group ----->
        <?php if (!empty($bg)): ?>
            <div class="filters">
                <button type="submit" name="set_blood_group" value="A" class="filter-button <?= ($blood_group === 'A') ? 'active' : '' ?>"><?= lang('filter_blood_group_A') ?></button>
                <button type="submit" name="set_blood_group" value="B" class="filter-button <?= ($blood_group === 'B') ? 'active' : '' ?>"><?= lang('filter_blood_group_B') ?></button>
                <button type="submit" name="set_blood_group" value="AB" class="filter-button <?= ($blood_group === 'AB') ? 'active' : '' ?>"><?= lang('filter_blood_group_AB') ?></button>
                <button type="submit" name="set_blood_group" value="O" class="filter-button <?= ($blood_group === 'O') ? 'active' : '' ?>"><?= lang('filter_blood_group_O') ?></button>
            </div>

            <input type="hidden" name="blood_group" value="<?= $blood_group ?>">
        <?php endif; ?>

        <!----- Search By MRN ----->
        <?php if (!empty($mr)): ?>
            <div class="mrn-filter">
                <label><?= lang('filter_mrn') ?>
                <input type="text" name="mrn" id="form-mrn" value="<?= htmlspecialchars($mrn) ?>">
                </label>
            </div>
        <?php endif; ?>
    </div>
</form>

<script>
const form = document.getElementById('filters-form');
const formMrn = document.getElementById('form-mrn');

formMrn.addEventListener('change', () => {
    form.submit();
});
</script>