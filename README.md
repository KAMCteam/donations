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

| Route | Controller | Purpose |
| --- | --- | --- |
| `/Organ` | `Organ` | Pick kidney or liver; everything else is gated on this |
| `/` , `/Patient` | `Patient` | Add a recipient/donor for the selected programme |
| `/MRP` | `MRP` | Add labs and MRPs (most responsible physicians) |
| `/WaitingList` | `WaitingList` | Unmatched recipients, ranked by urgency and score |
| `/UpdatePatient` | `UpdatePatient` | Find a patient, then edit them and their pairing |
| `/Pairs` | `Pairs` | All pairs, and the single-pair comparison view |
| `/Dashboard` | `Dashboard` | Chart.js counters |

Every route except `/Organ` runs behind the `organ` filter
(`App\Filters\OrganFilter`), which sends visitors to the organ picker until they
have chosen one.

### The `ui/*` screens

A second, newer front end lives under `/ui`, merged in from the
`donations_html_ui` design package. It is **additive** — the screens above are
untouched and still the ones behind `/`, so both can run side by side while the
new set is reviewed.

| Route | Purpose |
| --- | --- |
| `/ui/login` | Staff login |
| `/ui/organ` | Programme picker (kidney / liver) |
| `/ui/dashboard` | Programme statistics and high-priority waitlist |
| `/ui/recipients` | Recipient waitlist, filterable by blood type |
| `/ui/recipients/new`, `/ui/recipients/{id}` | Add / open a recipient |
| `/ui/donors`, `/ui/donors/new`, `/ui/donors/{id}` | Donor registry and records |
| `/ui/pairs` | Pairs register, filterable by blood type and status |
| `/ui/pairs/export` | The filtered pairs as CSV |
| `/ui/pairs/new`, `/ui/pairs/{id}` | Add / open a pair |
| `/ui/mrp` | Register a Medical Responsible Person |

These routes sit **outside** the `organ` filter on purpose: they carry their own
login and programme picker and keep the choice in the `ui_organ` session key, so
the filter would otherwise bounce every one of them to `/Organ`.

#### HTML is the source of these screens

The design package arrived as a single-page app: one empty `<div id="app">` and
ten JavaScript files that wrote every screen into it as concatenated strings.
That was inverted during the merge — the markup is now HTML and JavaScript only
reacts to it:

| Concern | Where it lives now |
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
full-page screenshot diffs at 1440px. Login, the programme picker and the pairs
register are pixel-identical; the rest differ by 200–400 pixels out of 1.4–3.1
million, all of it antialiasing on a single character boundary in labels such as
"4 unmatched donors" or "Back to Recipient Waitlist". The package built those
strings with a helper that inserted an empty HTML comment between each part to
imitate React's text nodes, which changes how the browser kerns the join; the
views here emit one ordinary string and let the browser shape it normally.

Records are held per session and seeded from `UiSeed.php`, the design package's
demo data converted to PHP — nothing is read from or written to the database
yet. Point the read methods in `UiStore` at `App\Models\*` to put the screens
on the live `patients` / `pairs` tables; the views take plain arrays and need no
changes. The login accepts any non-empty Staff ID and password, exactly as the
package's did, and must be wired to the real staff directory before these
screens go anywhere near production.

## Migration notes (CodeIgniter 3 → 4)

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

- **`organ_chosen()` became a filter.** CI4 cannot redirect from inside a helper
  called by a constructor, so the guard is now `App\Filters\OrganFilter`, applied
  to a route group. The helper is still there for any remaining call sites, but
  it returns a redirect rather than sending one.
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

### Still outstanding

`Patient::add()` was unfinished in the CodeIgniter 3 project — it dumped the
collected recipient and donor arrays instead of saving them. That behaviour is
preserved (as a plain-text response rather than a `var_dump()`/`exit`), and the
older working version is kept as `Patient::add_deprecated()`.

## Licence

See [LICENSE](LICENSE). CodeIgniter itself is MIT-licensed; see the
[user guide](https://codeigniter.com/user_guide/) for framework documentation.
