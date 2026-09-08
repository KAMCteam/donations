<?php if (!empty($form[$index])): ?>
    <div>
    <h3 class="form-name"><?= htmlspecialchars($form[$index . '_title']) ?></h3>
    <?php $count = 1; foreach ($form[$index] as $field): ?>
        <?php if (empty($field['type'])) { continue; } ?>
        <?php if (($count-1) % 5 === 0): ?><div class="row"><?php endif; ?>
            <div class="item">
                <?php if ($field['type'] === 'select'): ?>
                    <label><?= htmlspecialchars($field['label']) ?>
                    <select name="<?= $field['name'] ?>" id="<?= $field['id'] ?>">
                        <?php foreach ($field['options'] as $option): ?>
                            <option value="<?= $option['value'] ?>"><?= $option['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    </label>
                <?php else: ?>
                    <label><?= htmlspecialchars($field['label']) ?>
                    <input type="<?= $field['type'] ?>" name="<?= $field['name'] ?>" id="<?= $field['id'] ?>">
                    </label>
                <?php endif; ?>
            </div>
        <?php if ($count % 5 === 0): ?></div><?php endif; ?>
    <?php $count++; endforeach; ?>
    <?php if ($count % 5 != 1): ?></div><?php endif; ?>

    <?php if ($index === 'pair') { echo '</div>'; return; } ?>
    <hr>

    <h3><?= lang('form_labs_header') ?></h3>
    
    <?php foreach ($form[$index]['labs'] as $lab_parent): ?>
        <h4><?= htmlspecialchars($lab_parent['parent_name']) ?></h4>
        <div class="lab-row">
            <?php foreach ($lab_parent['labs'] as $lab): ?>
                <div class="lab-item">
                    <label><?= htmlspecialchars($lab['label']) ?>
                    <select name="<?= $lab['name'] ?>">
                        <?php foreach ($lab['options'] as $option): ?>
                            <option value="<?= $option['value'] ?>"><?= htmlspecialchars($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    </label>

                    <label><?= htmlspecialchars('Comment') ?>
                    <textarea name="<?= $lab['name'] . '_comment' ?>" class="lab-comment"></textarea>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    

    <hr>

    <div>
        <label><?= lang('form_note') ?>
        <textarea name="note<?= ($index === 'donor') ? '_2' : '' ?>"></textarea>
        </label>
    </div>
    <hr class="step">
    </div>
<?php endif; ?>