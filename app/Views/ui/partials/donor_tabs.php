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
 * **Delink** ends a donor's part in the pair, and asks before it does
 * anything, in a dialog over the pair rather than on a screen of its own: the
 * pair is what somebody is deciding about, so taking it off the display to ask
 * was taking away the answer.
 *
 * It archives this donor: the tab stays, read-only, because a donor the pair
 * worked up and did not go ahead with is part of what happened. Pressed on the
 * active donor it asks the further question — who takes their place, or
 * whether the pair comes apart altogether.
 *
 * Raising one of the pair's own reserves is not a button of its own any more:
 * the word a donor is on is a field on their card, so standing one down and
 * raising another is two edits, in the place every other fact about them is
 * edited.
 *
 * Archived is a mode and not a status: the word the donor was given stays on
 * their tab, because being finished with by this pair says nothing about them.
 *
 * Tabs are links, not script: which one is open is in the address, so it can
 * be sent to somebody and comes back on a refresh.
 *
 * The section outlives the pair. When a pair comes apart the recipient goes
 * back to the waiting list, and this same section goes with them onto their
 * own record: every donor they were ever linked with, every tab archived,
 * every workup still readable. A pair ending is not the record ending, so
 * nothing here disappears — it only stops being editable. That is what
 * `$tabsArchiveOf` says: whose record the section is being read on, when it
 * is not being read on a pair.
 *
 * @var array<string, mixed>|null  $pair      The pair these donors belong to,
 *                                            null on a recipient's own record
 * @var string                     $tabsArchiveOf  That recipient's MRN, '' on a pair
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
// Read on a recipient's own record, the pair is gone: the tabs hang off the
// record instead, and there is nothing to press on any of them.
$isArchive = ($tabsArchiveOf ?? '') !== '';
$home      = $isArchive
    ? site_url('recipients/' . rawurlencode((string) $tabsArchiveOf))
    : site_url('pairs/' . rawurlencode($pair['id']));
$tabUrl    = static fn (array $t): string => $home . '?donor=' . (int) $t['number'];
$offerable = $isArchive ? [] : ($offerable ?? []);

// Plain words, in the same weight as everything else on the tab. A colour here
// would be read as a warning, and "on hold" is not one.
$statusWord = static fn (string $status): string => UiStore::PERSON_STATUS_OPTIONS[$status] ?? $status;

// Whether the pair has the donor it is going ahead with. While it has, nobody
// else can be set active — standing that one down comes first.
$hasActive = false;

foreach ($tabs as $t) {
    $hasActive = $hasActive || $t['isActive'];
}

