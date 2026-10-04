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

// Is this file number free? The one thing the forms cannot answer themselves,
// so `ui.js` asks it as the box is being typed into rather than leaving it to
// the save. Reads nothing but the two registers, writes nothing.
$routes->get('mrn-taken/(:segment)/(:num)', 'Ui::mrnTaken/$1/$2');

$routes->get('recipients', 'Ui::recipients');
// Before the record route, which would otherwise read `print` as an MRN.
$routes->get('recipients/print', 'Ui::printRecipients');
$routes->match(['get', 'post'], 'recipients/new', 'Ui::addRecipient');
// Both link routes come before the record route: `(:segment)` stops at a
// slash, so they cannot be confused, but reading them in this order makes
// that obvious.
$routes->get('recipients/(:segment)/link', 'Ui::linkRecipient/$1');
$routes->match(['get', 'post'], 'recipients/(:segment)/link/existing', 'Ui::linkRecipientExisting/$1');
$routes->post('recipients/(:segment)/labs', 'Ui::addLab/recipient/$1');
$routes->match(['get', 'post'], 'recipients/(:segment)/labs/(:num)/delete', 'Ui::removeLab/recipient/$1/$2');
$routes->get('recipients/(:segment)/print', 'Ui::printRecipient/$1');
$routes->match(['get', 'post'], 'recipients/(:segment)/delete', 'Ui::deleteRecipient/$1');
$routes->match(['get', 'post'], 'recipients/(:segment)', 'Ui::recipient/$1');

$routes->get('donors', 'Ui::donors');
$routes->get('donors/print', 'Ui::printDonors');
$routes->match(['get', 'post'], 'donors/new', 'Ui::addDonor');
$routes->post('donors/(:segment)/labs', 'Ui::addLab/donor/$1');
$routes->match(['get', 'post'], 'donors/(:segment)/labs/(:num)/delete', 'Ui::removeLab/donor/$1/$2');
$routes->get('donors/(:segment)/print', 'Ui::printDonor/$1');
$routes->match(['get', 'post'], 'donors/(:segment)/delete', 'Ui::deleteDonor/$1');
$routes->get('donors/(:segment)/link', 'Ui::linkDonor/$1');
$routes->match(['get', 'post'], 'donors/(:segment)/link/existing', 'Ui::linkDonorExisting/$1');
$routes->match(['get', 'post'], 'donors/(:segment)', 'Ui::donor/$1');

$routes->get('pairs', 'Ui::pairs');
$routes->get('pairs/print', 'Ui::printPairs');
$routes->match(['get', 'post'], 'pairs/new', 'Ui::addPair');
// A pair's donors are all worked from the pair's own screen — added, moved
// between the three words, swapped and archived — so each of these posts and
// comes straight back to it, at the tab it was pressed on. There is no page
// of their own: the tabs are on the pair.
$routes->post('pairs/(:segment)/donors', 'Ui::addPairDonor/$1');
$routes->post('pairs/(:segment)/donors/(:num)/status', 'Ui::pairDonorStatus/$1/$2');
$routes->match(['get', 'post'], 'pairs/(:segment)/donors/(:num)/delink', 'Ui::delinkPairDonor/$1/$2');
$routes->post('pairs/(:segment)/donors/(:num)/swap', 'Ui::swapPairDonor/$1/$2');
// A donor's workup is edited where it is read, so adding and removing a test
// they added for themselves comes back to the pair's screen too.
$routes->post('pairs/(:segment)/donors/(:num)/labs', 'Ui::addPairDonorLab/$1/$2');
$routes->match(['get', 'post'], 'pairs/(:segment)/donors/(:num)/labs/(:num)/delete', 'Ui::removePairDonorLab/$1/$2/$3');
$routes->post('pairs/(:segment)/labs/(:segment)', 'Ui::addPairLab/$1/$2');
$routes->match(['get', 'post'], 'pairs/(:segment)/labs/(:segment)/(:num)/delete', 'Ui::removePairLab/$1/$2/$3');
$routes->get('pairs/(:segment)/print', 'Ui::printPair/$1');
$routes->match(['get', 'post'], 'pairs/(:segment)/delete', 'Ui::deletePair/$1');
$routes->match(['get', 'post'], 'pairs/(:segment)', 'Ui::pair/$1');

// Paired exchange. `build` is one address for the screen and its four
// actions, so every button on it is a plain form post.
$routes->get('exchange', 'Exchange::index');
$routes->post('exchange/start/(:segment)', 'Exchange::start/$1');
$routes->match(['get', 'post'], 'exchange/build', 'Exchange::build');
$routes->get('exchange/review', 'Exchange::review');

// Reports. One screen, and the same report on paper at two depths.
$routes->get('reports', 'Reports::index');
$routes->get('reports/export/(:segment)', 'Reports::export/$1');

// The one screen that makes users — physicians and coordinators both. The
// lookup route is the hospital directory's, and comes back saying it is not
// connected yet until it is.
$routes->get('mrp', 'Ui::mrp');
$routes->post('mrp', 'Ui::addMrp');
$routes->post('mrp/lookup', 'Ui::lookupMrp');
$routes->post('mrp/(:num)/active', 'Ui::setMrpActive/$1');
$routes->post('mrp/(:num)', 'Ui::updateMrp/$1');
