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
| `/recipients` | Recipient waitlist, filterable by blood type |
| `/recipients/new`, `/recipients/{id}` | Add / open a recipient |
| `/donors`, `/donors/new`, `/donors/{id}` | Donor registry and records |
| `/pairs` | Pairs register, filterable by blood type and status |
| `/pairs/export` | The filtered pairs as CSV |
| `/pairs/new`, `/pairs/{id}` | Add / open a pair |
| `/mrp` | Register a Medical Responsible Person |

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
| Records | `app/Libraries/UiStore.php`, seeded from `UiSeed.php` |
| Inline SVG icons, tone lookups | `app/Helpers/ui_helper.php` |
| Stylesheets and images | `public/assets/ui/` — copied byte-for-byte |
| Behaviour only | `public/assets/ui/js/ui.js` — one file, ~190 lines |

Navigation, filtering, sorting and saving are links and form posts, so every
screen renders, navigates and submits **with JavaScript switched off**. `ui.js`
is left with the mobile sidebar, the lab cards (status buttons, result editor,
running totals), whole-row click targets and keeping "Urgency" in step with
"Urgent?".

The result was checked against the design package screen by screen with
full-page screenshot diffs at 1440px. Login, the programme picker, the pairs
register and Add MRP are pixel-identical; the rest differ by under 400 pixels
out of 1.4–3.3 million, all of it antialiasing on a single character boundary in
labels such as "4 unmatched donors" or "Back to Recipient Waitlist". The
package built those strings with a helper that inserted an empty HTML comment
between each part to imitate React's text nodes, which changes how the browser
kerns the join; the views here emit one ordinary string and let the browser
shape it normally.

### Not wired up yet

Two things still stand between these screens and production use:

