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

### After every pull

```bash
php spark migrate                          # whatever the new commits added
```

On a machine set up before the sign-in went in, that `migrate` creates the
`users` table and the login screen will refuse everybody until it has a row in
it — see [Creating accounts](#creating-accounts).

The screens are code; the check list, the programmes and the words a status
can take are **data**. A commit that adds an answer to a test, or a word to a
status, ships a migration that writes it — and until the migration is run, the
copy on that machine still holds the old data and the screen still shows the
old thing, with nothing to say it is behind. Migrations are safe to run again:
each one runs once and the command does nothing when there is nothing new.

`php spark db:seed DatabaseSeeder` is also safe to re-run, and is the other way
to bring the lab catalogue up to date: it writes the check list as the code has
it, over whatever the rows say.

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

### When the project root is served anyway

On XAMPP it usually is: the whole folder sits under `htdocs/` and the address is
`http://localhost:8080/donations/`, which is the project root rather than
`public/`. Apache, finding no index file, lists the folder — `app/`, `vendor/`,
`writable/`, the `.env` — and anybody who can reach the port can open them.

Two things are in the repository against that:

- **`index.html` at the root**, which Apache serves instead of the listing. It
  sends the browser on to `public/` three ways — a `<meta http-equiv="refresh">`
  for a browser with scripting off, `window.location.replace()` so Back does not
  come straight back to it, and a plain link if both are blocked.
- **`index.html` in every other folder**, saying *Directory access is
  forbidden*. CodeIgniter ships these in the folders it creates; the rest were
  written by `scripts/guard-directories.php`, which writes one into any folder
  that has no index file and never touches an existing one. Run it after
  `composer install` or `composer update`, which replace the whole of `vendor/`
  and take its guards with them:

  ```bash
  php scripts/guard-directories.php
  ```

Those stop the *listing*. They do not stop a direct URL: with the project root
served, `…/donations/.env` still returns the database password to anybody who
asks for it by name. The document root is the fix, and until it is moved an
`.htaccess` at this level is the next best thing:

```apache
Options -Indexes

<FilesMatch "^\.env|\.md$|composer\.(json|lock)$">
    Require all denied
</FilesMatch>
```

`public/` needs none of this: its own `.htaccess` already carries
`Options -Indexes`, and `index.php` is what Apache serves there anyway.

## Screens

| Route | Purpose |
| --- | --- |
| `/` | Entry point — the login screen, or the dashboard when already signed in |
| `/login` | Sign in — User ID and password, checked against `users` |
| `/admin` | The register, and who may sign into it. Admin permission only |
| `/logout` | Empties the session and returns to the login screen |
| `/doctor/dashboard`, `/coordinator/dashboard` | Where each role lands |
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
| `/admin` | The register: Add MRP, Registered MRPs and Login Activity. Admin permission only |

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
register and the user register are pixel-identical; the rest differ by under 400 pixels
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
- **Link with an existing donor** — a select of the donors on this programme
  who are free, each read as name, MRN, blood group and age, and a **Link**
  button beside it.

The second choice used to be a screen of its own: *Choose a donor*, a table of
every unpaired donor with a Link button on each row and Pair Details above it.
That screen is gone, and so is its mirror for recipients. Choosing somebody
already registered needs a name, a blood group and an age, which fit on a line,
so the whole choice is made in the dialog without leaving the record. Nothing
else is asked there — no status, which is the pair's to say about its donors on
its own screen, and no relationship or crossmatch date, which belong to the
pair and are entered on it.

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
asks first: the donor's **Donor Status** field on their card, and both doors of
Add donor.

The word a donor is on is set in one place — that field, on their Personal
Information card, where it sits with the rest of their details. The tab's head
carried a second control for the same answer beside **Delink**; it is gone, and
with it the `pairs/{id}/donors/{n}/status` post it made — and later **Swap**
and its own post went the same way, for the same reason. **Delink** is what is
left in that head, because it is not a field: it is a thing done to the link,
not a fact about the donor.

Changing which donor that is, is **moving the words about**, each on its own
card: stand the current one down to On Hold or Declined, then set another to
Active. Nobody is archived; both are still the pair's donors and either can be
taken back up.

There was a **Swap** beside Delink that did it in one press, archiving the
donor swapped out. It is gone, with its route and its store method: the word a
donor is on is a field on their card, so the two edits are made where every
other fact about them is edited, and a press that archived somebody sat one
button away from a press that did not.

#### Archived is a mode, not a status

An archived tab is a link that has been **ended**. The donor keeps whatever
word they were given — archiving is the pair's doing and says nothing about
them — and because the link is ended they are free again: their own record is
editable, they are back on the Donors List, and they can be linked to somebody
else.

One thing archives a donor: **Delink**, available on every live tab. On a
reserve it archives that donor and nothing else. On the active one it asks the
pair's own future, in three answers.

Delinking the **active** donor is the pair's own future, so the dialog says so
first — taking this donor off takes the pair apart unless another is linked in
their place — and then asks which:

| | What it does |
| --- | --- |
| **Link with a new donor** | Opens Add Donor for this pair. They join it as the donor it is going ahead with: the pair has nobody active at that moment, so **Active** is the word already in the box. |
| **Link with an existing donor** | A select of the donors on the register who are not in a pair, under the answer itself. The one chosen is set to Active on this pair. |
| **Take the pair apart** | The recipient goes back to the waitlist, the donors back to the register. |

The donor being delinked is archived whichever is answered; what the answer
decides is whether anybody takes their place. A donor the pair already has in
reserve is not on that list — the list is of donors free on the register.
Raising one of the pair's own is standing this one down and setting that one
Active, on their cards, which archives nobody.

Taking the pair apart closes every link at once: the recipient goes back to the
waiting list and every donor back to the register. Nothing is deleted.

An ended pair also comes **off the Pairs List**, altogether — not as a greyed
row, which was the register saying there is a pair here when there is not one.
The list is of the pairs there are: it is off the printed sheet and off the
search for the same reason, and the dashboard's **Linked Pairs** counts the
same set. None of it is deletion — the pair's own address still opens it, and
the recipient's record keeps every donor it ever had.

What is *not* that is a pair somebody has called **Closed**. That is a word
like On Hold or Declined: it describes the case, it does not end it. Such a
pair stays on the list under its own Status chip, with both of its people still
in it and off their own registers, and the word can be taken back off again.
Ending a pair is **Delink**'s job, and nothing else's.

An archived tab carries its own dates and the reason it ended, so the tabs are
the history: *Linked 30/09/2026, archived 04/10/2026. Delinked from the pair.* There is no separate archive, because `pairs` **is** the log —
one row per link ever made, and closing one is how a link ends.

#### The section outlives the pair

A pair ending is not the record ending. The recipient is back on the waiting
list, and the donors worked up for them are still theirs — so the **Donors**
section goes with the recipient onto their own record: the same tabs, the same
cards, the same workups, every tab archived and nothing on any of them to
press. Opening that recipient from the waitlist later opens all of it, which is
the point: their old labs and every donor ever considered for them are on the
one screen they are reached by, not on a pair screen somebody has to know the
number of.

The section is on the record **only while no open pair holds them.** While one
does, the pair's own screen is where the tabs are; two copies would be two
places to read the same thing and one of them stale. Linking the recipient
again moves the whole section back onto the new pair, the archived tabs
included, because it is the same list either way — every donor they have ever
had.

The donor's half of it is a line rather than a section, and it sits **under
their name** in the header: *Previously linked with [Mishal Al-Harthy
(980101)] · archived 04/10/2026*, with the reason after it. The name is the
link, styled as one, because the pair's record is kept on the recipient's
screen and getting there is the whole point of the line — somebody reading
"who is this donor" is already looking at the name. It stays said if they are
linked again: an ended link is history a new pair does not cancel.

#### Where it is stored

`pairs` holds it all: one row per donor ever linked to a recipient, with the
link's own status. Archived is `ended_at` being set, which is the same row
state that frees both sides — so being archived and being released are one
fact, not two that could disagree. The word on the row (`status`) is a separate
fact and frees nobody. The donor's Active / On Hold / Declined is
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
- **Link with an existing recipient** — a select of the recipients on the
  waitlist who are not already paired, and a **Link** button beside it.

The choice is a `<dialog>` on the record, opened by `ui.js`. The button under it
is a real link to `donors/{id}/link`, the same choice at its own URL, so with
JavaScript off it is simply followed; the select is a plain form either way and
posts to `donors/{id}/link/existing`, which is a post and nothing else — there
is no screen behind it to open. A donor already in an open pair is sent to
that pair rather than offered a second one, and a person who holds a row in both
registers under one MRN is not offered as their own counterpart.

### The search narrows the list you are on

Above the page header and centred in the content, on the five screens that
have a list to narrow — Recipient Waitlist, Donors List, Pairs List, Paired
Exchange, Reports. The dashboard, the Admin screen and the record screens have nothing
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

Both tables carry a **Status** column too, in the same coloured tag the rest of
the platform uses. A list narrowed by something it does not show can only be
read for it one chip at a time, and under "All" it could not be read for it at
all.

Both carry an **Export PDF** beside their Add button, and it exports what is on
the screen: the filtered rows, in the columns the list shows, with the filters
named on the letterhead so a printout says what it is a printout of. The two
sheets are one view — `ui/register_print` — because they are the same document
with different columns, and the caller hands the cells over already formatted.

The Pairs List's own sheet, `ui/pairs_print`, is the same promise and has to be
kept to it by hand: it is a second view of the same table, so a column renamed
on the screen has to be renamed on the sheet, or the sheet quietly becomes a
different list. Its columns are the list's, in the list's order and the list's
words, less the one the list ends with — the delete button, which paper has no
use for. It carried a **Note** column the list itself dropped; that is gone
too. A pair's note is on the pair's own screen and on its own printed sheet,
where there is room to read it rather than twelve characters of it.

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

#### The review asks what each new pair is

An exchange makes several pairs in one press, and the screen that usually asks
what a pair *is* — the Pair Details card — does not exist for them until they
do. So the review asks, with the card's own three fields under each new pair:
**Relationship**, **Date of Crossmatch**, **Status**. Saving writes them with
the link, so a pair the exchange made arrives as complete as one entered by
hand, instead of as a bare row somebody has to go and finish.

Two of the three answer themselves. Status starts on **Paired Exchange**,
because that is what these pairs are, and **Closed** is not on its list at all:
closing is what frees both sides again, and a pair that frees them the moment
it exists is no pair. Relationship starts **empty**, deliberately — the donor's
stored relationship is to the recipient they came in with, and "Sister" on a
donor being crossed to somebody else's recipient is a wrong answer filled in
for them.

`ui/partials/pair_details_fields` is the three fields, with the caller naming
them: the exchange asks once per pair and has to keep the answers apart, so
they post as `pairDetails[<recipient MRN>][…]`. The review is one form around
the whole summary now — which is why its Back and its close are buttons
`ui.js` shuts the dialog with rather than little forms of their own: a form
cannot sit inside another.

#### Who the chain may offer

Four lists, and a condition on each. Blood group decides the rest: a donor has
to be able to give to the recipient they are offered to.

| Offered to | From | On condition |
| --- | --- | --- |
| A recipient needing a donor | a pair whose **status is Paired Exchange** | the donor's own status is **Active** — which is what tells the pair's own donor from its reserves |
| A recipient needing a donor | the **Donors List** | their own status is **Active** |
| A donor needing a recipient | a pair whose **status is Paired Exchange** | — a pair has one recipient, and they are it whatever word they are on |
| A donor needing a recipient | the **waiting list** | their own status is **Active** |

Two things follow from reading them closely. A pair reaches these lists by its
**status**, not by the Pair Exchange button: the button puts a pair on the
exchange list and lets one be started from it, and setting the status to Paired
Exchange does both of those as well *and* makes its two people reachable by a
chain. And a reserve donor on an exchange pair is never offered — the pair is
going ahead with one donor, and the others are not its to give away.

Worth knowing when a new donor seems to be missing: Add Donor starts on **On
Hold**, and the exchange will not see them until somebody says Active.

It is why saving an exchange no longer writes **Paired Exchange** onto the
recipient. That was already at odds with the rest of the platform — a person is
not in a paired exchange, their case is — and it would now leave them holding a
word that is not Active, making the registry's own saved exchanges the one
thing the next exchange could not offer. The rows written before it stopped are
set back to Active by a migration, since no screen offers that word and nobody
could take it off by hand.

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
an Entry Date the recipient screens collect rather than only show. The three sections
below say what each is for.

### Donor type

What kind of donation this is, asked with the list the screen can actually
answer:

| Screen | Offers |
| --- | --- |
| Add Donor, and a donor's record while they are in no pair | Living · Deceased |
| A linked donor's record | all four |
| Add Pair, pair profile | Living Related · Living Unrelated · Deceased |

Relatedness is a question about a donor *and* a recipient, so it can only be
answered where both are in view. Registering a donor on their own records
`living`, which is the same kind of donation with that part not yet known —
not a fourth kind. One column, `donation_type`, holds all four.

What decides the list on a donor's record is the pairing, not whether the
record is new: a donor nobody is paired with is offered the two, because
"related" has nobody to be related *to*. Linked, they are offered all four,
which is also where the answer is worth giving.

The one exception is the type the record already holds. A donor archived off a
pair comes back onto the register still saying Living Related; that answer
stays on their list, selected, so no save on another card quietly rewrites what
happened to them. It is their own answer being kept, not a fifth option being
offered — the other pair type is still not on the list.

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
| `done` | Not done · Pending · Done |
| `positive_negative` | Not done · Pending · Positive · Negative |
| `acceptable_abnormal` | Not done · Pending · Acceptable · Abnormal |
| `acceptable_abnormal_na` | the same, and **Not applicable** |
| `cleared_not_cleared` | Not done · Pending · Cleared · Not cleared · Not applicable |
| `given_not_given` | Given · Not required · Not given · Not applicable |
| `seen_not_seen` | Not done · Seen · Not seen |
| `free_text` | nothing — the card is its comment box |

`not_done` starts most of them — nobody has looked yet — and **Not
applicable** is on the ones where a test can fail to arise at all: a clearance
nobody needs, a vaccine that does not apply, and the two urine collections, the
24-hour protein and the creatinine clearance, which an anuric patient has no
urine to make. That last pair answered Acceptable / Abnormal only until the
word was added to them, and "Not done" was being pressed for a collection that
could never have been done — which says something else entirely.

`given_not_given` has no Not done of its own: a vaccination that was not given
says so, and the two would be one answer under two names. Until one is pressed
the card shows nothing, while the record still holds `not_done`, which is how
the workup knows it is outstanding. The answer is stored in `lab_results.status`,
and the bar counts the tests that have one, whichever it is. An answer a test
does not offer is refused rather than stored, checked against the catalogue
rather than against the form.

Red marks the answer somebody has to act on — Positive, Abnormal, Not cleared
— not merely an unwelcome one.

**An answer wears its colour only once it is the answer.** Until then every
chip on the row is the same quiet white: a colour on a medical record is a
statement, and four of them side by side are four statements about a test
nobody has looked at yet. Pressing one paints it, and **the answer recorded
colours the whole card** — a workup is seventy cards read by running down it,
and a colour you have to look twice for is not read at all. The wash on the
card is a shade lighter than the chip, because the card is a surface and a
surface in the chip's strength would shout over what is sitting on it. The
neutral answers — Not done, Not applicable, Not required — colour nothing:
every card would be wearing grey, which is another way of saying nothing.

#### Where the sheet disagrees with that

Red is the answer somebody has to act on, and on most of the sheet that is
Positive. On a handful of serologies it is the other way round, so
`UiStore::SHEET_ANSWER_TONES` says where — by side, then by heading, then by
test, because the recipient's sheet asks VZV twice and a jab is not a serology:

| Side | Heading | Tests | Reads |
| --- | --- | --- | --- |
| Recipient | Infectious workup | HbsAb, Mumps, Rubella, CMV, Measles, VZV | Positive **green**, Negative **red** |
| Recipient | Vaccinations | every line | Not given **red** |
| Donor | Infectious workup | HbsAb, Mumps, Measles | Positive **blue** |

A recipient with antibodies to measles, mumps, rubella, VZV, CMV or hepatitis B
is a recipient who is protected: the positive is the reassuring answer, and the
one worth chasing is the negative. Painting Positive red there said the
opposite of what the result means, on the colour alone, which is how a card is
read at a glance. The donor's sheet asks the same serologies for a different
question — what the donor carries, not whether they are covered — so a positive
is neither good news nor bad but a fact the transplant is planned around, which
is blue. A vaccination not given is one somebody still has to give.

Everything not named there keeps the colour it carries everywhere else. And a
colour chosen by hand through **Edit results** wins over all of it: the sheet's
colours are what a test wears when nobody has said otherwise.

Pressing **Not done** on a test that has an answer takes the answer back. An
unanswered test still writes no row — there is nothing to write — but once
there is one, a record that cannot be corrected is worse than one with an empty
row in it.

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
- **Add custom result** at the foot: type a name and press **+**, and it joins
  the list above with a pencil and a bin of its own — so several can be added
  before the card is saved, renamed, or taken off again. Without `ui.js` the
  box still adds the one result it holds when the card is saved, and rubbing a
  name out still removes it, which is how it worked before there were buttons.
- A swatch beside each, opening five colours — **Red, Yellow, Green, Blue,
  Gray** — named for themselves, because what a colour means is the test's
  business and a wheel of sixteen million would mean nothing. *No colour* is
  first in the palette, so one can be taken back off. The answers arrive
  wearing the colours the platform would have given them anyway: there is
  nothing to be gained by asking somebody to paint Positive red.

Once it is saved the test behaves exactly like every other: its answers wear
their colours, and the one recorded colours the card. Ticked answers are the
buttons on the card, those with a colour in it and the rest plain,
and the list itself is only on screen while the card is being edited: once it
is saved the card shows the chosen answers and nothing else. Its own **Edit
results**, beside the pill, opens the workup at that card with the ticks,
the names and the colours as they were left. Until a new test is given any
answers it offers Not done · Pending · Done, which is what most of them want.

**Add lab** sits under the Other heading rather than beside it, on its own and
with nothing written under it, and it can be pressed while the card is being
read: adding a test is not editing this one, so it does not wait for the
pencil. That puts it outside the card's disabled fieldset — the workup's fields
are in two, with the button between them — and it answers to a form of the
page's own, so Enter in one of the card's boxes cannot press it. It used to:
Add lab was the first submit button the form had, and a name typed and
confirmed added a test nobody asked for.

The set lives in `labs.answer_set`, JSON, on the test's own row — one set per
test, so two tests on one record are independent, and a test on one record is
nothing to do with the same-named test on another. A catalogue row's
`answer_set` is NULL and its card has no list: the sheet has already said what
those answer. Because an answer somebody invented cannot be a value in an
ENUM, `lab_results.status` is a `VARCHAR(60)`; keys for invented answers are
slugs prefixed `c_`, which is what keeps them from colliding with ours.

The card renders the answers it is handed, so both paths that build one have
to hand them over: `UiStore::labTests()` for a saved record, and
`UiStore::defaultLabTests()` for the blank workup an Add screen starts from.
The second was missed when the answers moved onto the test, and every card on
Add Recipient, Add Donor and Add Pair came up with nothing to press and a
comment box — which is what a free-text card looks like, so it read as a
deliberate change rather than as the omission it was.

### Asking before taking something away

Every delete asks first, and asks **over the screen it was pressed on**. It
used to ask on a page: pressing a bin by accident left the list, loaded a
screen with one question on it, and finding the way back was the apology. Those
pages — `ui/confirm_delete` and `ui/delink_donor` — are gone, and the addresses
behind them answer to a post and to nothing else. A GET that deletes goes off
the moment a browser prefetches the link, which on a patient register is not
recoverable.

What asks is `ui/partials/confirm_dialog`, one `<dialog>` per thing that can be
taken away, sitting beside the button that opens it: a row's bin on each of the
three lists, **Remove this test** at the foot of a test somebody added, and
**Delink** on a pair's donor tab. The delink one is the question with two
answers, each a line of its own, under a line saying why it is being asked at
all — this donor is the pair's active one, and without somebody in their place
the pair comes apart:

| | What it does |
| --- | --- |
| **Link with a new donor** | Add Donor, opened for this pair; they join it as its active donor. |
| **Link with an existing donor** | A select of the donors free on the register, under the answer; the one chosen is set to Active. |
| **Take the pair apart** | The recipient goes back to the waitlist and the Donor back to the Donors list. |

Two things make a dialog able to live anywhere. Its button belongs to
`confirm-post` — an empty form in the layout, beside `lab-add` — and carries
its own `formaction`, because a form cannot sit inside another and these sit
inside the card's, inside a table cell. And the dialog says its own text
(alignment, weight, colour): the top layer does not stop it inheriting from
where it stands, and an urgent row's red was reading straight through into the
question it asked.

With scripting off the opener is a link to the dialog's own id and `:target`
shows the panel over the screen; the button posts, Cancel clears the address.
So the pages are gone without anything going with them.

### Cards that fold

A card with a fold is a `<details>` with its head as the `<summary>`: the whole
head is the control, so there is no small target to find, and it works with
scripting off. Two kinds have one.

**The workups**, on every screen that shows one. A workup is seventy-odd cards
— a screenful and a half between whoever is reading the pair and the donors
underneath it. On the pair's screen both workups, the recipient's and the open
donor tab's, are **shut** when the screen opens, with the card being edited
open because that is the card somebody came for. On a record screen the workup
is most of the screen, so it arrives **open** and shutting it is a choice
somebody makes. `ui/partials/lab_tests` takes `foldable` and `open`, so which
it is belongs to the screen rather than to the workup.

Shut, a workup is one line — so the line carries the groups, as the next
section says.

**The personal details**, on the record screens and on the pair, each donor tab
included. These start **open**: the details are what a record is, and a record
that opens shut would be asking to be opened before it could be read. Shut, the
summary keeps the three things anybody scans the card for — the name, the blood
group and the status — and keeps them *as fields*: the label above and the
value in the same read-only box the card's own fields wear while they are not
being edited, so a shut card reads like the open one rather than like a caption
of it. They are the values and not controls, because the card's real fields are
a few lines below and a second set carrying the same names would post every
answer twice. They go again when it opens, where the card says all three in its
own fields.

### How far a workup has got, group by group

The card's head has always said "12 of 74 completed" with a bar against the
whole sheet. One number against seventy-odd tests is the one number nobody
works from: a workup is read group by group — immunology is somebody's morning
and serology is somebody else's — and "16%" does not say which of them is
waiting.

So every group carries its own bar and percentage beside its heading, counted
exactly as the card's own is (`UiStore::labProgress`, over that group's tests).
A group of nothing but free-text lines gets none: there is nothing in it to
complete, and a percentage of nothing is a number about nothing.

