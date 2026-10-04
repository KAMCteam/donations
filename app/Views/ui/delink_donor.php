<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

/**
 * "Are you sure?" for taking a donor off a pair.
 *
 * Its own page rather than `confirm_delete`, because the active donor is asked
 * two questions and not one. Delinking a donor the pair was holding in reserve
 * archives that donor and nothing else. Delinking the one it was going ahead
 * with can mean either of two different things, and the difference is the
 * whole pair:
 *
 *   **Carry on without them** keeps the pair. The tab is archived, the other
 *   donors stay where they are, and the pair goes on with whoever is raised to
 *   active next.
 *
 *   **Take the pair apart** ends it. Every link closes, the recipient goes
 *   back to the waiting list, every donor back to the register — and this
 *   screen stays, every tab on it archived, as the record of what happened.
 *
 * Radios and a submit, so the question works with scripting off, and the
 * safer answer is the one already chosen.
 *
 * @var array<string, mixed> $pair
 * @var array<string, mixed> $tab      The donor being taken off
 * @var bool                 $isActive Whether they are the pair's own
 * @var string               $action   Where the POST goes
 * @var string               $backUrl  The tab it came from
 */
?>
<div class="page">
    <div class="page-header page-header--plain">
        <a class="back-link" href="<?= esc($backUrl) ?>"><?= ui_icon('back') ?>Back</a>
        <div class="eyebrow">Delink</div>
        <h1 class="page-title">Delink <?= esc($tab['name']) ?>?</h1>
    </div>

    <div class="card card--pad">
        <form method="post" action="<?= esc($action) ?>" class="confirm">
            <?= csrf_field() ?>
            <?php if ($isActive): ?>
                <p class="confirm-detail">
                    <?= esc($tab['name']) ?> is the donor this pair is going ahead with. Taking them off it
                    archives their tab either way &mdash; it stays here, read-only, with the status they have now.
                    What happens to the pair is the question.
                </p>
                <div class="confirm-choices">
                    <label class="confirm-choice">
                        <input type="radio" name="outcome" value="carry-on" checked>
                        <span>
                            <span class="confirm-choice-label">Carry on with another donor</span>
                            <span class="confirm-choice-hint">The pair stays. Its other donors keep their place, and whichever is set to Active next is the one it goes ahead with.</span>
                        </span>
                    </label>
                    <label class="confirm-choice">
                        <input type="radio" name="outcome" value="dissolve">
                        <span>
                            <span class="confirm-choice-label">Take the pair apart</span>
                            <span class="confirm-choice-hint">Every donor on it is archived, <?= esc($pair['recipientName']) ?> goes back to the waiting list and the donors back to the register. This screen stays as the record of it.</span>
                        </span>
                    </label>
                </div>
            <?php else: ?>
                <p class="confirm-detail">
                    This pair has finished with <?= esc($tab['name']) ?>. Their tab stays here, read-only,
                    with the status they have now &mdash; and their own record is untouched, so they go back
                    to the register and can be linked again from their own screen.
                </p>
                <input type="hidden" name="outcome" value="carry-on">
            <?php endif; ?>
            <div class="confirm-actions">
                <a class="btn-outline" href="<?= esc($backUrl) ?>">Cancel</a>
                <button type="submit" class="btn-danger"><?= ui_icon('unlink') ?>Delink donor</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
