<?php
// app/Config/AdminRoutes.php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

/*──────────────────────── لوحة الإدارة العامة ───────────────────────*/
$routes->get('admins',                  'Admins::index',                       ['filter' => 'auth']);
$routes->get('admins/debug/session',    'admins\DebugController::sessionDump', ['filter' => 'auth']);

// Fragments عامة
$routes->get('ui/upgrade-modal', 'Ui\Fragments::upgradeModal');

/*────────────── صفحة الملف الشخصي ───────────*/
$routes->get('admins/pages/account', 'ProfileController::index', ['filter' => 'auth']);

/*────────────── خيارات الأعوام الدراسية ───────────*/
$routes->get(
    'admins/pages/academic_year_options',
    'AcademicYearOptions::index',
    ['as' => 'admins.academicYearOptions', 'filter' => 'auth']
);

/*────────────── مسارات الخطط ───────────*/
$routes->group('admins', ['namespace' => 'App\Controllers\Admins', 'filter' => 'auth'], static function($routes){
    $routes->get   ('pages/plans',             'PlansMeta::page');
    $routes->get   ('api/plans',               'PlansMeta::index');
    $routes->post  ('api/plans',               'PlansMeta::store');
    $routes->put   ('api/plans/(:num)',        'PlansMeta::update/$1');
    $routes->delete('api/plans/(:num)',        'PlansMeta::delete/$1');

    $routes->post  ('api/plans/(:num)/features', 'PlansMeta::addFeature/$1');
    $routes->put   ('api/plan-features/(:num)',  'PlansMeta::updateFeature/$1');
    $routes->delete('api/plan-features/(:num)',  'PlansMeta::deleteFeature/$1');
});

/*────────────── طلبات الخدمة (جدول) ───────────*/
$routes->get ('admins/service-requests-table',               'adminsServiceRequestsTableController::index',            ['filter' => 'auth']);
$routes->get ('admins/service-requests-table/list',          'adminsServiceRequestsTableController::getRequestsList',  ['filter' => 'auth']);
$routes->get ('admins/service-requests-table/adminss',       'adminsServiceRequestsTableController::getadminssList',   ['filter' => 'auth']); // intentional "adminss"
$routes->get ('admins/service-requests-table/stats',         'adminsServiceRequestsTableController::getStats',         ['filter' => 'auth']);
$routes->post('admins/service-requests-table/status/(:num)', 'adminsServiceRequestsTableController::updateStatus/$1',  ['filter' => 'auth']);
$routes->post('admins/service-requests-table/assign/(:num)', 'adminsServiceRequestsTableController::assignRequest/$1', ['filter' => 'auth']);

/*────────────── شات طلبات الخدمة ───────────*/
$routes->group('admins/service-requests-chat', ['filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get ('/',                     'adminsServiceRequestsChatController::index');
    $routes->get ('list',                  'adminsServiceRequestsChatController::getRequestsList');
    $routes->get ('stats',                 'adminsServiceRequestsChatController::stats');
    $routes->get ('details/(:num)',        'adminsServiceRequestsChatController::getRequestDetails/$1');
    $routes->get ('messages/(:num)',       'adminsServiceRequestsChatController::getMessageHistory/$1');
    $routes->get ('new-messages/(:num)',   'adminsServiceRequestsChatController::getNewMessages/$1');
    $routes->post('message/(:num)',        'adminsServiceRequestsChatController::sendMessage/$1');
    $routes->post('message/(:num)/delete', 'adminsServiceRequestsChatController::deleteMessage/$1');
    $routes->get ('file/(:num)',           'adminsServiceRequestsChatController::downloadFile/$1');
    $routes->post('mark-read/(:num)',      'adminsServiceRequestsChatController::markAsRead/$1');
    $routes->get ('unread-counts',         'adminsServiceRequestsChatController::getUnreadCounts');
    $routes->post('status/(:num)',         'adminsServiceRequestsChatController::updateStatus/$1');
    $routes->post('status',                'adminsServiceRequestsChatController::updateStatus');
    $routes->post('update-status/(:num)',  'adminsServiceRequestsChatController::updateStatus/$1');
    $routes->post('update-status',         'adminsServiceRequestsChatController::updateStatus');
});

/*────────────── إحصائيات الأدمن ───────────*/
$routes->group('admins/stats', ['filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('users-activity',          'DashboardStatsController::usersActivity');
    $routes->get('ai-usage',                'DashboardStatsController::aiUsage');
    $routes->get('satisfaction-level',      'DashboardStatsController::satisfactionLevel');
    $routes->get('survey-devices',          'DashboardStatsController::surveyDevices');
    $routes->get('survey-sources',          'DashboardStatsController::surveySources');
    $routes->get('registration-completion', 'DashboardStatsController::registrationCompletion');
});
$routes->get('admins/stats', 'adminsStats::index', ['filter' => 'auth']);

/*────────────── مكتبة المنصة ───────────*/
$routes->group('admins/library', ['filter' => 'auth'], static function ($routes) {
    $routes->get ('uploads', 'admins\LibraryUpload::index');
    $routes->post('uploads', 'admins\LibraryUpload::store');

    $routes->get('meta/grades',                 'admins\LibraryMeta::grades');
    $routes->get('meta/grades/(:num)/subjects', 'admins\LibraryMeta::subjects/$1');
    $routes->get('meta/semesters',              'admins\LibraryMeta::semesters');
    $routes->get('meta/categories',             'admins\LibraryMeta::categories');

    $routes->get   ('files',               'admins\LibraryMeta::files');
    $routes->delete('files/(:num)',        'admins\LibraryMeta::delete/$1');
    $routes->post  ('files/(:num)/delete', 'admins\LibraryMeta::delete/$1');
});
$routes->get('admins/library/debug', 'admins\LibraryMeta::debug', ['filter' => 'auth']);

/*────────────── Catch-all لصفحات الأدمن ───────────*/
$routes->get('admins/pages/(:segment)', 'Admins::loadPage/$1', ['filter' => 'auth']);