Shut, the card shows the groups by name with the same percentages, two or three
across depending on the width — which is the whole point of being able to shut
it: the workup is out of the way and still readable at a glance. Open, the
lines go, because each group is saying it over its own tests.

`ui.js` keeps all of them in step as answers are pressed: the group a card is
in is read off the markup (`data-lab-group="3"` on its cards, the same number
on its heading and on its line in the folded summary), because the Other
group's heading sits outside the fieldset — to keep **Add lab** pressable while
the card is read-only — and the numbers are what tie the two together. Without
the script, every bar is still right: the server renders them.

### Status: three facts, not one

A recipient's status, a donor's, and the Match Status of a pair were once one
value shared between screens — setting it on either set the other. They are
three separate facts:

| Where | What it says |
| --- | --- |
| **Recipient Status**, on the record and on the pair's recipient card | Is this person on the programme |
| **Donor Status**, on the record and on the pair's donor card | Is this person still being worked up |
| **Match Status**, on the pair's own card | What became of this link |

What broke the old arrangement was a recipient being allowed more than one
donor: with three links open, there is no saying which of them a Declined on
the person was about. So declining a recipient does not decline their donors,
and a person's own card writes the person and nothing else.

`UiStore::STATUS_OPTIONS` still holds every word any of them can take, so a
value stored before this reads correctly wherever it appears. What each screen
*offers* is narrower: `PERSON_STATUS_OPTIONS` and `DONOR_STATUS_OPTIONS` are On
Hold / Active / Declined / **Transplanted**, and `PAIR_STATUS_OPTIONS` adds the
two that are a pair's alone — Paired Exchange and Closed.

