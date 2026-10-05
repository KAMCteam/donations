<?php

use App\Libraries\UiStore;

/**
 * What saving the exchange would do, said before it happens.
 *
 * Shared by the dialog on the builder and by `ui/exchange_review`, the page
 * that same button links to so the summary is still reachable with JavaScript
 * off. Neither one writes anything: the form inside posts back to the builder,
 * which is where confirming is handled.
 *
 * The new pairs are not only listed: each one asks what it *is* — the Pair
 * Details card's own three fields, because this is where the pair is made and
 * the screen that usually asks them does not exist for it yet. Saving writes
 * them with the link, so a pair the exchange made arrives as complete as one
 * entered by hand.
 *
 * @var array<string, mixed> $state
 */
$summary = $state['summary'];

// A pair being made cannot already be closed — closing is what frees both
// sides, and a pair that frees them the moment it exists is no pair at all.
$newPairStatuses = array_diff_key(UiStore::PAIR_STATUS_OPTIONS, ['closed' => '']);
?>
<h2 class="summary-title">Review the exchange</h2>
<p class="summary-sub">Nothing has changed yet. Saving does all of the following at once.</p>

<div class="summary-block">
    <h3 class="summary-heading">New pairs <span class="summary-count"><?= count($summary['links']) ?></span></h3>
    <?php if ($summary['links'] === []): ?>
        <p class="summary-empty">None yet.</p>
    <?php else: ?>
        <ul class="summary-list summary-list--pairs">
            <?php foreach ($summary['links'] as $link): ?>
                <?php $mrn = (string) $link['recipient']['mrn']; ?>
                <li class="summary-pair">
                    <div class="summary-pair-line">
                        <span class="summary-name"><?= esc($link['recipient']['name']) ?></span>
                        <span class="chip-blood"><?= esc($link['recipient']['blood_group']) ?></span>
                        <span class="summary-arrow">&larr;</span>
                        <span class="summary-name"><?= esc($link['donor']['name']) ?></span>
                        <span class="chip-blood"><?= esc($link['donor']['blood_group']) ?></span>
                    </div>
                    <?= view('ui/partials/pair_details_fields', [
                        'idStem' => 'xd-' . $mrn,
                        'names'  => [
                            'relationship'   => 'pairDetails[' . $mrn . '][relationship]',
                            'crossmatchDate' => 'pairDetails[' . $mrn . '][crossmatchDate]',
                            'status'         => 'pairDetails[' . $mrn . '][status]',
                        ],
                        // Empty, every one of them. The donor's stored
                        // relationship is to the recipient they came in with:
                        // "Sister" on a donor being crossed to somebody else's
                        // recipient is not their sister, and a prefilled wrong
                        // answer is worse than a blank one.
                        'values' => [
                            'relationship'   => '',
                            'crossmatchDate' => '',
                            'status'         => 'paired_exchange',
                        ],
                        'statuses' => $newPairStatuses,
                    ], ['saveData' => false]) ?>
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
