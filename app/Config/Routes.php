<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

/*
 * The transplant UI (app/Controllers/Ui.php, app/Views/ui).
 *
 * These screens replaced the CodeIgniter 3 ones that this application was
 * migrated with — Patient, MRP, WaitingList, UpdatePatient, Pairs, Dashboard,
 * Organ and Test, together with the `organ` filter that guarded them — so they
 * now own the root of the site rather than sitting under a `/ui` prefix.
 *
 * Auto-routing is off (Config\Routing::$autoRoute), so every reachable
 * endpoint is listed here. Fixed segments are declared before the
 * `(:segment)` catch-alls, otherwise `recipients/new` would be read as a
 * recipient id.
 */

// Entry point: sends visitors to the login screen, or to the dashboard if they
// are already signed in.
$routes->get('/', 'Ui::index');

$routes->get('login', 'Ui::login');
$routes->post('login', 'Ui::attemptLogin');
$routes->get('logout', 'Ui::logout');

// Programme picker. Its own screen rather than a filter, since the UI asks for
// the programme once per session, right after signing in.
$routes->get('organ', 'Ui::organSelector');
$routes->get('organ/(:segment)', 'Ui::chooseOrgan/$1');

$routes->get('dashboard', 'Ui::dashboard');

$routes->get('recipients', 'Ui::recipients');
$routes->match(['get', 'post'], 'recipients/new', 'Ui::addRecipient');
// Both link routes come before the record route: `(:segment)` stops at a
// slash, so they cannot be confused, but reading them in this order makes
// that obvious.
$routes->get('recipients/(:segment)/link', 'Ui::linkRecipient/$1');
$routes->match(['get', 'post'], 'recipients/(:segment)/link/existing', 'Ui::linkRecipientExisting/$1');
$routes->match(['get', 'post'], 'recipients/(:segment)', 'Ui::recipient/$1');

$routes->get('donors', 'Ui::donors');
$routes->match(['get', 'post'], 'donors/new', 'Ui::addDonor');
$routes->get('donors/(:segment)/link', 'Ui::linkDonor/$1');
$routes->match(['get', 'post'], 'donors/(:segment)/link/existing', 'Ui::linkDonorExisting/$1');
$routes->match(['get', 'post'], 'donors/(:segment)', 'Ui::donor/$1');

$routes->get('pairs', 'Ui::pairs');
$routes->get('pairs/print', 'Ui::printPairs');
$routes->match(['get', 'post'], 'pairs/new', 'Ui::addPair');
$routes->match(['get', 'post'], 'pairs/(:segment)', 'Ui::pair/$1');

$routes->get('mrp', 'Ui::mrp');
$routes->post('mrp', 'Ui::addMrp');
