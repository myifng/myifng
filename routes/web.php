<?php
/**
 * वेबसाइट के रूट। Phase 5 में होम, श्रेणी, लोकेशन, ख़बर आदि यहाँ जुड़ेंगे।
 * @var App\Core\Router $router
 */
use App\Controllers\Front\HomeController;

$router->group(['middleware' => ['maintenance']], function ($r) {
    $r->get('/', [HomeController::class, 'index'])->name('home');
});
