<?php

use App\Controllers\Auth;

/**
 * Application shell — sidebar, top bar and page host.
 *
 * Replaces the `render()` / `sidebarHTML()` string builders of the prototype's
 * `js/app.js`: the markup below is the same DOM those functions produced, only
 * now it is written as HTML and the active nav item is resolved by PHP instead
 * of by comparing a `state.page` string in the browser.
 *
 * @var string $title    Browser tab title.
 * @var string $navPage  Nav item to mark active (dashboard, recipients, ...).
 * @var string $organ    Current programme, for the top-bar title.
 * @var string $searchQuery  What the search box is showing, if anything.
 * @var string $searchPlaceholder  What it says when it is empty.
 */
$navItems = [
    ['page' => 'dashboard',  'label' => 'Dashboard',          'icon' => 'dashboard', 'url' => site_url('dashboard')],
    ['page' => 'recipients', 'label' => 'Recipient Waitlist', 'icon' => 'users',     'url' => site_url('recipients')],
    ['page' => 'donors',     'label' => 'Donors List',        'icon' => 'heart',     'url' => site_url('donors')],
    ['page' => 'pairs',      'label' => 'Pairs List',         'icon' => 'link17',    'url' => site_url('pairs')],
    ['page' => 'exchange',   'label' => 'Paired Exchange',    'icon' => 'shuffle',   'url' => site_url('exchange')],
    ['page' => 'reports',    'label' => 'Reports',            'icon' => 'clipboard', 'url' => site_url('reports')],
];

// Everything about a user — registering one, editing one, the sign-in log —
// is one screen behind one permission, so it is one item and only for the
// people who hold it. Add MRP used to sit here for everybody.
//
// Leaving it out is a courtesy and not the lock: `admin` on the route is what
// keeps anybody else out, and the address is typeable either way.
if (Auth::isAdmin()) {
    $navItems[] = ['page' => 'admin', 'label' => 'Admin', 'icon' => 'userPlus', 'url' => site_url('admin')];
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Streamline organ donation and transplant processes with a user-friendly platform for managing recipients, donors, and pairs, enhancing efficiency for healthcare professionals.">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($title ?? 'Transplant Program') ?></title>

    <link rel="icon" type="image/x-icon" href="<?= base_url('assets/img/KAMC.png') ?>">

    <link rel="stylesheet" href="<?= base_url('assets/ui/css/base.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/layout.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/components.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/ui/css/pages.css') ?>">
    <?php // A screen with enough of its own to say adds a stylesheet here,
          // after the shared ones so it can build on them. ?>
    <?= $this->renderSection('head') ?>
</head>

<body>
    <div id="app">
        <div class="app">
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-logo">
                    <img src="<?= base_url('assets/ui/img/kamc-white.png') ?>" alt="King Abdullah Medical City">
                    <button type="button" class="sidebar-close" data-sidebar-close aria-label="Close menu"><?= ui_icon('close') ?></button>
                </div>

                <nav class="sidebar-nav">
                    <?php foreach ($navItems as $item): ?>
                        <a class="nav-item<?= ($navPage ?? '') === $item['page'] ? ' is-active' : '' ?>" href="<?= $item['url'] ?>">
                            <span class="nav-icon"><?= ui_icon($item['icon']) ?></span><?= esc($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="sidebar-footer">
                    <a class="signout-btn" href="<?= site_url('logout') ?>"><?= ui_icon('signOut') ?>Sign out</a>
                </div>
            </aside>

            <main class="app-main has-sidebar">
                <div class="topbar">
                    <button type="button" class="topbar-menu-btn" data-sidebar-open aria-label="Open menu"><?= ui_icon('menu') ?></button>
                    <span class="topbar-title"><?= esc($organ) ?> Transplant Program</span>
                </div>

                <div id="page" class="page-host">
                    <?php // The search narrows the list you are looking at,
                          // so it posts back to the screen you are on and
                          // never leaves it. A screen with a list to narrow
                          // asks for it by setting `searchOn`; one without —
                          // the dashboard, Add MRP, a record — has nothing for
                          // it to do and does not carry it.
                          //
                          // A GET form, so the question is in the address,
                          // comes back on a refresh and can be sent to
                          // somebody. The filters already on the screen ride
                          // along as hidden fields, so searching narrows what
                          // is showing rather than replacing it. ?>
                    <?php
                    // Which screens have one is decided here, from the nav item
                    // every screen already declares, rather than from a flag
                    // each would have to remember to pass: CodeIgniter keeps
                    // view data between `view()` calls, so a missing flag is
                    // not a false one — it is the last screen's.
                    $searchable = in_array($navPage ?? '', ['recipients', 'donors', 'pairs', 'exchange', 'reports'], true);
                    ?>
                    <?php if ($searchable): ?>
                        <div class="app-search-bar">
                            <form class="app-search" method="get" action="<?= current_url() ?>" role="search">
                                <?php // Everything in the address but the
                                      // question itself, carried as it was —
                                      // Reports asks its filters as arrays, so
                                      // this has to nest. ?>
                                <?php
                                $keepFields = static function (array $values, string $prefix) use (&$keepFields): void {
                                    foreach ($values as $key => $value) {
                                        $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

                                        if (is_array($value)) {
                                            $keepFields($value, $name);

                                            continue;
                                        }

                                        echo '<input type="hidden" name="' . esc($name, 'attr') . '" value="' . esc((string) $value, 'attr') . '">';
                                    }
                                };
                                $keep = service('request')->getGet();
                                unset($keep['q']);
                                $keepFields($keep, '');
                                ?>
                                <label class="sr-only" for="app-q">Search this list</label>
                                <span class="app-search-icon"><?= ui_icon('search') ?></span>
                                <input type="search" id="app-q" name="q" class="app-search-input"
                                       value="<?= esc($searchQuery ?? '') ?>" placeholder="<?= esc($searchPlaceholder ?? 'Search this list by MRN or name') ?>" inputmode="search" autocomplete="off">
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php // What just happened, once. Set by the actions that
                          // redirect rather than render — a delete has no screen
                          // of its own to say it worked on.
                          //
                          // Once is the word: a screen with a slot of its own
                          // for the refusal reads the same flash, and printing
                          // it here as well said everything twice. ?>
                    <?php foreach (['ui_notice' => 'notice', 'ui_error' => 'notice notice--error'] as $key => $class): ?>
                        <?php if ($key === 'ui_error' && ($error ?? '') !== '') { continue; } ?>
                        <?php if ((string) session()->getFlashdata($key) !== ''): ?>
                            <div class="<?= $class ?>" role="status"><?= esc(session()->getFlashdata($key)) ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?= $this->renderSection('content') ?>
                </div>
            </main>
        </div>
    </div>

    <?php // The form the Add lab buttons belong to. Empty and out of the way,
          // because a form cannot sit inside another and these buttons sit
          // inside the card's: owned by this one, they cannot be what Enter
          // presses in a box somewhere else on the card. Each button carries
          // its own `formaction`, so one form serves every workup on a page. ?>
    <form id="lab-add" method="post" hidden><?= csrf_field() ?></form>

    <?php // And the form every "Are you sure?" answers to, for the same
          // reason: the dialogs that ask sit wherever the thing they are about
          // is, which is often already inside a form. Each button carries its
          // own `formaction`, so one form serves every question on a page. ?>
    <form id="confirm-post" method="post" hidden><?= csrf_field() ?></form>

    <script src="<?= base_url('assets/ui/js/ui.js') ?>"></script>
    <?php // What a single screen needs and the others do not. ?>
    <?= $this->renderSection('scripts') ?>
</body>

</html>
