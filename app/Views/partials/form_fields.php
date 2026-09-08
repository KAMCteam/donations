<?php
/**
 * Renders one section of the add-patient form.
 *
 * $index picks which part of $form to draw: 'recipient', 'donor' or 'pair'.
 * The pair section has no labs or note, so it returns early.
 */
?>
<?php if (! empty($form[$index])): ?>
    <div>
    <h3 class="form-name"><?= esc($form[$index . '_title'] ?? '') ?></h3>
    <?php $count = 1; ?>
    <?php foreach ($form[$index] as $field): ?>
        <?php if (empty($field['type'])) {
            continue;
        } ?>
        <?php if (($count - 1) % 5 === 0): ?><div class="row"><?php endif; ?>
            <div class="item">
                <?php if ($field['type'] === 'select'): ?>
                    <label><?= esc($field['label']) ?>
                    <select name="<?= esc($field['name'], 'attr') ?>" id="<?= esc($field['id'], 'attr') ?>">
                        <?php foreach ($field['options'] as $option): ?>
                            <option value="<?= esc($option['value'], 'attr') ?>"><?= esc($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    </label>
                <?php else: ?>
                    <label><?= esc($field['label']) ?>
                    <input type="<?= esc($field['type'], 'attr') ?>" name="<?= esc($field['name'], 'attr') ?>" id="<?= esc($field['id'], 'attr') ?>">
                    </label>
                <?php endif; ?>
            </div>
        <?php if ($count % 5 === 0): ?></div><?php endif; ?>
    <?php $count++; ?>
    <?php endforeach; ?>
    <?php if ($count % 5 !== 1): ?></div><?php endif; ?>

    <?php if ($index === 'pair'): ?>
        </div>
    <?php else: ?>
    <hr>

    <h3><?= esc(lang('Form.form_labs_header')) ?></h3>

    <?php foreach ($form[$index]['labs'] as $lab_parent): ?>
        <h4><?= esc($lab_parent['parent_name'] ?? '') ?></h4>
        <div class="lab-row">
            <?php foreach ($lab_parent['labs'] as $lab): ?>
                <div class="lab-item">
                    <label><?= esc($lab['label']) ?>
                    <select name="<?= esc($lab['name'], 'attr') ?>">
                        <?php foreach ($lab['options'] as $option): ?>
                            <option value="<?= esc($option['value'], 'attr') ?>"><?= esc($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    </label>

                    <label><?= esc('Comment') ?>
                    <textarea name="<?= esc($lab['name'] . '_comment', 'attr') ?>" class="lab-comment"></textarea>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <hr>

    <div>
        <label><?= esc(lang('Form.form_note')) ?>
        <textarea name="note<?= ($index === 'donor') ? '_2' : '' ?>"></textarea>
        </label>
    </div>
    <hr class="step">
    </div>
    <?php endif; ?>
<?php endif; ?>
