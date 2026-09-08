<?php
/**
 * Scratch view carried over from the CodeIgniter 3 project.
 */
?>
<form method="post" action="<?= site_url('Test/test') ?>">
    <?= csrf_field() ?>
    <input type="text" name="name">
    <button type="submit">submit</button>
</form>

<pre><?= esc(print_r($test, true)) ?></pre>
