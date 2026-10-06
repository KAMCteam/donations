<?= $this->extend('ui/layout') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/ui/css/auth.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php

use App\Models\MrpModel;

/**
 * The register, and who may sign into it. One screen for one job.
 *
 * It was **Add MRP**, in the sidebar for everybody, and it made the physicians
 * and coordinators a record can be assigned to. It is still that, and now it
 * is the rest of what there is to say about a user as well — all behind the
 * `admin` permission, which a doctor or a coordinator may hold on top of their
 * own work.
 *
 * Three sections, in the order somebody uses them:
 *
 *   1. **Add MRP** — unchanged. A user is not invented here: they already
 *      exist in the hospital's directory, and the right way to add one is to
 *      look them up by the ID they already have, which is what **Search** is
 *      for. The directory is not connected yet, so the button comes back
 *      saying so and the fields stay open to be typed.
 *
 *      The directory will return a name and an ID. It will not return a
 *      password into this database: a copied credential is a credential in two
 *      places, and the point of looking somebody up is that the directory is
 *      where their sign-in is checked. Registering somebody makes their
 *      account here with no password at all — it matches nothing until an
 *      administrator sets one.
 *
 *   2. **Registered MRPs** — everybody registered, with the row to edit, to
 *      deactivate, to grant the permission on, and to reset the password of.
 *
 *   3. **Login Activity** — every attempt to sign in. Read-only, because a log
 *      somebody can tidy is not a log.
 *
 * @var string                     $saved    What just happened, if anything
 * @var string                     $error
 * @var string                     $editing  The row open for editing, '' for none
 * @var array<string, mixed>       $lookup   What the last search came back with
 * @var list<array<string, mixed>> $users
 * @var list<array<string, mixed>> $activity
 */
$chosenKind = (string) ($lookup['kind'] ?? MrpModel::DOCTOR);
$chosenKind = isset(MrpModel::KINDS[$chosenKind]) ? $chosenKind : MrpModel::DOCTOR;

