<?php

use App\Libraries\UiStore;

/**
 * A pair's donors, one tab each.
 *
 * A pair is a recipient and the donors being worked up for them. One of those
 * is the donor it is going ahead with — the Active one, and there is never
 * more than one — and the rest are being held: On Hold while they are still a
 * possibility, Declined once they are not. Both are still the pair's donors,
 * and either can be taken back up by moving the words around — on the donor's
 * own card, where the word they are on is one of their fields.
 *
 * Opening a tab shows that donor in full, here: who they are, and their whole
 * workup. The point of the screen is comparing them, and comparing means not
 * having to leave the pair to see one of them.
 *
 * Two buttons end a donor's part in the pair, and they are not the same.
 * **Delink** archives this one: the tab stays, read-only, because a donor the
 * pair worked up and did not go ahead with is part of what happened. Pressed
 * on the active donor it asks the further question — whether the pair carries
 * on with somebody else, or comes apart altogether. **Swap** is only ever on
 * the active donor: it archives them and raises another of the pair's own in
 * their place, which is the difference between changing your mind and
 * finishing with somebody.
 *
 * Archived is a mode and not a status: the word the donor was given stays on
 * their tab, because being finished with by this pair says nothing about them.
 *
 * Tabs are links, not script: which one is open is in the address, so it can
 * be sent to somebody and comes back on a refresh.
 *
 * @var array<string, mixed>       $pair      The pair these donors belong to
 * @var list<array<string, mixed>> $tabs
 * @var int                        $openTab   1-based, or 0 for none
 * @var array<string, mixed>|null  $donor     The open tab's donor
 * @var array<string, mixed>       $v         That donor's fields, d-prefixed
 * @var list<array<string, mixed>> $labTests  The open tab's workup
 * @var string                     $editing   Which of their cards is open
 * @var list<array<string, mixed>> $offerable Donors the Add dialog can offer
 * @var list<array{id: string, name: string}> $mrps
 * @var list<array<string, mixed>> $coordinators Everyone registered as one
 */
$pairUrl = site_url('pairs/' . rawurlencode($pair['id']));
$tabUrl  = static fn (array $t): string => site_url('pairs/' . rawurlencode($pair['id'])) . '?donor=' . (int) $t['number'];

// Plain words, in the same weight as everything else on the tab. A colour here
// would be read as a warning, and "on hold" is not one.
$statusWord = static fn (string $status): string => UiStore::PERSON_STATUS_OPTIONS[$status] ?? $status;

// Whether the pair has the donor it is going ahead with. While it has, nobody
// else can be set active — standing that one down comes first, or swapping.
$hasActive = false;

foreach ($tabs as $t) {
    $hasActive = $hasActive || $t['isActive'];
}

