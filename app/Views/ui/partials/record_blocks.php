<?php

use App\Libraries\UiStore;

/**
 * The blocks a printed record is made of.
 *
 * `RecordBlocks` decides what a record consists of; this renders it. Two sheets
 * use it — one record from its own screen, and a filtered set of them from
 * Reports — which is why it is a partial rather than part of either.
 *
 * A block is `fields` for a labelled grid, `text` for notes, or `labs` for a
 * workup.
 *
 * @var list<array<string, mixed>> $blocks
 */
$dash = '—';
?>
    <?php foreach ($blocks as $block): ?>
        <section class="rec-block">
            <h2 class="rec-block-title">
                <?= esc($block['title']) ?>
                <?php if (($block['subtitle'] ?? '') !== ''): ?>
                    <small><?= esc($block['subtitle']) ?></small>
                <?php endif; ?>
            </h2>

            <?php if ($block['kind'] === 'fields'): ?>
                <?php
                // The grid is three across and a wide field takes two of
                // them, so rows do not fill evenly: a wide field with only
                // one column left is pushed to the next row and leaves a
                // hole behind it, and the last row usually ends short.
                // Every gap is filled with an empty cell, because an
                // unfilled one leaves the block's border stopping halfway
                // and the box reads as torn rather than ended.
                //
                // Walking the row here rather than leaving it to the
                // browser is what makes the holes findable at all: laid
                // out, they are between cells, not after them.
                $cells  = [];
                $column = 0;

                foreach ($block['fields'] as $field) {
                    $width = ($field['wide'] ?? false) ? 2 : 1;

                    if ($column + $width > 3) {
                        for (; $column < 3; $column++) {
                            $cells[] = null;
                        }

                        $column = 0;
                    }

                    $cells[] = $field;
                    $column  = ($column + $width) % 3;
                }

                for (; $column > 0 && $column < 3; $column++) {
                    $cells[] = null;
                }
                ?>
                <div class="rec-fields">
                    <?php foreach ($cells as $field): ?>
                        <?php if ($field === null): ?>
                            <div class="rec-field rec-field--filler"></div>
                        <?php else: ?>
                            <div class="rec-field<?= ($field['wide'] ?? false) ? ' rec-field--wide' : '' ?>">
                                <div class="rec-label"><?= esc($field['label']) ?></div>
                                <div class="rec-value<?= ($field['mono'] ?? false) ? ' rec-value--mono' : '' ?><?= ($field['strong'] ?? false) ? ' rec-value--strong' : '' ?>"><?= esc($field['value'] !== '' ? $field['value'] : $dash) ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($block['kind'] === 'text'): ?>
                <?php $text = trim((string) $block['text']); ?>
                <div class="rec-text<?= $text === '' ? ' rec-text--empty' : '' ?>"><?= $text === '' ? 'Nothing recorded.' : esc($text) ?></div>

            <?php else: ?>
                <?php
                // The workup, in the check list's own groups and order. The
                // progress line is the same count the screen shows, so a
                // printed sheet and the record it came from agree.
                $progress = UiStore::labProgress($block['tests']);
                $groups   = [];

                foreach ($block['tests'] as $test) {
                    $groups[$test['group'] ?? ''][] = $test;
                }
                ?>
                <p class="rec-progress"><?= $progress['done'] ?> of <?= $progress['total'] ?> completed &middot; <?= $progress['pct'] ?>%</p>

                <?php foreach ($groups as $groupName => $groupTests): ?>
                    <?php if ($groupName !== ''): ?>
                        <h3 class="rec-group-name"><?= esc($groupName) ?></h3>
                    <?php endif; ?>
                    <table class="rec-labs">
                        <thead>
                            <tr>
                                <?php // Value / finding and Date have gone from
                                      // the cards, so there is nothing left to
                                      // print in a column for them. ?>
                                <th class="l-test">Test</th>
                                <th class="l-answer">Result</th>
                                <th class="l-comment">Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groupTests as $test): ?>
                                <?php
                                // "Other" has no answer to give — it is the
                                // sheet's blank line — so its row carries
                                // only what was written in it.
                                $freeText   = in_array($test['resultType'], UiStore::FREE_TEXT_TYPES, true);
                                $status     = (string) $test['status'];
                                $unanswered = in_array($status, UiStore::RESULT_UNANSWERED, true);
                                // The test's own answers, which for one the
                                // record added are the ones it was given.
                                $byKey      = array_column($test['answers'] ?? [], null, 'key');
                                $flag       = ($byKey[$status]['tone'] ?? UiStore::RESULT_TONE[$status] ?? '') === 'tone-red';
                                // A test that does not offer the answer it
                                // holds has not been answered at all, and
                                // the sheet says so the way an empty field
                                // does — with a dash, not with a word the
                                // screen never showed.
                                $blank      = $freeText || ! isset($byKey[$status]);
                                ?>
                                <tr>
                                    <td class="l-test"><?= esc($test['name']) ?></td>
                                    <td class="l-answer<?= $unanswered ? ' l-answer--unanswered' : '' ?><?= $flag ? ' l-answer--flag' : '' ?>"><?= $blank ? $dash : esc($byKey[$status]['label'] ?? $status) ?></td>
                                    <td class="l-comment"><?= esc(($test['notes'] ?? '') !== '' ? $test['notes'] : $dash) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
