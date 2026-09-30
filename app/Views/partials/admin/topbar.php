<?php $me = user(); ?>
<header class="topbar">
  <button class="btn btn-icon btn-ghost d-lg-none" type="button" data-sidebar-toggle aria-label="मेनू खोलें" aria-controls="sidebar"><i class="fa-solid fa-bars"></i></button>

  <div class="quick-search" role="search">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="search" id="quickSearch" placeholder="मेनू में खोजें… (Ctrl + K)" aria-label="मेनू में खोजें" autocomplete="off">
    <div class="quick-results list-group shadow" id="quickResults" hidden></div>
  </div>

  <div class="topbar-actions">
    <?php $router = app('router'); $quick = array_filter([
        can('news.create') && $router->has('admin.news.create') ? ['admin.news.create', 'fa-pen-nib', 'नई ख़बर'] : null,
        can('breaking.create') && $router->has('admin.breaking.index') ? ['admin.breaking.index', 'fa-bolt', 'ब्रेकिंग चलाएँ'] : null,
        can('assignments.create') && $router->has('admin.assignments.create') ? ['admin.assignments.create', 'fa-list-check', 'नया असाइनमेंट'] : null,
        can('videos.create') && $router->has('admin.videos.create') ? ['admin.videos.create', 'fa-video', 'नया वीडियो'] : null,
        can('web_stories.create') && $router->has('admin.web_stories.create') ? ['admin.web_stories.create', 'fa-mobile-screen', 'नई वेब स्टोरी'] : null,
        can('pages.create') && $router->has('admin.pages.create') ? ['admin.pages.create', 'fa-file-circle-plus', 'नया पेज'] : null,
        can('media.create') && $router->has('admin.media.index') ? ['admin.media.index', 'fa-cloud-arrow-up', 'मीडिया अपलोड'] : null,
        can('users.create') ? ['admin.users.create', 'fa-user-plus', 'नया यूज़र'] : null,
        can('roles.create') ? ['admin.roles.create', 'fa-user-shield', 'नया रोल'] : null,
    ]); if ($quick): ?>
    <div class="dropdown">
      <button class="btn btn-brand btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-plus"></i><span class="d-none d-md-inline ms-1">नया</span></button>
      <ul class="dropdown-menu dropdown-menu-end shadow">
        <?php foreach ($quick as [$r, $ic, $l]): ?><li><a class="dropdown-item" href="<?= e(route($r)) ?>"><i class="fa-solid <?= e($ic) ?> fa-fw me-2"></i><?= e($l) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <a class="btn btn-icon btn-ghost d-none d-sm-inline-flex" href="<?= e(url()) ?>" target="_blank" rel="noopener" title="वेबसाइट देखें" aria-label="वेबसाइट देखें"><i class="fa-solid fa-globe"></i></a>
    <button class="btn btn-icon btn-ghost" type="button" data-theme-toggle title="डार्क/लाइट मोड" aria-label="डार्क या लाइट मोड"><i class="fa-solid fa-circle-half-stroke"></i></button>

    <?php $unreadN = \App\Services\NotificationService::unread('user', (int) auth()->id()); ?>
    <div class="dropdown" data-bell="<?= e(route('admin.notifications.bell')) ?>" data-bell-read="<?= e(route('admin.notifications.read')) ?>">
      <button class="btn btn-icon btn-ghost position-relative" data-bs-toggle="dropdown" aria-expanded="false" aria-label="सूचनाएँ<?= $unreadN ? " ($unreadN नई)" : '' ?>"><i class="fa-regular fa-bell"></i><span class="bell-count" data-bell-count<?= $unreadN ? '' : ' hidden' ?>><?= min(99, $unreadN) ?></span></button>
      <div class="dropdown-menu dropdown-menu-end shadow notif-menu">
        <div class="px-3 py-2 fw-semibold border-bottom d-flex justify-content-between align-items-center">सूचनाएँ <button type="button" class="btn btn-link btn-sm p-0" data-bell-readall>सब पढ़ी</button></div>
        <div class="notif-items" data-bell-items><div class="p-4 text-center text-body-secondary small"><i class="fa-regular fa-bell-slash fa-2x mb-2 d-block opacity-50"></i>अभी कोई नई सूचना नहीं है।</div></div>
        <a class="d-block text-center small py-2 border-top" href="<?= e(route('admin.notifications.mine')) ?>">सभी सूचनाएँ</a>
      </div>
    </div>

    <div class="dropdown">
      <button class="user-btn" data-bs-toggle="dropdown" aria-expanded="false">
        <?= avatar_html($me['avatar'] ?? null, $me['name'] ?? '', 'sm') ?>
        <span class="d-none d-md-block text-start lh-sm"><b><?= e($me['name'] ?? '') ?></b><small><?= e($me['role_name'] ?? '') ?></small></span>
        <i class="fa-solid fa-chevron-down small opacity-50 d-none d-md-inline"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow">
        <li><a class="dropdown-item" href="<?= e(route('admin.profile')) ?>"><i class="fa-regular fa-user fa-fw me-2"></i>मेरी प्रोफ़ाइल</a></li>
        <?php if (app('router')->has('admin.portal') && \App\Services\ReporterService::forUser((int) auth()->id())): ?><li><a class="dropdown-item" href="<?= e(route('admin.portal')) ?>"><i class="fa-regular fa-address-card fa-fw me-2"></i>मेरा रिपोर्टर प्रोफ़ाइल</a></li><?php endif; ?>
        <li><hr class="dropdown-divider"></li>
        <li><form method="post" action="<?= e(route('admin.logout')) ?>"><?= csrf_field() ?><button class="dropdown-item text-danger" type="submit"><i class="fa-solid fa-arrow-right-from-bracket fa-fw me-2"></i>लॉगआउट</button></form></li>
      </ul>
    </div>
  </div>
</header>
