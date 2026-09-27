<?php

/**
 * What saving the exchange would do, said before it happens.
 *
 * Shared by the dialog on the builder and by `ui/exchange_review`, the page
 * that same button links to so the summary is still reachable with JavaScript
 * off. Neither one writes anything: the form inside posts back to the builder,
 * which is where confirming is handled.
 *
 * @var array<string, mixed> $state
 */
$summary = $state['summary'];
?>
<h2 class="summary-title">Review the exchange</h2>
<p class="summary-sub">Nothing has changed yet. Saving does all of the following at once.</p>

<div class="summary-block">
    <h3 class="summary-heading">New pairs <span class="summary-count"><?= count($summary['links']) ?></span></h3>
    <?php if ($summary['links'] === []): ?>
        <p class="summary-empty">None yet.</p>
    <?php else: ?>
        <ul class="summary-list">
            <?php foreach ($summary['links'] as $link): ?>
                <li>
                    <span class="summary-name"><?= esc($link['recipient']['name']) ?></span>
                    <span class="chip-blood"><?= esc($link['recipient']['blood_group']) ?></span>
                    <span class="summary-arrow">&larr;</span>
                    <span class="summary-name"><?= esc($link['donor']['name']) ?></span>
                    <span class="chip-blood"><?= esc($link['donor']['blood_group']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="summary-block">
    <h3 class="summary-heading">Pairs closed <span class="summary-count"><?= count($summary['closed']) ?></span></h3>
    <p class="summary-empty">
        <?= $summary['closed'] === [] ? 'None.' : 'Pair #' . implode(', #', array_map('intval', $summary['closed'])) ?>
        &mdash; kept as history, not deleted.
    </p>
</div>

<?php if ($summary['released'] !== []): ?>
    <div class="summary-block">
        <h3 class="summary-heading">Moved to the available donors list <span class="summary-count"><?= count($summary['released']) ?></span></h3>
        <ul class="summary-list">
            <?php foreach ($summary['released'] as $donor): ?>
                <li>
                    <span class="summary-name"><?= esc($donor['name']) ?></span>
                    <span class="chip-blood"><?= esc($donor['blood_group']) ?></span>
                    <span class="summary-meta"><?= esc($donor['mrn']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($summary['deleted'] !== []): ?>
    <div class="summary-block summary-block--danger">
        <h3 class="summary-heading">Deleted from the system <span class="summary-count"><?= count($summary['deleted']) ?></span></h3>
        <ul class="summary-list">
            <?php foreach ($summary['deleted'] as $donor): ?>
                <li>
                    <span class="summary-name"><?= esc($donor['name']) ?></span>
                    <span class="chip-blood"><?= esc($donor['blood_group']) ?></span>
                    <span class="summary-meta"><?= esc($donor['mrn']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="summary-warning">
            This removes the record, its whole lab workup, and the pair records that name them —
            including the one this exchange is closing. It cannot be undone.
        </p>
    </div>
<?php endif; ?>