// DD/MM/YYYY HH:MM, as every other date on the platform is written.
$when = static function (string $stamp): string {
    if (trim($stamp) === '') {
        return '—';
    }

    $time = strtotime($stamp);

    return $time === false ? $stamp : date('d/m/Y H:i', $time);
};
?>
<div class="page">
    <div class="page-header page-header--plain">
        <div class="eyebrow">Admin</div>
        <h1 class="page-title">Users</h1>
        <p class="page-subtitle">Register the people who use this platform, and see who has signed in.</p>
    </div>

    <div class="mrp-wrap">
        <?php if ($saved !== ''): ?>
            <div class="mrp-success"><?= ui_icon('check') ?><?= esc($saved) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= esc($error) ?></div>
        <?php endif; ?>

        <?php // ---- 1. Add MRP -------------------------------------------
              // One form, two buttons: Search sends it to the directory and
              // Add user registers it. `formaction` is what makes that one
              // form rather than two that have to copy each other's fields,
              // and it needs no scripting — so pressing Enter in the ID box
              // searches, which is what somebody typing an ID means. ?>
        <h2 class="mrp-list-title">Add MRP</h2>
        <form class="card card--pad stack-5 mrp-form" method="post" action="<?= site_url('admin/users') ?>" novalidate>
            <?= csrf_field() ?>

            <div>
                <label class="mrp-label" for="mrp-id">MRP ID</label>
                <div class="mrp-search-row">
                    <input type="text" id="mrp-id" name="id" class="input input--mono"
                           value="<?= esc((string) ($lookup['code'] ?? '')) ?>" placeholder="e.g. MRP-004">
                    <button type="submit" class="btn-outline" formaction="<?= site_url('admin/users/lookup') ?>">Search</button>
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
                <p class="mrp-hint">Admin is not a third kind. It is a permission granted to a doctor or a coordinator, from the register below.</p>
            </div>

            <div>
                <label class="mrp-label" for="mrp-name">Full name</label>
                <input type="text" id="mrp-name" name="name" class="input"
                       value="<?= esc((string) ($lookup['name'] ?? '')) ?>" placeholder="e.g. Dr. Amira Hassan">
            </div>

            <button type="submit" class="mrp-submit">Add user</button>
        </form>

        <?php // ---- 2. Registered MRPs ------------------------------------ ?>
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
                                            <form class="mrp-edit" method="post" action="<?= site_url('admin/users/' . rawurlencode($user['id'])) ?>" novalidate>
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
                                                <div>
                                                    <?php // The permission, beside the type and not
                                                          // instead of it: granting it leaves them the
                                                          // doctor or the coordinator they were. ?>
                                                    <span class="field-label">Permission</span>
                                                    <label class="admin-toggle">
                                                        <input type="checkbox" name="isAdmin" value="1"<?= $user['isAdmin'] ? ' checked' : '' ?><?= $user['hasLogin'] ? '' : ' disabled' ?>>
                                                        <span>Admin</span>
                                                    </label>
                                                    <p class="mrp-hint"><?= $user['hasLogin']
                                                        ? 'Keeps their type. Adds the register, the delete buttons and this screen.'
                                                        : 'No sign-in account, so there is nothing to grant.' ?></p>
                                                </div>
                                                <div class="mrp-edit-actions">
                                                    <a class="btn-outline" href="<?= site_url('admin') ?>">Cancel</a>
                                                    <button type="submit" class="btn-save">Save</button>
                                                </div>
                                            </form>
                                        </td>
                                    <?php else: ?>
                                        <td class="cell-name">
                                            <?= esc($user['name']) ?>
                                            <?php if (! $user['hasPassword'] && $user['hasLogin']): ?>
                                                <span class="mrp-flag">No password set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="mono"><?= esc($user['code']) ?></td>
                                        <td>
                                            <?php // Their own type, always. The permission is a
                                                  // separate word beside it, because it is a
                                                  // separate thing. ?>
                                            <span class="badge <?= $user['kind'] === MrpModel::COORDINATOR ? 'tone-teal-soft' : 'tone-blue-soft' ?>"><?= esc(MrpModel::KINDS[$user['kind']] ?? $user['kind']) ?></span>
                                            <?php if ($user['isAdmin']): ?>
                                                <span class="admin-mark" title="Also looks after the register">Admin &check;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge <?= $user['active'] ? 'tone-emerald' : 'tone-red' ?>"><?= $user['active'] ? 'Active' : 'Deactivated' ?></span></td>
                                        <td class="cell-action">
                                            <a class="btn-edit" href="<?= site_url('admin') ?>?edit=<?= esc($user['id']) ?>"><?= ui_icon('edit') ?>Edit</a>

                                            <?php // Setting a password without going round the
                                                  // directory again. The dialog says what it does
                                                  // not do yet, because a screen that looks like it
                                                  // set a password and did not is worse than none. ?>
                                            <?php $resetId = 'reset-' . $user['id']; ?>
                                            <a class="btn-outline" href="#<?= esc($resetId) ?>" data-dialog="<?= esc($resetId) ?>">Reset password</a>

                                            <?php // Deactivate is this register's delete. Never a
                                                  // real one: the records they are on still name
                                                  // them, and a register that forgot somebody would
                                                  // make those records say nobody. ?>
                                            <form method="post" action="<?= site_url('admin/users/' . rawurlencode($user['id']) . '/active') ?>" class="inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="active" value="<?= $user['active'] ? '0' : '1' ?>">
                                                <button type="submit" class="btn-outline<?= $user['active'] ? ' btn-outline--danger' : '' ?>"
                                                        <?= $user['active'] ? 'data-confirm="Deactivate ' . esc($user['name']) . '? They can no longer sign in, they stop being offered on new records, and they stay on every record that names them."' : '' ?>><?= $user['active'] ? 'Deactivate' : 'Reactivate' ?></button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php // The dialogs sit outside the table: a <dialog> inside a
                      // row is inside the row's own form on some browsers, and
                      // forms do not nest. ?>
                <?php foreach ($users as $user): ?>
                    <dialog id="reset-<?= esc($user['id']) ?>" class="dialog">
                        <div class="confirm">
                            <h2 class="confirm-title">Reset password</h2>
                            <p class="confirm-detail">
                                A new password for <?= esc($user['name']) ?> (<?= esc($user['code']) ?>),
                                set here rather than by looking them up in the directory again.
                            </p>
                            <form method="post" action="<?= site_url('admin/users/' . rawurlencode($user['id']) . '/password') ?>" class="stack-4">
                                <?= csrf_field() ?>
                                <div>
                                    <label class="field-label" for="pw-<?= esc($user['id']) ?>">New password</label>
                                    <input type="text" id="pw-<?= esc($user['id']) ?>" name="password" class="input input--mono"
                                           autocomplete="off" placeholder="Type one, or generate">
                                </div>
                                <?php // Generated in the browser and shown in
                                      // the box, so whoever is setting it can
                                      // read it out. Nothing is generated
                                      // server-side yet, because nothing is
                                      // stored yet. ?>
                                <button type="button" class="btn-outline" data-generate-password="pw-<?= esc($user['id']) ?>">Generate a temporary password</button>
                                <p class="reset-warning">
                                    Not connected yet. This screen is the flow and nothing behind it:
                                    what is typed here is not stored, and <?= esc($user['name']) ?>&rsquo;s password does not change.
                                </p>
                                <div class="confirm-actions">
                                    <a class="btn-outline" href="<?= site_url('admin') ?>">Cancel</a>
                                    <button type="submit" class="btn-save">Set password</button>
                                </div>
                            </form>
                        </div>
                    </dialog>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php // ---- 3. Login Activity -------------------------------------
              // Every attempt, successful or not. Read-only: there is no
              // button on it, because the reason it exists is the attempts
              // nobody meant to be read. ?>
        <div class="mrp-list-block">
            <h2 class="mrp-list-title">Login Activity</h2>
            <p class="mrp-hint">Every attempt to sign in, newest first. Nothing on this list can be changed or removed.</p>
            <div class="card card--scroll">
                <?php if ($activity === []): ?>
                    <div class="empty-state">No sign-in attempts recorded yet.</div>
                <?php else: ?>
                    <table class="table list-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>User ID</th>
                                <th>When</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activity as $attempt): ?>
                                <tr>
                                    <td class="cell-name"><?= esc($attempt['name'] !== '' ? $attempt['name'] : 'Unknown user') ?></td>
                                    <td class="mono"><?= esc($attempt['login_id']) ?></td>
                                    <td><?= esc($when((string) $attempt['created_at'])) ?></td>
                                    <td>
                                        <?php if ((int) $attempt['succeeded'] === 1): ?>
                                            <span class="badge tone-emerald">Successful</span>
                                        <?php else: ?>
                                            <span class="badge tone-red">Failed</span>
                                            <?php if ($attempt['reason'] !== ''): ?>
                                                <span class="activity-why"><?= esc($attempt['reason']) ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/ui/js/admin.js') ?>"></script>
<?= $this->endSection() ?>
