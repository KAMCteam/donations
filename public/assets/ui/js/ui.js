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
     - putting the page back where it was after a press
     - keeping a card somebody shut shut

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

  /* The sidebar, which narrows on a wide screen and slides away on a narrow
     one.

     Narrow, it comes in over the page with a backdrop behind it, as it always
     has. Wide, shutting it leaves **the rail**: its icons, four and a bit rem
     of them, with the page taking the rest of the width back. Not nothing —
     a register is fifteen columns wide and the navigation beside it is in the
     way, but taking it off the screen altogether left whoever was reading one
     with no way to anywhere until they went looking for the button that
     brings it back.

     **Open is the default**, every time, on both. What is remembered is a
     viewer narrowing it, kept for the session and nowhere else; a session
     that never narrows it opens wide.

     One button does both, in the sidebar, where it is whichever state it is
     in: a chevron while there is something to narrow, the menu mark while
     there is something to widen. It is this file's doing, and the markup says
     so — the control lives under `.has-js`, which is set here, so with this
     file blocked the sidebar is the one it always was. */

  var SIDEBAR_KEY = "ui-sidebar";

  function wideScreen() {
    return window.matchMedia("(min-width: 64rem)").matches;
  }

  function initSidebar() {
    var sidebar = document.getElementById("sidebar");
    var shell = document.querySelector(".app");
    if (!sidebar || !shell) return;

    // What makes the toggles visible at all: see the comment above.
    document.documentElement.classList.add("has-js");

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

    var toggle = sidebar.querySelector("[data-sidebar-close]");

    function setCollapsed(collapsed, remember) {
      shell.classList.toggle("is-collapsed", collapsed);

      // The one button, wearing the state it would put the sidebar in.
      var wide = sidebar.querySelector("[data-when-wide]");
      var narrow = sidebar.querySelector("[data-when-narrow]");

      if (wide) wide.hidden = collapsed;
      if (narrow) narrow.hidden = !collapsed;

      if (toggle && wideScreen()) {
        toggle.setAttribute("aria-label", collapsed ? "Widen the menu" : "Narrow the menu");
      }

      if (!remember) return;

      // Private browsing, blocked site data: the sidebar still narrows and
      // widens, it is only the remembering that goes.
      try {
        window.sessionStorage.setItem(SIDEBAR_KEY, collapsed ? "closed" : "open");
      } catch (e) {}
    }

    var narrowed = false;

    try {
      narrowed = window.sessionStorage.getItem(SIDEBAR_KEY) === "closed";
    } catch (e) {}

    // Always, not only when it was narrowed: it is what puts the right mark
    // and the right word on the button before anybody has pressed it.
    setCollapsed(narrowed, false);

    document.addEventListener("click", function (e) {
      if (e.target.closest("[data-sidebar-open]")) {
        if (wideScreen()) { setCollapsed(false, true); } else { setOpen(true); }
        return;
      }

      if (e.target.closest("[data-sidebar-close]")) {
        // Wide, the one button goes both ways: there is no second control on
        // the rail, and nor should there be.
        if (wideScreen()) {
          setCollapsed(!shell.classList.contains("is-collapsed"), true);
        } else {
          setOpen(false);
        }
      }
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

    /* The + beside "Add custom result" puts what is in the box into the list
       above it, so several can be added before the card is saved; the pencil
       opens a result's name for typing, and the bin takes the row off the
       card. None of the three is required: with this file absent the box still
       adds the one result it holds when the card is saved, and rubbing a name
       out still removes it, which is how all of it worked before there were
       buttons to press. */
    document.addEventListener("click", function (e) {
      var add = e.target.closest("[data-answer-add]");

      if (add) {
        addAnswerRow(add.closest("[data-answer-picker]"));

        return;
      }

      var pencil = e.target.closest("[data-answer-edit]");

      if (pencil) {
        var name = pencil.closest(".lab-answer").querySelector(".lab-answer-name");
        if (name) { name.focus(); name.select(); }

        return;
      }

      var bin = e.target.closest("[data-answer-remove]");

      // Off the card and out of the post, so saving is what makes it true.
      if (bin) bin.closest(".lab-answer").remove();
    });
  }

  /* One row, built from what the box at the foot is holding. It posts under
     `newAnswers[]` because it has no key yet: the server mints one, the same
     way it does for the box itself. */
  function addAnswerRow(picker) {
    if (!picker) return;

    var box = picker.querySelector(".lab-answer-new .lab-answer-name");
    var label = box ? box.value.trim() : "";
    if (label === "") { if (box) box.focus(); return; }

    var tone = picker.querySelector('.lab-answer-new input[type="radio"]:checked');
    var base = picker.getAttribute("data-base");
    var at = picker.querySelectorAll("[data-answer-list] .lab-answer").length;
    var field = base + "[newAnswers][" + at + "]";

    var row = document.createElement("div");
    row.className = "lab-answer is-on";
    row.innerHTML =
      '<label class="lab-answer-tick">' +
        '<input type="checkbox" checked disabled>' +
        '<input type="text" class="lab-answer-name" maxlength="40" autocomplete="off" aria-label="Name of this answer">' +
      "</label>";

    // The name and the colour are set as values rather than written into the
    // markup, so a result called <b>Positive</b> is a name and not a tag.
    var name = row.querySelector(".lab-answer-name");
    name.name = field + "[label]";
    name.value = label;

    var hidden = document.createElement("input");
    hidden.type = "hidden";
    hidden.name = field + "[tone]";
    hidden.value = tone ? tone.value : "";
    row.appendChild(hidden);

    var swatch = document.createElement("span");
    swatch.className = "tone-swatch " + (hidden.value || "tone-swatch--none");
    row.appendChild(swatch);

    // The pencil and the bin, copied off a row that already has them so the
    // icons live in one place — the helper that draws them, in PHP.
    ["[data-answer-edit]", "[data-answer-remove]"].forEach(function (what) {
      var from = picker.querySelector(what);
      if (from) row.appendChild(from.cloneNode(true));
    });

    picker.querySelector("[data-answer-list]").appendChild(row);

    box.value = "";
    box.focus();
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

    // The card wears the answer's own colour, as the server rendered it —
    // except the neutral one, which every card would be wearing.
    var tone = chosen.getAttribute("data-lab-tone");
    var toned = tone && tone !== "tone-slate"
      ? " lab-card--toned lab-card--" + tone.slice("tone-".length)
      : "";

    card.className = "lab-card" +
      (card.classList.contains("lab-card--animated") ? " lab-card--animated" : "") +
      (card.classList.contains("lab-card--free") ? " lab-card--free" : "") +
      toned +
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
      var own = btn.getAttribute("data-lab-tone");

      // Every answer that has a colour wears it, whether or not it is the one
      // recorded; the one recorded is told apart by its weight.
      btn.className = "lab-status-btn" +
        (own ? " " + own : "") +
        (btn === chosen ? " is-active" : "");
    });
  }

  /* How far a set of cards has got: the same count the server makes. */
  function labCount(root) {
    // Not the Other box: it has no answer to give, so it is neither done nor
    // outstanding. `LabProgress` leaves it out for the same reason,
    // and the two counts have to agree — otherwise the total jumps the moment
    // the first answer is pressed.
    var cards = root.querySelectorAll(".lab-card:not(.lab-card--free)");
    var done = 0;

    // Answered is anything but "not done" and "pending" — which the buttons
    // themselves say, so the count does not need the vocabulary either.
    cards.forEach(function (card) {
      var chosen = card.querySelector('[data-lab-status="' + labStatus(card) + '"]');
      if (chosen && !chosen.hasAttribute("data-lab-unanswered")) done += 1;
    });

    return {
      done: done,
      total: cards.length,
      pct: Math.round((done / Math.max(cards.length, 1)) * 100)
    };
  }

  /* A group's own bar, wherever it is drawn: over its tests, and on the line
     the card shows while it is shut. */
  function paintGroup(section, number, pct) {
    section.querySelectorAll(
      '[data-lab-group-head="' + number + '"], [data-lab-sum="' + number + '"]'
    ).forEach(function (place) {
      var fill = place.querySelector("[data-lab-group-fill]");
      if (fill) fill.style.width = pct + "%";

      var label = place.querySelector("[data-lab-group-pct]");
      if (label) label.textContent = pct + "%";
    });
  }

  /* The figure at the top of the card: each group's progress, times what that
     group is worth.

     The weights are on the markup, one per group, because they are the
     server's — App\Libraries\LabProgress holds them — and a second copy here
     would be a second thing to keep in step. A sheet with no weights on it at
     all (nothing has given that side a table) falls back to counting cards,
     which is what the server does for it too.

     A group with no cards contributes nothing and keeps its weight out of the
     total by doing so, exactly as the server has it. */
  function labWeighted(section, groups, plain) {
    var weighed = false;
    var sum = 0;

    groups.forEach(function (group) {
      var weight = parseInt(group.getAttribute("data-lab-weight"), 10) || 0;
      if (!weight) return;

      weighed = true;
      sum += weight * (labCount(group).pct / 100);
    });

    return weighed ? Math.round(sum) : plain;
  }

  /* "3 of 7 completed", the bar and the percentage — the card's, and each
     group's. */
  function updateLabSummary(section) {
    var whole = labCount(section);
    // Each group's bar sits beside its heading and its cards are elsewhere —
    // the Other group's heading is outside the fieldset, to keep Add lab
    // pressable — so the number on the markup is what ties them together.
    var groups = section.querySelectorAll("[data-lab-group]");

    // Still cards: that sentence is about cards and is read as such.
    var count = section.querySelector("[data-lab-count]");
    if (count) count.textContent = whole.done + " of " + whole.total + " completed";

    var pct = labWeighted(section, groups, whole.pct);

    var fill = section.querySelector("[data-lab-fill]");
    if (fill) fill.style.width = pct + "%";

    var label = section.querySelector("[data-lab-pct]");
    if (label) label.textContent = pct + "%";

    groups.forEach(function (group) {
      paintGroup(section, group.getAttribute("data-lab-group"), labCount(group).pct);
    });
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

  /* ---- "Other" on an Add screen -------------------------------------------
     On a record, Other is a heading with an Add lab button under it and a card
     for each test that has been added. The Add screens show the same thing,
     but nothing there can post an added test: there is no record for it to
     belong to until Save. So the screen renders one blank card and a hidden
     button, and this file swaps which of the two is showing — the card becomes
     the template it adds copies of, and the button becomes what adds them.

     Which leaves the no-script case holding the half that works on its own:
     the blank card, there to be typed into, with no button beside it offering
     something it cannot do.

     A copy is the blank card exactly as it was found — before anybody typed in
     it, so there is nothing to clear afterwards — with its index bumped in the
     field names the form posts under and in the ids its labels point at. The
     index is ours and is a number, so the replacing is exact. */

  function initBlankLabCards() {
    document.querySelectorAll("[data-lab-add-blank]").forEach(function (head) {
      var section = head.closest("[data-lab-section]");
      var number = head.getAttribute("data-lab-group-head");
      if (!section || !number) return;

      // The group's own grid, found by its number and inside this workup: a
      // screen showing two of them numbers each from one.
      var grid = section.querySelector('.lab-grid[data-lab-group="' + number + '"]');
      var button = head.querySelector("[data-lab-add-more]");
      if (!grid || !button) return;

      var blank = grid.querySelector(".lab-card:last-child");
      if (!blank) return;

      var field = head.getAttribute("data-lab-add-blank");
      var template = blank.outerHTML;
      // The index the template carries, and the one the next copy gets. Both
      // are needed: every copy is made from the same untouched template, so
      // what is replaced is always the template's own number.
      var from = Number(blank.getAttribute("data-idx"));
      var next = from - 1;

      if (!template || isNaN(from)) return;

      // The swap: the card goes, the button arrives, and Other reads as it
      // does on a record.
      blank.remove();
      button.hidden = false;

      // Dropping one is the same for every copy, so it is listened for on
      // the grid rather than bound to each card as it arrives.
      grid.addEventListener("click", function (e) {
        var drop = e.target.closest("[data-lab-drop]");
        if (!drop) return;

        var card = drop.closest(".lab-card");
        if (card) card.remove();
      });

      button.addEventListener("click", function () {
        next += 1;

        var holder = document.createElement("div");
        // `labs[74]` -> `labs[75]` in every name, and `labs-74-` -> `labs-75-`
        // in every id and the labels that point at them.
        holder.innerHTML = template
          .split(field + "[" + from + "]").join(field + "[" + next + "]")
          .split(field + "-" + from + "-").join(field + "-" + next + "-")
          .split("lab-new-" + field + "-" + from).join("lab-new-" + field + "-" + next)
          .split('data-idx="' + from + '"').join('data-idx="' + next + '"');

        var card = holder.firstElementChild;
        if (!card) return;

        // The way back out of a press. Hidden in the markup because only this
        // file can take a card off the form, and shown on each copy as it is
        // made — the template itself never goes on the screen.
        var drop = card.querySelector("[data-lab-drop]");
        if (drop) drop.hidden = false;

        grid.appendChild(card);
        // Typing is what somebody came to do, so the new card's name box is
        // where the cursor goes.
        var name = card.querySelector(".lab-name-field");
        if (name) name.focus();
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

  /* ---- A note that answers a choice ---------------------------------------

     A line that is only true of what somebody has just picked, and is only
     shown once they have picked it. Unlike the reveals above it does **not**
     sync on load: a page arriving is not a choice being made, and a note
     standing under a field at all times is small print rather than an answer.

     It is written hidden, so with this file blocked it is not on the screen
     at all — which is the honest state for a line that reports a press. */

  function initChoiceNotes() {
    document.querySelectorAll("[data-note-for]").forEach(function (note) {
      var select = document.getElementById(note.getAttribute("data-note-for"));
      if (!select) return;

      var when = (note.getAttribute("data-note-when") || "").split(",").map(function (v) {
        return v.trim();
      });

      select.addEventListener("change", function () {
        note.hidden = when.indexOf(select.value) === -1;
      });
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

  /* ---- Staying where you were ---------------------------------------------

     Pressing Edit, or Save, or Cancel, or Add lab loads a page. A page starts
     at the top. On a record that is seventy cards long that means the card
     somebody was working on is now a screen and a half away, and they have to
     find it again — every time, for every card.

     So the position is remembered as the page is left and put back when that
     same screen comes round again — keyed by its path, so a record remembers
     its own place and not some other record's, and opening a record for the
     first time starts where a page starts.

     A handful of screens' worth and no more. Nobody is retracing twenty
     screens, and a store that only grows is a store that eventually holds
     something wrong.

     An enhancement and nothing more. With this file blocked every button does
     exactly what it did before — the page loads at the top, which is where it
     always loaded. Nothing about what a press *does* is in here.

     An address with a `#` on it is already being sent somewhere by the
     browser, and that is the right answer when there is nothing remembered —
     a link somebody was given, opened cold, lands on the card it names. When
     there *is* something remembered for that screen this wins, because the
     anchor puts the card at the top of the window and this puts it back
     exactly where it was. Back and forward are left alone: the browser
     restores those itself, and better. */
  var SCROLL_KEY = "ui-scroll";
  var SCROLL_KEEP = 8;

  /* What is in the store, or an empty one. Never throws: a private window and
     storage switched off both look like having nothing remembered, which is
     the state this started from. */
  function scrollStore() {
    try {
      var read = JSON.parse(window.sessionStorage.getItem(SCROLL_KEY) || "[]");
      return Array.isArray(read) ? read : [];
    } catch (e) {
      return [];
    }
  }

  function rememberScroll() {
    var path = window.location.pathname;
    var kept = scrollStore().filter(function (entry) {
      return entry && entry.path !== path;
    });

    // Newest first, so the oldest is what falls off the end.
    kept.unshift({ path: path, y: window.scrollY });

    try {
      window.sessionStorage.setItem(SCROLL_KEY, JSON.stringify(kept.slice(0, SCROLL_KEEP)));
    } catch (e) {
      /* Nothing to do: the page loads at the top, which is what it did
         before any of this. */
    }
  }

  function restoreScroll() {
    var path = window.location.pathname;
    var found = scrollStore().filter(function (entry) {
      return entry && entry.path === path;
    })[0];

    var y = found ? parseInt(found.y, 10) || 0 : 0;
    if (y <= 0) return;

    window.scrollTo(0, y);
    // Again once the images and fonts have settled, which is what moves a
    // long card list under somebody. The browser clamps it to the page, which
    // is the right answer when the page has got shorter — pressing Save on
    // the workup replaces a card where every test is open for answering with
    // one where none is, and there is simply less page to come back to.
    window.addEventListener("load", function () {
      window.scrollTo(0, y);
    });
  }

  /* ---- A card you shut stays shut ----------------------------------------

     The foldable cards arrive the way the screen thinks is most useful — the
     workup open on a record and on a pair, because that is what somebody
     opened it to read. Somebody who disagrees shuts it, and then presses
     Edit, or Save, or a donor tab, and the page comes back with it open
     again, and they shut it again.

     So which cards are shut is remembered, per screen and per card, and put
     back. Only where it differs from what the screen sent: a card nobody has
     touched is left exactly as the server rendered it, so changing a default
     changes what everybody sees.

     A card that is open *for editing* is left alone whatever is remembered.
     Somebody pressed Edit to see inside it, and shutting it on them would be
     this remembering a preference against the thing they just asked for. The
     Save button inside is how that is known: it is only rendered on a card
     that is being edited. */
  var FOLD_KEY = "ui-folds";
  var FOLD_KEEP = 60;

  function foldStore() {
    try {
      var read = JSON.parse(window.sessionStorage.getItem(FOLD_KEY) || "[]");
      return Array.isArray(read) ? read : [];
    } catch (e) {
      return [];
    }
  }

  function foldKey(card) {
    return window.location.pathname + "|" + card.id;
  }

  function rememberFold(card) {
    var key = foldKey(card);
    var kept = foldStore().filter(function (entry) {
      return entry && entry.key !== key;
    });

    kept.unshift({ key: key, open: card.open });

    try {
      window.sessionStorage.setItem(FOLD_KEY, JSON.stringify(kept.slice(0, FOLD_KEEP)));
    } catch (e) {
      /* Nothing to do: the cards arrive as the screen sent them, which is
         what they did before any of this. */
    }
  }

  function initFolds() {
    document.querySelectorAll("details.card-fold[id]").forEach(function (card) {
      // Open to be edited: left as it is, and not remembered either — that
      // state is the screen's doing and not a preference.
      if (card.querySelector(".card-actions button.btn-save")) return;

      var key = foldKey(card);
      var found = foldStore().filter(function (entry) {
        return entry && entry.key === key;
      })[0];

      if (found) card.open = found.open === true;

      // Listening afterwards, so putting back what was remembered does not
      // count as somebody saying it again.
      card.addEventListener("toggle", function () {
        rememberFold(card);
      });
    });
  }

  function initScrollMemory() {
    // Remembered on the way out of every page, whatever brought somebody to
    // it: a screen reached by an anchor is still a screen they then press
    // Save on. `pagehide` is the one event that fires on every way out,
    // including the ones `beforeunload` is not allowed to see.
    window.addEventListener("pagehide", rememberScroll);

    // Back and forward restore themselves, better than this could.
    var nav = (window.performance &&
      window.performance.getEntriesByType &&
      window.performance.getEntriesByType("navigation")[0]) || null;

    if (nav && nav.type === "back_forward") return;

    // An address with a `#` on it is already being sent somewhere, and that
    // is the right answer when there is nothing remembered — a link somebody
    // was given, opened cold. When there *is* something remembered for this
    // screen it is the better answer: the anchor puts the card at the top of
    // the window, and this puts it back exactly where it was.
    restoreScroll();
  }

  document.addEventListener("DOMContentLoaded", function () {
    // Before the scroll is put back: a card unfolding changes how far down
    // everything under it sits.
    initFolds();
    initScrollMemory();
    initSidebar();
    initRowLinks();
    initMenus();
    initLabSections();
    initBlankLabCards();
    initDateFields();
    initDialogs();
    initAutoSubmit();
    initDialogClosers();
    initClosers();
    initMrnFields();
    initAnswerPickers();
    initConfirmButtons();
    initReveals();
    initChoiceNotes();
  });
})();
