<?= $this->extend('ui/layout') ?>

<?= $this->section('content') ?>
<?php

use App\Models\MrpModel;

/**
 * The one screen that makes users: the physicians and coordinators a record
 * can be assigned to. Was `js/pages/add-mrp.js`.
 *
 * A user is not invented here. They already exist in the hospital's directory,
 * and the right way to add one is to look them up by the ID they already have
 * — which is what **Search** beside the ID is for. The directory is not
 * connected yet, so the button comes back saying so and the fields stay open
 * to be typed; when it is wired, what changes is what fills them, not this
 * screen.
 *
 * The directory will return a name and an ID. It will not return a password
 * into this database: a copied credential is a credential in two places, and
 * the point of looking somebody up in the directory is that the directory is
 * where their sign-in is checked.
 *
 * @var string                     $saved    What just happened, if anything
 * @var string                     $error
 * @var string                     $editing  The row open for editing, '' for none
 * @var array<string, mixed>       $lookup   What the last search came back with
 * @var list<array<string, mixed>> $users
 */
$chosenKind = (string) ($lookup['kind'] ?? MrpModel::DOCTOR);
$chosenKind = isset(MrpModel::KINDS[$chosenKind]) ? $chosenKind : MrpModel::DOCTOR;
?>
<div class="page">
    <div class="page-header page-header--plain">
        <div class="eyebrow">Users</div>
        <h1 class="page-title">Add MRP</h1>
        <p class="page-subtitle">Register a physician or a coordinator from the hospital directory.</p>
    </div>

    <div class="mrp-wrap">
        <?php if ($saved !== ''): ?>
            <div class="mrp-success"><?= ui_icon('check') ?><?= esc($saved) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= esc($error) ?></div>
        <?php endif; ?>

        <?php // One form, two buttons: Search sends it to the directory and
              // Add user registers it. `formaction` is what makes that one
              // form rather than two that have to copy each other's fields,
              // and it needs no scripting — so pressing Enter in the ID box
              // searches, which is what somebody typing an ID means. ?>
        <form class="card card--pad stack-5 mrp-form" method="post" action="<?= site_url('mrp') ?>" novalidate>
            <?= csrf_field() ?>

            <div>
                <label class="mrp-label" for="mrp-id">MRP ID</label>
                <div class="mrp-search-row">
                    <input type="text" id="mrp-id" name="id" class="input input--mono"
                           value="<?= esc((string) ($lookup['code'] ?? '')) ?>" placeholder="e.g. MRP-004">
                    <button type="submit" class="btn-outline" formaction="<?= site_url('mrp/lookup') ?>">Search</button>
                </div>
                <p class="mrp-hint">Looks the ID up in the hospital directory and fills in the name below.</p>
            </div>

            <?php if ($lookup !== []): ?>
                <?php // What the search came back with. The directory is not
                      // connected yet, so what it comes back with is that. ?>
                <div class="mrp-lookup<?= ($lookup['found'] ?? false) ? ' is-found' : '' ?>">
                    <div class="mrp-lookup-head">Directory lookup</div>
                    <p class="mrp-lookup-note"><?= esc((string) ($lookup['reason'] ?? '')) ?></p>
                    <dl class="mrp-lookup-fields">
                        <?php foreach ([
                            'Full name'         => (string) ($lookup['name'] ?? ''),
                            'MRP ID'            => (string) ($lookup['code'] ?? ''),
                            'Directory account' => (string) ($lookup['account'] ?? ''),
                        ] as $label => $value): ?>
                            <div>
                                <dt><?= esc($label) ?></dt>
                                <dd><?= esc($value !== '' ? $value : '—') ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                    <p class="mrp-lookup-foot">Sign-in is checked against the directory, so no password is kept here.</p>
                </div>
            <?php endif; ?>

            <div>
                <?php // Which kind of user this is, asked before the name,
                      // because it is what the name is being entered as. ?>
                <span class="mrp-label">This user is</span>
                <div class="mrp-kinds">
                    <?php foreach (MrpModel::KINDS as $value => $label): ?>
                        <label class="mrp-kind">
                            <input type="radio" name="kind" value="<?= esc($value) ?>"<?= $chosenKind === $value ? ' checked' : '' ?>>
                            <span><?= esc($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label class="mrp-label" for="mrp-name">Full name</label>
                <input type="text" id="mrp-name" name="name" class="input"
                       value="<?= esc((string) ($lookup['name'] ?? '')) ?>" placeholder="e.g. Dr. Amira Hassan">
            </div>

            <button type="submit" class="mrp-submit">Add user</button>
        </form>

        <?php if ($users !== []): ?>
            <div class="mrp-list-block">
                <h2 class="mrp-list-title">Registered MRPs</h2>
                <div class="card card--scroll">
                    <table class="table list-table mrp-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>MRP ID</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <?php $open = $editing === $user['id']; ?>
                                <tr class="<?= $user['active'] ? '' : 'is-inactive' ?>">
                                    <?php if ($open): ?>
                                        <?php // The row becomes the form, as a
                                              // record's card does: editing is
                                              // where the thing being edited is. ?>
                                        <td colspan="5" class="mrp-edit-cell">
                                            <form class="mrp-edit" method="post" action="<?= site_url('mrp/' . rawurlencode($user['id'])) ?>" novalidate>
                                                <?= csrf_field() ?>
                                                <div>
                                                    <label class="field-label" for="edit-name-<?= esc($user['id']) ?>">Name</label>
                                                    <input type="text" id="edit-name-<?= esc($user['id']) ?>" name="name" class="input" value="<?= esc($user['name']) ?>">
                                                </div>
                                                <div>
                                                    <label class="field-label" for="edit-code-<?= esc($user['id']) ?>">MRP ID</label>
                                                    <input type="text" id="edit-code-<?= esc($user['id']) ?>" name="id" class="input input--mono" value="<?= esc($user['code']) ?>">
                                                </div>
                                                <div>
                                                    <label class="field-label" for="edit-kind-<?= esc($user['id']) ?>">Type</label>
                                                    <select id="edit-kind-<?= esc($user['id']) ?>" name="kind" class="input">
                                                        <?php foreach (MrpModel::KINDS as $value => $label): ?>
                                                            <option value="<?= esc($value) ?>"<?= $user['kind'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="mrp-edit-actions">
                                                    <a class="btn-outline" href="<?= site_url('mrp') ?>">Cancel</a>
                                                    <button type="submit" class="btn-save">Save</button>
                                                </div>
                                            </form>
                                        </td>
                                    <?php else: ?>
                                        <td class="cell-name"><?= esc($user['name']) ?></td>
                                        <td class="mono"><?= esc($user['code']) ?></td>
                                        <td><span class="badge <?= $user['kind'] === MrpModel::COORDINATOR ? 'tone-teal-soft' : 'tone-blue-soft' ?>"><?= esc(MrpModel::KINDS[$user['kind']] ?? $user['kind']) ?></span></td>
                                        <td><span class="badge <?= $user['active'] ? 'tone-emerald' : 'tone-slate' ?>"><?= $user['active'] ? 'Active' : 'Inactive' ?></span></td>
                                        <td class="cell-action">
                                            <a class="btn-edit" href="<?= site_url('mrp') ?>?edit=<?= esc($user['id']) ?>"><?= ui_icon('edit') ?>Edit</a>
                                            <?php // Never a delete: the records
                                                  // they are on still name them. ?>
                                            <form method="post" action="<?= site_url('mrp/' . rawurlencode($user['id']) . '/active') ?>" class="inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="active" value="<?= $user['active'] ? '0' : '1' ?>">
                                                <button type="submit" class="btn-outline<?= $user['active'] ? ' btn-outline--danger' : '' ?>"
                                                        <?= $user['active'] ? 'data-confirm="Deactivate ' . esc($user['name']) . '? They stay on the records that name them, and stop being offered on new ones."' : '' ?>><?= $user['active'] ? 'Deactivate' : 'Reactivate' ?></button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
