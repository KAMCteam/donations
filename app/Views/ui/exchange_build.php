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
 * The screen is the chain and nothing else: the pair it started from is its
 * first link, and each choice adds another — recipient ← donor, in the order
 * one follows from another. A recipient without a donor is an open node with
 * its own list of compatible donors under it; a donor without a recipient is
 * an open node the other way, with a list of compatible recipients.
 *
 * A spare donor is only offered the two things that can become of them —
 * back to the register, or deleted — once they are the only one left over.
 * While a recipient is still without a donor the chain has somewhere to go,
 * and ending it here is not yet a choice anybody has to make.
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
// A spare donor is only asked about once nobody else is waiting: while a
// recipient is still without one, the chain has somewhere to go.
$onlyDonorsLeft = $state['openRecipients'] === [];

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

    <?php // ---- The chain --------------------------------------------------- ?>
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

                            <?php // The dropdown is always there while somebody compatible
                                  // is free — extending the chain is the tidier answer.
                                  // What the fates wait for is the donor being the *only*
                                  // one left: while a recipient is still without a donor,
                                  // ending the chain here is not yet a choice to make, and
                                  // offering it invites a decision nobody needs. ?>
                            <?php if ($donor['choosableRecipients'] !== []): ?>
                                <?= $chooser('chooseRecipient', 'donorMrn', (string) $donor['mrn'],
                                    'recipientMrn', $donor['choosableRecipients'], '') ?>
                            <?php else: ?>
                                <p class="node-empty">No compatible recipients are free.</p>
                            <?php endif; ?>

                            <?php if ($onlyDonorsLeft): ?>
                                <p class="node-open-hint">Everyone else is matched. This donor ends the chain &mdash; say what becomes of them.</p>

                                <div class="node-fates">
                                    <form method="post" action="<?= site_url('exchange/build') ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="fate">
                                        <input type="hidden" name="donorMrn" value="<?= esc($donor['mrn']) ?>">
                                        <input type="hidden" name="fate" value="available">
                                        <button type="submit" class="btn-fate<?= $donor['fate'] === 'available' ? ' is-chosen' : '' ?>"><?= ui_icon('back') ?><?= esc(ExchangeDraft::FATES['available']) ?></button>
                                    </form>

                                    <form method="post" action="<?= site_url('exchange/build') ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="fate">
                                        <input type="hidden" name="donorMrn" value="<?= esc($donor['mrn']) ?>">
                                        <input type="hidden" name="fate" value="delete">
                                        <?php // Asked before it is set, not only before it is saved. ?>
                                        <button type="submit" class="btn-fate btn-fate--danger<?= $donor['fate'] === 'delete' ? ' is-chosen' : '' ?>" data-confirm="Delete <?= esc($donor['name']) ?> (MRN <?= esc($donor['mrn']) ?>) from the system? The record, its whole lab workup and the pair records naming them all go with it. This cannot be undone."><?= ui_icon('trash') ?><?= esc(ExchangeDraft::FATES['delete']) ?></button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <?php if ($donor['fate'] !== ''): ?>
                                <p class="node-decided"><?= ui_icon('check') ?><?= esc(ExchangeDraft::FATES[$donor['fate']]) ?> &mdash; decided.</p>
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
