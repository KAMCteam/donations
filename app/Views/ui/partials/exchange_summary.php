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
        <?php // Each one with the word they go back on. Closing their pair is
              // what puts them back on the register; what it cannot say is
              // whether they are to be matched again straight away. Active is
              // offered first because a donor on the available list who is not
              // Active is not available to anybody — and On Hold is the true
              // word for one who is going back but is not to be offered yet,
              // which is the case this asks about. Asked here rather than on
              // the chain, because it is a fact about the donor and not a step
              // in working the chain out. ?>
        <ul class="summary-list summary-list--released">
            <?php foreach ($summary['released'] as $donor): ?>
                <li class="summary-released">
                    <div class="summary-released-who">
                        <span class="summary-name"><?= esc($donor['name']) ?></span>
                        <span class="chip-blood"><?= esc($donor['blood_group']) ?></span>
                        <span class="summary-meta"><?= esc($donor['mrn']) ?></span>
                    </div>
                    <div class="field">
                        <label class="field-label" for="xs-<?= esc($donor['mrn']) ?>">Donor Status</label>
                        <select id="xs-<?= esc($donor['mrn']) ?>" name="donorStatus[<?= esc($donor['mrn']) ?>]" class="input">
                            <?php foreach (UiStore::PERSON_STATUS_OPTIONS as $value => $label): ?>
                                <option value="<?= esc($value) ?>"<?= $value === 'active' ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

