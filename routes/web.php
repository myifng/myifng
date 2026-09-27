<?php
/**
 * वेबसाइट के रूट। लोकेशन वाला रूट (/{राज्य}/{ज़िला}/…) सबसे आख़िर में, ताकि बाकी रास्ते पहले मिलें।
 * @var App\Core\Router $router
 */
use App\Controllers\Front\AudioController;
use App\Controllers\Front\CategoryController;
use App\Controllers\Front\EpaperController;
use App\Controllers\Front\GalleryController;
use App\Controllers\Front\HomeController;
use App\Controllers\Front\LiveBlogController;
use App\Controllers\Front\LiveTvController;
use App\Controllers\Front\LocationApiController;
use App\Controllers\Front\LocationController;
use App\Controllers\Front\NewsController;
use App\Controllers\Front\PageController;
use App\Controllers\Front\ReporterJoinController;
use App\Controllers\Front\SearchController;
use App\Controllers\Front\TopicController;
use App\Controllers\Front\VerifyController;
use App\Controllers\Front\VideoController;
use App\Controllers\Front\WebStoryController;

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

    // Phase 6: रिपोर्टर बनें, आवेदन की स्थिति, सत्यापन
    $r->get('/join-as-reporter', [ReporterJoinController::class, 'form'])->name('join');
    $r->post('/join-as-reporter', [ReporterJoinController::class, 'submit'])->name('join.submit')->middleware('throttle:5,60');
    $r->get('/join-as-reporter/done', [ReporterJoinController::class, 'done'])->name('join.done');
    $r->get('/application-status', [ReporterJoinController::class, 'status'])->name('application.status');
    $r->post('/application-status', [ReporterJoinController::class, 'lookup'])->name('application.lookup')->middleware('throttle:10,15');
    $r->post('/application-status/otp', [ReporterJoinController::class, 'verifyOtp'])->name('application.otp')->middleware('throttle:15,15');
    $r->post('/application-status/reset', [ReporterJoinController::class, 'reset'])->name('application.reset');
    $r->get('/verify-reporter', [VerifyController::class, 'form'])->name('verify');
    $r->post('/verify-reporter', [VerifyController::class, 'check'])->name('verify.check')->middleware('throttle:20,10');
    $r->get('/verify-reporter/{code:[A-Za-z]+-[0-9]+-[0-9]+}/{token:[a-f0-9]+}', [VerifyController::class, 'qr'])->name('verify.qr');

    // Phase 7: लाइव ब्लॉग, लाइव टीवी, वीडियो, फ़ोटो, वेब स्टोरी, ऑडियो/पॉडकास्ट
    $r->get('/live-updates/{id:\d+}', [LiveBlogController::class, 'updates'])->name('live.updates');
    $r->get('/live-tv', [LiveTvController::class, 'show'])->name('live_tv');
    $r->get('/live-tv/{slug:[a-z0-9-]+}', [LiveTvController::class, 'show'])->name('live_tv.channel');
    $r->get('/videos', [VideoController::class, 'index'])->name('videos');
    $r->get('/videos/playlist/{slug:[a-z0-9-]+}', [VideoController::class, 'playlist'])->name('videos.playlist');
    $r->get('/video/{slug:[a-z0-9-]+}', [VideoController::class, 'show'])->name('video.show');
    $r->get('/photos', [GalleryController::class, 'index'])->name('galleries');
    $r->get('/photos/{slug:[a-z0-9-]+}', [GalleryController::class, 'show'])->name('gallery.show');
    $r->get('/web-stories', [WebStoryController::class, 'index'])->name('stories');
    $r->get('/web-stories/{slug:[a-z0-9-]+}', [WebStoryController::class, 'show'])->name('story.show');
    $r->get('/web-stories/{slug:[a-z0-9-]+}/amp', [WebStoryController::class, 'amp'])->name('story.amp');
    $r->get('/audio', [AudioController::class, 'index'])->name('audio');
    $r->get('/audio/{slug:[a-z0-9-]+}', [AudioController::class, 'show'])->name('audio.show');
    $r->get('/podcast/{slug:[a-z0-9-]+}', [AudioController::class, 'series'])->name('podcast');
    $r->get('/podcast/{slug:[a-z0-9-]+}/feed', [AudioController::class, 'feed'])->name('podcast.feed');

    // Phase 8: ई-पेपर
    $r->get('/epaper', [EpaperController::class, 'index'])->name('epaper');
    $r->get('/epaper/go', [EpaperController::class, 'go'])->name('epaper.go');
    $r->get('/epaper/{edition:[a-z0-9-]+}', [EpaperController::class, 'edition'])->name('epaper.edition');
    $r->get('/epaper/{edition:[a-z0-9-]+}/archive', [EpaperController::class, 'archive'])->name('epaper.archive');
    $r->get('/epaper/{edition:[a-z0-9-]+}/{date:[0-9]{4}-[0-9]{2}-[0-9]{2}}', [EpaperController::class, 'show'])->name('epaper.issue');
    $r->get('/epaper/{edition:[a-z0-9-]+}/{date:[0-9]{4}-[0-9]{2}-[0-9]{2}}/pdf', [EpaperController::class, 'pdf'])->name('epaper.pdf')->middleware('throttle:30,10');

    // लोकेशन: सबसे आख़िर में (slug आरक्षित शब्दों से नहीं टकराते: TaxonomyService::RESERVED)
    $r->get('/{path:[a-z0-9-]+(?:/[a-z0-9-]+)*}', [LocationController::class, 'show'])->name('location');
});
