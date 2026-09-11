<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->get('login', '\CodeIgniter\Shield\Controllers\LoginController::loginView', ['as'=>'login']);
$routes->post('login', '\CodeIgniter\Shield\Controllers\LoginController::loginAction', ['filter'=>'auth-rates']);
$routes->post('logout', '\CodeIgniter\Shield\Controllers\LoginController::logoutAction', ['as'=>'logout']);
$routes->get('/', 'Dashboard::index', ['filter'=>'internal:clientes.ver']);
$routes->get('clientes', 'Clients::index', ['filter'=>'internal:clientes.ver']);
$routes->get('clientes/nuevo', 'Clients::form', ['filter'=>'internal:clientes.crear']);
$routes->post('clientes', 'Clients::save', ['filter'=>'internal:clientes.crear']);
$routes->get('clientes/(:num)', 'Clients::form/$1', ['filter'=>'internal:clientes.editar']);
$routes->post('clientes/(:num)', 'Clients::save/$1', ['filter'=>'internal:clientes.editar']);
$routes->post('clientes/(:num)/estado', 'Clients::state/$1', ['filter'=>'internal:clientes.desactivar']);
$routes->get('catalogo', 'Catalog::index', ['filter'=>'internal:catalogo.gestionar']);
$routes->get('catalogo/nuevo', 'Catalog::form', ['filter'=>'internal:catalogo.gestionar']);
$routes->post('catalogo', 'Catalog::save', ['filter'=>'internal:catalogo.gestionar']);
$routes->get('catalogo/(:num)', 'Catalog::form/$1', ['filter'=>'internal:catalogo.gestionar']);
$routes->post('catalogo/(:num)', 'Catalog::save/$1', ['filter'=>'internal:catalogo.gestionar']);
