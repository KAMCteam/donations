<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Libraries\UiStore;

/**
 * Pick the other half of a pair from those already registered.
 *
 * One form around the whole table: the relationship and crossmatch date are
 * filled in once at the top, and each row's Link button carries that person's
 * MRN, so choosing a row and submitting the pair's own details is a single
 * post with no JavaScript.
 *
 * @var string                     $personType   The side we already have
 * @var string                     $counterpart  The side being chosen
 * @var array<string, mixed>       $person
 * @var list<array<string, mixed>> $candidates   Unpaired, this programme
 * @var string                     $error
 * @var string                     $backUrl
 */
$isDonorList = $counterpart === 'donor';
$headers     = $isDonorList
    ? ['Name', 'MRN', 'Age', 'Gender', 'Blood Group', 'Type', '']
    : ['Name', 'MRN', 'Age', 'Gender', 'Blood Group', 'Urgent', ''];
?>
<div class="page">
    <div class="page-header page-header--plain">
        <a class="back-link" href="<?= esc($backUrl) ?>"><?= ui_icon('back') ?>Back to <?= esc($person['name']) ?></a>
        <div class="eyebrow">Link</div>
        <h1 class="page-title">Choose a <?= esc($counterpart) ?></h1>
        <p class="page-subtitle">
            <?= esc(ui_plural(count($candidates), 'unpaired ' . $counterpart)) ?>
            in this programme, matched to <?= esc($person['name']) ?>.
        </p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="form-error" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= current_url() ?>">
        <?= csrf_field() ?>

        <div class="card card--pad link-pair-details">
            <h2 class="card-title card-title--mb4">Pair Details</h2>
            <div class="form-grid-2">
                <div>
                    <label class="field-label" for="f-relationship">Relationship</label>
                    <input type="text" id="f-relationship" name="relationship" class="input" value="<?= esc(old('relationship', '')) ?>" placeholder="e.g. Sibling, Spouse">
                </div>
                <div>
                    <label class="field-label" for="f-crossmatch">Date of Crossmatch</label>
                    <?= view('ui/partials/date_field', ['id' => 'f-crossmatch', 'name' => 'crossmatchDate', 'value' => old('crossmatchDate', '')], ['saveData' => false]) ?>
                </div>
            </div>
        </div>

        <div class="card card--scroll">
            <?php if ($candidates === []): ?>
                <div class="empty-state">No unpaired <?= esc($counterpart) ?>s in this programme.</div>
            <?php else: ?>
                <table class="table list-table">
                    <thead>
                        <tr>
                            <?php foreach ($headers as $header): ?>
                                <th><?= esc($header) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $candidate): ?>
                            <tr>
                                <td class="cell-name"><?= esc($candidate['name']) ?></td>
                                <td class="mono"><?= esc($candidate['id']) ?></td>
                                <td><?= esc($candidate['age']) ?></td>
                                <td><?= esc($isDonorList ? $candidate['donorGender'] : $candidate['gender']) ?></td>
                                <td class="mono"><?= esc($candidate['bloodType']) ?></td>
                                <?php if ($isDonorList): ?>
                                    <td><span class="badge <?= str_starts_with($candidate['donationType'], 'living') ? 'tone-teal-soft' : 'tone-slate' ?>"><?= esc(UiStore::DONATION_TYPES[$candidate['donationType']] ?? $candidate['donationType']) ?></span></td>
                                <?php else: ?>
                                    <td><?= $candidate['urgent'] ? '<span class="badge tone-red">Urgent</span>' : '&mdash;' ?></td>
                                <?php endif; ?>
                                <td class="cell-action">
                                    <button type="submit" name="mrn" value="<?= esc($candidate['id']) ?>" class="btn-edit"><?= ui_icon('link14') ?>Link</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