Transplanted was the pair's alone until the register read wrongly for it: a
recipient whose transplant has happened is not "active" on a waiting list, and
the donor who gave is not "active" either. A transplant is a thing that happens
to people, so people can hold the word. `recipients.status` and `donors.status`
are ENUMs, so it took a migration to let them.

#### One direction carries: the pair's status hands the word on

The four a person can hold are exactly the first four a pair can, and where the
fact is the same fact, saying it on the pair says it. Saving Pair Details sets
the recipient's and the donor's own status to the same word, and the card says
so under the field before it is saved — a save that writes two other records
should not do it quietly, and the notice afterwards names who it was set on.

Paired Exchange and Closed carry nothing: a person is not "in a paired
exchange" and is not "closed", their case is. Nor does calling a pair Closed
move anybody: both of them stay in it, on whatever word their own record
holds.

One rule survives the carrying. Only one of a case's donors may be Active, so
a pair set Active while another of its donors holds that word sets the
recipient and leaves the donor alone. `UiStore::applyPairStatus()` is the whole
of it, and it is the only thing that writes a person's status from a pair.

All six are words and nothing more — **Closed** included. It used to be what
"open pair" was defined against, so choosing it took the pair apart; a pair is
open until it is *ended* now, and the word says only what somebody wanted it
to say.

