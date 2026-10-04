<?php

/**
 * The delete button at the end of a row, for all three lists, and the question
 * it asks.
 *
 * A link, not a form button, for two reasons: it opens the question rather
 * than doing anything, and `initRowLinks` in `ui.js` already leaves an `<a>`
 * alone, so pressing it does not also open the record the row points at.
 *
 * The question is its own dialog, beside the button that opens it — one per
 * row, because each row's is about a different person and the wording says so.
 * The id is cut from the address it posts to: unique per row without the list
 * having to invent one.
 *
 * @var string $url     Where the answer posts
 * @var string $name    What is being deleted, as the screens name it
 * @var string $detail  Exactly what goes, and what stays
 * @var string $kind    recipient | donor | pair, for the button's label
 */
$dialogId = 'confirm-' . substr(sha1($url), 0, 10);
?>
<a class="btn-icon-danger" href="#<?= esc($dialogId) ?>"
   data-dialog="<?= esc($dialogId) ?>"
   title="Delete <?= esc($kind) ?>"
   aria-label="Delete <?= esc($name) ?>"><?= ui_icon('trash') ?></a>
<?= view('ui/partials/confirm_dialog', [
    'id'     => $dialogId,
    'title'  => 'Delete ' . $name . '?',
    'detail' => $detail,
    'action' => $url,
    'confirmVerb' => 'Delete ' . $kind,
], ['saveData' => false]) ?>
