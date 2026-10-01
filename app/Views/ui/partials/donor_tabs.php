<?php

use App\Libraries\UiStore;

/**
 * A recipient's potential donors, one tab each.
 *
 * A recipient is worked up against several donors at once — they are collected,
 * tested, and set aside one by one — and only at the end is one of them the
 * pair. None of these is a pair yet. Each is somebody being considered, and
 * the tab carries how that is going.
 *
 * Opening a tab shows that donor in full, here: who they are, and their whole
 * workup. The point of the screen is comparing them, and comparing means not
 * having to leave the record to see one of them.
 *
 * Two buttons end a candidacy, and they are opposites. **Delink** sets this
 * one aside: the tab stays, read-only, because a donor who was looked at and
 * declined is part of what happened. **Pair up** chooses this one: the pair is
 * made, every other candidate is set aside with it, and the pair's own screen
 * opens. One recipient, one donor, one pair.
 *
 * Tabs are links, not script: which one is open is in the address, so it can
 * be sent to somebody and comes back on a refresh.
 *
 * @var string                     $mrn      The recipient's
 * @var list<array<string, mixed>> $tabs
 * @var int                        $openTab  1-based, or 0 for none
 * @var array<string, mixed>|null  $donor    The open tab's donor
 * @var array<string, mixed>       $v        That donor's fields, d-prefixed
 * @var list<array<string, mixed>> $labTests The open tab's workup
 * @var string                     $editing  Which of their cards is open
 * @var list<array{id: string, name: string}> $mrps
 * @var callable                   $ageNote
 */
$recipientUrl = site_url('recipients/' . rawurlencode((string) $mrn));
$dash         = '—';

// Plain words, in the same weight as everything else on the tab. A colour here
// would be read as a warning, and "on hold" is not one.
$statusWord = static fn (string $status): string => UiStore::STATUS_OPTIONS[$status] ?? $status;
?>
<div class="card card--pad donor-tabs">
    <div class="card-head">
        <h2 class="card-title">Potential Donors</h2>
        <p class="lab-count"><?= esc(ui_plural(count($tabs), 'potential donor')) ?></p>
    </div>

    <div class="tabs" role="tablist">
        <?php foreach ($tabs as $tab): ?>
            <?php $isOpen = (int) $tab['number'] === $openTab; ?>
            <a class="tab<?= $isOpen ? ' is-open' : '' ?><?= $tab['delinked'] ? ' tab--delinked' : '' ?>"
               role="tab" aria-selected="<?= $isOpen ? 'true' : 'false' ?>"
               href="<?= $recipientUrl ?>?donor=<?= (int) $tab['number'] ?>">
                <span class="tab-number">donor-<?= (int) $tab['number'] ?></span>
                <span class="tab-status"><?= esc($statusWord($tab['status'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($donor === null): ?>
        <p class="tab-panel-empty">Choose a potential donor above.</p>
    <?php else: ?>
        <?php $tab = $tabs[$openTab - 1]; ?>
        <div class="tab-panel<?= $tab['delinked'] ? ' tab-panel--delinked' : '' ?>" role="tabpanel">
            <?php if ($tab['delinked']): ?>
                <?php // Why nothing on it can be pressed, said once. ?>
                <p class="tab-note">This potential donor was set aside, so the tab is shown as it was and cannot be changed.</p>
            <?php elseif ($tab['pairId'] !== ''): ?>
                <p class="tab-note">This is the pair.
                    <a class="stat-link" href="<?= site_url('pairs/' . rawurlencode($tab['pairId'])) ?>">Open the pair</a></p>
            <?php endif; ?>

            <div class="tab-panel-head">
                <div>
                    <div class="eyebrow">donor-<?= (int) $tab['number'] ?> &middot; <?= esc($donor['id']) ?></div>
                    <h3 class="tab-panel-name"><?= esc($donor['name']) ?></h3>
                </div>
                <?php if (! $tab['delinked']): ?>
                    <div class="header-actions">
                        <?php // Active or On Hold, from the tab itself. Setting
                              // aside has its own button, because it cannot be
                              // undone by choosing again. ?>
                        <form method="post" action="<?= $recipientUrl ?>/donors/<?= esc($tab['id']) ?>/status" class="inline-form tab-status-form">
                            <?= csrf_field() ?>
                            <label class="sr-only" for="tab-status">Status</label>
                            <select id="tab-status" name="status" class="input" data-auto-submit>
                                <?php foreach (['active' => 'Active', 'on_hold' => 'On Hold'] as $value => $label): ?>
                                    <option value="<?= esc($value) ?>"<?= $tab['status'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-outline">Set</button>
                        </form>

                        <?php // The question is asked on the page it leads to. ?>
                        <a class="btn-outline btn-outline--danger" href="<?= $recipientUrl ?>/donors/<?= esc($tab['id']) ?>/delink"><?= ui_icon('unlink') ?>Delink</a>

                        <?php if ($tab['pairId'] === ''): ?>
                            <?php // The decision the list was leading to: one of
                                  // them is the donor, the rest are set aside,
                                  // and the pair's own screen opens. ?>
                            <form method="post" action="<?= $recipientUrl ?>/donors/<?= esc($tab['id']) ?>/pair" class="inline-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-primary"
                                        data-confirm="Pair <?= esc($donor['name']) ?> with this recipient? Every other potential donor will be set to Declined."><?= ui_icon('link14') ?>Pair up</button>
                            </form>
                        <?php endif; ?>
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
                    'viewUrl'  => $recipientUrl . '?donor=' . (int) $tab['number'],
                    'labUrl'   => $recipientUrl . '/donors/' . rawurlencode($tab['id']) . '/labs',
                    'mrps'     => $mrps,
                    'ageNote'  => $ageNote,
                ], ['saveData' => false]) ?>
            </div>
        </div>
    <?php endif; ?>
</div>
