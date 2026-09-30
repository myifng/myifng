<?php
/**
 * एडमिन पैनल के रूट। प्रीफ़िक्स config('app.admin_path') से (डिफ़ॉल्ट /admin)।
 * हर रूट पर अनुमति middleware: can:मॉड्यूल.action
 * @var App\Core\Router $router
 */
use App\Controllers\Admin\AdController;
use App\Controllers\Admin\AdSlotController;
use App\Controllers\Admin\AdvertiserController;
use App\Controllers\Admin\ApplicationController;
use App\Controllers\Admin\AssignmentController;
use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\AudioController;
use App\Controllers\Admin\BreakingController;
use App\Controllers\Admin\BureauController;
use App\Controllers\Admin\CampaignController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\CommentController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\EditorController;
use App\Controllers\Admin\EpaperController;
use App\Controllers\Admin\EpaperEditionController;
use App\Controllers\Admin\GalleryController;
use App\Controllers\Admin\HomepageController;
use App\Controllers\Admin\InvoiceController;
use App\Controllers\Admin\LiveBlogController;
use App\Controllers\Admin\LiveTvController;
use App\Controllers\Admin\LocationController;
use App\Controllers\Admin\MediaController;
use App\Controllers\Admin\MenuController;
use App\Controllers\Admin\NewsController;
use App\Controllers\Admin\NewsletterController;
use App\Controllers\Admin\NotificationController;
use App\Controllers\Admin\NewsWorkflowController;
use App\Controllers\Admin\PageController;
use App\Controllers\Admin\PlaylistController;
use App\Controllers\Admin\PollController;
use App\Controllers\Admin\PodcastController;
use App\Controllers\Admin\ReporterController;
use App\Controllers\Admin\ReporterPortalController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\SystemController;
use App\Controllers\Admin\TagController;
use App\Controllers\Admin\TopicController;
use App\Controllers\Admin\ProfileController;
use App\Controllers\Admin\ReaderController;
use App\Controllers\Admin\RedirectController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\SeoController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\VideoController;
use App\Controllers\Admin\WebStoryController;

