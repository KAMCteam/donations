<?php

/**
 * The two sheets a record can be exported as, as the body of a dialog.
 *
 * Export PDF used to be one button and one answer: the whole record, every
 * group, every test, every comment — seventy-odd rows for a recipient, twice
 * that for a pair. That is the right sheet for a transplant meeting and the
 * wrong one for everything else, so somebody wanting to send the record on
 * printed ten pages of Not done.
 *
 * So it asks. **Full** is what it always was. **Summary** is the same record
 * with the workup reduced to its headings: one line per group saying how far
 * that group has got, and no result on the page at all. A patient's own
 * results are the part of a record worth being careful with, and a sheet that
 * says "Immunology 6 of 9" without saying what any of them were is one that
 * can be handed over.
 *
 * Shared by the record screens — where it sits inside a `<dialog>` the Export
 * button opens — and by `ui/export_choice`, the page that same button links to
 * so the choice is still reachable with JavaScript off. Both answers are
 * ordinary links, so either way this is two addresses and nothing else.
 *
 * @var string $exportSubject  Whose record it is
 * @var string $exportFullUrl
 * @var string $exportSummaryUrl
 */
?>
<h2 class="link-choice-title">Export <?= esc($exportSubject) ?></h2>
<p class="link-choice-sub">Choose what goes on the sheet.</p>

<div class="link-choice-options">
    <a class="link-choice" href="<?= esc($exportFullUrl) ?>" target="_blank" rel="noopener">
        <span class="link-choice-icon"><?= ui_icon('clipboard') ?></span>
        <span>
            <span class="link-choice-label">Full record</span>
            <span class="link-choice-hint">Everything on the record, with every lab test, its result and its comment.</span>
        </span>
    </a>

    <a class="link-choice" href="<?= esc($exportSummaryUrl) ?>" target="_blank" rel="noopener">
        <span class="link-choice-icon"><?= ui_icon('printer') ?></span>
        <span>
            <span class="link-choice-label">Summary</span>
            <span class="link-choice-hint">The same record with the workup as its group headings only &mdash; how far each has got, and no results.</span>
        </span>
    </a>
</div>
