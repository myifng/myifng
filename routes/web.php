<?php
/**
 * वेबसाइट के रूट। Phase 5 में होम, श्रेणी, लोकेशन, ख़बर आदि यहाँ जुड़ेंगे।
 * @var App\Core\Router $router
 */
use App\Controllers\Front\HomeController;
use App\Controllers\Front\LocationApiController;
use App\Controllers\Front\PageController;

$router->group(['middleware' => ['maintenance', 'uptodate']], function ($r) {
    $r->get('/', [HomeController::class, 'index'])->name('home');
    $r->get('/page/{slug:[a-z0-9-]+}', [PageController::class, 'show'])->name('page');

    // मेरा शहर (Phase 3)
    $r->get('/api/locations', [LocationApiController::class, 'search'])->name('api.locations');
    $r->post('/api/my-city', [LocationApiController::class, 'save'])->name('api.my_city')->middleware('throttle:30,10');
});
