<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;
use App\Models\MrpModel;

/**
 * What one question asked of every register came back with.
 *
 * Grouped by what kind of thing each answer is, because that is what the
 * person searching has to decide next: this is a recipient, so it is the
 * waitlist; this is a pair, so it is the pair's screen. A group with nothing
 * in it is left out rather than shown empty — four headings over one result
 * would bury it.
 *
 * Every row is a link and nothing else. Searching is for finding; the screen
 * that owns the record is where anything is done about it.
 *
 * @var string                     $query
 * @var array<string, list<array<string, mixed>>> $results
 * @var int                        $total
 */
?>
<div class="page">
    <div class="page-header page-header--plain">
        <div class="eyebrow">Search</div>
        <h1 class="page-title"><?= $query === '' ? 'Search' : esc($query) ?></h1>
        <p class="page-subtitle">
            <?php if ($query === ''): ?>
                Type an MRN or a name into the bar above to search every register at once.
            <?php else: ?>
                <?= esc(ui_plural($total, 'result')) ?> across the recipients, donors, pairs and users on this programme.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($query !== '' && $total === 0): ?>
        <div class="card card--pad">
            <div class="empty-state">Nothing on this programme matches &ldquo;<?= esc($query) ?>&rdquo;.</div>
        </div>
    <?php endif; ?>

    <?php if ($results['recipients'] !== []): ?>
        <div class="search-group">
            <h2 class="search-group-title">Recipients <span><?= count($results['recipients']) ?></span></h2>
            <div class="card card--clip">
                <?php foreach ($results['recipients'] as $person): ?>
                    <a class="search-hit" href="<?= site_url('recipients/' . rawurlencode($person['id'])) ?>">
                        <span class="search-hit-name"><?= esc($person['name']) ?></span>
                        <span class="search-hit-meta">
                            <span class="mono"><?= esc($person['id']) ?></span>
                            &middot; <span class="mono"><?= esc($person['bloodType']) ?></span>
                            &middot; <?= esc(UiStore::STATUS_OPTIONS[$person['status']] ?? $person['status']) ?>
                            <?php if ($person['urgent']): ?>&middot; Urgent<?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($results['donors'] !== []): ?>
        <div class="search-group">
            <h2 class="search-group-title">Donors <span><?= count($results['donors']) ?></span></h2>
            <div class="card card--clip">
                <?php foreach ($results['donors'] as $person): ?>
                    <a class="search-hit" href="<?= site_url('donors/' . rawurlencode($person['id'])) ?>">
                        <span class="search-hit-name"><?= esc($person['name']) ?></span>
                        <span class="search-hit-meta">
                            <span class="mono"><?= esc($person['id']) ?></span>
                            &middot; <span class="mono"><?= esc($person['bloodType']) ?></span>
                            &middot; <?= esc(UiStore::DONATION_TYPES[$person['donationType']] ?? $person['donationType']) ?>
                            &middot; <?= esc($person['donorStatus']) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($results['pairs'] !== []): ?>
        <div class="search-group">
            <h2 class="search-group-title">Pairs <span><?= count($results['pairs']) ?></span></h2>
            <div class="card card--clip">
                <?php foreach ($results['pairs'] as $pair): ?>
                    <a class="search-hit" href="<?= site_url('pairs/' . rawurlencode($pair['id'])) ?>">
                        <span class="search-hit-name">Pair <?= esc($pair['id']) ?></span>
                        <span class="search-hit-meta">
                            <span class="mono"><?= esc($pair['recipientId']) ?></span>
                            &rarr; <span class="mono"><?= esc($pair['donorId']) ?></span>
                            &middot; <?= esc(UiStore::STATUS_OPTIONS[$pair['status']] ?? $pair['status']) ?>
                            <?php if ($pair['relationship'] !== ''): ?>&middot; <?= esc($pair['relationship']) ?><?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($results['users'] !== []): ?>
        <div class="search-group">
            <h2 class="search-group-title">Users <span><?= count($results['users']) ?></span></h2>
            <div class="card card--clip">
                <?php foreach ($results['users'] as $user): ?>
                    <?php // The MRP screen is where they are changed, so that is
                          // where the row goes — to their own row on it. ?>
                    <a class="search-hit" href="<?= site_url('mrp') ?>?edit=<?= esc($user['id']) ?>">
                        <span class="search-hit-name"><?= esc($user['name']) ?></span>
                        <span class="search-hit-meta">
                            <span class="mono"><?= esc($user['code']) ?></span>
                            &middot; <?= esc(MrpModel::KINDS[$user['kind']] ?? $user['kind']) ?>
                            &middot; <?= $user['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
