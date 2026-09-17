<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

/*
 * Migrated from the CodeIgniter 3 application/config/routes.php.
 *
 * CI3 auto-routed every controller/method pair; CI4 ships with auto-routing
 * disabled, so every endpoint the old application exposed is declared here.
 * `organ_chosen()` used to run in each controller constructor - that guard is
 * now the `organ` filter applied to the group below.
 */

// Organ selection must stay outside the guard: it is where the guard redirects.
$routes->get('Organ', 'Organ::index');
$routes->get('Organ/destroy_sess', 'Organ::destroySession');

/*
 * The transplant screens ported from the HTML prototype (app/Views/ui).
 *
 * Outside the `organ` guard on purpose: these screens carry their own login and
 * programme picker and keep the choice in `ui_organ`, so the guard would bounce
 * every one of them to the old `Organ` page. They are additive — nothing above
 * or below changes — which is what lets both front ends run side by side while
 * the new one is reviewed.
 *
 * Fixed segments are declared before the `(:segment)` catch-alls so that
 * `ui/pairs/new` cannot be read as a pair id.
 */
$routes->group('ui', static function (RouteCollection $routes): void {
    $routes->get('/', 'Ui::index');

    $routes->get('login', 'Ui::login');
    $routes->post('login', 'Ui::attemptLogin');
    $routes->get('logout', 'Ui::logout');

    $routes->get('organ', 'Ui::organSelector');
    $routes->get('organ/(:segment)', 'Ui::chooseOrgan/$1');

    $routes->get('dashboard', 'Ui::dashboard');

    $routes->get('recipients', 'Ui::recipients');
    $routes->match(['get', 'post'], 'recipients/new', 'Ui::addRecipient');
    $routes->match(['get', 'post'], 'recipients/(:segment)', 'Ui::recipient/$1');

    $routes->get('donors', 'Ui::donors');
    $routes->match(['get', 'post'], 'donors/new', 'Ui::addDonor');
    $routes->match(['get', 'post'], 'donors/(:segment)', 'Ui::donor/$1');

    $routes->get('pairs', 'Ui::pairs');
    $routes->get('pairs/export', 'Ui::exportPairs');
    $routes->match(['get', 'post'], 'pairs/new', 'Ui::addPair');
    $routes->match(['get', 'post'], 'pairs/(:segment)', 'Ui::pair/$1');

    $routes->get('mrp', 'Ui::mrp');
    $routes->post('mrp', 'Ui::addMrp');
});

$routes->group('', ['filter' => 'organ'], static function (RouteCollection $routes): void {
    // $route['default_controller'] = 'Patient';
    $routes->get('/', 'Patient::index');

    $routes->get('Patient', 'Patient::index');
    $routes->get('Patient/getByMRN', 'Patient::getByMRN');
    $routes->post('Patient/add', 'Patient::add');

    $routes->get('MRP', 'MRP::index');
    $routes->post('MRP/addLab', 'MRP::addLab');
    $routes->post('MRP/addMRP', 'MRP::addMRP');

    $routes->get('WaitingList', 'WaitingList::index');

    // $route['UpdatePatient/update'] must win over $route['UpdatePatient/(:any)'].
    $routes->post('UpdatePatient/update', 'UpdatePatient::update');
    $routes->get('UpdatePatient', 'UpdatePatient::index');
    $routes->get('UpdatePatient/(:segment)', 'UpdatePatient::index/$1');

    $routes->get('Pairs', 'Pairs::index');
    $routes->get('Pairs/(:segment)', 'Pairs::index/$1');

    $routes->get('Dashboard', 'Dashboard::index');
    $routes->get('Dashboard/drawGraphs', 'Dashboard::drawGraphs');

    $routes->get('Test', 'Test::index');
    $routes->post('Test/test', 'Test::test');
});