Two of them bring a question with them, and the Pair Details card asks it where
the answer belongs — beside the word, and only while the word is on the screen:

| Status | What it asks for | Column |
| --- | --- | --- |
| **Closed** | Why was it closed? | `closed_reason` |
| **Transplanted** | Date of Transplant | `surgery_date` |

Both are kept only while their status holds. Moving a pair off Closed clears
the reason and moving it off Transplanted clears the date, because a sentence
about a decision that was changed, or a day for a transplant that was taken back,
is worse than nothing. One `<select>` reveals both: `data-reveal` and
`data-reveal-when` carry a list each and are read in step, and with `ui.js`
absent the blocks are simply always visible — the server drops what does not
belong either way.

### Age is a date of birth

Age used to be typed as a number, which is a fact with a shelf life: right on
the day it was entered and quietly wrong every year after, with nothing in the
system to ask again. The field collects a **date of birth** now, and **Age** is
a field of its own beside it — read-only, with nothing to type into, because it
is that date read out rather than a second question. It fills in as the date is
typed and the server works out the same number when it saves.

The two share one cell of whatever grid they are dropped into
(`ui/partials/birth_date_field`), so giving the age a field of its own moved
nothing else on any of the five screens that ask for a birthday.

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

It is the **recipient's** field and only theirs. A recipient's entry date is a
fact about the wait — the score is worked out from it — where a donor's was
only the day the register took their row, which is bookkeeping and not one of
their details. So no donor screen shows it: not Add Donor, not their record,
not their tab on a pair, and the mixed report leaves the cell empty for a donor
row the way it already leaves the dialysis ones. `donors.registered_on` stays,
written once when the record is made and never asked about again.

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

