<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

/**
 * Building a paired exchange.
 *
 * The pairs in play have come apart into two columns — every recipient on one
 * side, every donor on the other — drawn from the pairs this exchange has
 * pulled in plus everyone on the waiting list and the donor register who is
 * free. Pick one from each, press Pair them, and the pair joins the list at
 * the top.
 *
 * The bar above the columns is the whole rule in one line: pairing somebody
 * who is standing in a pair takes their partner out of it too, and that
 * partner now needs a pair of their own. While anybody is still owed one the
 * Confirm button is disabled and says why. Nothing is written until it is
 * pressed.
 *
 * Each column is a radio group in one form, so choosing and submitting works
 * with no JavaScript at all; `ui.js` adds nothing here.
 *
 * @var array{
 *     sources: list<array<string, mixed>>,
 *     formed: list<array{recipient: array<string, mixed>, donor: array<string, mixed>}>,
 *     owedRecipients: list<array<string, mixed>>,
 *     owedDonors: list<array<string, mixed>>,
 *     recipients: list<array<string, mixed>>,
 *     donors: list<array<string, mixed>>,
 *     complete: bool, done: int, needed: int
 * } $state
 * @var string $error
 */
$pct = (int) round($state['done'] / max($state['needed'], 1) * 100);

/** One person as a choosable row: name, file number, and where they stand. */
$person = static function (array $row, string $field, string $kind): string {
    $held  = $row['heldByPair'] ?? null;
    $where = $held === null
        ? ($kind === 'recipient' ? 'On the waiting list' : 'Unmatched')
        : 'In pair #' . $held;

    return '<label class="pick">'
        . '<input type="radio" name="' . $field . '" value="' . esc($row['mrn']) . '">'
        . '<span class="pick-body">'
        . '<span class="pick-name">' . esc($row['name']) . '</span>'
        . '<span class="pick-meta">' . esc($row['mrn']) . ' &middot; ' . esc($row['blood_group']) . ' &middot; ' . esc($where) . '</span>'
        . '</span></label>';
};
?>
<div class="page">
    <div class="page-header page-header--start page-header--wrap">
        <div>
            <a class="back-link" href="<?= site_url('exchange') ?>"><?= ui_icon('back') ?>Back to Paired Exchange</a>
            <div class="eyebrow">Exchange</div>
            <h1 class="page-title">Build the exchange</h1>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <div class="card card--pad">
        <div class="lab-head">
            <div>
                <h2 class="card-title">Progress</h2>
                <p class="lab-count"><?= $state['done'] ?> of <?= $state['needed'] ?> pairs complete</p>
            </div>
            <div class="lab-progress">
                <div class="progress">
                    <div class="progress-fill" style="width:<?= $pct ?>%;background-color:<?= $state['complete'] ? '#059669' : '#b45309' ?>"></div>
                </div>
                <span class="lab-pct"><?= $pct ?>%</span>
            </div>
        </div>

        <?php if ($state['formed'] !== []): ?>
            <div class="stack-2 exchange-formed">
                <?php foreach ($state['formed'] as $i => $pair): ?>
                    <div class="formed-pair">
                        <span class="formed-side"><?= esc($pair['recipient']['name']) ?> <span class="pick-meta"><?= esc($pair['recipient']['mrn']) ?></span></span>
                        <span class="formed-arrow"><?= ui_icon('link14') ?></span>
                        <span class="formed-side"><?= esc($pair['donor']['name']) ?> <span class="pick-meta"><?= esc($pair['donor']['mrn']) ?></span></span>
                        <form method="post" action="<?= site_url('exchange/build') ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unlink">
                            <input type="hidden" name="index" value="<?= $i ?>">
                            <button type="submit" class="btn-edit">Undo</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php // Who this exchange has left without a partner. Naming them is
              // the point: the rule is that none of them may stay that way. ?>
        <?php if ($state['owedRecipients'] !== [] || $state['owedDonors'] !== []): ?>
            <p class="exchange-owed">
                <strong>Still to pair:</strong>
                <?php
                // Escaped one name at a time: escaping the joined string
                // would escape the separator with it and print the entity.
                $owed = [];

                foreach ($state['owedRecipients'] as $row) {
                    $owed[] = esc($row['name']) . ' (' . esc($row['mrn']) . ', recipient)';
                }

                foreach ($state['owedDonors'] as $row) {
                    $owed[] = esc($row['name']) . ' (' . esc($row['mrn']) . ', donor)';
                }
                ?>
                <?= implode(' &middot; ', $owed) ?>
            </p>
        <?php else: ?>
            <p class="exchange-owed exchange-owed--done">Nobody is left without a pair. The exchange can be confirmed.</p>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= site_url('exchange/build') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="link">

        <div class="exchange-columns">
            <div class="card card--pad card--scroll-y">
                <h2 class="card-title card-title--mb4">Recipients</h2>
                <?php if ($state['recipients'] === []): ?>
                    <div class="empty-state">No recipients left to pair.</div>
                <?php else: ?>
                    <div class="pick-list">
                        <?php foreach ($state['recipients'] as $row): ?>
                            <?= $person($row, 'recipientMrn', 'recipient') ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card card--pad card--scroll-y">
                <h2 class="card-title card-title--mb4">Donors</h2>
                <?php if ($state['donors'] === []): ?>
                    <div class="empty-state">No donors left to pair.</div>
                <?php else: ?>
                    <div class="pick-list">
                        <?php foreach ($state['donors'] as $row): ?>
                            <?= $person($row, 'donorMrn', 'donor') ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-save"><?= ui_icon('link14') ?>Pair them</button>
        </div>
    </form>

    <div class="card card--pad">
        <div class="card-actions">
            <form method="post" action="<?= site_url('exchange/build') ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="discard">
                <button type="submit" class="btn-outline">Discard exchange</button>
            </form>
            <form method="post" action="<?= site_url('exchange/build') ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="confirm">
                <?php // Disabled is not the guard — ExchangeDraft::confirm() refuses
                      // the same thing server-side, whatever arrives. ?>
                <button type="submit" class="btn-save"<?= $state['complete'] ? '' : ' disabled title="Everyone this exchange releases needs a pair first"' ?>>Confirm exchange</button>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
