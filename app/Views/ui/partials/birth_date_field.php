<?php

use App\Libraries\UiStore;

/**
 * Date of birth, and the age it comes to — the date, and a box beside it.
 *
 * The age used to sit in the date's own label, as a grey note. That reads as a
 * remark about the field rather than as an answer, and an age is one of the
 * things somebody scans a record for. So it is a field now, next to the one it
 * is worked out from, and plainly not a second question: it is read-only, it
 * has nothing to type into, and it changes as the date beside it is typed.
 *
 * Both boxes share one cell of whatever grid they are dropped into, so adding
 * the age did not have to move anything else on any of the five screens that
 * ask for a date of birth.
 *
 * The sum is `UiStore::ageFrom()` on the way in and the same sum in `ui.js`
 * while the date is being typed. Nothing here is collected: `age` travels as a
 * hidden field so a record saved without a birth date keeps the number it was
 * entered with, and the server rewrites it from the date whenever there is one.
 *
 * @var string $id      Element id for the date box
 * @var string $name    The date field's name
 * @var string $value   The date, DD/MM/YYYY, or ''
 * @var string $ageName The hidden field the stored age travels in
 * @var string $age     The age stored on the record
 */
// Derived first, stored second: a record entered before birth dates were
// collected has only the number, and that is what shows.
$shown = trim($value) === '' ? (string) $age : (string) UiStore::ageFrom($value);
$shown = $shown === '0' ? '' : $shown;
?>
<div class="dob-field">
    <div class="dob-field-date">
        <label class="field-label" for="<?= esc($id) ?>">Date of Birth</label>
        <?= view('ui/partials/date_field', ['id' => $id, 'name' => $name, 'value' => $value, 'past' => true], ['saveData' => false]) ?>
    </div>
    <div class="dob-field-age">
        <label class="field-label" for="<?= esc($id) ?>-age">Age</label>
        <?php // The number still travels, so a record saved without a birth
              // date keeps the age it was entered with rather than dropping
              // to zero. ?>
        <input type="hidden" name="<?= esc($ageName) ?>" value="<?= esc($age) ?>">
        <input type="text" id="<?= esc($id) ?>-age" class="input-ro input-ro--age" data-age-for="<?= esc($id) ?>"
               value="<?= esc($shown) ?>" placeholder="&mdash;" readonly tabindex="-1">
    </div>
</div>
