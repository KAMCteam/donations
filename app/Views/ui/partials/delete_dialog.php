<?php

/**
 * The one confirmation dialog a list needs, whichever row asked for it.
 *
 * A dialog per row would be a dialog per patient; instead every delete button
 * carries the question on itself — what it would remove, and where to post —
 * and `ui.js` copies that in before opening this. With JavaScript off the
 * button is an ordinary link to `ui/confirm_delete`, which asks the same
 * question as a page, so nothing here is load-bearing.
 */
?>
<dialog class="dialog" id="confirm-delete">
    <form method="post" class="confirm" data-delete-form>
        <?= csrf_field() ?>
        <h2 class="confirm-title" data-delete-title></h2>
        <p class="confirm-detail" data-delete-detail></p>
        <div class="confirm-actions">
            <button type="button" class="btn-outline" data-delete-cancel>Cancel</button>
            <button type="submit" class="btn-danger"><?= ui_icon('trash') ?>Delete</button>
        </div>
    </form>
</dialog>
