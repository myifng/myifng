<?php
/**
 * वेबसाइट के रूट। लोकेशन वाला रूट (/{राज्य}/{ज़िला}/…) सबसे आख़िर में, ताकि बाकी रास्ते पहले मिलें।
 * @var App\Core\Router $router
 */
use App\Controllers\Front\CategoryController;
use App\Controllers\Front\HomeController;
use App\Controllers\Front\LocationApiController;
use App\Controllers\Front\LocationController;
use App\Controllers\Front\NewsController;
use App\Controllers\Front\PageController;
use App\Controllers\Front\SearchController;
use App\Controllers\Front\TopicController;

$router->group(['middleware' => ['maintenance', 'uptodate']], function ($r) {
    $r->get('/', [HomeController::class, 'index'])->name('home');
    $r->get('/page/{slug:[a-z0-9-]+}', [PageController::class, 'show'])->name('page');

    // मेरा शहर (Phase 3)
    $r->get('/api/locations', [LocationApiController::class, 'search'])->name('api.locations');
    $r->post('/api/my-city', [LocationApiController::class, 'save'])->name('api.my_city')->middleware('throttle:30,10');

    // Phase 5: ख़बरें और सूची वाले पेज
    $r->get('/news/{slug:[a-z0-9-]+}', [NewsController::class, 'show'])->name('news.show');
    $r->get('/category/{slug:[a-z0-9-]+}', [CategoryController::class, 'show'])->name('category');
    $r->get('/topic/{slug:[a-z0-9-]+}', [TopicController::class, 'topic'])->name('topic');
    $r->get('/tag/{slug:[a-z0-9-]+}', [TopicController::class, 'tag'])->name('tag');
    $r->get('/latest', [SearchController::class, 'latest'])->name('latest');
    $r->get('/search', [SearchController::class, 'search'])->name('search');

    // लोकेशन: सबसे आख़िर में (slug आरक्षित शब्दों से नहीं टकराते: TaxonomyService::RESERVED)
    $r->get('/{path:[a-z0-9-]+(?:/[a-z0-9-]+)*}', [LocationController::class, 'show'])->name('location');
});
