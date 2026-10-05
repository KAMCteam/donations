<?php

use App\Libraries\UiStore;

/**
 * What a pair is, as three fields: Relationship, Date of Crossmatch, Status.
 *
 * The Pair Details card asks these on the pair's own screen. They are asked
 * again where a pair is *made* and nobody has been to that screen yet — in the
 * exchange's review, where saving makes several pairs at once and the only
 * chance to say what each one is, is before it exists.
 *
 * The names are the caller's, because the exchange asks them once per pair and
 * has to keep the answers apart. So is the id stem, since more than one of
 * these can be on a screen and a label has to point at its own field.
 *
 * @var string                $idStem     Unique per block, for the labels
 * @var array<string, string> $names      relationship | crossmatchDate | status
 * @var array<string, string> $values     The same keys, as they stand
 * @var array<string, string> $statuses   What the Status select offers
 */
?>
<div class="form-grid-3">
    <div>
        <label class="field-label" for="<?= esc($idStem) ?>-relationship">Relationship</label>
        <input type="text" id="<?= esc($idStem) ?>-relationship" name="<?= esc($names['relationship']) ?>"
               class="input" value="<?= esc($values['relationship'] ?? '') ?>" placeholder="e.g. Sibling, Spouse">
    </div>
    <div>
        <label class="field-label" for="<?= esc($idStem) ?>-crossmatch">Date of Crossmatch</label>
        <?= view('ui/partials/date_field', [
            'id'    => $idStem . '-crossmatch',
            'name'  => $names['crossmatchDate'],
            'value' => $values['crossmatchDate'] ?? '',
        ], ['saveData' => false]) ?>
    </div>
    <div>
        <label class="field-label" for="<?= esc($idStem) ?>-status">Status</label>
        <select id="<?= esc($idStem) ?>-status" name="<?= esc($names['status']) ?>" class="input">
            <?php foreach ($statuses as $value => $label): ?>
                <option value="<?= esc($value) ?>"<?= ($values['status'] ?? '') === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
