<?php
/**
 * एडमिन पैनल के रूट। प्रीफ़िक्स config('app.admin_path') से (डिफ़ॉल्ट /admin)।
 * हर रूट पर अनुमति middleware: can:मॉड्यूल.action
 * @var App\Core\Router $router
 */
use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
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
    $r->group(['middleware' => ['auth']], function ($r) {
        $r->post('/logout', [AuthController::class, 'logout'])->name('logout');
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

        // ऑडिट लॉग
        $r->get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index')->middleware('can:audit.view');
        $r->get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit.export')->middleware('can:audit.export');
        $r->get('/audit-logs/{id:\d+}', [AuditLogController::class, 'show'])->name('audit.show')->middleware('can:audit.view');
    });
});
