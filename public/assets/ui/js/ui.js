/* ==========================================================================
   ui.js — behaviour for the transplant screens

   What is left of the prototype's ten JavaScript files. Everything those files
   did to *build* markup now happens in PHP (app/Views/ui/), so this file only
   reacts to clicks on markup the server already sent:

     - the mobile sidebar and its backdrop        (was js/app.js)
     - whole-row links in the tables              (was the list pages)
     - the lab test cards: status, editor, totals (was js/lab-section.js)
     - keeping "Urgency" and "Urgent?" in step    (was person-form / add-pair)

   Nothing here is required to read a page: every screen renders, navigates and
   submits with scripting switched off.
   ========================================================================== */
(function () {
  "use strict";

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
      if (!row || e.target.closest("a")) return;
      window.location.href = row.getAttribute("data-href");
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
      pill.className = "lab-pill " + chosen.getAttribute("data-lab-tone");
      pill.textContent = chosen.getAttribute("data-lab-label");
    }

    card.querySelectorAll("[data-lab-status]").forEach(function (btn) {
      var active = btn === chosen;
      btn.className = "lab-status-btn" + (active ? " is-active " + btn.getAttribute("data-lab-tone") : "");
    });
  }

  /* "3 of 7 completed", the bar and the percentage. */
  function updateLabSummary(section) {
    var cards = section.querySelectorAll(".lab-card");
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

  /* Mirrors an edited result / date back into the card head. The two lines are
     present but `hidden` while empty, which renders the same as the source's
     "only add the node when there is a value". */
  function applyEditor(card) {
    var editor = card.querySelector("[data-lab-editor]");
    if (!editor) return;

    [["[data-lab-result]", 0], ["[data-lab-date-text]", 1]].forEach(function (pair) {
      var target = card.querySelector(pair[0]);
      var field = editor.querySelectorAll(".lab-editor-field")[pair[1]];
      if (!target || !field) return;

      target.textContent = field.value;
      target.hidden = field.value === "";
    });
  }

  function initLabSections() {
    document.querySelectorAll("[data-lab-section]").forEach(function (section) {
      section.addEventListener("click", function (e) {
        var card = e.target.closest(".lab-card");
        if (!card) return;

        var statusBtn = e.target.closest("[data-lab-status]");
        if (statusBtn) {
          setLabStatus(card, statusBtn.getAttribute("data-lab-status"));
          updateLabSummary(section);
          return;
        }

        var editor = card.querySelector("[data-lab-editor]");

        if (e.target.closest("[data-lab-edit]")) {
          if (!editor) return;
          var opening = editor.hidden;

          // One editor at a time, as in the source.
          section.querySelectorAll("[data-lab-editor]").forEach(function (other) {
            other.hidden = true;
          });
          editor.hidden = !opening;
          return;
        }

        if (e.target.closest("[data-lab-save]")) {
          applyEditor(card);
          if (editor) editor.hidden = true;
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

  function bindDateText(input) {
    input.addEventListener("input", function () {
      var digits = input.value.replace(/\D/g, "").slice(0, 8);
      var atEnd = input.selectionStart === input.value.length;
      input.value = formatDate(digits);

      // Only chase the caret to the end when it was already there, so editing
      // the middle of a date does not throw you to the end of it.
      if (atEnd) input.setSelectionRange(input.value.length, input.value.length);
    });
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

  document.addEventListener("DOMContentLoaded", function () {
    initSidebar();
    initRowLinks();
    initLabSections();
    initDateFields();
    initDialogs();
    initConfirmDelete();
  });
})();