// Who a swap can swap to: this pair's other donors, the archived apart.
$swapTo = array_values(array_filter(
    $tabs,
    static fn (array $t): bool => ! $t['archived'] && ! $t['isActive']
));
?>
<div class="card card--pad donor-tabs">
    <div class="card-head">
        <div>
            <h2 class="card-title">Donors</h2>
            <p class="lab-count"><?= esc(ui_plural(count($tabs), 'donor')) ?> on this pair</p>
        </div>
        <?php // With scripting off the link goes to Add Donor opened for this
              // pair, which is the commoner of the two choices; ui.js opens
              // the dialog below instead when it can. ?>
        <a class="btn-primary" href="<?= site_url('donors/new') ?>?pair=<?= esc($pair['recipientId']) ?>" data-dialog="add-donor"><?= ui_icon('plus') ?>Add donor</a>
    </div>

    <div class="tabs" role="tablist">
        <?php foreach ($tabs as $tab): ?>
            <?php $isOpen = (int) $tab['number'] === $openTab; ?>
            <a class="tab<?= $isOpen ? ' is-open' : '' ?><?= $tab['archived'] ? ' tab--delinked' : '' ?>"
               role="tab" aria-selected="<?= $isOpen ? 'true' : 'false' ?>"
               href="<?= esc($tabUrl($tab)) ?>">
                <span class="tab-number">Donor-<?= (int) $tab['number'] ?></span>
                <span class="tab-status"><?= esc($statusWord($tab['status'])) ?><?= $tab['archived'] ? ' &middot; Archived' : '' ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($donor === null): ?>
        <p class="tab-panel-empty">This pair has no donor yet. Use <strong>Add donor</strong> to put one on it.</p>
    <?php else: ?>
        <?php $tab = $tabs[$openTab - 1]; ?>
        <div class="tab-panel<?= $tab['archived'] ? ' tab-panel--delinked' : '' ?>" role="tabpanel">
            <?php if ($tab['archived']): ?>
                <?php // Why nothing on it can be pressed, said once — with the
                      // dates, because an archived tab is the history. ?>
                <p class="tab-note">
                    This pair has finished with <?= esc($donor['name']) ?>, so the tab is shown as it was and cannot be changed.
                    Linked <?= esc(UiStore::isoToDMY($tab['linkedOn'])) ?><?php if ($tab['endedOn'] !== ''): ?>, archived <?= esc(UiStore::isoToDMY($tab['endedOn'])) ?><?php endif; ?>.
                    <?php if ($tab['reason'] !== ''): ?><span class="tab-note-why"><?= esc($tab['reason']) ?></span><?php endif; ?>
                </p>
            <?php elseif ($tab['isActive']): ?>
                <p class="tab-note">This is the donor the pair is going ahead with.</p>
            <?php endif; ?>

            <div class="tab-panel-head">
                <div>
                    <div class="eyebrow">Donor-<?= (int) $tab['number'] ?> &middot; <?= esc($donor['id']) ?></div>
                    <h3 class="tab-panel-name"><?= esc($donor['name']) ?></h3>
                </div>
                <?php if (! $tab['archived']): ?>
                    <div class="header-actions">
                        <?php // No status control here. The word a donor is on
                              // is one of their own fields, asked on their
                              // Personal Information card below with the rest
                              // of them; a second control for it in the head
                              // was the same answer in two places. ?>
                        <?php if ($tab['isActive'] && $swapTo !== []): ?>
                            <?php // Only ever on the active donor: swapping a
                                  // reserve would be swapping nothing. ?>
                            <form method="post" action="<?= $pairUrl ?>/donors/<?= esc($tab['id']) ?>/swap" class="inline-form tab-swap-form">
                                <?= csrf_field() ?>
                                <label class="sr-only" for="tab-swap">Swap to</label>
                                <select id="tab-swap" name="toId" class="input">
                                    <?php foreach ($swapTo as $other): ?>
                                        <option value="<?= esc($other['id']) ?>">Donor-<?= (int) $other['number'] ?> &mdash; <?= esc($other['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-outline"
                                        data-confirm="Swap this pair to the donor chosen? <?= esc($donor['name']) ?> will be archived on this pair, with the status they have now."><?= ui_icon('shuffle14') ?>Swap</button>
                            </form>
                        <?php endif; ?>

                        <?php // The question is asked on the page it leads to. ?>
                        <a class="btn-outline btn-outline--danger" href="<?= $pairUrl ?>/donors/<?= esc($tab['id']) ?>/delink"><?= ui_icon('unlink') ?>Delink</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php // The donor's own cards, in the order the rest of the
                  // platform uses them: who they are, their workup, their
                  // notes — each opened for editing on its own. ?>
            <div class="stack-5">
                <?= view('ui/partials/donor_cards', [
                    'donor'    => $donor,
                    'tab'      => $tab,
                    'v'        => $v,
                    'labTests' => $labTests,
                    'editing'  => $editing,
                    'viewUrl'  => $tabUrl($tab),
                    'labUrl'   => $pairUrl . '/donors/' . rawurlencode($tab['id']) . '/labs',
                    'mrps'     => $mrps,
                    'coordinators' => $coordinators,
                    'hasActive' => $hasActive,
                ], ['saveData' => false]) ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<dialog id="add-donor" class="dialog">
    <div class="dialog-body">
        <form method="dialog" class="dialog-close-form">
            <button class="dialog-close" aria-label="Close">&times;</button>
        </form>
        <?= view('ui/partials/pair_donor_choice', [
            'pair'      => $pair,
            'newUrl'    => site_url('donors/new') . '?pair=' . rawurlencode($pair['recipientId']),
            'addUrl'    => $pairUrl . '/donors',
            'offerable' => $offerable,
            'hasActive' => $hasActive,
        ], ['saveData' => false]) ?>
    </div>
</dialog>