?>
<div class="card card--pad donor-tabs<?= $isArchive ? ' donor-tabs--archive' : '' ?>">
    <div class="card-head">
        <div>
            <h2 class="card-title">Donors</h2>
            <p class="lab-count"><?= esc(ui_plural(count($tabs), 'donor')) ?> <?= $isArchive ? 'previously linked' : 'on this pair' ?></p>
        </div>
        <?php // Nobody is added to a pair that is not there any more: on a
              // recipient's own record this is the history, and the way to
              // give them another donor is Link with Donor in the header. ?>
        <?php if (! $isArchive): ?>
            <?php // With scripting off the link goes to Add Donor opened for
                  // this pair, which is the commoner of the two choices;
                  // ui.js opens the dialog below instead when it can. ?>
            <a class="btn-primary" href="<?= site_url('donors/new') ?>?pair=<?= esc($pair['recipientId']) ?>" data-dialog="add-donor"><?= ui_icon('plus') ?>Add donor</a>
        <?php endif; ?>
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
        <?php if ($isArchive): ?>
            <p class="tab-panel-empty">This donor's own record is no longer on the register, so there is nothing left to show on the tab.</p>
        <?php else: ?>
            <p class="tab-panel-empty">This pair has no donor yet. Use <strong>Add donor</strong> to put one on it.</p>
        <?php endif; ?>
    <?php else: ?>
        <?php $tab = $tabs[$openTab - 1]; ?>
        <div class="tab-panel<?= $tab['archived'] ? ' tab-panel--delinked' : '' ?>" role="tabpanel">
            <?php if ($tab['archived']): ?>
                <?php // Why nothing on it can be pressed, said once — with the
                      // dates, because an archived tab is the history. ?>
                <p class="tab-note">
                    <?php if ($isArchive): ?>
                        This recipient is back on the waiting list, so <?= esc($donor['name']) ?>'s tab is shown as it was and cannot be changed.
                    <?php else: ?>
                        This pair has finished with <?= esc($donor['name']) ?>, so the tab is shown as it was and cannot be changed.
                    <?php endif; ?>
                    Linked <?= esc(UiStore::isoToDMY($tab['linkedOn'])) ?><?php if ($tab['endedOn'] !== ''): ?>, archived <?= esc(UiStore::isoToDMY($tab['endedOn'])) ?><?php endif; ?>.
                    <?php if ($tab['reason'] !== ''): ?><span class="tab-note-why"><?= esc($tab['reason']) ?></span><?php endif; ?>
                </p>
            <?php elseif ($tab['isActive']): ?>
                <p class="tab-note">This is the donor the pair is going ahead with.</p>
            <?php endif; ?>

            <?php $delinkId = 'confirm-delink-' . (int) $tab['id']; ?>
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
                        <?php // The question is asked over the pair, in the
                              // dialog below, rather than on a screen that
                              // takes the pair off the display to ask it. ?>
                        <a class="btn-outline btn-outline--danger" href="#<?= esc($delinkId) ?>" data-dialog="<?= esc($delinkId) ?>"><?= ui_icon('unlink') ?>Delink</a>
                    </div>

                    <?php // Delinking a donor the pair is holding in reserve
                          // archives that donor and nothing else. Delinking
                          // the one it is going ahead with can mean either of
                          // two things, and the difference is the whole pair,
                          // so that one is asked which. ?>
                    <?php
                    // The three answers. Two of them keep the pair by putting
                    // somebody in the place being vacated — one entered now,
                    // one already on the register — and the third ends it.
                    // Choosing from the register is only an answer while there
                    // is somebody free to choose.
                    $delinkChoices = [];

                    if ($tab['isActive']) {
                        $delinkChoices[] = [
                            'value' => 'new',
                            'label' => 'Link with a new donor',
                            'hint'  => 'Opens Add Donor for this pair. They join it as the donor it is going ahead with.',
                        ];

                        if ($offerable !== []) {
                            $delinkChoices[] = [
                                'value'  => 'existing',
                                'label'  => 'Link with an existing donor',
                                'hint'   => 'Choose from the donors on the register who are not in a pair. They are set to "Active" on this pair.',
                                'select' => [
                                    'name'    => 'donorMrn',
                                    'label'   => 'Donor to link',
                                    'options' => array_map(static fn (array $free): array => [
                                        'value' => (string) $free['id'],
                                        'label' => $free['name'] . ' (' . $free['id'] . ') — ' . $free['bloodType']
                                            . (($free['age'] ?? 0) > 0 ? ', ' . $free['age'] : ''),
                                    ], $offerable),
                                ],
                            ];
                        }

                        $delinkChoices[] = [
                            'value' => 'dissolve',
                            'label' => 'Take the pair apart',
                            'hint'  => 'The recipient goes back to the waitlist and the Donor back to the Donors list.',
                        ];
                    }
                    ?>
                    <?= view('ui/partials/confirm_dialog', [
                        'id'      => $delinkId,
                        'title'   => 'Delink ' . $donor['name'] . '?',
                        'detail'  => $tab['isActive']
                            ? $donor['name'] . ' is this pair\'s active donor, so taking them off it takes the pair '
                                . 'apart — unless another donor is linked in their place. Their tab is archived '
                                . 'either way: it stays here, read-only, with the status they have now.'
                            : 'This pair has finished with ' . $donor['name'] . '. Their tab stays here, read-only, '
                                . 'with the status they have now — and their own record is untouched, so they go '
                                . 'back to the register and can be linked again from their own screen.',
                        'action'  => $home . '/donors/' . rawurlencode((string) $tab['id']) . '/delink',
                        'confirmVerb' => 'Delink donor',
                        'confirmIcon' => 'unlink',
                        'confirmChoices' => $delinkChoices,
                    ], ['saveData' => false]) ?>
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
                    'labUrl'   => $home . '/donors/' . rawurlencode($tab['id']) . '/labs',
                    'mrps'     => $mrps,
                    'coordinators' => $coordinators,
                    'hasActive' => $hasActive,
                ], ['saveData' => false]) ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (! $isArchive): ?>
<dialog id="add-donor" class="dialog">
    <div class="dialog-body">
        <form method="dialog" class="dialog-close-form">
            <button class="dialog-close" aria-label="Close">&times;</button>
        </form>
        <?= view('ui/partials/pair_donor_choice', [
            'pair'      => $pair,
            'newUrl'    => site_url('donors/new') . '?pair=' . rawurlencode($pair['recipientId']),
            'addUrl'    => $home . '/donors',
            'offerable' => $offerable,
            'hasActive' => $hasActive,
        ], ['saveData' => false]) ?>
    </div>
</dialog>
<?php endif; ?>
