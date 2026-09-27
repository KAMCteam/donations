<?= $this->extend('ui/layout') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/ui/css/exchange.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\ExchangeDraft;

/**
 * Building a paired exchange.
 *
 * The screen is the chain, drawn as it is: the pair it started from at the
 * top, then each link the choices have added, recipient ← donor, in the order
 * one follows from another. A recipient without a donor is an open node with
 * its own list of compatible donors under it; a donor without a recipient is
 * an open node the other way, with a list of compatible recipients and — since
 * a spare donor is allowed at the end of a chain — the two things that can
 * become of them instead.
 *
 * Every list only ever offers a blood-group match, donor to recipient, and a
 * donor already spoken for is gone from the rest. So a recipient cannot be
 * handed back to their own donor when incompatibility is what the exchange was
 * for, and nobody can be promised twice.
 *
 * Every control is a form post. The dropdowns submit their own choice, the
 * arrows are CSS, and the review before saving is a dialog where it can be and
 * a page (`exchange/review`) where it cannot — which is the same pattern the
 * pairing choice on a record screen uses.
 *
 * @var array<string, mixed> $state
 * @var string               $error
 */
$blood = static fn (?array $person): string => $person === null
    ? ''
    : '<span class="chip-blood">' . esc($person['blood_group']) . '</span>';

