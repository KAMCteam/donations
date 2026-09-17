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

  /* Kept in step with UiStore::LAB_STATUS_TONE / LAB_STATUS_LABEL. */
  var LAB_STATUS_TONE = {
    completed: "tone-emerald",
    pending: "tone-amber-soft",
    flagged: "tone-red",
  };

  var LAB_STATUS_LABEL = {
    completed: "Done",
    pending: "Pending",
    flagged: "Flagged",
  };

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
    return input ? input.value : "pending";
  }

  function setLabStatus(card, status) {
    var input = card.querySelector("[data-lab-status-value]");
    if (input) input.value = status;

    card.className = "lab-card" +
      (card.classList.contains("lab-card--animated") ? " lab-card--animated" : "") +
      " status-" + status;

    var pill = card.querySelector("[data-lab-pill]");
    if (pill) {
      pill.className = "lab-pill " + LAB_STATUS_TONE[status];
      pill.textContent = LAB_STATUS_LABEL[status];
    }

    card.querySelectorAll("[data-lab-status]").forEach(function (btn) {
      var value = btn.getAttribute("data-lab-status");
      btn.className = "lab-status-btn" + (value === status ? " is-active " + LAB_STATUS_TONE[value] : "");
    });
  }

  /* "3 of 7 completed", the bar and the percentage. */
  function updateLabSummary(section) {
    var cards = section.querySelectorAll(".lab-card");
    var total = cards.length;
    var done = 0;

    cards.forEach(function (card) {
      if (labStatus(card) === "completed") done += 1;
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

  /* ---- Urgency <-> Urgent? ---------------------------------------------- */

  function initUrgencySync() {
    var urgency = document.querySelector("[data-urgency]");
    var urgent = document.querySelector("[data-urgent]");
    if (!urgency || !urgent) return;

    function isUrgent(value) {
      return value === "critical" || value === "high" ? "yes" : "no";
    }

    urgency.addEventListener("change", function () {
      urgent.value = isUrgent(urgency.value);
    });

    urgent.addEventListener("change", function () {
      urgency.value = urgent.value === "yes" ? "high" : "low";
      urgent.value = isUrgent(urgency.value);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initSidebar();
    initRowLinks();
    initLabSections();
    initUrgencySync();
  });
})();
