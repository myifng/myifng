<?php
/**
 * डैशबोर्ड विजेट रजिस्ट्री। 'module' का phase पूरा होने और यूज़र के पास 'permission' होने पर ही विजेट दिखता है।
 * provider: DashboardService का method, जो ['value' => .., 'sub' => .., 'alert' => bool] लौटाए।
 * आगे के phase यहाँ अपने कार्ड जोड़ते हैं (ख़बरें, रिपोर्टर, ई-पेपर, विज्ञापन…)।
 */
return [
    'cards' => [
        // Phase 4
        'news_today'      => ['label' => 'आज की ख़बरें', 'icon' => 'fa-newspaper', 'module' => 'news', 'permission' => 'news.view', 'provider' => 'newsToday', 'route' => 'admin.news.index'],
        'news_pending'    => ['label' => 'मंज़ूरी बाकी', 'icon' => 'fa-hourglass-half', 'module' => 'news', 'permission' => 'news.approve', 'provider' => 'newsPending', 'route' => 'admin.news.index', 'query' => 'status=desk'],
        'news_mine'       => ['label' => 'मेरी ख़बरें', 'icon' => 'fa-pen-nib', 'module' => 'news', 'permission' => 'news.create', 'provider' => 'newsMine', 'route' => 'admin.news.index', 'query' => 'mine=1'],
        'news_scheduled'  => ['label' => 'शेड्यूल', 'icon' => 'fa-calendar-check', 'module' => 'news', 'permission' => 'news.publish', 'provider' => 'newsScheduled', 'route' => 'admin.news.index', 'query' => 'status=scheduled'],
        'my_assignments'  => ['label' => 'मेरे असाइनमेंट', 'icon' => 'fa-list-check', 'module' => 'assignments', 'permission' => 'assignments.view', 'provider' => 'myAssignments', 'route' => 'admin.assignments.index', 'query' => 'mine=1'],
        // Phase 6
        'reporters'       => ['label' => 'सक्रिय रिपोर्टर', 'icon' => 'fa-id-card', 'module' => 'reporters', 'permission' => 'reporters.view', 'provider' => 'reporters', 'route' => 'admin.reporters.index'],
        'applications'    => ['label' => 'नए रिपोर्टर आवेदन', 'icon' => 'fa-file-signature', 'module' => 'applications', 'permission' => 'applications.view', 'provider' => 'applications', 'route' => 'admin.applications.index'],
        // Phase 3
        'media'           => ['label' => 'मीडिया लाइब्रेरी', 'icon' => 'fa-photo-film', 'module' => 'media', 'permission' => 'media.view', 'provider' => 'media', 'route' => 'admin.media.index'],
        'categories'      => ['label' => 'श्रेणियाँ', 'icon' => 'fa-folder-tree', 'module' => 'categories', 'permission' => 'categories.view', 'provider' => 'categories', 'route' => 'admin.categories.index'],
        'locations'       => ['label' => 'लोकेशन', 'icon' => 'fa-map-location-dot', 'module' => 'locations', 'permission' => 'locations.view', 'provider' => 'locations', 'route' => 'admin.locations.index'],
        // Phase 1-2
        'users'           => ['label' => 'कुल यूज़र', 'icon' => 'fa-users', 'module' => 'users', 'permission' => 'users.view', 'provider' => 'users', 'route' => 'admin.users.index'],
        'pages'           => ['label' => 'पेज', 'icon' => 'fa-file-lines', 'module' => 'pages', 'permission' => 'pages.view', 'provider' => 'pages', 'route' => 'admin.pages.index'],
        'home_sections'   => ['label' => 'होमपेज सेक्शन', 'icon' => 'fa-table-cells-large', 'module' => 'homepage', 'permission' => 'homepage.view', 'provider' => 'homeSections', 'route' => 'admin.homepage'],
        'logins_today'    => ['label' => 'आज के लॉगिन', 'icon' => 'fa-right-to-bracket', 'module' => 'dashboard', 'permission' => 'dashboard.view', 'provider' => 'loginsToday', 'route' => null],
        'failed_logins'   => ['label' => 'असफल लॉगिन (24 घंटे)', 'icon' => 'fa-shield-halved', 'module' => 'audit', 'permission' => 'audit.view', 'provider' => 'failedLogins', 'route' => 'admin.audit.index'],
    ],
    'max_cards' => 10,
];
