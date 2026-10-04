<?php

/**
 * Who is coordinating this case, chosen from the people registered as one.
 *
 * It was a text box, which asked somebody to remember a colleague's name and
 * spell it the way the last person did — and quietly registered a second
 * coordinator when they did not. Coordinators are registered on **Add MRP**
 * now, alongside the physicians, so this is a list of them rather than a place
 * to invent one.
 *
 * The value posted is still the name, because that is what the rest of the
 * platform passes about: `UiStore` turns it into `coordinator_id` on the way
 * in and back into a name on the way out, and a name chosen from the list is
 * one it already holds.
 *
 * A record whose coordinator has since been taken off the list keeps them: the
 * name is added to the bottom of the list, marked, rather than silently
 * becoming "Choose Coordinator" the next time the card is saved.
 *
 * @var string                     $id           The control's id
 * @var string                     $name         The field name to post
 * @var string                     $value        The name stored now
 * @var list<array<string, mixed>> $coordinators Everyone registered
 */
$names = array_map(static fn (array $row): string => (string) $row['name'], $coordinators);
$value = trim($value);
?>
<select id="<?= esc($id) ?>" name="<?= esc($name) ?>" class="input">
    <option value=""<?= $value === '' ? ' selected' : '' ?>>Choose Coordinator</option>
    <?php foreach ($names as $coordinator): ?>
        <option value="<?= esc($coordinator) ?>"<?= $value === $coordinator ? ' selected' : '' ?>><?= esc($coordinator) ?></option>
    <?php endforeach; ?>
    <?php if ($value !== '' && ! in_array($value, $names, true)): ?>
        <option value="<?= esc($value) ?>" selected><?= esc($value) ?> &mdash; no longer registered</option>
    <?php endif; ?>
</select>
