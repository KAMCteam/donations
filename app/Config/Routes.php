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
