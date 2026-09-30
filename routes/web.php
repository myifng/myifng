<?php
/**
 * वेबसाइट के रूट। लोकेशन वाला रूट (/{राज्य}/{ज़िला}/…) सबसे आख़िर में, ताकि बाकी रास्ते पहले मिलें।
 * @var App\Core\Router $router
 */
use App\Controllers\Front\AccountController;
use App\Controllers\Front\AdController;
use App\Controllers\Front\AudioController;
use App\Controllers\Front\CategoryController;
use App\Controllers\Front\EngageController;
use App\Controllers\Front\EpaperController;
use App\Controllers\Front\FormController;
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
use App\Controllers\Front\SitemapController;
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

    // Phase 9: विज्ञापन की गिनती
    $r->get('/ad/{id:\d+}/click', [AdController::class, 'click'])->name('ad.click');
    $r->post('/ad/impressions', [AdController::class, 'impressions'])->name('ad.impressions')->middleware('throttle:120,5');

    // Phase 10: साइटमैप, robots.txt, RSS
    $r->get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
    $r->get('/sitemap-{part:[a-z0-9-]+}.xml', [SitemapController::class, 'part'])->name('sitemap.part');
    $r->get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
    $r->get('/feed', [SitemapController::class, 'feed'])->name('feed');
    $r->get('/category/{slug:[a-z0-9-]+}/feed', [SitemapController::class, 'categoryFeed'])->name('category.feed');

    // Phase 11: पाठक खाता
    $r->get('/account/login', [AccountController::class, 'loginForm'])->name('account.login');
    $r->post('/account/login', [AccountController::class, 'login'])->name('account.login.post')->middleware('throttle:20,10');
    $r->get('/account/register', [AccountController::class, 'registerForm'])->name('account.register');
    $r->post('/account/register', [AccountController::class, 'register'])->name('account.register.post')->middleware('throttle:5,60');
    $r->get('/account/verify/{token:[a-f0-9]{48}}', [AccountController::class, 'verify'])->name('account.verify');
    $r->post('/account/verify/resend', [AccountController::class, 'resend'])->name('account.resend')->middleware('throttle:3,30');
    $r->get('/account/forgot', [AccountController::class, 'forgotForm'])->name('account.forgot');
    $r->post('/account/forgot', [AccountController::class, 'forgot'])->name('account.forgot.post')->middleware('throttle:5,30');
    $r->get('/account/reset/{token:[a-f0-9]{48}}', [AccountController::class, 'resetForm'])->name('account.reset');
    $r->post('/account/reset/{token:[a-f0-9]{48}}', [AccountController::class, 'reset'])->name('account.reset.post')->middleware('throttle:10,30');
    $r->post('/account/logout', [AccountController::class, 'logout'])->name('account.logout');
    $r->get('/account', [AccountController::class, 'home'])->name('account');
    $r->get('/account/saved', [AccountController::class, 'saved'])->name('account.saved');
    $r->get('/account/history', [AccountController::class, 'history'])->name('account.history');
    $r->get('/account/following', [AccountController::class, 'following'])->name('account.following');
    $r->get('/account/notifications', [AccountController::class, 'notifications'])->name('account.notifications');
    $r->get('/account/settings', [AccountController::class, 'settings'])->name('account.settings');
    $r->post('/account/settings', [AccountController::class, 'saveProfile'])->name('account.profile');
    $r->post('/account/prefs', [AccountController::class, 'savePrefs'])->name('account.prefs');
    $r->post('/account/password', [AccountController::class, 'password'])->name('account.password')->middleware('throttle:10,30');
    $r->post('/account/history/clear', [AccountController::class, 'clearHistory'])->name('account.history.clear');
    $r->post('/account/delete', [AccountController::class, 'destroy'])->name('account.delete')->middleware('throttle:5,30');
    $r->post('/account/bookmark/{id:\d+}', [AccountController::class, 'bookmark'])->name('account.bookmark')->middleware('throttle:60,5');
    $r->post('/account/follow', [AccountController::class, 'follow'])->name('account.follow')->middleware('throttle:60,5');

    // Phase 11: टिप्पणी, पोल, न्यूज़लेटर, वेब पुश
    $r->post('/news/{slug:[a-z0-9-]+}/comments', [EngageController::class, 'comment'])->name('comments.store')->middleware('throttle:5,10');
    $r->get('/poll/{id:\d+}', [EngageController::class, 'poll'])->name('poll');
    $r->post('/poll/{id:\d+}/vote', [EngageController::class, 'vote'])->name('poll.vote')->middleware('throttle:20,10');
    $r->post('/newsletter', [EngageController::class, 'subscribe'])->name('newsletter.subscribe')->middleware('throttle:5,30');
    $r->get('/newsletter/confirm/{token:[a-f0-9]{40}}', [EngageController::class, 'confirm'])->name('newsletter.confirm');
    $r->get('/newsletter/unsubscribe/{token:[a-f0-9]{40}}', [EngageController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
    $r->post('/newsletter/unsubscribe/{token:[a-f0-9]{40}}', [EngageController::class, 'unsubscribe'])->name('newsletter.unsubscribe.post')->middleware('throttle:20,10');
    $r->get('/sw.js', [EngageController::class, 'serviceWorker'])->name('push.sw');
    $r->post('/push/subscribe', [EngageController::class, 'pushSubscribe'])->name('push.subscribe')->middleware('throttle:20,10');
    $r->post('/push/unsubscribe', [EngageController::class, 'pushUnsubscribe'])->name('push.unsubscribe')->middleware('throttle:20,10');

    // Phase 12: फ़ॉर्म, न्यूज़ टिप, शिकायत, करियर
    $r->get('/form/{slug:[a-z0-9-]+}', [FormController::class, 'show'])->name('form.show');
    $r->post('/form/{slug:[a-z0-9-]+}', [FormController::class, 'submit'])->name('form.submit')->middleware('throttle:8,10');
    $r->get('/form/{slug:[a-z0-9-]+}/done', [FormController::class, 'done'])->name('form.done');
    $r->get('/send-news', [FormController::class, 'sendNews'])->name('send_news');
    $r->get('/complaint', [FormController::class, 'complaint'])->name('complaint');
    $r->get('/complaint/track', [FormController::class, 'track'])->name('complaint.track');
    $r->post('/complaint/track', [FormController::class, 'track'])->name('complaint.track.post')->middleware('throttle:10,15');
    $r->get('/careers', [FormController::class, 'careers'])->name('careers');
    $r->get('/careers/{slug:[a-z0-9-]+}', [FormController::class, 'job'])->name('careers.show');

    // लोकेशन: सबसे आख़िर में (slug आरक्षित शब्दों से नहीं टकराते: TaxonomyService::RESERVED)
    $r->get('/{path:[a-z0-9-]+(?:/[a-z0-9-]+)*}', [LocationController::class, 'show'])->name('location');
});
