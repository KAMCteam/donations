/* ==========================================================================
   ui.js — behaviour for the transplant screens

   What is left of the prototype's ten JavaScript files. Everything those files
   did to *build* markup now happens in PHP (app/Views/ui/), so this file only
   reacts to clicks on markup the server already sent:

     - the mobile sidebar and its backdrop        (was js/app.js)
     - whole-row links in the tables              (was the list pages)
     - the lab test cards: status, editor, totals (was js/lab-section.js)
     - the date boxes: typing, the picker, no future dates, the age a date of
       birth comes to, and the field another field closes
     - the Reports filter menus and their counts

   Nothing here is required to read a page: every screen renders, navigates and
   submits with scripting switched off.
   ========================================================================== */
(function () {
  "use strict";

  /* Where the application lives, read off this file's own address: it is
     served from <base>/assets/ui/js/ui.js, so everything before that is the
     base. Nothing has to be printed into the page to tell it. */
  var BASE = ((document.currentScript && document.currentScript.src) || "")
    .replace(/\/assets\/ui\/js\/ui\.js.*$/, "");

  /* ---- Sidebar (app.js setSidebarOpen) ---------------------------------- */

  function initSidebar() {
    var sidebar = document.getElementById("sidebar");
    var shell = document.querySelector(".app");
    if (!sidebar || !shell) return;

    function setOpen(open) {
      sidebar.classList.toggle("is-open", open);

      var existing = shell.querySelector(".backdrop");
      if (open && !existing) {
        var backdrop = document.createElement("div");
        backdrop.className = "backdrop";
        backdrop.addEventListener("click", function () { setOpen(false); });
        shell.insertBefore(backdrop, sidebar);
      } else if (!open && existing) {
        existing.remove();
      }
    }

    document.addEventListener("click", function (e) {
      if (e.target.closest("[data-sidebar-open]")) { setOpen(true); return; }
      if (e.target.closest("[data-sidebar-close]")) setOpen(false);
    });
  }

  /* ---- Clickable table rows --------------------------------------------- */

  /* Each row also carries a real link in its first cell, so this only widens
     the target area; it never becomes the sole way to reach a record. */
  function initRowLinks() {
    document.addEventListener("click", function (e) {
      var row = e.target.closest("[data-href]");
      // Anything in the row that is itself something to press keeps its own
      // click: a link goes where it says, and a button submits the form it
      // belongs to rather than navigating away from it mid-post.
      if (!row || e.target.closest("a, button, input, select, textarea, label")) return;
      window.location.href = row.getAttribute("data-href");
    });
  }

  /* ---- Dropdown filters -------------------------------------------------- */

  /* The Reports filters and its export menu are each a `<details>`, so they
     open, hold a choice and close again with scripting off. The one thing a
     `<details>` will not do by itself is close when you press somewhere else,
     which leaves two menus overlapping each other; that is all this adds.

     A press inside a menu is left alone on purpose: the filters are checkboxes
     and choosing one is not the end of choosing. */
  function initMenus() {
    document.addEventListener("click", function (e) {
      var open = document.querySelectorAll("details.filter[open], details.export[open], details.tone-picker[open]");
      if (!open.length) return;

      var inside = e.target.closest("details.filter, details.export, details.tone-picker");

      Array.prototype.forEach.call(open, function (details) {
        if (details !== inside) details.removeAttribute("open");
      });
    });

    /* The number on the button is rendered from the report on screen, so
       until Apply is pressed it describes the last report rather than the one
       being asked for. Recounting as the boxes are ticked is the whole of the
       difference; with scripting off the button simply waits for Apply. */
    document.addEventListener("change", function (e) {
      var details = e.target.closest("details.filter");
      if (!details) return;

      var count = details.querySelector(".filter-count");
      if (!count) return;

      var boxes = details.querySelectorAll('input[type="checkbox"]');
      var text;

      if (boxes.length) {
        var chosen = details.querySelectorAll('input[type="checkbox"]:checked').length;
        // None and all select the same records, and both say so as "All".
        text = chosen === 0 || chosen >= boxes.length ? "All" : String(chosen);
      } else {
        // The date range is two ends rather than a list of values.
        text = "All";
        Array.prototype.forEach.call(details.querySelectorAll('input[type="text"]'), function (input) {
          if (input.value !== "") text = "Set";
        });
      }

      count.textContent = text;
      count.classList.toggle("is-set", text !== "All");
    });

    /* Escape closes whichever is open, as it does for the dialogs. */
    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;

      Array.prototype.forEach.call(
        document.querySelectorAll("details.filter[open], details.export[open], details.tone-picker[open]"),
        function (details) { details.removeAttribute("open"); }
      );
    });
  }

  /* ---- What a test somebody added answers --------------------------------

     Two things the markup cannot do for itself. Choosing a colour closes the
     palette and paints the swatch, so the answer shows the colour it will
     carry rather than the one it had; and ticking an answer marks its chip, so
     what will still be on the card after saving is visible before saving.

     Without this the controls still work — a `<details>` of radios and a
     checkbox are the whole of it — they simply say less while being used. */
  function initAnswerPickers() {
    document.addEventListener("change", function (e) {
      var tone = e.target.closest(".tone-option input");

      if (tone) {
        var picker = tone.closest(".tone-picker");
        var swatch = picker.querySelector(".tone-swatch");
        var dot = tone.parentNode.querySelector(".tone-dot");

        // The swatch wears the tone's own class, so it is repainted by
        // swapping that class rather than by knowing any colour. No colour
        // chosen leaves it empty, which is where every answer starts.
        swatch.className = "tone-swatch " + (tone.value || "tone-swatch--none");
        swatch.title = tone.value ? "Colour: " + tone.parentNode.textContent.trim() : "No colour";
        picker.removeAttribute("open");

        // An answer of ours shows its colour on the chip beside the tick.
        var label = picker.parentNode.querySelector(".lab-answer-label");
        if (label) label.className = "lab-answer-label badge " + (tone.value || "tone-none");

        return;
      }

      var tick = e.target.closest('.lab-answer-tick input[type="checkbox"]');
      if (tick) tick.closest(".lab-answer").classList.toggle("is-on", tick.checked);
    });
  }

  /* ---- Lab test cards (lab-section.js) ---------------------------------- */

  function labStatus(card) {
    var input = card.querySelector("[data-lab-status-value]");
    return input ? input.value : "not_done";
  }

  /* Each button carries its own tone and label, because every test asks a
     different question — Positive / Negative, Cleared / not, Given / not —
     and this file has no business holding a copy of all of them. */
  function setLabStatus(card, status) {
    var chosen = card.querySelector('[data-lab-status="' + status + '"]');
    if (!chosen) return;

    var input = card.querySelector("[data-lab-status-value]");
    if (input) input.value = status;

    card.className = "lab-card" +
      (card.classList.contains("lab-card--animated") ? " lab-card--animated" : "") +
      " status-" + status;

    var pill = card.querySelector("[data-lab-pill]");
    if (pill) {
      // A card that started with no answer — a vaccination — keeps its pill
      // hidden until one is pressed.
      pill.hidden = false;
      pill.className = "lab-pill " + (chosen.getAttribute("data-lab-tone") || "tone-none");
      pill.textContent = chosen.getAttribute("data-lab-label");
    }

    card.querySelectorAll("[data-lab-status]").forEach(function (btn) {
      var active = btn === chosen;
      var tone = btn.getAttribute("data-lab-tone");
      // Whether this answer keeps its colour when it is not the one recorded.
      // The server decided that — a colour somebody chose for a test of their
      // own — and this only has to not lose it.
      var tinted = btn.classList.contains("lab-status-btn--tinted");

      btn.className = "lab-status-btn" +
        (tinted ? " lab-status-btn--tinted " + tone : "") +
        (active ? " is-active" + (tone ? " " + tone : "") : "");
    });
  }

  /* "3 of 7 completed", the bar and the percentage. */
  function updateLabSummary(section) {
    // Not the Other box: it has no answer to give, so it is neither done nor
    // outstanding. `UiStore::labProgress` leaves it out for the same reason,
    // and the two counts have to agree — otherwise the total jumps the moment
    // the first answer is pressed.
    var cards = section.querySelectorAll(".lab-card:not(.lab-card--free)");
    var total = cards.length;
    var done = 0;

    // Answered is anything but "not done" and "pending" — which the buttons
    // themselves say, so the count does not need the vocabulary either.
    cards.forEach(function (card) {
      var chosen = card.querySelector('[data-lab-status="' + labStatus(card) + '"]');
      if (chosen && !chosen.hasAttribute("data-lab-unanswered")) done += 1;
    });

    var pct = Math.round((done / Math.max(total, 1)) * 100);

    var count = section.querySelector("[data-lab-count]");
    if (count) count.textContent = done + " of " + total + " completed";

    var fill = section.querySelector("[data-lab-fill]");
    if (fill) fill.style.width = pct + "%";

    var label = section.querySelector("[data-lab-pct]");
    if (label) label.textContent = pct + "%";
  }

  function initLabSections() {
    document.querySelectorAll("[data-lab-section]").forEach(function (section) {
      section.addEventListener("click", function (e) {
        var card = e.target.closest(".lab-card");
        if (!card) return;

        // Answering is all a card does now: the value, the date and the
        // pencil that opened them have gone.
        var statusBtn = e.target.closest("[data-lab-status]");
        if (statusBtn) {
          setLabStatus(card, statusBtn.getAttribute("data-lab-status"));
          updateLabSummary(section);
        }
      });
    });
  }

  /* ---- Dates -------------------------------------------------------------
     The screens write dates as DD/MM/YYYY. Typing eight digits is enough: the
     slashes go in as you reach them and anything that is not a digit is
     dropped. Beside the box sits a native date input with no name, there only
     for its calendar — picking a day writes the formatted date back. The box
     is the field, so with this file absent you type the date as before. */

  function initDateFields() {
    var texts = document.querySelectorAll("[data-date-text]");

    for (var i = 0; i < texts.length; i++) {
      bindDateText(texts[i]);
    }

    var fields = document.querySelectorAll("[data-date-field]");

    for (var j = 0; j < fields.length; j++) {
      bindDatePicker(fields[j]);
    }
  }

  /** "1" -> "1", "12" -> "12/", "1234" -> "12/34/", "12032024" -> "12/03/2024" */
  function formatDate(digits) {
    var out = digits.slice(0, 2);
    if (digits.length >= 2) out += "/";
    if (digits.length > 2) out += digits.slice(2, 4);
    if (digits.length >= 4) out += "/";
    if (digits.length > 4) out += digits.slice(4, 8);
    return out;
  }

  /** "12/03/2024" -> "2024-03-12", or "" if it is not a whole date yet. */
  function isoOf(value) {
    var parts = value.split("/");
    if (parts.length !== 3 || parts[2].length !== 4) return "";
    return parts[2] + "-" + parts[1] + "-" + parts[0];
  }

  function today() {
    var d = new Date();
    var pad = function (n) { return (n < 10 ? "0" : "") + n; };
    return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate());
  }

  /* Whole years, counted the way a birthday is: you are 40 until the day comes
     round again. The same sum as `UiStore::ageFrom`, which is what the server
     stores; this only shows it before the form is sent. */
  function ageFrom(iso) {
    var born = iso.split("-");
    var now = today().split("-");
    var age = Number(now[0]) - Number(born[0]);

    if (now[1] < born[1] || (now[1] === born[1] && now[2] < born[2])) age -= 1;
    return age;
  }

  /* The age that goes with a date of birth, in the box beside it. There is
     nothing to fill in: it is the same answer read out, so it follows the date
     as it is typed. The server works it out again from what is sent, so this
     is only what you see while you type. */
  function updateAgeNote(input) {
    var box = document.querySelector('[data-age-for="' + input.id + '"]');
    if (!box) return;

    var iso = isoOf(input.value);
    var age = iso === "" || iso > today() ? -1 : ageFrom(iso);

    box.value = age < 0 ? "" : String(age);
  }

  /* ---- Saying what is wrong with a box, while it is being filled in ------

     A form that waits for Save to say a date is impossible has already taken
     everything else away from the screen to say it. These write the reason
     under the box it belongs to, as it is typed.

     The element is made here rather than in the markup, so every screen that
     collects a record gets it without being edited and without being able to
     forget. The server still refuses the same things on save: this is the
     earlier of two answers, not the only one. */

  function problemSlot(input) {
    var id = input.id ? "problem-" + input.id : null;
    var holder = input.closest(".date-field") || input;
    var slot = holder.parentNode.querySelector(":scope > .field-problem");

    if (!slot) {
      slot = document.createElement("p");
      slot.className = "field-problem";
      slot.hidden = true;
      if (id) { slot.id = id; }
      holder.parentNode.insertBefore(slot, holder.nextSibling);
    }

    return slot;
  }

  /* One place decides what a wrong box looks like: the message under it, the
     red edge on it, and what a screen reader is told about it. */
  function sayProblem(input, message) {
    var slot = problemSlot(input);
    var wrong = message !== "";

    slot.textContent = message;
    slot.hidden = !wrong;
    input.classList.toggle("is-wrong", wrong);
    input.setAttribute("aria-invalid", wrong ? "true" : "false");
    if (slot.id) {
      if (wrong) { input.setAttribute("aria-describedby", slot.id); }
      else { input.removeAttribute("aria-describedby"); }
    }

    var field = input.closest(".date-field");
    if (field) { field.classList.toggle("is-future", wrong); }

    // Keeps the browser's own refusal in step, so a form cannot be sent with
    // a box this has already objected to.
    input.setCustomValidity(message);
  }

  /* Date-shaped is not a date: 55/66/1111 is four digits, two and two, and no
     day of any year. The same check the server makes, made sooner. */
  function realDate(iso) {
    var p = iso.split("-").map(Number);
    var d = new Date(Date.UTC(p[0], p[1] - 1, p[2]));

    return d.getUTCFullYear() === p[0] && d.getUTCMonth() === p[1] - 1 && d.getUTCDate() === p[2];
  }

  /* Everything the personal details ask for has already happened, so a date
     after today is a slip, and a day the calendar has not got is another. The
     picker will not offer either; this is for a date typed straight in. */
  function markFutureDate(input) {
    var value = input.value.trim();
    var whole = /^\d{2}\/\d{2}\/\d{4}$/.test(value);
    var iso = isoOf(value);
    var past = input.closest("[data-date-past]") !== null;

    if (value === "" || !whole) {
      // Half-typed is not wrong yet: nobody is told off mid-date.
      sayProblem(input, "");

      return;
    }

    if (!realDate(iso)) {
      sayProblem(input, "The calendar has no such day.");

      return;
    }

    sayProblem(input, past && iso > today() ? "This date is in the future." : "");
  }

  /* ---- The file number, checked as it is typed ---------------------------

     Two of the three things wrong with a file number this can see for itself —
     that there is none, and that it is not a number. The third, that somebody
     is already filed under it, only the server knows, so it asks: one GET per
     number, a quarter-second after typing stops, and the answer is the same
     sentence the save would have given.

     With this file absent the save says all three, as it always did. */

  function mrnProblem(value) {
    if (value === "") return "";
    if (!/^[0-9]+$/.test(value) || Number(value) < 1) return "MRN must be a number.";
    return "";
  }

  function bindMrn(input) {
    var register = input.getAttribute("data-mrn");
    var timer = null;
    var asked = "";

    function ask(value) {
      // The same number twice is the same answer; and a box emptied again has
      // nothing to ask about.
      if (value === asked || value === "") return;
      asked = value;

      fetch(BASE + "/mrn-taken/" + register + "/" + encodeURIComponent(value), {
        headers: { Accept: "application/json" },
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (answer) {
          // Only if the box still holds what was asked about: a slow answer
          // about an old number is worse than none.
          if (answer && input.value.trim() === value) sayProblem(input, answer.message || "");
        })
        .catch(function () { /* The save still refuses it; nothing to say. */ });
    }

    input.addEventListener("input", function () {
      var value = input.value.trim();
      var shape = mrnProblem(value);

      asked = "";
      sayProblem(input, shape);
      window.clearTimeout(timer);

      if (shape === "") timer = window.setTimeout(function () { ask(value); }, 250);
    });

    input.addEventListener("blur", function () {
      var value = input.value.trim();

      if (value === "") {
        sayProblem(input, "MRN is required — enter the number from the hospital record.");
      } else if (mrnProblem(value) === "") {
        window.clearTimeout(timer);
        ask(value);
      }
    });
  }

  function initMrnFields() {
    document.querySelectorAll("input[data-mrn]").forEach(bindMrn);
  }

  function bindDateText(input) {
    input.addEventListener("input", function () {
      var digits = input.value.replace(/\D/g, "").slice(0, 8);
      var atEnd = input.selectionStart === input.value.length;
      input.value = formatDate(digits);

      // Only chase the caret to the end when it was already there, so editing
      // the middle of a date does not throw you to the end of it.
      if (atEnd) input.setSelectionRange(input.value.length, input.value.length);

      updateAgeNote(input);
      markFutureDate(input);
    });

    markFutureDate(input);
  }

  function bindDatePicker(field) {
    var text = field.querySelector("[data-date-text]");
    var native = field.querySelector("[data-date-native]");
    var button = field.querySelector("[data-date-open]");
    if (!text || !native || !button) return;

    button.addEventListener("click", function () {
      // Open the calendar on the date already typed, when there is one.
      var parts = text.value.split("/");
      if (parts.length === 3 && parts[2].length === 4) {
        native.value = parts[2] + "-" + parts[1] + "-" + parts[0];
      }

      if (typeof native.showPicker === "function") {
        native.showPicker();
      } else {
        native.focus();
        native.click();
      }
    });

    native.addEventListener("change", function () {
      if (!native.value) return;
      var iso = native.value.split("-");
      text.value = iso[2] + "/" + iso[1] + "/" + iso[0];

      updateAgeNote(text);
      markFutureDate(text);
    });
  }

  /* ---- A field another field closes --------------------------------------

     Pre-emptive dialysis means a transplant before dialysis ever begins, so a
     recipient on it has no first dialysis date — not one nobody has filled in,
     but none there can be. The select says which field that is and on which
     answer, and the date closes and empties itself.

     With this file absent the field stays open and the server clears it on
     save, which is the honest fallback: the record ends up right either way. */
  function initClosers() {
    document.querySelectorAll("[data-closes]").forEach(function (select) {
      var text = document.getElementById(select.getAttribute("data-closes"));
      var when = select.getAttribute("data-closes-when");
      if (!text) return;

      var field = text.closest("[data-date-field]");
      var button = field ? field.querySelector("[data-date-open]") : null;

      function sync() {
        var closed = select.value === when;

        // Still posted, and posted empty, so saving clears a date left behind
        // from before the answer changed.
        if (closed) text.value = "";
        text.readOnly = closed;
        text.placeholder = closed ? "Not applicable" : "DD/MM/YYYY";
        if (button) button.disabled = closed;
        if (field) field.classList.toggle("is-closed", closed);
      }

      select.addEventListener("change", sync);
      sync();
    });
  }

  /* ---- Dialogs ----------------------------------------------------------
     A link carrying data-dialog opens that dialog instead of navigating. The
     href is a real page showing the same thing, so nothing here is required:
     without this, or without <dialog> support, the link is simply followed. */

  function initDialogs() {
    var openers = document.querySelectorAll("[data-dialog]");

    for (var i = 0; i < openers.length; i++) {
      bindDialog(openers[i]);
    }
  }

  function bindDialog(opener) {
    var dialog = document.getElementById(opener.getAttribute("data-dialog"));
    if (!dialog || typeof dialog.showModal !== "function") return;

    opener.addEventListener("click", function (event) {
      event.preventDefault();
      dialog.showModal();
    });

    // Clicking the backdrop closes it. The backdrop is the dialog element
    // itself, so a click lands on it only outside the panel.
    dialog.addEventListener("click", function (event) {
      if (event.target === dialog) dialog.close();
    });
  }

  /* ---- Delete, asked before it happens ---------------------------------- */

  /* Every delete button in a list is a link to a page that asks the question.
     Where this runs, the same question opens as a dialog instead, so the list
     is not lost — but the answer posts to the same address either way, and the
     link still works on its own if this never runs. */
  function initConfirmDelete() {
    var dialog = document.getElementById("confirm-delete");
    if (!dialog || typeof dialog.showModal !== "function") return;

    var form = dialog.querySelector("[data-delete-form]");
    var title = dialog.querySelector("[data-delete-title]");
    var detail = dialog.querySelector("[data-delete-detail]");
    var cancel = dialog.querySelector("[data-delete-cancel]");

    document.addEventListener("click", function (event) {
      var button = event.target.closest("[data-delete]");
      if (!button) return;

      event.preventDefault();
      form.action = button.getAttribute("data-delete-action");
      title.textContent = button.getAttribute("data-delete-title");
      detail.textContent = button.getAttribute("data-delete-detail");
      dialog.showModal();
    });

    if (cancel) cancel.addEventListener("click", function () { dialog.close(); });
    dialog.addEventListener("click", function (event) {
      if (event.target === dialog) dialog.close();
    });
  }

  /* ---- A select that reloads with its own choice ------------------------ */

  /* The dashboard's per-doctor statistic is a GET form: choosing a name and
     pressing Show reloads the page with `?mrp=`. Where this runs, changing
     the name is enough and the button hides, since it has nothing left to do. */
  function initAutoSubmit() {
    document.querySelectorAll("[data-auto-submit]").forEach(function (select) {
      var form = select.form;
      if (!form) return;

      select.addEventListener("change", function () { form.submit(); });

      var button = form.querySelector('button[type="submit"]');
      if (button) button.hidden = true;
    });
  }

  /* ---- Dialogs that close themselves, and buttons that ask --------------- */

  /* A Cancel inside a dialog that posts elsewhere cannot be method="dialog",
     so it says data-dialog-close instead. */
  function initDialogClosers() {
    document.addEventListener("click", function (event) {
      var button = event.target.closest("[data-dialog-close]");
      if (!button) return;

      var dialog = button.closest("dialog");
      if (dialog) { event.preventDefault(); dialog.close(); }
    });
  }

  /* An irreversible choice made in the middle of a screen, where a whole page
     of confirmation would lose the working out. Without this the post still
     goes through — the server asks again in the summary before anything is
     written, so this is the earlier of two warnings, not the only one. */
  function initConfirmButtons() {
    document.addEventListener("click", function (event) {
      var button = event.target.closest("[data-confirm]");
      if (!button) return;

      if (!window.confirm(button.getAttribute("data-confirm"))) event.preventDefault();
    });
  }

  /* ---- A field that only one answer asks for ----------------------------- */

  /* The pair's Closed status wants a reason and its Transplanted one wants the
     date it happened, and the other four want neither. One select, so the two
     attributes carry a list each and are read in step. The blocks are in the
     page either way, so with this file absent they are simply always visible —
     which is the honest fallback, since the server discards a reason that does
     not belong to a closed pair and a date that belongs to no transplant. */
  function initReveals() {
    document.querySelectorAll("[data-reveal]").forEach(function (select) {
      var ids = select.getAttribute("data-reveal").split(",");
      var whens = (select.getAttribute("data-reveal-when") || "").split(",");

      function sync() {
        ids.forEach(function (id, i) {
          var target = document.getElementById(id.trim());
          if (target) target.hidden = select.value !== (whens[i] || "").trim();
        });
      }

      select.addEventListener("change", sync);
      sync();
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initSidebar();
    initRowLinks();
    initMenus();
    initLabSections();
    initDateFields();
    initDialogs();
    initConfirmDelete();
    initAutoSubmit();
    initDialogClosers();
    initClosers();
    initMrnFields();
    initAnswerPickers();
    initConfirmButtons();
    initReveals();
  });
})();
