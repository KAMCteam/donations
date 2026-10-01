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
 * A pair does not end the list. The others stay on the screen, and Pair up
 * stays on them, because the one thing a list of candidates is for is changing
 * your mind — pressing it on another tab switches the pair to that donor. The
 * only tabs it leaves are the ones delinked by hand: that was a decision about
 * the donor, where being passed over was a decision about somebody else.
 *
 * Under the tabs is the archive, which is where switching shows: every pair
 * this recipient has had, who it was with, when it began and ended, and what
 * ended it.
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
 * @var list<array<string, mixed>> $history  Every pair this recipient has had
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
            <?php if ($tab['delinked'] && $tab['switchable']): ?>
                <?php // Passed over rather than turned down: nothing was
                      // decided about this donor, so the way back is open. ?>
                <p class="tab-note">This potential donor was set aside when another was paired. Their details are shown as they were; pairing them up again switches the pair back to them.</p>
            <?php elseif ($tab['delinked']): ?>
                <?php // Why nothing on it can be pressed, said once. ?>
                <p class="tab-note">This potential donor was delinked, so the tab is shown as it was and cannot be changed.</p>
            <?php elseif ($tab['pairId'] !== ''): ?>
                <p class="tab-note">This is the pair.
                    <a class="stat-link" href="<?= site_url('pairs/' . rawurlencode($tab['pairId'])) ?>">Open the pair</a></p>
            <?php endif; ?>

            <?php
            // Somebody else is the pair: pressing Pair up here moves it, which
            // the question says in so many words before it happens.
            $paired = null;

            foreach ($tabs as $other) {
                if ($other['pairId'] !== '') {
                    $paired = $other;
                }
            }

            $switching = $paired !== null && $paired['number'] !== $tab['number'];
            ?>
            <div class="tab-panel-head">
                <div>
                    <div class="eyebrow">donor-<?= (int) $tab['number'] ?> &middot; <?= esc($donor['id']) ?></div>
                    <h3 class="tab-panel-name"><?= esc($donor['name']) ?></h3>
                </div>
                <?php if ($tab['switchable']): ?>
                    <div class="header-actions">
                        <?php if (! $tab['delinked']): ?>
                            <?php // Active or On Hold, from the tab itself.
                                  // Setting aside has its own button, because
                                  // it cannot be undone by choosing again. ?>
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
                        <?php endif; ?>

                        <?php if ($tab['pairId'] === ''): ?>
                            <?php // The decision the list is for — and still for
                                  // after one has been made, because changing
                                  // your mind is what a list of candidates is. ?>
                            <form method="post" action="<?= $recipientUrl ?>/donors/<?= esc($tab['id']) ?>/pair" class="inline-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-primary"
                                        data-confirm="<?= $switching
                                            ? 'Switch the pair from ' . esc($paired['name']) . ' to ' . esc($donor['name']) . '? The pair with ' . esc($paired['name']) . ' will be closed and kept in the archive.'
                                            : 'Pair ' . esc($donor['name']) . ' with this recipient? Every other potential donor will be set to Declined.' ?>"><?= ui_icon('link14') ?><?= $switching ? 'Switch to this donor' : 'Pair up' ?></button>
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

    <?php // The archive. Closed on arrival, because it is history rather than
          // the state of the case — and open in one press, because when a pair
          // has been switched, who it used to be is the first thing asked. ?>
    <?php if ($history !== []): ?>
        <details class="pair-archive">
            <summary class="pair-archive-head">
                <span class="pair-archive-title">Pairing history</span>
                <span class="pair-archive-count"><?= esc(ui_plural(count($history), 'pair')) ?></span>
            </summary>
            <ol class="pair-archive-list">
                <?php foreach ($history as $entry): ?>
                    <li class="pair-archive-item<?= $entry['open'] ? ' is-open' : '' ?>">
                        <div class="pair-archive-who">
                            <a href="<?= site_url('pairs/' . rawurlencode($entry['id'])) ?>"><?= esc($entry['donorName'] !== '' ? $entry['donorName'] : 'MRN ' . $entry['donorId']) ?></a>
                            <span class="pair-archive-mrn mono"><?= esc($entry['donorId']) ?></span>
                            <span class="pair-archive-state"><?= $entry['open'] ? 'Current pair' : 'Closed' ?></span>
                        </div>
                        <div class="pair-archive-when">
                            Paired <?= esc(UiStore::isoToDMY($entry['pairedOn'])) ?><?php if ($entry['endedOn'] !== ''): ?> &middot; ended <?= esc(UiStore::isoToDMY($entry['endedOn'])) ?><?php endif; ?>
                        </div>
                        <?php if ($entry['reason'] !== ''): ?>
                            <div class="pair-archive-why"><?= esc($entry['reason']) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </details>
    <?php endif; ?>
</div>
