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
        'news_pending'    => ['label' => 'मंज़ूरी बाकी', 'icon' => 'fa-hourglass-half', 'module' => 'news', 'permission' => 'news.approve', 'provider' => 'newsPending', 'route' => 'admin.news.index'],
        // Phase 6
        'reporters'       => ['label' => 'सक्रिय रिपोर्टर', 'icon' => 'fa-id-card', 'module' => 'reporters', 'permission' => 'reporters.view', 'provider' => 'reporters', 'route' => 'admin.reporters.index'],
        'applications'    => ['label' => 'नए रिपोर्टर आवेदन', 'icon' => 'fa-file-signature', 'module' => 'applications', 'permission' => 'applications.view', 'provider' => 'applications', 'route' => 'admin.applications.index'],
        // Phase 1-2
        'users'           => ['label' => 'कुल यूज़र', 'icon' => 'fa-users', 'module' => 'users', 'permission' => 'users.view', 'provider' => 'users', 'route' => 'admin.users.index'],
        'pages'           => ['label' => 'पेज', 'icon' => 'fa-file-lines', 'module' => 'pages', 'permission' => 'pages.view', 'provider' => 'pages', 'route' => 'admin.pages.index'],
        'home_sections'   => ['label' => 'होमपेज सेक्शन', 'icon' => 'fa-table-cells-large', 'module' => 'homepage', 'permission' => 'homepage.view', 'provider' => 'homeSections', 'route' => 'admin.homepage'],
        'logins_today'    => ['label' => 'आज के लॉगिन', 'icon' => 'fa-right-to-bracket', 'module' => 'dashboard', 'permission' => 'dashboard.view', 'provider' => 'loginsToday', 'route' => null],
        'failed_logins'   => ['label' => 'असफल लॉगिन (24 घंटे)', 'icon' => 'fa-shield-halved', 'module' => 'audit', 'permission' => 'audit.view', 'provider' => 'failedLogins', 'route' => 'admin.audit.index'],
    ],
    'max_cards' => 8,
];
