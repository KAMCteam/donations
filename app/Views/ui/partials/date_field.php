<?php

/**
 * A date the screens write as DD/MM/YYYY.
 *
 * The text box is the field: it holds the day/month/year the rest of the
 * system speaks, and typing eight digits into it is enough — `ui.js` puts the
 * slashes in as you go and stops anything that is not a digit. Beside it is a
 * native `<input type="date">`, which contributes nothing to the form and
 * exists only for its calendar; picking a day writes the formatted date back
 * into the text box.
 *
 * A native date input is not the field itself on purpose: it posts ISO and
 * renders in the browser's locale, which on an English profile is MM/DD/YYYY —
 * the one order this must never show. Typing still works with JavaScript off,
 * exactly as it did before there was a picker.
 *
 * Every caller passes all three, `name` as null for a date the screen shows
 * but does not collect. None of them is optional on purpose: CodeIgniter keeps
 * view data between `view()` calls by default, so a `?? null` default here
 * would quietly inherit the previous date field's name — which it did, and the
 * read-only Entry Date posted as First Dialysis. The call sites pass
 * `['saveData' => false]` as well, so nothing carries over either way.
 *
 * @var string      $id     Element id for the label to point at
 * @var string|null $name   Form field name; null renders it read-only, no picker
 * @var string      $value  DD/MM/YYYY, or ''
 */
?>
<?php if ($name === null): ?>
    <input type="text" id="<?= esc($id) ?>" class="input-ro" value="<?= esc($value) ?>" readonly>
<?php else: ?>
    <div class="date-field" data-date-field>
        <input type="text" id="<?= esc($id) ?>" name="<?= esc($name) ?>" class="input date-field-text"
               value="<?= esc($value) ?>" placeholder="DD/MM/YYYY" inputmode="numeric"
               maxlength="10" autocomplete="off" data-date-text>
        <button type="button" class="date-field-button" data-date-open aria-label="Choose a date"><?= ui_icon('calendar') ?></button>
        <?php // Not part of the form: no name, so it posts nothing. ?>
        <input type="date" class="date-field-native" tabindex="-1" aria-hidden="true" data-date-native>
    </div>
<?php endif; ?>
