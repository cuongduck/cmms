<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Dashboard::index');

// Authentication routes
$routes->group('auth', static function ($routes) {
    $routes->get('login', 'Auth::login');
    $routes->post('login', 'Auth::loginProcess');
    $routes->get('logout', 'Auth::logout');
});

// Dashboard
$routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth']);

// User Management
$routes->group('users', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Users::index');
    $routes->get('create', 'Users::create');
    $routes->post('store', 'Users::store');
    $routes->get('edit/(:num)', 'Users::edit/$1');
    $routes->post('update/(:num)', 'Users::update/$1');
    $routes->delete('delete/(:num)', 'Users::delete/$1');
    $routes->post('ajax_list', 'Users::ajax_list');
});

// Equipment Management
$routes->group('thiet-bi', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'ThietBi::index');
    $routes->get('create', 'ThietBi::create');
    $routes->post('store', 'ThietBi::store');
    $routes->get('view/(:num)', 'ThietBi::view/$1');
    $routes->get('edit/(:num)', 'ThietBi::edit/$1');
    $routes->post('update/(:num)', 'ThietBi::update/$1');
    $routes->delete('delete/(:num)', 'ThietBi::delete/$1');
    $routes->post('ajax_list', 'ThietBi::ajax_list');
    $routes->get('generate_qr/(:num)', 'ThietBi::generateQR/$1');
});

// API routes for AJAX
$routes->group('api', ['filter' => 'auth'], static function ($routes) {
    $routes->get('xuong', 'Api::getXuong');
    $routes->get('line/(:num)', 'Api::getLineByXuong/$1');
    $routes->get('khu-vuc/(:num)', 'Api::getKhuVucByLine/$1');
    $routes->get('dong-may/(:num)', 'Api::getDongMayByKhuVuc/$1');
});