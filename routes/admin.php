<?php
/**
 * एडमिन पैनल के रूट। प्रीफ़िक्स config('app.admin_path') से (डिफ़ॉल्ट /admin)।
 * हर रूट पर अनुमति middleware: can:मॉड्यूल.action
 * @var App\Core\Router $router
 */
use App\Controllers\Admin\ApplicationController;
use App\Controllers\Admin\AssignmentController;
use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\BureauController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\EditorController;
use App\Controllers\Admin\HomepageController;
use App\Controllers\Admin\LocationController;
use App\Controllers\Admin\MediaController;
use App\Controllers\Admin\MenuController;
use App\Controllers\Admin\NewsController;
use App\Controllers\Admin\NewsWorkflowController;
use App\Controllers\Admin\PageController;
use App\Controllers\Admin\ReporterController;
use App\Controllers\Admin\ReporterPortalController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\SystemController;
use App\Controllers\Admin\TagController;
use App\Controllers\Admin\TopicController;
use App\Controllers\Admin\ProfileController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\UserController;

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

        // ऑडिट लॉग
        $r->get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index')->middleware('can:audit.view');
        $r->get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit.export')->middleware('can:audit.export');
        $r->get('/audit-logs/{id:\d+}', [AuditLogController::class, 'show'])->name('audit.show')->middleware('can:audit.view');
    });
});