/** A dropdown that posts the choice it is given, with a button beside it. */
$chooser = static function (string $action, string $ownField, string $ownMrn, string $pickField, array $options, string $label) use (&$chooser): string {
    if ($options === []) {
        return '<p class="node-empty">' . esc($label) . '</p>';
    }

    $html = '<form method="post" action="' . site_url('exchange/build') . '" class="node-chooser">'
        . csrf_field()
        . '<input type="hidden" name="action" value="' . esc($action) . '">'
        . '<input type="hidden" name="' . esc($ownField) . '" value="' . esc($ownMrn) . '">'
        . '<select name="' . esc($pickField) . '" class="input node-select">';

    foreach ($options as $option) {
        $html .= '<option value="' . esc($option['mrn']) . '">'
            . esc($option['name']) . ' — ' . esc($option['blood_group']) . ' — ' . esc($option['source'])
            . '</option>';
    }

    return $html . '</select><button type="submit" class="btn-edit">Choose</button></form>';
};
?>
<div class="page">
    <div class="page-header page-header--start page-header--wrap">
        <div>
            <a class="back-link" href="<?= site_url('exchange') ?>"><?= ui_icon('back') ?>Back to Paired Exchange</a>
            <div class="eyebrow">Exchange</div>
            <h1 class="page-title">Build the exchange</h1>
        </div>
        <div class="header-actions">
            <?php if ($state['canUndo']): ?>
                <form method="post" action="<?= site_url('exchange/build') ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="undo">
                    <button type="submit" class="btn-outline"><?= ui_icon('back') ?>Undo last step</button>
                </form>
            <?php endif; ?>
            <a class="btn-outline" href="<?= site_url('exchange/build') . '#cancel' ?>" data-dialog="cancel-exchange">Cancel exchange</a>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <?php // ---- What still stands between here and saving ------------------ ?>
    <?php if ($state['openRecipients'] !== []): ?>
        <div class="exchange-alert exchange-alert--stop" role="alert">
            <strong><?= count($state['openRecipients']) ?> recipient<?= count($state['openRecipients']) === 1 ? '' : 's' ?> still without a donor:</strong>
            <?php
            $names = [];

            foreach ($state['openRecipients'] as $row) {
                $names[] = esc($row['name']) . ' (' . esc($row['blood_group']) . ')';
            }
            ?>
            <?= implode(' &middot; ', $names) ?>.
            The exchange cannot be saved while anyone is left this way.
        </div>
    <?php elseif ($state['undecided'] !== []): ?>
        <div class="exchange-alert exchange-alert--warn" role="alert">
            <strong><?= count($state['undecided']) ?> donor<?= count($state['undecided']) === 1 ? '' : 's' ?> without a recipient.</strong>
            A spare donor is allowed at the end of a chain, but say what becomes of them below before saving.
        </div>
    <?php else: ?>
        <div class="exchange-alert exchange-alert--go">
            <strong>The chain is complete.</strong> Every recipient has a donor, and every spare donor has been decided.
        </div>
    <?php endif; ?>

    <?php // ---- The pair this started from, side by side -------------------- ?>
    <?php $first = $state['chain'][0] ?? null; ?>
    <?php if ($first !== null): ?>
        <?php
        $originalDonor = $first['wasTheirDonor'];
        $currentDonor  = $first['donor'];
        $stillTheirs   = $currentDonor !== null && $originalDonor !== null
            && (int) $currentDonor['mrn'] === (int) $originalDonor['mrn'];
        $compatible = $originalDonor !== null && ExchangeDraft::canGive(
            (string) $originalDonor['blood_group'],
            (string) $first['recipient']['blood_group']
        );
        ?>
        <div class="card card--pad">
            <h2 class="card-title card-title--mb4">
                The pair being exchanged<?= $first['fromPair'] === null ? '' : ' — pair #' . (int) $first['fromPair'] ?>
            </h2>

            <div class="swap-row">
                <div class="swap-side">
                    <div class="person-card person-card--recipient">
                        <span class="person-role">Recipient</span>
                        <span class="person-name"><?= esc($first['recipient']['name']) ?></span>
                        <span class="person-meta"><?= esc($first['recipient']['mrn']) ?></span>
                        <?= $blood($first['recipient']) ?>
                    </div>
                    <div class="swap-picker">
                        <label class="field-label">Compatible donors</label>
                        <?= $chooser('chooseDonor', 'recipientMrn', (string) $first['recipient']['mrn'],
                            'donorMrn',
                            $first['donor'] === null ? $first['choosableDonors'] : [],
                            $first['donor'] === null ? 'No compatible donors at the moment.' : 'Matched with ' . $first['donor']['name'] . '.') ?>
                    </div>
                </div>

                <?php // The state of the link between the two, in one glance. ?>
                <div class="swap-link <?= $stillTheirs ? 'is-linked' : ($currentDonor === null ? 'is-broken' : 'is-swapped') ?>">
                    <span class="swap-link-icon"><?= ui_icon($currentDonor === null ? 'unlink' : 'link14') ?></span>
                    <span class="swap-link-label">
                        <?php if ($currentDonor === null): ?>
                            Unlinked
                        <?php elseif ($stillTheirs): ?>
                            Linked
                        <?php else: ?>
                            Swapped
                        <?php endif; ?>
                    </span>
                    <?php if ($originalDonor !== null && ! $compatible): ?>
                        <span class="swap-link-note">Own donor incompatible</span>
                    <?php endif; ?>
                </div>

                <div class="swap-side">
                    <?php $shownDonor = $currentDonor ?? $originalDonor; ?>
                    <?php if ($shownDonor !== null): ?>
                        <div class="person-card person-card--donor">
                            <span class="person-role">Donor</span>
                            <span class="person-name"><?= esc($shownDonor['name']) ?></span>
                            <span class="person-meta"><?= esc($shownDonor['mrn']) ?></span>
                            <?= $blood($shownDonor) ?>
                        </div>
                        <div class="swap-picker">
                            <label class="field-label">Compatible recipients</label>
                            <?php
                            $draftRaw = $state['raw'] ?? null;
                            $forDonor = $state['donorChoices'][(int) $shownDonor['mrn']] ?? [];
                            ?>
                            <?= $chooser('chooseRecipient', 'donorMrn', (string) $shownDonor['mrn'],
                                'recipientMrn', $forDonor,
                                $forDonor === [] ? 'No compatible recipients at the moment.' : '') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php // ---- The chain --------------------------------------------------- ?>
    <?php if ($state['assigned'] > 0 || count($state['chain']) > 1 || $state['spareDonors'] !== []): ?>
        <div class="card card--pad">
            <h2 class="card-title card-title--mb4">The chain</h2>

            <ol class="chain">
                <?php foreach ($state['chain'] as $i => $node): ?>
                    <li class="chain-node <?= $node['donor'] === null ? 'chain-node--open' : '' ?>">
                        <div class="chain-index"><?= $i + 1 ?></div>

                        <div class="person-card person-card--recipient">
                            <span class="person-role">Recipient</span>
                            <span class="person-name"><?= esc($node['recipient']['name']) ?></span>
                            <span class="person-meta"><?= esc($node['recipient']['mrn']) ?><?= $node['fromPair'] === null ? ' &middot; waiting list' : ' &middot; pair #' . (int) $node['fromPair'] ?></span>
                            <?= $blood($node['recipient']) ?>
                        </div>

                        <span class="chain-arrow" aria-hidden="true">&larr;</span>

                        <?php if ($node['donor'] !== null): ?>
                            <div class="person-card person-card--donor">
                                <span class="person-role">Donor</span>
                                <span class="person-name"><?= esc($node['donor']['name']) ?></span>
                                <span class="person-meta"><?= esc($node['donor']['mrn']) ?></span>
                                <?= $blood($node['donor']) ?>
                            </div>
                        <?php else: ?>
                            <div class="node-open node-open--stop">
                                <span class="node-open-label">No donor yet</span>
                                <?= $chooser('chooseDonor', 'recipientMrn', (string) $node['recipient']['mrn'],
                                    'donorMrn', $node['choosableDonors'],
                                    'No compatible donors at the moment.') ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>

                <?php foreach ($state['spareDonors'] as $donor): ?>
                    <li class="chain-node chain-node--spare">
                        <div class="chain-index"><?= ui_icon('shuffle14') ?></div>

                        <div class="node-open node-open--warn">
                            <span class="node-open-label">Donor without a recipient</span>
                            <?= $chooser('chooseRecipient', 'donorMrn', (string) $donor['mrn'],
                                'recipientMrn', $donor['choosableRecipients'],
                                'No compatible recipients — choose one of the two below.') ?>

                            <div class="node-fates">
                                <?php foreach (ExchangeDraft::FATES as $value => $label): ?>
                                    <form method="post" action="<?= site_url('exchange/build') ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="fate">
                                        <input type="hidden" name="donorMrn" value="<?= esc($donor['mrn']) ?>">
                                        <input type="hidden" name="fate" value="<?= esc($value) ?>">
                                        <?php if ($value === 'delete'): ?>
                                            <?php // Asked before it is set, not only before it is saved. ?>
                                            <button type="submit" class="btn-outline btn-outline--danger"
                                                    data-confirm="Delete <?= esc($donor['name']) ?> (MRN <?= esc($donor['mrn']) ?>) from the system? The record and its whole lab workup go with it. This cannot be undone."><?= esc($label) ?></button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-outline<?= $donor['fate'] === $value ? ' is-chosen' : '' ?>"><?= esc($label) ?></button>
                                        <?php endif; ?>
                                    </form>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($donor['fate'] !== ''): ?>
                                <p class="node-decided"><?= esc(ExchangeDraft::FATES[$donor['fate']]) ?> &mdash; decided.</p>
                            <?php endif; ?>
                        </div>

                        <span class="chain-arrow" aria-hidden="true">&larr;</span>

                        <div class="person-card person-card--donor">
                            <span class="person-role">Donor</span>
                            <span class="person-name"><?= esc($donor['name']) ?></span>
                            <span class="person-meta"><?= esc($donor['mrn']) ?></span>
                            <?= $blood($donor) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php endif; ?>

    <div class="card card--pad">
        <div class="card-actions">
            <a class="btn-outline" href="<?= site_url('exchange/build') . '#cancel' ?>" data-dialog="cancel-exchange">Cancel exchange</a>
            <?php // The review is a page of its own, opened as a dialog where
                  // scripting allows. Saving happens from inside either one. ?>
            <a class="btn-save<?= $state['complete'] ? '' : ' is-disabled' ?>"
               href="<?= site_url('exchange/review') ?>"<?= $state['complete'] ? ' data-dialog="review-exchange"' : ' aria-disabled="true"' ?>>Review and save</a>
        </div>
    </div>
</div>

<dialog class="dialog dialog--wide" id="review-exchange">
    <div class="dialog-body">
        <form method="dialog" class="dialog-close-form">
            <button class="dialog-close" aria-label="Close">&times;</button>
        </form>
        <?= view('ui/partials/exchange_summary', ['state' => $state], ['saveData' => false]) ?>
        <div class="confirm-actions">
            <form method="dialog"><button type="submit" class="btn-outline">Back</button></form>
            <form method="post" action="<?= site_url('exchange/build') ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="confirm">
                <button type="submit" class="btn-save">Save the exchange</button>
            </form>
        </div>
    </div>
</dialog>

<dialog class="dialog" id="cancel-exchange">
    <form method="post" action="<?= site_url('exchange/build') ?>" class="confirm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="discard">
        <h2 class="confirm-title">Cancel this exchange?</h2>
        <p class="confirm-detail">Every pair goes back exactly as it was. Nothing has been written yet, so nothing is lost but the working out.</p>
        <div class="confirm-actions">
            <button type="button" class="btn-outline" data-dialog-close>Keep working</button>
            <button type="submit" class="btn-danger">Cancel exchange</button>
        </div>
    </form>
</dialog>
<?= $this->endSection() ?>
