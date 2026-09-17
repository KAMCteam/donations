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
  still held per session in `UiStore`. That store now starts **empty** — the
  design package's invented patients, donors and pairs were deleted — so the
  screens show their empty states until real records are entered. Reimplement
  the read and write methods in `UiStore` on top of `App\Models\*`; the views
  take plain arrays and need no changes, which is what that class's interface
  is shaped for.
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

The seeder inserts **reference rows only** — the two programmes and the lab
catalogue. No patients, no donors, no pairs, no staff accounts, no physicians
or coordinators. It is re-runnable and skips anything already there, so it will
not undo edits made through the screens.

### The tables, and why each one is there

Ten tables, arrived at by walking the screens and asking what each reads and
writes.

| Table | Exists because |
| --- | --- |
| `staff` | The login screen needs something to authenticate against |
| `organ_programs` | The picker's cards are content — label, description, icon — not code |
| `mrp` | Every record screen assigns a most responsible physician |
| `coordinators` | Every record screen assigns a coordinator |
| `lab_parents` | The workup is shown grouped: Virology, Imaging, Cardiac |
| `labs` | The catalogue: which tests a workup is made of, per programme and side |
| `recipients` | The waiting list, and the two dates the score is computed from |
| `donors` | The donor register |
| `pairs` | The link between a recipient and a donor, and its history |
| `lab_results` | One row per person per test: status, value, date |

Two tables rather than one for people, because a recipient and a donor are not
the same record. A recipient has `entry_date`, `dialysis_start`, `urgency` and
`diagnosis`; a donor has `donation_type` and `relationship`. Sharing one table
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
delete restriction, and the MRN cascade). It needs a MySQL `tests` group and
skips otherwise:

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
screens, so the open question is no longer "finish `Patient::add()`" but
"wire `UiStore` to `App\Models\*`", as noted under
[Not wired up yet](#not-wired-up-yet).

## Licence

See [LICENSE](LICENSE). CodeIgniter itself is MIT-licensed; see the
[user guide](https://codeigniter.com/user_guide/) for framework documentation.
