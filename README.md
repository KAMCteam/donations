# Donations — Organ Donation & Pairing System

Patient, donor and pairing registry for the kidney/liver transplant programme,
built on **CodeIgniter 4.7** and **PHP 8.2+**.

This application was migrated from CodeIgniter 3 (see
[Migration notes](#migration-notes-codeigniter-3--4) below for what changed and where things moved).

## Requirements

- PHP **8.2** or higher, with the `intl`, `mbstring`, `json` and `mysqli` extensions
- MySQL / MariaDB, with a `donations` schema
- Composer
- `odbc` extension — only if the TrakCare demographics lookup is used

## Getting started

```bash
composer install
cp env .env          # then edit .env (see below)
php spark migrate                          # create the schema
php spark db:seed DatabaseSeeder           # programmes + lab catalogue
php spark serve      # http://localhost:8080
```

Set at least the following in `.env`:

```ini
CI_ENVIRONMENT = development

app.baseURL = 'http://localhost:8080/'

database.default.hostname = localhost
database.default.database = donations
database.default.username = your_user
database.default.password = your_password
database.default.DBDriver = MySQLi
database.default.port = 3306
```

The MRN lookup in `App\Models\ServicesModel` talks to a TrakCare (InterSystems
IRIS) instance over ODBC. Its credentials are **not** in source — set them in
`.env` when the lookup is needed:

```ini
trak.server   = 10.x.x.x
trak.username = your_user
trak.password = your_password
```

Leave them blank and the lookup returns an `ODBC connection failed` payload
instead of throwing; the rest of the application is unaffected.

### Deployment

Point the web server's document root at **`public/`**, not at the project root —
`index.php` lives there in CodeIgniter 4. The CodeIgniter 3 version ran from the
project root under a `/donations/` sub-path; if that URL has to be kept, use an
alias to `public/` rather than the old `.htaccess` rewrite.

## Screens

| Route | Purpose |
| --- | --- |
| `/` | Entry point — the login screen, or the dashboard when already signed in |
| `/login` | Staff login |
| `/organ` | Programme picker (kidney / liver) |
| `/dashboard` | Programme statistics and high-priority waitlist |
| `/recipients` | Recipient waitlist, filterable by blood type and status |
| `/recipients/print` | The filtered waitlist as a printable sheet |
| `/recipients/new`, `/recipients/{id}` | Add / open a recipient |
| `/donors` | Donor registry, filterable by blood type and status |
| `/donors/print` | The filtered registry as a printable sheet |
| `/donors/new`, `/donors/{id}` | Add / open a donor |
| `/pairs` | Pairs register, filterable by blood type and status |
| `/pairs/export` | The filtered pairs as CSV |
| `/pairs/new`, `/pairs/{id}` | Add / open a pair |
| `/exchange` | Paired exchange: the chains, and the builder; filterable by the recipient's blood type |
| `/reports` | Both registers read across, under nine filters |
| `/reports/export/{general\|internal}` | The filtered report as a printable sheet |
| `/mrp` | Register users: physicians and coordinators |

Auto-routing is off, so `app/Config/Routes.php` lists every reachable endpoint.
The programme is chosen once per session on `/organ` and kept in the `ui_organ`
session key; there is no longer a filter gating the rest of the site on it.

### These screens replaced the CodeIgniter 3 ones

The application was migrated from CodeIgniter 3 with its original screens —
`Patient`, `MRP`, `WaitingList`, `UpdatePatient`, `Pairs`, `Dashboard`, `Organ`
and `Test`. They have been **removed** and the screens above, built from the
`donations_html_ui` design package, took their place at the root of the site.
Removed with them: those controllers, their views and partials under
`app/Views/`, the `organ` filter (`App\Filters\OrganFilter`) that gated them,
the now-unused `organ_chosen()` helper, and the `public/assets/css` and
`public/assets/js` that only styled them. Their git history still holds all of
it if any of it is wanted back.

What was **kept**: `app/Models/*` (the database layer these screens will be
wired to next), `app/Helpers/`, `app/Language/`, and the brand images, SVGs and
font under `public/assets/{img,svg,fonts}`.

### HTML is the source of these screens

The design package arrived as a single-page app: one empty `<div id="app">` and
ten JavaScript files that wrote every screen into it as concatenated strings.
That was inverted during the merge — the markup is now HTML and JavaScript only
reacts to it:

| Concern | Where it lives |
| --- | --- |
| Every screen's markup | `app/Views/ui/*.php` — literal HTML with `foreach` loops |
| Shell, sidebar, top bar | `app/Views/ui/layout.php`, `layout_bare.php` |
| Lab-tests card | `app/Views/ui/partials/lab_tests.php` |
| Screen selection, form posts | `app/Controllers/Ui.php` |
| Records | `app/Libraries/UiStore.php`, over `app/Models/*` |
| Inline SVG icons, tone lookups | `app/Helpers/ui_helper.php` |
| Stylesheets and images | `public/assets/ui/` — copied byte-for-byte |
| Behaviour only | `public/assets/ui/js/ui.js` — one file, ~190 lines |

Navigation, filtering, sorting, opening a card for editing and saving are links
and form posts, so every screen renders, navigates and submits **with
JavaScript switched off**. `ui.js`
is left with the mobile sidebar, the lab cards (status buttons, result editor,
running totals), whole-row click targets, opening the pairing choice as a
dialog, and the date fields (slashes as you type, and the calendar button).

The result was checked against the design package screen by screen with
full-page screenshot diffs at 1440px. Login, the programme picker, the pairs
register and Add MRP are pixel-identical; the rest differ by under 400 pixels
out of 1.4–3.3 million, all of it antialiasing on a single character boundary in
labels such as "4 unmatched donors" or "Back to Recipient Waitlist". The
package built those strings with a helper that inserted an empty HTML comment
between each part to imitate React's text nodes, which changes how the browser
kerns the join; the views here emit one ordinary string and let the browser
shape it normally.

### A record is read first, then edited a card at a time

The recipient, donor and pair screens open read-only. Each card — personal
information, the lab workup, clinical notes, and on a pair its own details —
carries an **Edit** of its own, which opens just that card with Save and
Cancel; the rest stay as they are.

Edit is a link (`?edit=personal`) and the card comes back as a form, so this
works with JavaScript switched off like everything else here. A card that is
not open renders inside a disabled `<fieldset>`: every value sits exactly
where it does when editable, and the browser posts none of it. That is what
keeps saving the notes from touching the crossmatch date. The server does not
take the browser's word for it — the post names its card, and only the fields
that card owns are applied, so a stale tab or a hand-made post cannot reach
past the card it claims to be.

One consequence worth stating: because an open card posts all of its own
fields, an emptied box now means the value was removed, and a nullable column
is cleared. A wrong phone number could not be taken off a record before. A
column that cannot be NULL — a name, a blood group — keeps what it had, since
blanking one is a slip rather than an instruction.

The add screens are unchanged: a new record has nothing to read yet, so it
stays one open form with a single Save.

### A pair and its donors

A pair is a recipient and the donors being worked up for them. One of those is
the donor the pair is going ahead with and there is never more than one; the
rest are being held — **On Hold** while they are still a possibility, **Declined**
once they are not — and both are still the pair's donors, so either can be
taken back up.

There is no such thing as a potential donor. A recipient with no pair has
**Link with Donor**, which makes the pair in one step, and every donor after
the first is added on the pair's own screen. Nothing in between.

#### The recipient's record: one button, and only until there is a pair

**Link with Donor** opens a `<dialog>` with the two ways in, the same two the
donor's record has always had:

- **Link with a new donor** — Add Pair, with the recipient already filled in
  and shown read-only.
- **Link with an existing donor** — the donors on this programme who are free,
  one form, the relationship and crossmatch date beside the choice.

Either way the pair exists as soon as it is answered, and the pair's own screen
opens. The button is on the record only while the recipient has no pair; once
they have one it reads **Linked: <name>** and goes to it, because that is where
their donors are.

#### The pair's screen: the donors, as tabs

Under the recipient's three cards, each donor the pair has ever had is a tab —
**Donor-1**, **Donor-2**, in the order they were taken on — with the donor's own
status beside the name as a plain word, in no colour: *Active*, *On Hold*,
*Declined* are three ordinary answers, and a colour here would be read as a
warning. A tab the pair has finished with says **· Archived** as well.

Opening a tab shows that donor as the same three cards the record screens use —
**Donor — Personal Information**, **Donor — Required Lab Tests**, **Donor —
Clinical Notes** — each opened for editing on its own and each saving on its
own. The section a card posts names the link as well as the card
(`pd12-personal`), because a pair may have several donors on the screen and the
server has to know which one a save is about. They write the donor's own
record, so a change here is a change there.

**Add donor** puts another one on the pair, as a new donor (Add Donor opened
with `?pair=<recipient MRN>`) or one already on the register. Both ask the word
the pair starts them on, and both refuse **Active** while the pair already has
its own — the control does not offer it, and the store refuses it if it is
posted anyway.

#### One active donor, and the two ways to change which

**One donor to a pair may be Active.** A pair that said it was going ahead with
two people would be saying nothing, so everything that could make a second one
asks first: the tab's status control, the donor's card behind it, and both
doors of Add donor.

There are two ways to change which donor that is, and they are not the same:

- **Move the words about.** Stand the current one down to On Hold or Declined,
  then set another to Active. Nobody is archived; both are still the pair's
  donors and either can be taken back up.
- **Swap**, which is only ever offered on the active donor — swapping a reserve
  would be swapping nothing. The one swapped out is **archived**: finished with,
  kept read-only, with the word they were given still on their tab. The one
  swapped to is the pair's donor from that moment.

#### Archived is a mode, not a status

An archived tab is a link that is closed. The donor keeps whatever word they
were given — archiving is the pair's doing and says nothing about them — and
because the link is closed they are free again: their own record is editable,
they are back on the Donors List, and they can be linked to somebody else.

Exactly two things archive a donor:

| | What happens |
| --- | --- |
| **Delink** | Available on every live tab. On a reserve it archives that donor and nothing else. On the active one it asks the further question: carry on with another donor, or take the pair apart. |
| **A swap** | The donor swapped out is archived, and the one swapped to is the pair's. |

Taking the pair apart closes every link at once: the recipient goes back to the
waiting list, every donor back to the register, and the pair's screen stays
with every tab on it archived — which is where anybody asking what happened
goes. Nothing is deleted.

An archived tab carries its own dates and the reason it ended, so the tabs are
the history: *Linked 30/09/2026, archived 04/10/2026. Swapped for Nouf
Al-Shamrani.* There is no separate archive, because `pairs` **is** the log —
one row per link ever made, and closing one is how a link ends.

#### Where it is stored

`pairs` holds it all: one row per donor ever linked to a recipient, with the
link's own status. Archived is `status = 'closed'`, which is the same row state
that frees both sides — so being archived and being released are one fact, not
two that could disagree. The donor's Active / On Hold / Declined is
`donors.status`, their own, read wherever they are.

`potential_donors` is gone, and so is the middle state it held: its rows became
pairs, a declined candidate becoming an archived one. `donors.is_listed` stays
as a column but nothing writes 0 to it — it existed so a donor entered as
somebody's candidate stayed off the register until a pair was made, and a donor
is only ever entered into a pair now.

### Pairing a donor from their own record

A donor goes the other way and still pairs in one step, because a donor has one
recipient. "Link with Recipient" opens the same kind of choice:

- **Link with a new recipient** — Add Pair, with this record already filled in
  and shown read-only, and only the other person to enter.
- **Link with an existing recipient** — the recipients on this programme who
  are not already paired, one form: the relationship and crossmatch date are
  entered once at the top and each row's Link button carries that person's MRN.

The choice is a `<dialog>` on the record, opened by `ui.js`. The button under it
is a real link to `donors/{id}/link`, the same choice at its own URL, so with
JavaScript off it is simply followed. A donor already in an open pair is sent to
that pair rather than offered a second one, and a person who holds a row in both
registers under one MRN is not offered as their own counterpart.

### The search narrows the list you are on

Above the page header and centred in the content, on the five screens that
have a list to narrow — Recipient Waitlist, Donors List, Pairs List, Paired
Exchange, Reports. The dashboard, Add MRP and the record screens have nothing
for it to do, so they do not carry it.

It never leaves the screen. The form posts back to the same address with the
filters already showing carried as hidden fields, so searching narrows what is
on the page rather than replacing it — and the chips carry `q` in their own
links, so pressing one keeps the search. Both end up in the address together
(`?bt=A&q=Dosari`), which the Export PDF button then takes with it, so the
sheet is what the screen was showing.

What each screen matches is its own: the two registers match an MRN or a name
in SQL; the Pairs List and Paired Exchange match either of a pair's people by
number or name, and the Pairs List matches the pair number as well; Reports
narrows the rows its nine filters chose.

Which screens have the box is decided in the layout from the `navPage` every
screen already declares, not from a flag each would have to pass. CodeIgniter
keeps view data between `view()` calls, so a screen that simply forgot the flag
would inherit the last screen's — which is how the dashboard first came to have
a search box it was supposed not to have.

### Filtering the two registers

The Recipient Waitlist and the Donors List carry the same two chip rows the
Pairs List does — **Blood type** and **Status** — and they narrow together: each
chip rebuilds the address keeping the other filter, so `?bt=A&status=on_hold`
is one list rather than two that overwrite each other. A filter on `all` drops
out of the URL, so a plain list has a plain address.

Both carry an **Export PDF** beside their Add button, and it exports what is on
the screen: the filtered rows, in the columns the list shows, with the filters
named on the letterhead so a printout says what it is a printout of. The two
sheets are one view — `ui/register_print` — because they are the same document
with different columns, and the caller hands the cells over already formatted.

### Paired Exchange: the status is the offer

A pair reaches the Paired Exchange list two ways, and they are the same way.
**Pair Exchange** on the pair's own screen puts it forward, and so does setting
the pair's **status** to Paired Exchange — the status already says what the
button says, and making somebody say it twice only let the two disagree: a pair
marked Paired Exchange that was not on the exchange list.

So the status writes `for_exchange` with it, on a save and on a new pair alike.
While the status says it, there is nothing to press: the header shows *On the
exchange list* and no Withdraw, because withdrawing would be undone by the next
save of the card. Taking the pair off means saying something else on the card,
which is where the status is. A pair put forward by the button under any other
status still withdraws by it, since the status is not what put it there.

Paired Exchange has a blood-type row too, labelled **Recipient blood type**,
and it asks a different question from the others': the Pairs List's matches a
pair when *either* side has the group, and this one is the recipient's alone.
An exchange exists because a donor cannot give to their own recipient, so
matching either side would hide the very pairs that make one work. What
somebody narrowing that list wants is the recipients needing a group they have
somewhere to place. It has no status row — being on the list is already a
status, and the few a pair can hold there are true of every row on it.

Both are applied in SQL, not by filtering a loaded array, because the waitlist
is ordered by a computed score and PHP cannot sort by it. Status offers the
three a record is ever set to — On Hold, Active, Declined — since the rest of
`STATUS_OPTIONS` belongs to a pair or is retired, and offering those would be
offering empty lists. An address naming a status nobody can choose is read as
no filter at all.

### What a recipient record holds

Diagnosis, Hospital and the four-level Urgency scale came off the recipient.
Urgency is one yes/no question now — a checkbox, stored as `is_urgent` — and
the waiting list reads urgent first, then by score, so the score still orders
each group. Recipient Coordinator was added, and works like the donor's: the
design collects it as free text, so typing a name looks it up and registers it
if it is new.

`donors.hospital` went with them. The donor screen never had the field; the
column was only ever filled from the recipient form's Hospital box, so with
that gone nothing could write it and nothing showed it.

Added since: `birth_date` on both registers, `dialysis_type` on recipients, and
an Entry Date the screens collect rather than only show. The three sections
below say what each is for.

### Donor type

What kind of donation this is, asked with the list the screen can actually
answer:

| Screen | Offers |
| --- | --- |
| Add Donor | Living · Deceased |
| Add Pair, pair profile | Living Related · Living Unrelated · Deceased |

Relatedness is a question about a donor *and* a recipient, so it can only be
answered where both are in view. Registering a donor on their own records
`living`, which is the same kind of donation with that part not yet known —
not a fourth kind. One column, `donation_type`, holds all four.

A saved donor record offers the full list rather than the two the add screen
asks, so a donor registered through a pair as Living Related is not silently
downgraded to Living by someone opening their record.

### The workup is the check list

`lab_parents` and `labs` hold the transplant check list, group for group and
test for test — the recipient's pre-transplant sheet (71 tests) and the
donor's pre-Tx sheet (51), in the sheet's own order:

Immunology tests · Hematology/Biochemistry · Infectious workup · Urine/stool ·
Cancer screening · Imaging · Referrals and Clearances · Vaccinations

The record screens show it that way too: a group heading, then that group's
tests beneath it. Where both sheets ask for the same test it is stored once
and marked `both`; where only one does, it is marked for that side — so a
donor sees 51 cards and a recipient 71. The spellings that differ between the
sheets for one test ("Ca/Phos/Mg" and "Calcium/Phosphorus/Mg") are reconciled
rather than stored twice, and the donor sheet's abbreviated headings use the
recipient's fuller wording so one set of headings serves both.

Each card offers its own test's answers, from the sheet — not one generic
Pending / Done / Flagged:

| `result_type` | The card offers |
| --- | --- |
| `blood_group` | Not done · A · B · AB · O |
| `done` | Not done · Pending · Done · N/A |
| `positive_negative` | Not done · Pending · Positive · Negative · N/A |
| `acceptable_abnormal` | Not done · Pending · Acceptable · Abnormal · N/A |
| `cleared_not_cleared` | Not done · Pending · Cleared · Not cleared · N/A |
| `given_not_given` | Not done · Given · Not required · Not given · N/A |

`not_done` starts them all — nobody has looked yet — and N/A ends most, since
a test that cannot apply to this patient is a real answer; the sheet adds it
to several vocabularies by hand. The answer is stored in `lab_results.status`,
and the bar counts the tests that have one, whichever it is. An answer a test
does not offer is refused rather than stored, checked against the catalogue
rather than against the form.

Red marks the answer somebody has to act on — Positive, Abnormal, Not cleared
— not merely an unwelcome one.

The sheet names no organ, so both programmes carry both lists.

### A test added under "Other" says what it answers

The check list's tests each answer a fixed question, because the sheet they
come from asks one. A test added with **Add lab**, under the group the sheet
calls Other, has no sheet behind it — only the person adding it knows whether
it says Cleared, or Seen, or something the platform has no word for at all. So
the card asks, under **What this test answers**:

- Every answer the platform has, seventeen of them, each a tick box — Not
  done, Pending, Done, Acceptable, Abnormal, Negative, Positive, Applicable,
  Not applicable, Cleared, Not cleared, Given, Not given, Required, Not
  required, Seen, Not seen.
- A box at the foot for an answer of their own: type a name, save, and it is
  on the card with the rest. Renaming it renames the answer; rubbing the name
  out removes it, which is the same gesture as unticking one of ours.
- A swatch beside each, opening the colours the check list's own answers use.
  The palette names what each colour is for — *Act on this*, *Outstanding*,
  *Recorded* — because a colour on a medical record means something, and a
  wheel of sixteen million would mean nothing. An answer starts with **no
  colour**: the swatch is empty until one is picked, and *No colour* is the
  first thing in the palette so it can be taken back off again. A colour means
  somebody chose it, which it would not if every answer arrived wearing one.

Ticked answers are the buttons on the card, those with a colour in it and the
rest plain,
and the list itself is only on screen while the card is being edited: once it
is saved the card shows the chosen answers and nothing else. Its own **Edit
results**, beside the pill, opens the workup at that card with the ticks,
the names and the colours as they were left. Until a new test is given any
answers it offers Not done · Pending · Done, which is what most of them want.

The set lives in `labs.answer_set`, JSON, on the test's own row — one set per
test, so two tests on one record are independent, and a test on one record is
nothing to do with the same-named test on another. A catalogue row's
`answer_set` is NULL and its card has no list: the sheet has already said what
those answer. Because an answer somebody invented cannot be a value in an
ENUM, `lab_results.status` is a `VARCHAR(60)`; keys for invented answers are
slugs prefixed `c_`, which is what keeps them from colliding with ours.

### Status: three facts, not one

A recipient's status, a donor's, and the Match Status of a pair were once one
value shared between screens — setting it on either set the other. They are
three separate facts now, and nothing carries one to another:

| Where | What it says |
| --- | --- |
| **Recipient Status**, on the record and on the pair's recipient card | Is this person on the programme |
| **Donor Status**, on the record and on the pair's donor card | Is this person still being worked up |
| **Match Status**, on the pair's own card | What became of this link |

What broke the old arrangement was a recipient being allowed more than one
donor: with three links open, there is no saying which of them a Declined on
the person was about. So closing a pair no longer closes the recipient,
declining a recipient no longer declines their donors, and each of the three
selects is saved by the card it sits on and no other.

`UiStore::STATUS_OPTIONS` still holds every word any of them can take, so a
value stored before this reads correctly wherever it appears. What each screen
*offers* is narrower: `PERSON_STATUS_OPTIONS` and `DONOR_STATUS_OPTIONS` are On
Hold / Active / Declined, and `PAIR_STATUS_OPTIONS` adds the three that are a
pair's alone.

One of those means more than its label: **Closed** is what "open pair" is
defined against, so closing a pair puts both sides back on their lists. That is
the link's doing, not a change to either person's own status.

Two of them bring a question with them, and the Pair Details card asks it where
the answer belongs — beside the word, and only while the word is on the screen:

| Status | What it asks for | Column |
| --- | --- | --- |
| **Closed** | Why was it closed? | `closed_reason` |
| **Transplanted** | Date of Transplant | `surgery_date` |

Both are kept only while their status holds. Moving a pair off Closed clears
the reason and moving it off Transplanted clears the date, because a sentence
about an ending that was undone, or a day for a transplant that was taken back,
is worse than nothing. One `<select>` reveals both: `data-reveal` and
`data-reveal-when` carry a list each and are read in step, and with `ui.js`
absent the blocks are simply always visible — the server drops what does not
belong either way.

### Age is a date of birth

Age used to be typed as a number, which is a fact with a shelf life: right on
the day it was entered and quietly wrong every year after, with nothing in the
system to ask again. The field collects a **date of birth** now, and the age it
comes to today is written beside the field's own label — not a second box to
fill, the same answer read out. It updates as the date is typed, and the server
works out the same number when it saves.

`age` is still a column, because every list, filter and report reads it and
because the records entered before `birth_date` existed have nothing else. It
is rewritten from the birth date whenever one is saved, so the two cannot
disagree; a record with no birth date keeps the number it was given.

### Dialysis: which kind, and whether there is a date at all

`dialysis_type` says which: **Hemodialysis**, **Peritoneal dialysis** or
**Preemptive dialysis**. It is asked before First Dialysis because it decides
whether that question has an answer — pre-emptive means a transplant before
dialysis ever begins, so there is no first one. Choosing it closes the date
field on the screen and clears the column on save, and the Pairs List and both
reports carry the kind in a column of its own.

### No date in the future

Everything the personal details collect has already happened: when somebody was
born, when their dialysis began, the day they joined the register. So those
fields will not take a later date. The calendar stops at today, `ui.js` marks a
later one typed straight in, and the controller refuses the save — three
guards, because the first two are in the browser and the browser is not where
the record is kept.

**Entry Date** is one of them, and is now a field rather than a read-out: a new
record opens on today, which is almost always right, and somebody entering a
patient who arrived last week can say so. Nothing else moves it — editing any
other card used to re-date the record to the day of the edit.

### Reports

Every other screen answers one question — who is waiting, who is free, which
pairs there are. `/reports` answers whatever is asked of it: both registers in
one table, under nine filters, with two ways of taking the answer away.

One rule decides the filters. Each is a list of checkboxes, each starts empty,
and **empty means all** — so an untouched filter narrows nothing and the query
does not mention it at all. That is one rule for all nine rather than a default
per filter, and it is why the button on a filter reads "All" both when nothing
is chosen and when everything is: those select the same records.

The columns are not the user's to choose. The **record type** decides them,
because a donor has no entry date and a recipient has no donor type:

| Record type | Columns |
| --- | --- |
| Recipient | Recipient MRN, Recipient Name, Age, Blood Group, MRP, Gender, Phone Number, Type Dialysis, First Dialysis, Entry Date, Related Donor, Relationship, Status, Date of Crossmatch |
| Donor | Donor MRN, Donor Name, Age, Blood Group, MRP, Gender, Phone Number, Donor Type, Related Recipient, Status, Date of Crossmatch |
| Both, or neither | MRN, Name, Age, **Type**, Blood Group, MRP, Gender, Phone Number, Type Dialysis, First Dialysis, Entry Date, Related Donor/Recipient, Relationship, Status, Date of Crossmatch |

**Type** appears only in the mixed table, and only there because only there is
it needed: the other two sets say which register a row came from in their own
headings — "Recipient MRN", "Donor Name" — and a table of both cannot. It is
fixed, not one of the optional columns, for the same reason.

The Columns filter offers only the five a report may or may not be about —
Type Dialysis, First Dialysis, Entry Date, Relationship, Date of Crossmatch —
and all five start on. The rest are what a row *is*, and a table without them
could not be read. A column the chosen record type does not have stays off
whether or not it is ticked: there is nothing to show.

Related Donor/Recipient is a list, not a single name: a recipient may hold
several donors, and the cell names each one with their MRN.

Both exports print the rows the filters chose and no others, which is the point
of exporting from here rather than from a register. **General** is the table as
it stands, in the columns showing at the time. **Internal** is that table
followed by each of its rows opened out — the whole record and the whole
workup, one per page — built from `App\Libraries\RecordBlocks`, the same
blocks a single record's own printed sheet is made of, so the two sheets cannot
drift apart.

### Add MRP is where users are made

It is the one screen that creates somebody a record can be assigned to, and a
transplant programme assigns two kinds. The form asks which — **Doctor** or
**Coordinator** — before it asks the name, because that is what the name is
being entered as. `mrp.kind` holds it, and everybody registered before the
column existed is a physician, which is all the screen could make.

A record's **MRP** field offers physicians only: it asks for the responsible
physician, and a coordinator there would be an answer the question does not
take. A coordinator registered here also gets a row in `coordinators`, which is
what `recipients.coordinator_id` and `donors.coordinator_id` point at — without
both, somebody registered here could not be assigned to anybody.

**Search**, beside the MRP ID, is the hospital directory. A user is not
invented on this screen: they already exist in the directory with an ID, and
the right way to add one is to look them up by it. The directory is not
connected yet, so the button is a real control with nothing behind it — it
comes back saying so, carries the ID that was searched for into the form, and
leaves the name to be typed meanwhile. When it is wired, what changes is what
fills `ui_mrp_lookup`, not this screen.

It will fill a name and an ID. It will not fill a password into this database:
a copied credential is a credential in two places, and the point of looking
somebody up in the directory is that the directory is where their sign-in is
checked. There is no password field on this screen for that reason.

**Registered MRPs** under it is a table — Name, MRP ID, Type, Status, and the
two controls. **Edit** turns the row into a form where the row is, as a
record's card does. **Deactivate** is never a delete: the records they are on
still name them, and a physician who has left is part of what those records
say. A deactivated user stays on the list, greyed, with **Reactivate**, and
stops being offered on new records.

### Dates

Every date the screens collect is DD/MM/YYYY, in a box you can simply type
eight digits into — `ui.js` puts the slashes in as you reach them and drops
anything that is not a digit. Beside it is a calendar button that opens a
native date picker; choosing a day writes the date back in the same order.

The picker is a second `<input type="date">` with no name, which posts
nothing. It is not the field itself on purpose: a native date input renders in
the browser's locale, which on an English profile is MM/DD/YYYY — the one
order a clinical record must not show — and posts ISO. Typing still works with
JavaScript off, exactly as it did before there was a picker.

### Medical record numbers

The MRN is the hospital's own number — it arrives with the patient, off
TrakCare — so the Add Recipient, Add Donor and Add Pair forms collect it and
the system never invents one. It used to render as a read-only "Auto" and get
assigned as the highest existing number plus one, which would have filed
everybody under numbers that mean nothing to the hospital.

A save is refused, with the reason on the form and the rest of what was typed
still in it, when the number is missing, is not a number, or is already on that
register — the MRN is the primary key, so a second row under it would be one
person filed under another's identity. On Add Pair both numbers are checked
before either person is written: half a pair is worse than none.

The two registers are checked separately. The same MRN on both is one person
who is a recipient on one programme and a donor on another, which is allowed;
only the pair screen refuses it, where it would mean donating to oneself. On a
saved record the MRN is shown but not editable: it is what identifies the row.

### Where the data lives

Every screen reads and writes the database. `UiStore` is the translation layer
between the shapes the views expect and the tables in `app/Database/Migrations`:
it holds no records of its own, and each of its methods is a query through
`App\Models\*`. The only thing still kept in the session is which organ
programme you picked and whether you are signed in.

Two consequences worth knowing:

- **The waiting list is ordered by the real score.** `RecipientModel::SCORE_CALC`
  is the original system's formula — a tenth of a point per month waiting plus a
  tenth per month on dialysis — computed by the query rather than stored, so it
  keeps counting up on its own. The Score column shows it, the blood-group chips
  filter in SQL, and the dashboard's "most urgent" panel is the top of the same
  query. A recipient with no dialysis date scores nothing and shows a dash,
  which is what the original did.
- **A coordinator is registered by being typed.** The design collects the
  coordinator as free text and has no screen that registers one, but the column
  is a foreign key. Typing a name looks it up and creates it if it is new, so
  `coordinators` fills from use. Typing the same name twice reuses the row.

### Not wired up yet

One thing still stands between these screens and production use:

- **No real login.** `/login` accepts any non-empty Staff ID and password,
  exactly as the design package's did. The `staff` table is there for it to
  check against; until `Ui::attemptLogin()` does, this must not be deployed
  anywhere reachable.

## Database

The schema is the migrations in `app/Database/Migrations`. There is no `.sql`
file — the migrations are the only definition, so there is nothing to keep in
step with them:

```bash
php spark migrate
php spark db:seed DatabaseSeeder
```

**Upgrading a database made before the field changes.** Run `php spark migrate`
and it sorts itself out — `AlignExistingDatabases` asks the database what it
currently has and catches it up, keeping the records: High and Critical
urgency become urgent, a pair's `scheduled` becomes Confirmed, a recipient's
`ready` and `cancelled` land in the shared status list, and Diagnosis,
Hospital and the old urgency column go. It does nothing on a database made
from scratch, so there is one command either way.

It exists because the six `Create…` migrations were edited in place as the
screens changed rather than added to. That suits a fresh install, but a
database that ran them earlier keeps the old columns and `php spark migrate`
would otherwise find nothing to do — leaving screens that offer "Living
Related" or "Paired Exchange" failing on save with nothing to explain why.

The seeder inserts **reference rows only** — the two programmes and the lab
catalogue. No patients, no donors, no pairs, no staff accounts, no physicians
or coordinators. It is re-runnable and skips anything already there, so it will
not undo edits made through the screens.

### The tables, and why each one is there

Eleven tables, arrived at by walking the screens and asking what each reads and
writes.

| Table | Exists because |
| --- | --- |
| `staff` | The login screen needs something to authenticate against |
| `organ_programs` | The picker's cards are content — label, description, icon — not code |
| `mrp` | Every record screen assigns a most responsible physician |
| `coordinators` | Every record screen assigns a coordinator |
| `lab_parents` | The groups the workup is shown under: Immunology tests, Imaging, … |
| `labs` | The catalogue: which tests a workup is made of, per programme and side |
| `recipients` | The waiting list, and the two dates the score is computed from |
| `donors` | The donor register |
| `pairs` | One row per donor ever linked to a recipient: the pair, and its history |
| `lab_results` | One row per person per test: status, value, date |

Two tables rather than one for people, because a recipient and a donor are not
the same record. A recipient has `entry_date`, `dialysis_type`,
`dialysis_start` and `is_urgent`; a donor has `donation_type` and
`relationship`. Sharing one table
means four columns that are always NULL on one side. The same person can hold a
row in both under one MRN — they may donate on one programme and be listed on
another.

`labs` is separate from `lab_results` for the same reason: the definition of a
workup changes, and when it does no patient record should be touched. Retiring
a test hides it from new workups and leaves the results already recorded
against it alone.

### The score

Two columns on `recipients` carry it — `entry_date` and `dialysis_start` — and
the expression lives in one place, `RecipientModel::SCORE_CALC`:

```sql
(0.1 * TIMESTAMPDIFF(MONTH, entry_date,     CURDATE())) +
(0.1 * TIMESTAMPDIFF(MONTH, dialysis_start, CURDATE()))
```

A tenth of a point per month waiting, plus a tenth per month on dialysis: 20
months on the list and 30 on dialysis is 5.0. Unchanged from the original
system.

It is **computed at read time and never stored**, so it keeps counting up on
its own and cannot go stale — there is no job to run and no column to refresh.
Its one quirk is kept: `dialysis_start` is nullable and NULL plus a number is
NULL in SQL, so a recipient with no dialysis date scores NULL rather than
counting only the waiting time. `score_waiting_only` sits beside it for anyone
who needs a number in that case.

`urgency` is declared `ENUM('low','medium','high','critical')` — least-urgent
first — because MySQL sorts an ENUM by declaration index, so the waiting list's
`ORDER BY urgency DESC, score DESC` reads most-urgent-first, then highest score
inside each band.

### The linking system

One rule runs through all of it, written once as `PairModel::CLOSED`:

> **A pair is open unless its status is `closed`.**

Everything follows from that:

- A recipient is on the waiting list when no open pair holds them.
- A donor is on the register when no open pair holds them.
- Closing a pair releases both sides, and either can be linked again — to each
  other or to somebody else.
- `closed` is a status, not a deleted row, so a failed match stays on the
  record with its `closed_reason`.

`PairModel::link()` refuses to link somebody who is already in an open pair.
That check is in the model rather than the database because the natural way to
express it — a unique index over a generated column that goes NULL once closed
— is something MySQL rejects when the column belongs to an `ON UPDATE CASCADE`
foreign key, and both MRN columns do, so that a corrected MRN still follows
through to the pair. The cascade is worth more than the index.

Both sides are foreign keys into their own register with `ON DELETE RESTRICT`,
so a recipient's MRN cannot be filed as the donor half, neither side can name
somebody who does not exist, and deleting someone who is half of a pair fails
loudly instead of quietly dropping the match.

### `lab_results` has no foreign key on the person

`person_mrn` plus `person_type` identifies the row, because a result belongs to
the person in the role they were being worked up for. MySQL cannot point one
column at either of two tables, so there is no foreign key there — `lab_id` is
still a real one. Two `AFTER DELETE` triggers,
`recipients_delete_lab_results` and `donors_delete_lab_results`, do the cascade
a foreign key would have.

This is the one integrity guarantee the two-register design costs. It is stated
here rather than hidden.

### Verifying it

`tests/database/SchemaTest.php` covers exactly the two systems above: the score
(the arithmetic, the NULL case, that it counts up on its own, and the waiting
list's ordering and filters) and the linking rules (linking, closing,
re-linking, refusing a double link, refusing a recipient as a donor, the
delete restriction, and the MRN cascade).

`tests/database/ScreenRoundTripTest.php` posts each form and then looks in the
table. It exists because of a specific failure: several controls — gender, MRP,
first dialysis, donor status, the pair's relationship — were posted faithfully
by the views and read by nothing, so a record saved from a filled-in screen came
back half empty, and no error was raised anywhere. A controller quietly dropping
a field cannot be caught by unit-testing the controller, only by saving a form
and reading the row back.

Both need a MySQL `tests` group and skip otherwise:

```ini
database.tests.hostname = 127.0.0.1
database.tests.database = donations_test
database.tests.username = ...
database.tests.password = ...
database.tests.DBDriver = MySQLi
database.tests.DBPrefix =
```

### Creating the first staff account

Nothing is seeded, and `Ui::attemptLogin()` does not check `staff` yet — it
still accepts any non-empty credentials. Once it does, an account is a row with
a `password_hash()` digest; `StaffModel::authenticate()` is already written for
it.

## Migration notes (CodeIgniter 3 → 4)

This section records the CodeIgniter 3 → 4 migration as it stood when it was
done. The screens it discusses — `Patient`, `MRP`, `WaitingList`,
`UpdatePatient`, `Pairs`, `Dashboard`, `Organ`, `Test` — have since been
replaced by the ones under [Screens](#screens) and no longer exist in the tree;
it is kept because the models, helpers and query notes below still apply, and
because it is the reference for anything that has to be brought back from git
history.

### Where files moved

| CodeIgniter 3 | CodeIgniter 4 |
| --- | --- |
| `application/controllers/*.php` | `app/Controllers/*.php` (namespaced, extend `BaseController`) |
| `application/models/Xxx_model.php` | `app/Models/XxxModel.php` |
| `application/views/` | `app/Views/` |
| `application/helpers/` | `app/Helpers/` |
| `application/language/english/form_lang.php` | `app/Language/en/Form.php` (returns an array) |
| `application/config/routes.php` | `app/Config/Routes.php` |
| `application/config/database.php` | `app/Config/Database.php` + `.env` |
| `application/config/autoload.php` | `BaseController::$helpers` |
| `assets/` | `public/assets/` |
| `index.php` (project root) | `public/index.php` |

### Query Builder

Every model was rewritten against the CI4 builder:

| CodeIgniter 3 | CodeIgniter 4 |
| --- | --- |
| `$this->db->select()->from('t')` | `$this->db->table('t')->select()` |
| `->get()->result_array()` | `->get()->getResultArray()` |
| `->get()->row_array()` | `->get()->getRowArray()` |
| `->get()->row('col')` | `->get()->getRowArray()['col']` |
| `->order_by(...)` | `->orderBy(...)` |
| `$this->db->insert('t', $d)` | `$this->db->table('t')->insert($d)` |
| `$this->db->update('t', $d)` | `$this->db->table('t')->update($d)` |
| `$this->db->count_all_results('t')` | `$this->db->table('t')->countAllResults()` |

The raw-SQL escapes (`where($sql, null, false)`, `select($sql, false)`) carry the
same meaning in CI4 and were kept as they were, so the waiting-list score
expression and the `NOT IN` sub-selects are unchanged.

### Framework API

| CodeIgniter 3 | CodeIgniter 4 |
| --- | --- |
| `$this->input->get('x')` / `->post('x')` | `$this->request->getGet('x')` / `getPost('x')` |
| `$this->session->userdata('x')` | `session()->get('x')` |
| `$this->session->set_userdata(...)` | `session()->set(...)` |
| `$this->session->flashdata(...)` | `session()->getFlashdata(...)` |
| `$this->load->model('X_model')` | `model(XModel::class)` |
| `$this->load->view('v', $d)` | `return view('v', $d)` |
| `$this->load->view('partials/p')` (in a view) | `$this->include('partials/p')` |
| `redirect('X')` | `return redirect()->to(site_url('X'))` |
| `lang('key')` | `lang('Form.key')` |
| `htmlspecialchars($x)` | `esc($x)` |
| `show_error($msg, 403)` | `throw new App\Exceptions\ForbiddenException($msg)` |

### Behavioural changes

These were needed to run on CodeIgniter 4 and PHP 8.2+, and are the only places
where behaviour differs from the CodeIgniter 3 original:

- **`organ_chosen()` became a filter**, because CI4 cannot redirect from inside
  a helper called by a constructor. Both that filter and the helper have since
  been removed along with the screens they guarded; the current screens ask for
  the programme on `/organ` instead.
- **Redirects return instead of exiting.** `Patient::validation()` and the auth
  helpers hand a `RedirectResponse` back to the caller.
- **`exit('...')` on bad input became flash-message redirects** in `MRP::addLab()`
  and `MRP::addMRP()`, and `ListsModel::get_add_form_content()` returns `null` for
  an unknown programme instead of killing the request.
- **`UpdatePatient::update()` no longer touches `$pair` when it is empty** — the
  CI3 code read `$pair['recipient_mrn']` unconditionally, which is a fatal
  "undefined array key" on PHP 8.
- **Null-safe output.** `htmlspecialchars(null)` is deprecated from PHP 8.1, so
  every view uses `esc($value ?? '')`.
- **`Labs_model::get_custom_labs()` uses `whereIn()`** rather than interpolating
  `$patient_type` into a SQL string; `ListsModel::get_enum_values()` binds the
  column name and escapes the table identifier.
- **HTML fixes in the views** — the CI3 `update_patient.php` was missing two
  `</select>` tags and had a stray `</label>`, and `pair.php` emitted the lab
  cells outside a `<tr>`. The add-form JavaScript no longer throws when a
  programme renders only some of the MRN fields.
- **`system/helpers` was renamed to `system/Helpers`.** The framework looks for
  the capitalised name, so on a case-sensitive filesystem `helper()` — and with
  it `base_url()`, `site_url()` and the form helper — could not load at all.
- **The profiler calls were dropped**; CI4 shows the same information in the
  debug toolbar when `CI_ENVIRONMENT=development`.
- **Not carried over:** `application/controllers/Patient.php-disabled` and
  `application/views/add_form.php-disabled`, which were disabled in the CI3
  project.

### Resolved by the UI replacement

`Patient::add()` was unfinished in the CodeIgniter 3 project — it dumped the
collected recipient and donor arrays instead of saving them — and the migration
preserved that. The controller has since been removed with the rest of the old
screens, and `UiStore` now writes through `App\Models\*` to the tables, so
adding a recipient, a donor or a pair saves. What remains open is the login,
under [Not wired up yet](#not-wired-up-yet).

## Licence

See [LICENSE](LICENSE). CodeIgniter itself is MIT-licensed; see the
[user guide](https://codeigniter.com/user_guide/) for framework documentation.