$router->group(['prefix' => '/' . config('app.admin_path', 'admin'), 'as' => 'admin.'], function ($r) {

    // ---------- बिना लॉगिन ----------
    $r->group(['middleware' => ['guest']], function ($r) {
        $r->get('/login', [AuthController::class, 'showLogin'])->name('login');
        $r->post('/login', [AuthController::class, 'login'])->name('login.submit')->middleware('throttle:20,5');
        $r->get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.forgot');
        $r->post('/forgot-password', [AuthController::class, 'sendReset'])->name('password.email')->middleware('throttle:5,15');
        $r->get('/reset-password/{token:[a-f0-9]{64}}', [AuthController::class, 'showReset'])->name('password.reset');
        $r->post('/reset-password', [AuthController::class, 'reset'])->name('password.update')->middleware('throttle:10,15');
    });

    // ---------- लॉगिन के बाद ----------
    // लॉगआउट और सिस्टम अपडेट: डेटाबेस अपडेट बाकी हो तब भी चलें
    $r->group(['middleware' => ['auth']], function ($r) {
        $r->post('/logout', [AuthController::class, 'logout'])->name('logout');
        $r->post('/system/migrate', [SystemController::class, 'migrate'])->name('system.migrate');
    });

    $r->group(['middleware' => ['auth', 'uptodate']], function ($r) {
        $r->get('/', [DashboardController::class, 'index'])->name('dashboard')->middleware('can:dashboard.view');

        $r->get('/profile', [ProfileController::class, 'edit'])->name('profile');
        $r->post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        $r->post('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

        // यूज़र
        $r->get('/users', [UserController::class, 'index'])->name('users.index')->middleware('can:users.view');
        $r->get('/users/export', [UserController::class, 'export'])->name('users.export')->middleware('can:users.export');
        $r->get('/users/create', [UserController::class, 'create'])->name('users.create')->middleware('can:users.create');
        $r->post('/users', [UserController::class, 'store'])->name('users.store')->middleware('can:users.create');
        $r->get('/users/{id:\d+}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('can:users.edit');
        $r->put('/users/{id:\d+}', [UserController::class, 'update'])->name('users.update')->middleware('can:users.edit');
        $r->post('/users/{id:\d+}/status', [UserController::class, 'status'])->name('users.status')->middleware('can:users.edit');
        $r->delete('/users/{id:\d+}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('can:users.delete');
        $r->get('/users/{id:\d+}/permissions', [UserController::class, 'permissions'])->name('users.permissions')->middleware('can:users.manage');
        $r->post('/users/{id:\d+}/permissions', [UserController::class, 'savePermissions'])->name('users.permissions.save')->middleware('can:users.manage');

        // रोल और अनुमतियाँ
        $r->get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('can:roles.view');
        $r->get('/roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('can:roles.create');
        $r->post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('can:roles.create');
        $r->get('/roles/{id:\d+}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('can:roles.edit');
        $r->put('/roles/{id:\d+}', [RoleController::class, 'update'])->name('roles.update')->middleware('can:roles.edit');
        $r->delete('/roles/{id:\d+}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('can:roles.delete');
        $r->post('/roles/sync', [RoleController::class, 'sync'])->name('roles.sync')->middleware('can:roles.manage');

        // ---------- Phase 2 ----------
        // साइट सेटिंग
        $r->get('/settings', [SettingsController::class, 'index'])->name('settings.index')->middleware('can:settings.view');
        $r->get('/settings/{tab:[a-z_]+}', [SettingsController::class, 'edit'])->name('settings')->middleware('can:settings.view');
        $r->post('/settings/{tab:[a-z_]+}', [SettingsController::class, 'update'])->name('settings.update')->middleware('can:settings.edit,settings.manage');

        // पेज
        $r->get('/pages', [PageController::class, 'index'])->name('pages.index')->middleware('can:pages.view');
        $r->get('/pages/create', [PageController::class, 'create'])->name('pages.create')->middleware('can:pages.create');
        $r->post('/pages', [PageController::class, 'store'])->name('pages.store')->middleware('can:pages.create');
        $r->post('/pages/bulk', [PageController::class, 'bulk'])->name('pages.bulk')->middleware('can:pages.edit,pages.delete,pages.publish');
        $r->get('/pages/{id:\d+}/edit', [PageController::class, 'edit'])->name('pages.edit')->middleware('can:pages.edit');
        $r->put('/pages/{id:\d+}', [PageController::class, 'update'])->name('pages.update')->middleware('can:pages.edit');
        $r->get('/pages/{id:\d+}/preview', [PageController::class, 'preview'])->name('pages.preview')->middleware('can:pages.view');
        $r->post('/pages/{id:\d+}/duplicate', [PageController::class, 'duplicate'])->name('pages.duplicate')->middleware('can:pages.create');
        $r->post('/pages/{id:\d+}/trash', [PageController::class, 'trash'])->name('pages.trash')->middleware('can:pages.delete');
        $r->post('/pages/{id:\d+}/restore', [PageController::class, 'restore'])->name('pages.restore')->middleware('can:pages.delete');
        $r->delete('/pages/{id:\d+}', [PageController::class, 'destroy'])->name('pages.destroy')->middleware('can:pages.delete');

        // मेनू बिल्डर
        $r->get('/menus', [MenuController::class, 'index'])->name('menus.index')->middleware('can:menus.view');
        $r->get('/menus/{id:\d+}', [MenuController::class, 'edit'])->name('menus.edit')->middleware('can:menus.view');
        $r->post('/menus/{id:\d+}', [MenuController::class, 'update'])->name('menus.update')->middleware('can:menus.edit');

        // होमपेज बिल्डर
        $r->get('/homepage', [HomepageController::class, 'index'])->name('homepage')->middleware('can:homepage.view');
        $r->post('/homepage/sections', [HomepageController::class, 'store'])->name('homepage.store')->middleware('can:homepage.edit');
        $r->post('/homepage/reorder', [HomepageController::class, 'reorder'])->name('homepage.reorder')->middleware('can:homepage.edit');
        $r->put('/homepage/sections/{id:\d+}', [HomepageController::class, 'update'])->name('homepage.update')->middleware('can:homepage.edit');
        $r->post('/homepage/sections/{id:\d+}/toggle', [HomepageController::class, 'toggle'])->name('homepage.toggle')->middleware('can:homepage.edit');
        $r->post('/homepage/sections/{id:\d+}/duplicate', [HomepageController::class, 'duplicate'])->name('homepage.duplicate')->middleware('can:homepage.edit');
        $r->delete('/homepage/sections/{id:\d+}', [HomepageController::class, 'destroy'])->name('homepage.destroy')->middleware('can:homepage.edit');

        // रिच एडिटर में इमेज
        $r->post('/editor/upload', [EditorController::class, 'upload'])->name('editor.upload')->middleware('can:pages.create,pages.edit,news.create,news.edit', 'throttle:60,5');

        // ---------- Phase 3 ----------
        // श्रेणियाँ
        $r->get('/categories', [CategoryController::class, 'index'])->name('categories.index')->middleware('can:categories.view');
        $r->get('/categories/create', [CategoryController::class, 'create'])->name('categories.create')->middleware('can:categories.create');
        $r->post('/categories', [CategoryController::class, 'store'])->name('categories.store')->middleware('can:categories.create');
        $r->get('/categories/{id:\d+}/edit', [CategoryController::class, 'edit'])->name('categories.edit')->middleware('can:categories.edit');
        $r->put('/categories/{id:\d+}', [CategoryController::class, 'update'])->name('categories.update')->middleware('can:categories.edit');
        $r->post('/categories/{id:\d+}/move', [CategoryController::class, 'move'])->name('categories.move')->middleware('can:categories.edit');
        $r->delete('/categories/{id:\d+}', [CategoryController::class, 'destroy'])->name('categories.destroy')->middleware('can:categories.delete');

        // टॉपिक और विशेष सेक्शन
        $r->get('/topics', [TopicController::class, 'index'])->name('topics.index')->middleware('can:topics.view');
        $r->get('/topics/create', [TopicController::class, 'create'])->name('topics.create')->middleware('can:topics.create');
        $r->post('/topics', [TopicController::class, 'store'])->name('topics.store')->middleware('can:topics.create');
        $r->get('/topics/{id:\d+}/edit', [TopicController::class, 'edit'])->name('topics.edit')->middleware('can:topics.edit');
        $r->put('/topics/{id:\d+}', [TopicController::class, 'update'])->name('topics.update')->middleware('can:topics.edit');
        $r->post('/topics/{id:\d+}/feature', [TopicController::class, 'feature'])->name('topics.feature')->middleware('can:topics.edit');
        $r->delete('/topics/{id:\d+}', [TopicController::class, 'destroy'])->name('topics.destroy')->middleware('can:topics.delete');

        // टैग
        $r->get('/tags', [TagController::class, 'index'])->name('tags.index')->middleware('can:tags.view');
        $r->get('/tags/search', [TagController::class, 'search'])->name('tags.search')->middleware('can:tags.view,news.create');
        $r->post('/tags', [TagController::class, 'store'])->name('tags.store')->middleware('can:tags.create');
        $r->post('/tags/bulk', [TagController::class, 'bulk'])->name('tags.bulk')->middleware('can:tags.delete');
        $r->get('/tags/{id:\d+}/edit', [TagController::class, 'edit'])->name('tags.edit')->middleware('can:tags.edit');
        $r->put('/tags/{id:\d+}', [TagController::class, 'update'])->name('tags.update')->middleware('can:tags.edit');
        $r->delete('/tags/{id:\d+}', [TagController::class, 'destroy'])->name('tags.destroy')->middleware('can:tags.delete');

        // लोकेशन
        $r->get('/locations', [LocationController::class, 'index'])->name('locations.index')->middleware('can:locations.view');
        $r->get('/locations/search', [LocationController::class, 'search'])->name('locations.search')->middleware('can:locations.view,news.create,assignments.create');
        $r->get('/locations/create', [LocationController::class, 'create'])->name('locations.create')->middleware('can:locations.create');
        $r->post('/locations', [LocationController::class, 'store'])->name('locations.store')->middleware('can:locations.create');
        $r->get('/locations/import', [LocationController::class, 'importForm'])->name('locations.import')->middleware('can:locations.create');
        $r->post('/locations/import', [LocationController::class, 'import'])->name('locations.import.run')->middleware('can:locations.create', 'throttle:10,10');
        $r->get('/locations/export', [LocationController::class, 'export'])->name('locations.export')->middleware('can:locations.view');
        $r->get('/locations/{id:\d+}/edit', [LocationController::class, 'edit'])->name('locations.edit')->middleware('can:locations.edit');
        $r->put('/locations/{id:\d+}', [LocationController::class, 'update'])->name('locations.update')->middleware('can:locations.edit');
        $r->post('/locations/{id:\d+}/move', [LocationController::class, 'move'])->name('locations.move')->middleware('can:locations.edit');
        $r->post('/locations/{id:\d+}/popular', [LocationController::class, 'popular'])->name('locations.popular')->middleware('can:locations.edit');
        $r->delete('/locations/{id:\d+}', [LocationController::class, 'destroy'])->name('locations.destroy')->middleware('can:locations.delete');

        // मीडिया लाइब्रेरी
        $r->get('/media', [MediaController::class, 'index'])->name('media.index')->middleware('can:media.view');
        $r->get('/media/browse', [MediaController::class, 'browse'])->name('media.browse')->middleware('can:media.view');
        $r->post('/media', [MediaController::class, 'store'])->name('media.store')->middleware('can:media.create', 'throttle:120,10');
        $r->post('/media/bulk', [MediaController::class, 'bulk'])->name('media.bulk')->middleware('can:media.edit,media.delete,media.manage');
        $r->get('/media/{id:\d+}', [MediaController::class, 'edit'])->name('media.edit')->middleware('can:media.view');
        $r->put('/media/{id:\d+}', [MediaController::class, 'update'])->name('media.update')->middleware('can:media.edit');
        $r->post('/media/{id:\d+}/replace', [MediaController::class, 'replace'])->name('media.replace')->middleware('can:media.edit', 'throttle:30,10');
        $r->post('/media/{id:\d+}/regenerate', [MediaController::class, 'regenerate'])->name('media.regenerate')->middleware('can:media.edit');
        $r->post('/media/{id:\d+}/trash', [MediaController::class, 'trash'])->name('media.trash')->middleware('can:media.delete');
        $r->post('/media/{id:\d+}/restore', [MediaController::class, 'restore'])->name('media.restore')->middleware('can:media.delete');
        $r->delete('/media/{id:\d+}', [MediaController::class, 'destroy'])->name('media.destroy')->middleware('can:media.manage');
        $r->post('/media/folders', [MediaController::class, 'folderStore'])->name('media.folders.store')->middleware('can:media.manage');
        $r->put('/media/folders/{id:\d+}', [MediaController::class, 'folderUpdate'])->name('media.folders.update')->middleware('can:media.manage');
        $r->delete('/media/folders/{id:\d+}', [MediaController::class, 'folderDestroy'])->name('media.folders.destroy')->middleware('can:media.manage');

        // ---------- Phase 4 ----------
        // ख़बरें
        $r->get('/news', [NewsController::class, 'index'])->name('news.index')->middleware('can:news.view');
        $r->get('/news/search', [NewsController::class, 'search'])->name('news.search')->middleware('can:news.view');
        $r->get('/news/export', [NewsController::class, 'export'])->name('news.export')->middleware('can:news.export');
        $r->get('/news/create', [NewsController::class, 'create'])->name('news.create')->middleware('can:news.create');
        $r->post('/news', [NewsController::class, 'store'])->name('news.store')->middleware('can:news.create');
        $r->post('/news/bulk', [NewsController::class, 'bulk'])->name('news.bulk')->middleware('can:news.view');
        $r->get('/news/{id:\d+}/edit', [NewsController::class, 'edit'])->name('news.edit')->middleware('can:news.view');
        $r->put('/news/{id:\d+}', [NewsController::class, 'update'])->name('news.update')->middleware('can:news.edit');
        $r->get('/news/{id:\d+}/preview', [NewsController::class, 'preview'])->name('news.preview')->middleware('can:news.view');
        $r->post('/news/{id:\d+}/duplicate', [NewsController::class, 'duplicate'])->name('news.duplicate')->middleware('can:news.create');
        $r->post('/news/{id:\d+}/trash', [NewsController::class, 'trash'])->name('news.trash')->middleware('can:news.delete');
        $r->post('/news/{id:\d+}/restore', [NewsController::class, 'restore'])->name('news.restore')->middleware('can:news.delete');
        $r->delete('/news/{id:\d+}', [NewsController::class, 'destroy'])->name('news.destroy')->middleware('can:news.delete');
        // वर्कफ़्लो और हिस्ट्री
        $r->post('/news/{id:\d+}/transition', [NewsWorkflowController::class, 'transition'])->name('news.transition')->middleware('can:news.view');
        $r->post('/news/{id:\d+}/remarks', [NewsWorkflowController::class, 'remark'])->name('news.remark')->middleware('can:news.view', 'throttle:60,10');
        $r->get('/news/{id:\d+}/history', [NewsWorkflowController::class, 'history'])->name('news.history')->middleware('can:news.view');
        $r->get('/news/{id:\d+}/revisions/{rid:\d+}', [NewsWorkflowController::class, 'revision'])->name('news.revision')->middleware('can:news.view');
        $r->post('/news/{id:\d+}/revisions/{rid:\d+}/restore', [NewsWorkflowController::class, 'restore'])->name('news.revision.restore')->middleware('can:news.edit');

        // असाइनमेंट डेस्क
        $r->get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index')->middleware('can:assignments.view');
        $r->get('/assignments/create', [AssignmentController::class, 'create'])->name('assignments.create')->middleware('can:assignments.create');
        $r->post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store')->middleware('can:assignments.create');
        $r->get('/assignments/{id:\d+}/edit', [AssignmentController::class, 'edit'])->name('assignments.edit')->middleware('can:assignments.edit');
        $r->put('/assignments/{id:\d+}', [AssignmentController::class, 'update'])->name('assignments.update')->middleware('can:assignments.edit');
        $r->post('/assignments/{id:\d+}/status', [AssignmentController::class, 'status'])->name('assignments.status')->middleware('can:assignments.view');
        $r->post('/assignments/{id:\d+}/start', [AssignmentController::class, 'start'])->name('assignments.start')->middleware('can:news.create');
        $r->delete('/assignments/{id:\d+}', [AssignmentController::class, 'destroy'])->name('assignments.destroy')->middleware('can:assignments.delete');

        // ---------- Phase 6 ----------
        // रिपोर्टर आवेदन
        $r->get('/applications', [ApplicationController::class, 'index'])->name('applications.index')->middleware('can:applications.view');
        $r->get('/applications/export', [ApplicationController::class, 'export'])->name('applications.export')->middleware('can:applications.export');
        $r->get('/applications/{id:\d+}', [ApplicationController::class, 'show'])->name('applications.show')->middleware('can:applications.view');
        $r->get('/applications/{id:\d+}/documents/{key:[a-z_]+}', [ApplicationController::class, 'document'])->name('applications.document')->middleware('can:applications.view');
        $r->post('/applications/{id:\d+}/remarks', [ApplicationController::class, 'remark'])->name('applications.remark')->middleware('can:applications.edit');
        $r->post('/applications/{id:\d+}/status', [ApplicationController::class, 'status'])->name('applications.status')->middleware('can:applications.edit');
        $r->post('/applications/{id:\d+}/assign', [ApplicationController::class, 'assign'])->name('applications.assign')->middleware('can:applications.edit');
        $r->get('/applications/{id:\d+}/approve', [ApplicationController::class, 'approveForm'])->name('applications.approve')->middleware('can:applications.approve');
        $r->post('/applications/{id:\d+}/approve', [ApplicationController::class, 'approve'])->name('applications.approve.run')->middleware('can:applications.approve');
        $r->delete('/applications/{id:\d+}', [ApplicationController::class, 'destroy'])->name('applications.destroy')->middleware('can:applications.delete');

        // रिपोर्टर
        $r->get('/reporters', [ReporterController::class, 'index'])->name('reporters.index')->middleware('can:reporters.view');
        $r->get('/reporters/export', [ReporterController::class, 'export'])->name('reporters.export')->middleware('can:reporters.export');
        $r->get('/reporters/create', [ReporterController::class, 'create'])->name('reporters.create')->middleware('can:reporters.create');
        $r->post('/reporters', [ReporterController::class, 'store'])->name('reporters.store')->middleware('can:reporters.create');
        $r->get('/reporters/{id:\d+}', [ReporterController::class, 'show'])->name('reporters.show')->middleware('can:reporters.view');
        $r->get('/reporters/{id:\d+}/edit', [ReporterController::class, 'edit'])->name('reporters.edit')->middleware('can:reporters.edit');
        $r->put('/reporters/{id:\d+}', [ReporterController::class, 'update'])->name('reporters.update')->middleware('can:reporters.edit');
        $r->post('/reporters/{id:\d+}/renew', [ReporterController::class, 'renew'])->name('reporters.renew')->middleware('can:reporters.manage');
        $r->post('/reporters/{id:\d+}/status', [ReporterController::class, 'status'])->name('reporters.status')->middleware('can:reporters.manage');
        $r->get('/reporters/{id:\d+}/kyc/{key:[a-z_]+}', [ReporterController::class, 'kyc'])->name('reporters.kyc')->middleware('can:reporters.view');
        $r->post('/reporters/{id:\d+}/documents', [ReporterController::class, 'issue'])->name('reporters.issue')->middleware('can:reporters.approve');
        $r->get('/reporters/{id:\d+}/documents/{doc:\d+}', [ReporterController::class, 'document'])->name('reporters.document')->middleware('can:reporters.view');
        $r->post('/reporters/{id:\d+}/documents/{doc:\d+}/revoke', [ReporterController::class, 'revoke'])->name('reporters.revoke')->middleware('can:reporters.approve');

        // रिपोर्टर पोर्टल (अपना प्रोफ़ाइल)
        $r->get('/my-reporter-profile', [ReporterPortalController::class, 'index'])->name('portal');
        $r->get('/my-reporter-profile/documents/{doc:\d+}', [ReporterPortalController::class, 'document'])->name('portal.document');

        // ब्यूरो
        $r->get('/bureaus', [BureauController::class, 'index'])->name('bureaus.index')->middleware('can:bureaus.view');
        $r->get('/bureaus/create', [BureauController::class, 'create'])->name('bureaus.create')->middleware('can:bureaus.create');
        $r->post('/bureaus', [BureauController::class, 'store'])->name('bureaus.store')->middleware('can:bureaus.create');
        $r->get('/bureaus/{id:\d+}/edit', [BureauController::class, 'edit'])->name('bureaus.edit')->middleware('can:bureaus.edit');
        $r->put('/bureaus/{id:\d+}', [BureauController::class, 'update'])->name('bureaus.update')->middleware('can:bureaus.edit');
        $r->delete('/bureaus/{id:\d+}', [BureauController::class, 'destroy'])->name('bureaus.destroy')->middleware('can:bureaus.delete');

        // ---------- Phase 7: ब्रेकिंग, लाइव ब्लॉग, लाइव टीवी, वीडियो, गैलरी, वेब स्टोरी, ऑडियो ----------
        $r->get('/breaking', [BreakingController::class, 'index'])->name('breaking.index')->middleware('can:breaking.view');
        $r->post('/breaking', [BreakingController::class, 'store'])->name('breaking.store')->middleware('can:breaking.create');
        $r->get('/breaking/{id:\d+}/edit', [BreakingController::class, 'edit'])->name('breaking.edit')->middleware('can:breaking.edit');
        $r->put('/breaking/{id:\d+}', [BreakingController::class, 'update'])->name('breaking.update')->middleware('can:breaking.edit');
        $r->post('/breaking/{id:\d+}/stop', [BreakingController::class, 'stop'])->name('breaking.stop')->middleware('can:breaking.publish');
        $r->post('/breaking/{id:\d+}/restart', [BreakingController::class, 'restart'])->name('breaking.restart')->middleware('can:breaking.publish');
        $r->delete('/breaking/{id:\d+}', [BreakingController::class, 'destroy'])->name('breaking.destroy')->middleware('can:breaking.delete');

        $r->get('/live-blogs', [LiveBlogController::class, 'index'])->name('live_blogs.index')->middleware('can:live_blogs.view');
        $r->post('/live-blogs', [LiveBlogController::class, 'store'])->name('live_blogs.store')->middleware('can:live_blogs.publish');
        $r->get('/live-blogs/{id:\d+}', [LiveBlogController::class, 'show'])->name('live_blogs.show')->middleware('can:live_blogs.view');
        $r->post('/live-blogs/{id:\d+}/status', [LiveBlogController::class, 'status'])->name('live_blogs.status')->middleware('can:live_blogs.publish');
        $r->delete('/live-blogs/{id:\d+}', [LiveBlogController::class, 'destroy'])->name('live_blogs.destroy')->middleware('can:live_blogs.delete');
        $r->post('/live-blogs/{id:\d+}/updates', [LiveBlogController::class, 'storeUpdate'])->name('live_blogs.updates.store')->middleware('can:live_blogs.create', 'throttle:120,10');
        $r->put('/live-blogs/{id:\d+}/updates/{uid:\d+}', [LiveBlogController::class, 'updateUpdate'])->name('live_blogs.updates.update')->middleware('can:live_blogs.edit');
        $r->post('/live-blogs/{id:\d+}/updates/{uid:\d+}/pin', [LiveBlogController::class, 'pin'])->name('live_blogs.updates.pin')->middleware('can:live_blogs.edit');
        $r->delete('/live-blogs/{id:\d+}/updates/{uid:\d+}', [LiveBlogController::class, 'destroyUpdate'])->name('live_blogs.updates.destroy')->middleware('can:live_blogs.delete');

        $r->get('/live-tv', [LiveTvController::class, 'index'])->name('live_tv.index')->middleware('can:live_tv.view');
        $r->get('/live-tv/channels/create', [LiveTvController::class, 'create'])->name('live_tv.create')->middleware('can:live_tv.edit');
        $r->post('/live-tv/channels', [LiveTvController::class, 'store'])->name('live_tv.store')->middleware('can:live_tv.edit');
        $r->get('/live-tv/channels/{id:\d+}/edit', [LiveTvController::class, 'edit'])->name('live_tv.edit')->middleware('can:live_tv.edit');
        $r->put('/live-tv/channels/{id:\d+}', [LiveTvController::class, 'update'])->name('live_tv.update')->middleware('can:live_tv.edit');
        $r->post('/live-tv/channels/{id:\d+}/toggle', [LiveTvController::class, 'toggle'])->name('live_tv.toggle')->middleware('can:live_tv.edit');
        $r->delete('/live-tv/channels/{id:\d+}', [LiveTvController::class, 'destroy'])->name('live_tv.destroy')->middleware('can:live_tv.edit');
        $r->post('/live-tv/channels/{id:\d+}/programs', [LiveTvController::class, 'programStore'])->name('live_tv.programs.store')->middleware('can:live_tv.edit');
        $r->put('/live-tv/programs/{pid:\d+}', [LiveTvController::class, 'programUpdate'])->name('live_tv.programs.update')->middleware('can:live_tv.edit');
        $r->delete('/live-tv/programs/{pid:\d+}', [LiveTvController::class, 'programDestroy'])->name('live_tv.programs.destroy')->middleware('can:live_tv.edit');

        foreach ([
            ['videos', 'videos', VideoController::class], ['galleries', 'galleries', GalleryController::class],
            ['web-stories', 'web_stories', WebStoryController::class], ['audio', 'audio', AudioController::class],
        ] as [$path, $mod, $ctrl]) {
            $r->get("/$path", [$ctrl, 'index'])->name("$mod.index")->middleware("can:$mod.view");
            $r->get("/$path/create", [$ctrl, 'create'])->name("$mod.create")->middleware("can:$mod.create");
            $r->post("/$path", [$ctrl, 'store'])->name("$mod.store")->middleware("can:$mod.create");
            $r->get("/$path/{id:\\d+}/edit", [$ctrl, 'edit'])->name("$mod.edit")->middleware("can:$mod.view");
            $r->put("/$path/{id:\\d+}", [$ctrl, 'update'])->name("$mod.update")->middleware("can:$mod.edit");
            $r->delete("/$path/{id:\\d+}", [$ctrl, 'destroy'])->name("$mod.destroy")->middleware("can:$mod.delete");
        }
        $r->get('/video-playlists', [PlaylistController::class, 'index'])->name('playlists.index')->middleware('can:videos.view');
        $r->post('/video-playlists', [PlaylistController::class, 'store'])->name('playlists.store')->middleware('can:videos.create');
        $r->get('/video-playlists/{id:\d+}/edit', [PlaylistController::class, 'edit'])->name('playlists.edit')->middleware('can:videos.edit');
        $r->put('/video-playlists/{id:\d+}', [PlaylistController::class, 'update'])->name('playlists.update')->middleware('can:videos.edit');
        $r->delete('/video-playlists/{id:\d+}', [PlaylistController::class, 'destroy'])->name('playlists.destroy')->middleware('can:videos.delete');
        $r->get('/podcasts', [PodcastController::class, 'index'])->name('podcasts.index')->middleware('can:audio.view');
        $r->post('/podcasts', [PodcastController::class, 'store'])->name('podcasts.store')->middleware('can:audio.create');
        $r->get('/podcasts/{id:\d+}/edit', [PodcastController::class, 'edit'])->name('podcasts.edit')->middleware('can:audio.edit');
        $r->put('/podcasts/{id:\d+}', [PodcastController::class, 'update'])->name('podcasts.update')->middleware('can:audio.edit');
        $r->delete('/podcasts/{id:\d+}', [PodcastController::class, 'destroy'])->name('podcasts.destroy')->middleware('can:audio.delete');

        // ---------- Phase 8: ई-पेपर ----------
        $r->get('/epaper', [EpaperController::class, 'index'])->name('epaper.index')->middleware('can:epaper.view');
        $r->get('/epaper/create', [EpaperController::class, 'create'])->name('epaper.create')->middleware('can:epaper.create');
        $r->post('/epaper', [EpaperController::class, 'store'])->name('epaper.store')->middleware('can:epaper.create');
        $r->get('/epaper/{id:\d+}', [EpaperController::class, 'edit'])->name('epaper.edit')->middleware('can:epaper.view');
        $r->put('/epaper/{id:\d+}', [EpaperController::class, 'update'])->name('epaper.update')->middleware('can:epaper.edit');
        $r->delete('/epaper/{id:\d+}', [EpaperController::class, 'destroy'])->name('epaper.destroy')->middleware('can:epaper.delete');
        $r->post('/epaper/{id:\d+}/pages', [EpaperController::class, 'uploadPage'])->name('epaper.pages.store')->middleware('can:epaper.edit', 'throttle:600,10');
        $r->post('/epaper/{id:\d+}/pages/order', [EpaperController::class, 'order'])->name('epaper.pages.order')->middleware('can:epaper.edit');
        $r->post('/epaper/{id:\d+}/pages/clear', [EpaperController::class, 'clearPages'])->name('epaper.pages.clear')->middleware('can:epaper.edit');
        $r->put('/epaper/{id:\d+}/pages/{pid:\d+}', [EpaperController::class, 'updatePage'])->name('epaper.pages.update')->middleware('can:epaper.edit');
        $r->delete('/epaper/{id:\d+}/pages/{pid:\d+}', [EpaperController::class, 'deletePage'])->name('epaper.pages.destroy')->middleware('can:epaper.edit');
        $r->get('/epaper/{id:\d+}/pages/{pid:\d+}/hotspots', [EpaperController::class, 'hotspots'])->name('epaper.hotspots')->middleware('can:epaper.edit');
        $r->post('/epaper/{id:\d+}/pages/{pid:\d+}/hotspots', [EpaperController::class, 'saveHotspots'])->name('epaper.hotspots.save')->middleware('can:epaper.edit');
        $r->post('/epaper/{id:\d+}/pdf', [EpaperController::class, 'uploadPdf'])->name('epaper.pdf')->middleware('can:epaper.edit', 'throttle:30,10');
        $r->delete('/epaper/{id:\d+}/pdf', [EpaperController::class, 'deletePdf'])->name('epaper.pdf.destroy')->middleware('can:epaper.edit');
        $r->get('/epaper/editions', [EpaperEditionController::class, 'index'])->name('epaper.editions')->middleware('can:epaper.edit');
        $r->post('/epaper/editions', [EpaperEditionController::class, 'store'])->name('epaper.editions.store')->middleware('can:epaper.edit');
        $r->get('/epaper/editions/{id:\d+}/edit', [EpaperEditionController::class, 'edit'])->name('epaper.editions.edit')->middleware('can:epaper.edit');
        $r->put('/epaper/editions/{id:\d+}', [EpaperEditionController::class, 'update'])->name('epaper.editions.update')->middleware('can:epaper.edit');
        $r->delete('/epaper/editions/{id:\d+}', [EpaperEditionController::class, 'destroy'])->name('epaper.editions.destroy')->middleware('can:epaper.delete');

        // ---------- Phase 9: विज्ञापन और विज्ञापनदाता CRM ----------
        $r->get('/ads', [AdController::class, 'index'])->name('ads.index')->middleware('can:ads.view');
        $r->get('/ads/export', [AdController::class, 'export'])->name('ads.export')->middleware('can:ads.export');
        $r->get('/ads/create', [AdController::class, 'create'])->name('ads.create')->middleware('can:ads.create');
        $r->post('/ads', [AdController::class, 'store'])->name('ads.store')->middleware('can:ads.create');
        $r->get('/ads/{id:\d+}/edit', [AdController::class, 'edit'])->name('ads.edit')->middleware('can:ads.view');
        $r->put('/ads/{id:\d+}', [AdController::class, 'update'])->name('ads.update')->middleware('can:ads.edit');
        $r->post('/ads/{id:\d+}/toggle', [AdController::class, 'toggle'])->name('ads.toggle')->middleware('can:ads.edit');
        $r->post('/ads/{id:\d+}/duplicate', [AdController::class, 'duplicate'])->name('ads.duplicate')->middleware('can:ads.create');
        $r->delete('/ads/{id:\d+}', [AdController::class, 'destroy'])->name('ads.destroy')->middleware('can:ads.delete');
        $r->get('/ads/slots', [AdSlotController::class, 'index'])->name('ads.slots')->middleware('can:ads.view');
        $r->post('/ads/slots', [AdSlotController::class, 'store'])->name('ads.slots.store')->middleware('can:ads.create');
        $r->get('/ads/slots/{id:\d+}/edit', [AdSlotController::class, 'edit'])->name('ads.slots.edit')->middleware('can:ads.edit');
        $r->put('/ads/slots/{id:\d+}', [AdSlotController::class, 'update'])->name('ads.slots.update')->middleware('can:ads.edit');
        $r->delete('/ads/slots/{id:\d+}', [AdSlotController::class, 'destroy'])->name('ads.slots.destroy')->middleware('can:ads.delete');

        $r->get('/advertisers', [AdvertiserController::class, 'index'])->name('advertisers.index')->middleware('can:advertisers.view');
        $r->get('/advertisers/export', [AdvertiserController::class, 'export'])->name('advertisers.export')->middleware('can:advertisers.export');
        $r->get('/advertisers/create', [AdvertiserController::class, 'create'])->name('advertisers.create')->middleware('can:advertisers.create');
        $r->post('/advertisers', [AdvertiserController::class, 'store'])->name('advertisers.store')->middleware('can:advertisers.create');
        $r->get('/advertisers/{id:\d+}', [AdvertiserController::class, 'show'])->name('advertisers.show')->middleware('can:advertisers.view');
        $r->get('/advertisers/{id:\d+}/edit', [AdvertiserController::class, 'edit'])->name('advertisers.edit')->middleware('can:advertisers.edit');
        $r->put('/advertisers/{id:\d+}', [AdvertiserController::class, 'update'])->name('advertisers.update')->middleware('can:advertisers.edit');
        $r->delete('/advertisers/{id:\d+}', [AdvertiserController::class, 'destroy'])->name('advertisers.destroy')->middleware('can:advertisers.delete');
        $r->get('/revenue', [AdvertiserController::class, 'revenue'])->name('revenue')->middleware('can:advertisers.manage');

        $r->get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create')->middleware('can:advertisers.create');
        $r->post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store')->middleware('can:advertisers.create');
        $r->get('/campaigns/{id:\d+}', [CampaignController::class, 'show'])->name('campaigns.show')->middleware('can:advertisers.view');
        $r->get('/campaigns/{id:\d+}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit')->middleware('can:advertisers.edit');
        $r->put('/campaigns/{id:\d+}', [CampaignController::class, 'update'])->name('campaigns.update')->middleware('can:advertisers.edit');
        $r->delete('/campaigns/{id:\d+}', [CampaignController::class, 'destroy'])->name('campaigns.destroy')->middleware('can:advertisers.delete');

        $r->get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index')->middleware('can:advertisers.manage');
        $r->get('/invoices/export', [InvoiceController::class, 'export'])->name('invoices.export')->middleware('can:advertisers.manage');
        $r->get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create')->middleware('can:advertisers.manage');
        $r->post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store')->middleware('can:advertisers.manage');
        $r->get('/invoices/{id:\d+}', [InvoiceController::class, 'show'])->name('invoices.show')->middleware('can:advertisers.manage');
        $r->get('/invoices/{id:\d+}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit')->middleware('can:advertisers.manage');
        $r->put('/invoices/{id:\d+}', [InvoiceController::class, 'update'])->name('invoices.update')->middleware('can:advertisers.manage');
        $r->get('/invoices/{id:\d+}/print', [InvoiceController::class, 'print'])->name('invoices.print')->middleware('can:advertisers.manage');
        $r->post('/invoices/{id:\d+}/status', [InvoiceController::class, 'status'])->name('invoices.status')->middleware('can:advertisers.manage');
        $r->post('/invoices/{id:\d+}/payments', [InvoiceController::class, 'pay'])->name('invoices.pay')->middleware('can:advertisers.manage');
        $r->delete('/invoices/{id:\d+}/payments/{pid:\d+}', [InvoiceController::class, 'unpay'])->name('invoices.unpay')->middleware('can:advertisers.manage');
        $r->delete('/invoices/{id:\d+}', [InvoiceController::class, 'destroy'])->name('invoices.destroy')->middleware('can:advertisers.manage');

        // ---------- Phase 10: SEO कमांड सेंटर और रीडायरेक्ट ----------
        $r->get('/seo', [SeoController::class, 'index'])->name('seo.index')->middleware('can:seo.view');
        $r->get('/seo/audit', [SeoController::class, 'audit'])->name('seo.audit')->middleware('can:seo.view');
        $r->get('/seo/settings/{tab:[a-z_]+}', [SeoController::class, 'settings'])->name('seo.settings')->middleware('can:seo.view');
        $r->post('/seo/settings/{tab:[a-z_]+}', [SeoController::class, 'saveSettings'])->name('seo.settings.update')->middleware('can:seo.edit,seo.manage');
        $r->get('/seo/404', [SeoController::class, 'notFound'])->name('seo.404')->middleware('can:seo.view');
        $r->post('/seo/404', [SeoController::class, 'notFoundAction'])->name('seo.404.action')->middleware('can:seo.edit');
        $r->get('/seo/links', [SeoController::class, 'links'])->name('seo.links')->middleware('can:seo.view');
        $r->get('/seo/canonical', [SeoController::class, 'canonical'])->name('seo.canonical')->middleware('can:seo.view');

        $r->get('/redirects', [RedirectController::class, 'index'])->name('redirects.index')->middleware('can:redirects.view');
        $r->get('/redirects/export', [RedirectController::class, 'export'])->name('redirects.export')->middleware('can:redirects.view');
        $r->post('/redirects/import', [RedirectController::class, 'import'])->name('redirects.import')->middleware('can:redirects.create');
        $r->get('/redirects/create', [RedirectController::class, 'create'])->name('redirects.create')->middleware('can:redirects.create');
        $r->post('/redirects', [RedirectController::class, 'store'])->name('redirects.store')->middleware('can:redirects.create');
        $r->get('/redirects/{id:\d+}/edit', [RedirectController::class, 'edit'])->name('redirects.edit')->middleware('can:redirects.edit');
        $r->put('/redirects/{id:\d+}', [RedirectController::class, 'update'])->name('redirects.update')->middleware('can:redirects.edit');
        $r->post('/redirects/{id:\d+}/toggle', [RedirectController::class, 'toggle'])->name('redirects.toggle')->middleware('can:redirects.edit');
        $r->delete('/redirects/{id:\d+}', [RedirectController::class, 'destroy'])->name('redirects.destroy')->middleware('can:redirects.delete');

        // ---------- Phase 11: टिप्पणियाँ, पोल, न्यूज़लेटर, पाठक, नोटिफ़िकेशन ----------
        $r->get('/comments', [CommentController::class, 'index'])->name('comments.index')->middleware('can:comments.view');
        $r->post('/comments/action', [CommentController::class, 'action'])->name('comments.action')->middleware('can:comments.approve,comments.delete');
        $r->post('/comments/{id:\d+}/reply', [CommentController::class, 'reply'])->name('comments.reply')->middleware('can:comments.edit');
        $r->post('/comments/{id:\d+}/block', [CommentController::class, 'block'])->name('comments.block')->middleware('can:comments.edit');
        $r->get('/comments/blocks', [CommentController::class, 'blocks'])->name('comments.blocks')->middleware('can:comments.view');
        $r->post('/comments/blocks', [CommentController::class, 'addWord'])->name('comments.words')->middleware('can:comments.edit');
        $r->post('/comments/blocks/{id:\d+}/delete', [CommentController::class, 'unblock'])->name('comments.unblock')->middleware('can:comments.edit');

        $r->get('/polls', [PollController::class, 'index'])->name('polls.index')->middleware('can:polls.view');
        $r->get('/polls/create', [PollController::class, 'create'])->name('polls.create')->middleware('can:polls.create');
        $r->post('/polls', [PollController::class, 'store'])->name('polls.store')->middleware('can:polls.create');
        $r->get('/polls/{id:\d+}/edit', [PollController::class, 'edit'])->name('polls.edit')->middleware('can:polls.view');
        $r->put('/polls/{id:\d+}', [PollController::class, 'update'])->name('polls.update')->middleware('can:polls.edit');
        $r->get('/polls/{id:\d+}/export', [PollController::class, 'export'])->name('polls.export')->middleware('can:polls.view');
        $r->delete('/polls/{id:\d+}', [PollController::class, 'destroy'])->name('polls.destroy')->middleware('can:polls.delete');

        $r->get('/newsletter', [NewsletterController::class, 'index'])->name('newsletter.index')->middleware('can:newsletter.view');
        $r->get('/newsletter/create', [NewsletterController::class, 'create'])->name('newsletter.create')->middleware('can:newsletter.create');
        $r->post('/newsletter', [NewsletterController::class, 'store'])->name('newsletter.store')->middleware('can:newsletter.create');
        $r->post('/newsletter/process', [NewsletterController::class, 'process'])->name('newsletter.process')->middleware('can:newsletter.manage');
        $r->get('/newsletter/subscribers', [NewsletterController::class, 'subscribers'])->name('newsletter.subscribers')->middleware('can:newsletter.view');
        $r->post('/newsletter/subscribers', [NewsletterController::class, 'addSubscribers'])->name('newsletter.subscribers.add')->middleware('can:newsletter.create');
        $r->post('/newsletter/subscribers/action', [NewsletterController::class, 'subscriberAction'])->name('newsletter.subscribers.action')->middleware('can:newsletter.edit,newsletter.delete');
        $r->get('/newsletter/subscribers/export', [NewsletterController::class, 'export'])->name('newsletter.export')->middleware('can:newsletter.export');
        $r->get('/newsletter/lists', [NewsletterController::class, 'lists'])->name('newsletter.lists')->middleware('can:newsletter.view');
        $r->post('/newsletter/lists', [NewsletterController::class, 'saveList'])->name('newsletter.lists.save')->middleware('can:newsletter.edit');
        $r->delete('/newsletter/lists/{id:\d+}', [NewsletterController::class, 'deleteList'])->name('newsletter.lists.destroy')->middleware('can:newsletter.delete');
        $r->get('/newsletter/templates', [NewsletterController::class, 'templates'])->name('newsletter.templates')->middleware('can:newsletter.view');
        $r->post('/newsletter/templates', [NewsletterController::class, 'saveTemplate'])->name('newsletter.templates.save')->middleware('can:newsletter.manage');
        $r->delete('/newsletter/templates/{id:\d+}', [NewsletterController::class, 'deleteTemplate'])->name('newsletter.templates.destroy')->middleware('can:newsletter.manage');
        $r->get('/newsletter/{id:\d+}', [NewsletterController::class, 'show'])->name('newsletter.show')->middleware('can:newsletter.view');
        $r->get('/newsletter/{id:\d+}/preview', [NewsletterController::class, 'preview'])->name('newsletter.preview')->middleware('can:newsletter.view');
        $r->get('/newsletter/{id:\d+}/edit', [NewsletterController::class, 'edit'])->name('newsletter.edit')->middleware('can:newsletter.edit');
        $r->put('/newsletter/{id:\d+}', [NewsletterController::class, 'update'])->name('newsletter.update')->middleware('can:newsletter.edit');
        $r->post('/newsletter/{id:\d+}/cancel', [NewsletterController::class, 'cancel'])->name('newsletter.cancel')->middleware('can:newsletter.manage');
        $r->post('/newsletter/{id:\d+}/duplicate', [NewsletterController::class, 'duplicate'])->name('newsletter.duplicate')->middleware('can:newsletter.create');
        $r->delete('/newsletter/{id:\d+}', [NewsletterController::class, 'destroy'])->name('newsletter.destroy')->middleware('can:newsletter.delete');

        $r->get('/readers', [ReaderController::class, 'index'])->name('readers.index')->middleware('can:readers.view');
        $r->get('/readers/export', [ReaderController::class, 'export'])->name('readers.export')->middleware('can:readers.export');
        $r->get('/readers/{id:\d+}', [ReaderController::class, 'show'])->name('readers.show')->middleware('can:readers.view');
        $r->post('/readers/{id:\d+}', [ReaderController::class, 'update'])->name('readers.update')->middleware('can:readers.edit');
        $r->delete('/readers/{id:\d+}', [ReaderController::class, 'destroy'])->name('readers.destroy')->middleware('can:readers.delete');

        // घंटी: हर लॉगिन स्टाफ़ (अपनी सूचनाएँ); सेंटर: notifications.*
        $r->get('/notifications/bell', [NotificationController::class, 'bell'])->name('notifications.bell');
        $r->get('/notifications/mine', [NotificationController::class, 'mine'])->name('notifications.mine');
        $r->post('/notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');
        $r->get('/notifications/open/{id:\d+}', [NotificationController::class, 'open'])->name('notifications.open');
        $r->get('/notifications', [NotificationController::class, 'center'])->name('notifications.center')->middleware('can:notifications.view');
        $r->post('/notifications/send', [NotificationController::class, 'send'])->name('notifications.send')->middleware('can:notifications.create');
        $r->post('/notifications/process', [NotificationController::class, 'process'])->name('notifications.process')->middleware('can:notifications.manage');
        $r->post('/notifications/retry', [NotificationController::class, 'retry'])->name('notifications.retry')->middleware('can:notifications.manage');

        // ऑडिट लॉग
        $r->get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index')->middleware('can:audit.view');
        $r->get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit.export')->middleware('can:audit.export');
        $r->get('/audit-logs/{id:\d+}', [AuditLogController::class, 'show'])->name('audit.show')->middleware('can:audit.view');
    });
});