#### The table is laid out, not left to find its own width

Fifteen columns at their widest is wider than any screen, and a report somebody
has to pan sideways through is a report they read half of. So the table is
`table-layout: fixed` at the width it has, with a share per column written into
a `<colgroup>` on the page: what is long gets room, what is four characters
wide does not take any, and whichever subset the Columns filter leaves, the
shares still add up to the whole table. Cells wrap instead of pushing; a date
and a status tag hold their line until the screen is narrow enough that holding
it would spill, and below the width where fifteen columns can fit at all —
a phone — the table goes back to being panned, because nothing else would
help there.

Both exports print the rows the filters chose and no others, which is the point
of exporting from here rather than from a register. **General** is the table as
it stands, in the columns showing at the time. **Internal** is that table
followed by each of its rows opened out — the whole record and the whole
workup, one per page — built from `App\Libraries\RecordBlocks`, the same
blocks a single record's own printed sheet is made of, so the two sheets cannot
drift apart.

### The Admin screen is where users are made

**Add MRP**, its first section, creates somebody a record can be assigned to, and a
transplant programme assigns two kinds. The form asks which — **Doctor** or
**Coordinator** — before it asks the name, because that is what the name is
being entered as. `mrp.kind` holds it, and everybody registered before the
column existed is a physician, which is all the screen could make.