- **The screens do not read the database yet.** The schema exists and every
  field they collect has a column (see [Database](#database)), but records are
  still held per session and seeded from `UiSeed.php`, the design package's
  demo data converted to PHP. Reimplement the read and write methods in
  `UiStore` on top of `App\Models\*` — the views take plain arrays and need
  no changes, which is what that class's interface is shaped for.
- **No real login.** `/login` accepts any non-empty Staff ID and password,
  exactly as the design package's did. The `staff` table is there for it to
  check against; until `Ui::attemptLogin()` does, this must not be deployed
  anywhere reachable.

## Database

The schema lives in `app/Database/Migrations/` and is created with:

```bash
php spark migrate
php spark db:seed DatabaseSeeder
```

`app/Database/schema/donations_schema.sql` is the same schema as a single
importable dump, for phpMyAdmin or the `mysql` client. It carries the
`migrations` rows too, so importing it and then running `php spark migrate` is
a no-op rather than an attempt to create everything twice. Regenerate it with
`app/Database/schema/regenerate.sh` after changing a migration.

Neither route inserts people: no patients, no staff accounts, no physicians or
coordinators. Only structure, the two organ programmes and the lab catalogue.

### Tables

| Table | Holds |
| --- | --- |
| `patients` | One row per person, recipient or donor, keyed by `mrn` |
| `pairs` | Recipient/donor matches, their status and dates |
| `labs` | The catalogue of tests — what *can* be run, per organ and person type |
| `lab_parents` | The group each lab is listed under (Virology, Imaging, ...) |
| `lab_results` | One row per patient per lab: status, result, date, comment |
| `mrp` | Most responsible physicians |
| `coordinators` | Transplant coordinators |
| `organ_programs` | The programmes the picker offers: label, description, icon (new) |
| `staff` | Sign-in accounts for the login screen (new) |

### One database object per screen

Every screen in the design that shows data has something in the database named
after it and shaped the way it reads:

| Screen | Object | |
| --- | --- | --- |
| Recipient Waitlist | `waiting_list` | view — unmatched recipients, with the score |
| Donors List | `donors` | view — every donor, lab progress, `is_matched` |
| Pairs List | `pairs_overview` | view — one row per pair, both sides flattened |
| Dashboard | `dashboard_stats` | view — one row per programme, the four counters |
| (the recipient register) | `recipients` | view — the symmetric counterpart of `donors` |
| Organ picker | `organ_programs` | table — its label, description and icon |

**`donors` is a view over `patients WHERE type = 'donor'`, not a table of its
own.** A donor is a person, and so is a recipient: the same MRN, the same lab
results, the same two foreign keys from `pairs`. The original schema worked
this way and every retained model assumes it —
`PairsModel::get_unmatched_donors()` queries `patients WHERE type = 'donor'`,
and `expand_pairs()` calls `get_patient_info_modified()` for both halves of a
pair. A physical `donors` table would mean duplicating fifteen columns, giving
`pairs` two different foreign-key targets, splitting `lab_results.patient_id`
in two, and deciding what happens when the same person donates on one
programme and receives on another. The view gives the screen its own name and
its own shape with none of that.

`recipients` and `donors` hold *everyone* of that type and expose an
`is_matched` flag; each screen renders `WHERE is_matched = 0` for the unmatched
list. `waiting_list` is the one that filters for you, because "waiting list"
means unmatched.

The views are read-only. `patients` and `pairs` stay the only things written
to, so there is nothing to keep in sync.

`organ_programs` is a real table because it is content, not derivation: the
picker's heading, its subtitle and its icon were hardcoded in
`Ui::organSelector()`, which made adding a third programme a code change.
`patients.organs` and `labs.organ_type` stay ENUMs, since `ListsModel` reads
their values back with `SHOW COLUMNS` to build dropdowns, and a test asserts
the ENUMs and `organ_programs.code` never drift apart. So adding a programme is
a row here plus a migration widening those two ENUMs.

### The waiting-list score is unchanged

`waiting_list` carries the score expression over character for character from
`PairsModel::SCORE_CALC`:

```sql
(0.1 * TIMESTAMPDIFF(MONTH, entry_date, CURDATE())) +
(0.1 * TIMESTAMPDIFF(MONTH, dialysis,   CURDATE()))
```

So a recipient 20 months on the list and 30 months on dialysis still scores
5.0. Its one quirk is preserved too: `dialysis` is nullable and NULL plus a
number is NULL, so a recipient with no dialysis date scores NULL rather than
counting only the waiting time. That is the original behaviour and is left
alone — `score_entry_only` sits beside it for anyone who wants the
waiting-time half on its own.

Two subtleties that keep the retained models working:

- **`urgency` is declared least-urgent-first** — `ENUM('low','medium','high','critical')`.
  MySQL sorts an ENUM by declaration index, so `ORDER BY urgency DESC`, which
  `PairsModel::get_unmatched_recipients()` still does, keeps meaning
  most-urgent-first exactly as it did when the column held 0 or 1. Declaring
  it critical-first would silently invert the waiting list. The screens take
  their own order from `UiStore::URGENCY_OPTIONS`.
- **`patients.organs` and `pairs.programs` keep their original plural names**,
  because `ListsModel` reads them back by name with `SHOW COLUMNS` to build
  its dropdowns.

### What was added, and why

Everything the original schema had is present under the same name. These are
the additions, all of them fields the platform's screens collect and the old
schema had nowhere to put:

| Added | Table | Why |
| --- | --- | --- |
| `coordinator_id` | `patients` | The add-patient form always had a Coordinator dropdown, but no column existed, so the choice was silently dropped on save |
| `hospital` | `patients` | Shown on every record screen and in the donors table |
| `diagnosis` | `patients` | Recipient screens collect a primary diagnosis |
| `donation_type` | `patients` | living / deceased, rendered as a badge in the donors table |
| `relationship` | `patients` | "Brother of recipient R-001"; survives before a pair exists |
| `is_urgent`, `urgency_rank` | `patients` | Generated, never written: the old 0/1 urgency and a sortable rank |
| `status` values | `patients` | `completed` and `cancelled`, from the Donor Status dropdown |
| `note` | `pairs` | The pairs table has a Note column and the pair screen a notes box |
| `match_status` values | `pairs` | `active`, `scheduled`, `on_hold`, from the Match Status dropdown; the original five are untouched |
| `status` | `lab_results` | The pending / completed / flagged state every lab card shows and the progress bar counts |
| `result_date` | `lab_results` | Each card shows the date the result came back |
| `sort_order`, `is_active` | `labs` | Order a workup without depending on `lab_id` order; retire a test without deleting its history |
| `staff` (whole table) | — | The login screen had nothing to authenticate against |
| `organ_programs` (whole table) | — | The picker's label, description and icon were hardcoded in the controller |
| `recipients`, `donors`, `pairs_overview`, `dashboard_stats` | — | A view per list screen in the design, so each has an object shaped the way it reads |
| Foreign keys, indexes | all | None existed. Deleting a paired patient now fails loudly; the filter columns are indexed |

Two deliberate omissions:

- **`tests`** existed in the old database but no code referenced it, so its
  columns could not be recovered. It was not carried over. If it holds
  anything, its `SHOW CREATE TABLE` is all that is needed to add it.
- **No unique index for "one open pair per recipient/donor".** The natural way
  to write it is a unique index over a generated column that is NULL while the
  pair is closed, but MariaDB rejects a generated column reading a column that
  belongs to an `ON UPDATE CASCADE` foreign key (error 1901), and both MRN
  columns do so that a corrected MRN still propagates. The cascade is worth
  more; the rule stays in `PairsModel::pair_exists()`, where it already lived.

### Verifying it

`tests/database/SchemaTest.php` checks the schema against the three things it
has to satisfy — every retained model, every field the screens collect, and
every list screen's view returning what that screen renders. It needs a MySQL
`tests` group and skips otherwise:

```ini
database.tests.hostname = 127.0.0.1
database.tests.database = donations_test
database.tests.username = ...
database.tests.password = ...
database.tests.DBDriver = MySQLi
database.tests.DBPrefix =
```

The empty prefix is not incidental: `PairsModel`'s `NOT IN (SELECT ... FROM
pairs)` sub-select and `ListsModel`'s `SHOW COLUMNS FROM <table>` were carried
over from CodeIgniter 3 naming their tables directly, so neither survives a
`DBPrefix`. The `default` group has no prefix either, so this matches how the
application runs — but it is a real limitation of those two models if a
prefixed install is ever wanted.

### Creating the first staff account

Nothing is seeded, and `Ui::attemptLogin()` does not check this table yet — it
still accepts any non-empty credentials. Once it does, an account is:

```php
php spark db:query "INSERT INTO staff (staff_id, name, password_hash, role)
  VALUES ('DR-00421', 'Full Name', '$(php -r "echo password_hash('the-password', PASSWORD_DEFAULT);")', 'admin')"
```

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
screens, so the open question is no longer "finish `Patient::add()`" but
"wire `UiStore` to `App\Models\*`", as noted under
[Not wired up yet](#not-wired-up-yet).

## Licence

See [LICENSE](LICENSE). CodeIgniter itself is MIT-licensed; see the
[user guide](https://codeigniter.com/user_guide/) for framework documentation.
