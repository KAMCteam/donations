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
 * `$past` is every date the personal details collect: when somebody was born,
 * when their dialysis began, the day they joined the register. All of them
 * have happened, so the field will not take a date that has not — the picker
 * stops at today, `ui.js` refuses a later one typed in, and the controller
 * checks again, because neither of the first two is on the server.
 *
 * `$disabled` closes the field without taking it off the screen, for a date
 * that cannot apply rather than one nobody has filled in: a pre-emptive
 * recipient has no first dialysis. The box still posts, empty, so saving
 * clears whatever was there before the answer changed.
 *
 * @var string      $id       Element id for the label to point at
 * @var string|null $name     Form field name; null renders it read-only, no picker
 * @var string      $value    DD/MM/YYYY, or ''
 * @var bool        $past     Refuse a date later than today
 * @var bool        $disabled Render it closed, with a reason of its own
 */
$past     ??= false;
$disabled ??= false;
?>
<?php if ($name === null): ?>
    <input type="text" id="<?= esc($id) ?>" class="input-ro" value="<?= esc($value) ?>" readonly>
<?php else: ?>
    <div class="date-field<?= $disabled ? ' is-closed' : '' ?>" data-date-field<?= $past ? ' data-date-past' : '' ?>>
        <input type="text" id="<?= esc($id) ?>" name="<?= esc($name) ?>" class="input date-field-text"
               value="<?= esc($disabled ? '' : $value) ?>" placeholder="<?= $disabled ? 'Not applicable' : 'DD/MM/YYYY' ?>" inputmode="numeric"
               maxlength="10" autocomplete="off" data-date-text<?= $disabled ? ' readonly' : '' ?>>
        <button type="button" class="date-field-button" data-date-open aria-label="Choose a date"<?= $disabled ? ' disabled' : '' ?>><?= ui_icon('calendar') ?></button>
        <?php // Not part of the form: no name, so it posts nothing. The limit
              // is the picker's own, which is why it is set here and not on
              // the box the form actually sends. ?>
        <input type="date" class="date-field-native" tabindex="-1" aria-hidden="true" data-date-native<?= $past ? ' max="' . date('Y-m-d') . '"' : '' ?>>
    </div>
<?php endif; ?>
