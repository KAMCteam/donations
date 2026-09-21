<?php

/**
 * The delete button at the end of a row, for all three lists.
 *
 * A link, not a form button, for two reasons: it goes somewhere that asks
 * before anything happens, and `initRowLinks` in `ui.js` already leaves an
 * `<a>` alone, so pressing it does not also open the record the row points at.
 *
 * The question it would ask travels on the element, so the list needs only one
 * dialog for all its rows.
 *
 * @var string $url     GET asks, POST does — same address
 * @var string $name    What is being deleted, as the screens name it
 * @var string $detail  Exactly what goes, and what stays
 * @var string $kind    recipient | donor | pair, for the button's label
 */
?>
<a class="btn-icon-danger" href="<?= esc($url) ?>"
   data-delete
   data-delete-action="<?= esc($url) ?>"
   data-delete-title="Delete <?= esc($name) ?>?"
   data-delete-detail="<?= esc($detail) ?>"
   title="Delete <?= esc($kind) ?>"
   aria-label="Delete <?= esc($name) ?>"><?= ui_icon('trash') ?></a>