A record's **MRP** field offers physicians only: it asks for the responsible
physician, and a coordinator there would be an answer the question does not
take. A coordinator registered here also gets a row in `coordinators`, which is
what `recipients.coordinator_id` and `donors.coordinator_id` point at — without
both, somebody registered here could not be assigned to anybody.

The **Coordinator** field on every record, pair and donor card is a select of
those people — not a text box. A box asked somebody to remember a colleague's
name and spell it the way the last person did, and registered a second
coordinator under the misspelling when they did not. **Deactivate** reaches
both rows, so somebody stood down stops being offered; a record that already
names them keeps them, shown at the foot of the list as *— no longer
registered*, rather than quietly becoming nobody the next time the card is
saved.

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

Registering somebody does make their **sign-in account**, with no password at
all: an empty hash matches nothing, so the account exists and cannot be signed
into until an administrator sets one. The two were separate tables describing
the same staff, and the register's row is now the one place a person is edited,
deactivated, granted the permission, and given a password.

**Registered MRPs** under it is a table — Name, MRP ID, Type, Status, and the
row's controls. **Edit** turns the row into a form where the row is, as a
record's card does, and carries the **Admin** checkbox. **Deactivate** is never
a delete: the records they are on still name them, and a physician who has left
is part of what those records say. A deactivated user stays on the list, marked
*Deactivated*, with **Reactivate** — and cannot sign in, because deactivating
reaches their account as well as their row. **Reset password** sets one without
sending them round the directory again; it is the screen and nothing behind it
so far, and says so on the dialog.

