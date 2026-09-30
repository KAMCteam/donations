<?php

use App\Libraries\UiStore;

/**
 * A recipient's donors, one tab each.
 *
 * A recipient may be linked with several donors — they are looked at one
 * after another, and sometimes together — so the record carries a row of
 * tabs rather than a single "Linked with". Each is numbered in the order the
 * link was made and carries that link's own status, which is what the tab is
 * really about: is this donor active, on hold, or declined.
 *
 * Opening one shows that donor's record. A link that has been undone is
 * greyed and read-only: the donor was considered and set aside, which is part
 * of what happened and is not taken off the record.
 *
 * Tabs are links, not script: which one is open is in the address, so it can
 * be sent to somebody and comes back on a refresh.
 *
 * @var string                     $mrn      The recipient's
 * @var list<array<string, mixed>> $tabs
 * @var int                        $openTab  1-based, or 0 for none
 * @var array<string, mixed>|null  $donor    The open tab's donor
 */
$recipientUrl = site_url('recipients/' . rawurlencode((string) $mrn));
$dash         = '—';
?>
<div class="card card--pad donor-tabs">
    <div class="card-head">
        <h2 class="card-title">Donors</h2>
        <p class="lab-count"><?= esc(ui_plural(count($tabs), 'linked donor')) ?></p>
    </div>

    <div class="tabs" role="tablist">
        <?php foreach ($tabs as $tab): ?>
            <?php $isOpen = (int) $tab['number'] === $openTab; ?>
            <a class="tab<?= $isOpen ? ' is-open' : '' ?><?= $tab['delinked'] ? ' tab--delinked' : '' ?>"
               role="tab" aria-selected="<?= $isOpen ? 'true' : 'false' ?>"
               href="<?= $recipientUrl ?>?donor=<?= (int) $tab['number'] ?>">
                <span class="tab-number"><?= (int) $tab['number'] ?></span>
                <span class="tab-status <?= ui_tone('status', $tab['status']) ?>"><?= esc(UiStore::STATUS_OPTIONS[$tab['status']] ?? $tab['status']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($donor === null): ?>
        <p class="tab-panel-empty">Choose a donor above.</p>
    <?php else: ?>
        <?php $tab = $tabs[$openTab - 1]; ?>
        <div class="tab-panel<?= $tab['delinked'] ? ' tab-panel--delinked' : '' ?>" role="tabpanel">
            <?php if ($tab['delinked']): ?>
                <?php // Why it is grey, said once, rather than left to the colour. ?>
                <p class="tab-note">This link was undone, so it is shown as it was and cannot be changed.
                    <?= $tab['closedReason'] !== '' ? esc($tab['closedReason']) : '' ?></p>
            <?php endif; ?>

            <div class="tab-panel-head">
                <div>
                    <div class="eyebrow"><?= esc($donor['id']) ?></div>
                    <h3 class="tab-panel-name"><?= esc($donor['name']) ?></h3>
                </div>
                <div class="header-actions">
                    <a class="btn-outline" href="<?= site_url('donors/' . rawurlencode($donor['id'])) ?>">Open donor record</a>
                    <?php if (! $tab['delinked']): ?>
                        <?php // The question is asked on the page it leads to. ?>
                        <a class="btn-outline btn-outline--danger" href="<?= $recipientUrl ?>/donors/<?= esc($tab['pairId']) ?>/delink"><?= ui_icon('unlink') ?>Delink</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $fields = [
                ['Donor MRN', $donor['id'], true],
                ['Donor Name', $donor['name'], false],
                ['Age', (string) ($donor['age'] ?? ''), false],
                ['Gender', (string) ($donor['donorGender'] ?? ''), false],
                ['Blood Group', (string) ($donor['bloodType'] ?? ''), true],
                ['Phone Number', (string) ($donor['phone'] ?? ''), true],
                ['City', (string) ($donor['address'] ?? ''), false],
                ['Donor Type', UiStore::DONATION_TYPES[$donor['donationType'] ?? ''] ?? '', false],
                ['Relationship', (string) $tab['relationship'], false],
                ['Donor Status', (string) ($donor['donorStatus'] ?? ''), false],
            ];
            ?>
            <dl class="tab-fields">
                <?php foreach ($fields as [$label, $value, $mono]): ?>
                    <div class="tab-field">
                        <dt class="field-label"><?= esc($label) ?></dt>
                        <dd class="tab-value<?= $mono ? ' tab-value--mono' : '' ?>"><?= esc($value !== '' ? $value : $dash) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <?php // The workup, read-only: the donor's own screen is where it
                  // is filled in, and this is a view of who they are. ?>
            <p class="tab-labs">
                Workup:
                <?php $progress = UiStore::labProgress($donor['labTests'] ?? []); ?>
                <strong><?= $progress['done'] ?> of <?= $progress['total'] ?></strong> completed
                &middot; <a class="stat-link" href="<?= site_url('donors/' . rawurlencode($donor['id'])) ?>?edit=labs">Open the workup</a>
            </p>
        </div>
    <?php endif; ?>
</div>