**Login Activity**, the third section, is every attempt to sign in — name, User
ID, when, and whether it worked, with why when it did not. Read-only: there is
no button on it, because the reason it exists is the attempts nobody meant to
be read, and a log somebody can tidy is not a log.

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
- **A coordinator was registered by being typed.** The design collected the
  coordinator as free text and had no screen that registered one, so typing a
  name created it. Add MRP registers them now, and the field is a select of the
  people it registered. The name is still what the field posts — `UiStore` turns
  it into `coordinator_id` on the way in and back on the way out — so a name
  chosen from the list is one the table already holds.

### What is wrong with a box, said while it is being filled in

A form that waits for Save to say a date is impossible has already taken
everything else off the screen to say it. Every box that can be wrong now says
so as it is typed into, under the box itself:

| Box | What it says, and when |
| --- | --- |
| Any date | *The calendar has no such day* once eight digits are in and they are not one — 55/66/1111 is date-shaped and nothing more. *This date is in the future* for the dates that cannot be. Half a date is not wrong yet: nobody is told off mid-date. |
| MRN | *MRN must be a number* as it is typed, *MRN is required* on leaving it empty, and *A recipient with MRN … is already registered* a quarter-second after typing stops. |

Whether a number is free is the one thing a form cannot work out for itself, so
it asks: `GET /mrn-taken/<recipient|donor>/<number>` answers `{taken, message}`,
and the message is the sentence `Ui::mrnError()` would have given on save — the
same method, so the two cannot word it differently. The box carries
`data-mrn="recipient|donor"` to say which register it has to be free on, and a
saved record's number is read-only, so there is nothing to ask about.

The message element is made by `ui.js` beside the box rather than written into
the markup, so every screen that collects a record gets it without being edited
and without being able to forget one. The box is marked `aria-invalid` and
pointed at its message, and `setCustomValidity` keeps the browser's own refusal
in step, so a form cannot be sent with a value this has already objected to.

None of it is required: with scripting off nothing appears and the save refuses
exactly as before. It is the earlier of two answers, not the only one — and the
refusal is now printed **once**, because the layout prints what a redirecting
action left behind and a screen with a slot of its own was printing the same
flash again underneath it.

### Signing in

`/login` is a real sign-in. It was not: it took any non-empty pair of boxes,
exactly as the design package's did, and that was the one thing standing
between these screens and being used.

A row in `users` is an account — the staff number typed into **User ID**, a
`password_hash()` digest, and one of three roles. `App\Controllers\Auth` is
the whole of it, and two rules shape what the screen says back:

- **One message for a bad sign-in.** A wrong User ID and a wrong password both
  answer *Invalid User ID or password*. Saying which was wrong turns the form
  into a way of asking which staff numbers exist.
- **A switched-off account is told so** — *Your account is inactive. Please
  contact the administrator.* — but only once the password has been checked, so
  the sentence is only ever shown to the person whose account it is. Asking the
  question the other way round would answer it for anybody typing numbers in.

The User ID is checked for shape before either, because a staff number is
digits and anything else is a typing mistake rather than a failed sign-in:
*User ID must be numbers only.* There is no minimum length on it or on the
password yet. A refused attempt keeps the number in the box and never the
password — retyping a number is the annoying half, and a password echoed into
HTML is a password in the page source, in the browser's cache and in anything
that logs a body.

A successful one regenerates the session id, stores the account's id, name and
role, and stamps `last_login_at`. `/logout` empties the session and comes back
here.

The screen itself is the one that was designed — the same panel, the same two
fields, the same button. What was added is what a form that can now refuse
somebody needs: the message, a **Show / Hide** on the password, and a button
that says *Signing in…* while it works. The last two are
`public/assets/ui/js/login.js`, which only ever adds; with scripting off the
field is an ordinary password box and the button an ordinary submit, and
signing in works exactly the same. Nothing about what is accepted is decided in
the browser.

#### Two roles, and a permission over both

| Role | Lands on |
| --- | --- |
| `doctor` | `/doctor/dashboard` |
| `coordinator` | `/coordinator/dashboard` |

Both are placeholders and say so: a name, a role and the way out, with a link
into the platform. The mapping is written once, in `Auth::HOME`.

**Admin is not a third role.** Everybody who uses this system is a doctor or a
coordinator; some of them also look after the register, and `users.is_admin`
says which. An administrator keeps their own role, lands on their own
dashboard, and keeps every screen their role already had — what the permission
adds is on top:

- the **Admin** item in the sidebar, and the screen behind it;
- the **delete** button on the three lists, which nobody else is shown.

It was a role, briefly, and that was wrong in a way worth recording: an
"admin" had no clinical job at all, which is why their dashboard had no
patients on it and why granting somebody the register meant taking their role
away. The migration that undid it reads every `role = 'admin'` as a doctor who
has the permission.

Three filters guard the rest, put on the routes rather than on URI patterns so
that a guard is read next to the address it guards:

- **`auth`** — no session, no screen. It redirects to `/login` rather than
  refusing, because not being signed in is a state somebody can leave, and the
  way out is the screen they are being sent to.
- **`role:doctor`, `role:doctor,coordinator`** — allowing rather than denying,
  so a role added later is kept out of every screen until somebody writes it
  down.
- **`admin`** — the permission, asked of doctors and coordinators alike.

Signed in and refused either way gets a 403 built on the login screen's own
panel (`app/Views/errors/403.php`), naming their own dashboard as the way out
and not naming the screen they asked for.

Every address in `Config\Routes` but `/login` and `/logout` is inside the
`auth` group, so a route added to that file later is protected by being there;
the Admin screen and the three deletes are inside `admin` as well. Hiding a
button or a sidebar item is a courtesy — the address is still typeable, so the
filter is what actually refuses.

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
| `users` | Who may sign in, and as what: `login_id`, a password hash, a role, and whether they look after the register |
| `login_activity` | Every attempt to sign in, successful or not. Written by the login screen, read by the Admin screen, edited by nothing |
| `staff` | Superseded by `users`; kept, empty and unread, pending a decision to drop it |
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

One rule runs through all of it, written once as `PairModel::openSql()`:

> **A pair is open until it is ended** — `ended_at IS NULL`.

Everything follows from that:

- A recipient is on the waiting list when no open pair holds them.
- A donor is on the register when no open pair holds them.
- Ending a pair releases both sides, and either can be linked again — to each
  other or to somebody else.
- An ended pair is a stamped row, not a deleted one, so a failed match stays on
  the record with its `closed_reason`.

It used to be `status <> 'closed'`, which made the word on the Pair Details
card do the releasing: describing a pair as Closed took it apart. The two facts
are separate columns now — what a pair *is called* and whether it is *over* —
and only the second one frees anybody.

`PairModel::link()` refuses to link somebody who is already in an open pair.
That check is in the model rather than the database because the natural way to
express it — a unique index over a generated column that goes NULL once the
link is ended — is something MySQL rejects when the column belongs to an `ON UPDATE CASCADE`
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

### Creating accounts

An account is a row in `users` with a `password_hash()` digest — never a
password — and `UserModel::store()` is the one line that hashes one.

For development there are three, one per role:

```bash
php spark db:seed UserSeeder
```

| User ID | Password | Role | Admin |
| --- | --- | --- | --- |
| `1` | `A` | doctor | yes |
| `2` | `A` | doctor | no |
| `3` | `A` | coordinator | no |

**It refuses to run outside `development`.** The passwords are a single letter;
they exist so somebody building a screen can get past the login, and they would
be a way in for anybody who can read this file. The seeder reads `ENVIRONMENT`
and on anything else writes nothing and says why — so if it reports being
skipped, `CI_ENVIRONMENT` in `.env` is not `development`, which on a fresh
CodeIgniter `.env` it is not.

It is deliberately not part of `DatabaseSeeder`, which runs on every install.
These are made people, and nothing invents people into this system unless
somebody asks for it by name. Re-running it leaves accounts that already exist
exactly as they are, password included.

The first real account is the same row written by hand or from a console, with
`is_active = 1`, a role, and `is_admin = 1` so that there is somebody who can
register everybody else. After that, registering a person on the Admin screen
makes their account for them.

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
adding a recipient, a donor or a pair saves. The login was the last thing left
open and is now wired up too, under [Signing in](#signing-in).

## Licence

See [LICENSE](LICENSE). CodeIgniter itself is MIT-licensed; see the
[user guide](https://codeigniter.com/user_guide/) for framework documentation.
