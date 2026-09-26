<?php
/**
 * मॉड्यूल रजिस्ट्री: पूरे प्लेटफ़ॉर्म के सभी मॉड्यूल (अभी के और आगे के phase के)।
 *  - actions: इस मॉड्यूल की अनुमतियाँ (permissions टेबल में sync होती हैं)
 *  - route:   साइडबार का लिंक (जब मॉड्यूल तैयार हो जाए)
 *  - phase:   किस phase में बनेगा; config('app.phase') तक के ही मेनू में दिखते हैं
 * नया मॉड्यूल = यहाँ एक एंट्री + migration + controller + routes।
 */
$crud = ['view', 'create', 'edit', 'delete'];

return [
    'groups' => [
        'main'      => 'मुख्य',
        'newsroom'  => 'न्यूज़रूम',
        'content'   => 'कंटेंट',
        'media'     => 'मल्टीमीडिया',
        'reporters' => 'रिपोर्टर नेटवर्क',
        'revenue'   => 'राजस्व',
        'audience'  => 'पाठक और फ़ॉर्म',
        'insights'  => 'SEO और एनालिटिक्स',
        'special'   => 'विशेष सेक्शन',
        'website'   => 'वेबसाइट',
        'system'    => 'सिस्टम',
    ],

    'modules' => [
        // ---------- Phase 1 ----------
        'dashboard'    => ['label' => 'डैशबोर्ड', 'icon' => 'fa-gauge-high', 'group' => 'main', 'phase' => 1, 'route' => 'admin.dashboard', 'actions' => ['view']],
        'users'        => ['label' => 'यूज़र', 'icon' => 'fa-users', 'group' => 'system', 'phase' => 1, 'route' => 'admin.users.index', 'actions' => [...$crud, 'export', 'manage']],
        'roles'        => ['label' => 'रोल और अनुमतियाँ', 'icon' => 'fa-user-shield', 'group' => 'system', 'phase' => 1, 'route' => 'admin.roles.index', 'actions' => [...$crud, 'manage']],
        'audit'        => ['label' => 'ऑडिट लॉग', 'icon' => 'fa-clipboard-list', 'group' => 'system', 'phase' => 1, 'route' => 'admin.audit.index', 'actions' => ['view', 'export']],

        // ---------- Phase 2 ----------
        'settings'     => ['label' => 'साइट सेटिंग', 'icon' => 'fa-sliders', 'group' => 'website', 'phase' => 2, 'route' => 'admin.settings.index', 'actions' => ['view', 'edit', 'manage']],
        'pages'        => ['label' => 'पेज', 'icon' => 'fa-file-lines', 'group' => 'website', 'phase' => 2, 'route' => 'admin.pages.index', 'actions' => [...$crud, 'publish']],
        'menus'        => ['label' => 'मेनू बिल्डर', 'icon' => 'fa-bars-staggered', 'group' => 'website', 'phase' => 2, 'route' => 'admin.menus.index', 'actions' => [...$crud]],
        'homepage'     => ['label' => 'होमपेज बिल्डर', 'icon' => 'fa-table-cells-large', 'group' => 'website', 'phase' => 2, 'route' => 'admin.homepage', 'actions' => ['view', 'edit', 'manage']],

        // ---------- Phase 3 ----------
        'categories'   => ['label' => 'श्रेणियाँ', 'icon' => 'fa-folder-tree', 'group' => 'content', 'phase' => 3, 'route' => 'admin.categories.index', 'actions' => [...$crud]],
        'topics'       => ['label' => 'टॉपिक', 'icon' => 'fa-hashtag', 'group' => 'content', 'phase' => 3, 'route' => 'admin.topics.index', 'actions' => [...$crud]],
        'tags'         => ['label' => 'टैग', 'icon' => 'fa-tags', 'group' => 'content', 'phase' => 3, 'route' => 'admin.tags.index', 'actions' => [...$crud]],
        'locations'    => ['label' => 'लोकेशन', 'icon' => 'fa-map-location-dot', 'group' => 'content', 'phase' => 3, 'route' => 'admin.locations.index', 'actions' => [...$crud]],
        'media'        => ['label' => 'मीडिया लाइब्रेरी', 'icon' => 'fa-photo-film', 'group' => 'content', 'phase' => 3, 'route' => 'admin.media.index', 'actions' => [...$crud, 'manage']],

        // ---------- Phase 4 ----------
        'news'         => ['label' => 'ख़बरें', 'icon' => 'fa-newspaper', 'group' => 'newsroom', 'phase' => 4, 'actions' => [...$crud, 'approve', 'publish', 'export', 'manage']],
        'assignments'  => ['label' => 'असाइनमेंट डेस्क', 'icon' => 'fa-list-check', 'group' => 'newsroom', 'phase' => 4, 'actions' => [...$crud, 'manage']],

        // ---------- Phase 6 ----------
        'reporters'    => ['label' => 'रिपोर्टर', 'icon' => 'fa-id-card', 'group' => 'reporters', 'phase' => 6, 'actions' => [...$crud, 'approve', 'export', 'manage']],
        'applications' => ['label' => 'रिपोर्टर आवेदन', 'icon' => 'fa-file-signature', 'group' => 'reporters', 'phase' => 6, 'actions' => ['view', 'edit', 'delete', 'approve', 'export']],
        'bureaus'      => ['label' => 'ब्यूरो', 'icon' => 'fa-building', 'group' => 'reporters', 'phase' => 6, 'actions' => [...$crud]],
        'employees'    => ['label' => 'कर्मचारी (HR)', 'icon' => 'fa-people-group', 'group' => 'reporters', 'phase' => 6, 'actions' => [...$crud, 'export', 'manage']],

        // ---------- Phase 7 ----------
        'breaking'     => ['label' => 'ब्रेकिंग कंट्रोल रूम', 'icon' => 'fa-bolt', 'group' => 'newsroom', 'phase' => 7, 'actions' => [...$crud, 'publish']],
        'live_blogs'   => ['label' => 'लाइव ब्लॉग', 'icon' => 'fa-tower-broadcast', 'group' => 'newsroom', 'phase' => 7, 'actions' => [...$crud, 'publish']],
        'live_tv'      => ['label' => 'लाइव टीवी', 'icon' => 'fa-tv', 'group' => 'media', 'phase' => 7, 'actions' => ['view', 'edit']],
        'videos'       => ['label' => 'वीडियो', 'icon' => 'fa-video', 'group' => 'media', 'phase' => 7, 'actions' => [...$crud, 'publish']],
        'galleries'    => ['label' => 'फ़ोटो गैलरी', 'icon' => 'fa-images', 'group' => 'media', 'phase' => 7, 'actions' => [...$crud, 'publish']],
        'web_stories'  => ['label' => 'वेब स्टोरी', 'icon' => 'fa-mobile-screen', 'group' => 'media', 'phase' => 7, 'actions' => [...$crud, 'publish']],
        'audio'        => ['label' => 'ऑडियो / पॉडकास्ट', 'icon' => 'fa-podcast', 'group' => 'media', 'phase' => 7, 'actions' => [...$crud, 'publish']],

        // ---------- Phase 8-9 ----------
        'epaper'       => ['label' => 'ई-पेपर', 'icon' => 'fa-book-open', 'group' => 'media', 'phase' => 8, 'actions' => [...$crud, 'publish']],
        'ads'          => ['label' => 'विज्ञापन', 'icon' => 'fa-rectangle-ad', 'group' => 'revenue', 'phase' => 9, 'actions' => [...$crud, 'export']],
        'advertisers'  => ['label' => 'विज्ञापनदाता CRM', 'icon' => 'fa-handshake', 'group' => 'revenue', 'phase' => 9, 'actions' => [...$crud, 'export', 'manage']],

        // ---------- Phase 10 ----------
        'seo'          => ['label' => 'SEO कमांड सेंटर', 'icon' => 'fa-magnifying-glass-chart', 'group' => 'insights', 'phase' => 10, 'actions' => ['view', 'edit', 'manage']],
        'redirects'    => ['label' => 'रीडायरेक्ट', 'icon' => 'fa-diamond-turn-right', 'group' => 'insights', 'phase' => 10, 'actions' => [...$crud]],

        // ---------- Phase 11-12 ----------
        'comments'     => ['label' => 'टिप्पणियाँ', 'icon' => 'fa-comments', 'group' => 'audience', 'phase' => 11, 'actions' => ['view', 'edit', 'delete', 'approve']],
        'polls'        => ['label' => 'पोल', 'icon' => 'fa-square-poll-vertical', 'group' => 'audience', 'phase' => 11, 'actions' => [...$crud, 'publish']],
        'newsletter'   => ['label' => 'न्यूज़लेटर', 'icon' => 'fa-envelope-open-text', 'group' => 'audience', 'phase' => 11, 'actions' => [...$crud, 'export', 'manage']],
        'readers'      => ['label' => 'पाठक खाते', 'icon' => 'fa-user-group', 'group' => 'audience', 'phase' => 11, 'actions' => ['view', 'edit', 'delete', 'export']],
        'notifications'=> ['label' => 'नोटिफ़िकेशन', 'icon' => 'fa-bell', 'group' => 'audience', 'phase' => 11, 'actions' => ['view', 'create', 'manage']],
        'forms'        => ['label' => 'फ़ॉर्म बिल्डर', 'icon' => 'fa-wpforms', 'group' => 'audience', 'phase' => 12, 'actions' => [...$crud, 'export']],
        'news_tips'    => ['label' => 'न्यूज़ टिप', 'icon' => 'fa-lightbulb', 'group' => 'audience', 'phase' => 12, 'actions' => ['view', 'edit', 'delete', 'approve']],
        'complaints'   => ['label' => 'शिकायत / ग्रीवांस', 'icon' => 'fa-scale-balanced', 'group' => 'audience', 'phase' => 12, 'actions' => ['view', 'edit', 'delete', 'manage', 'export']],
        'contacts'     => ['label' => 'संपर्क / पूछताछ', 'icon' => 'fa-address-book', 'group' => 'audience', 'phase' => 12, 'actions' => ['view', 'edit', 'delete', 'export']],
        'careers'      => ['label' => 'करियर / इंटर्नशिप', 'icon' => 'fa-briefcase', 'group' => 'audience', 'phase' => 12, 'actions' => [...$crud, 'export']],

        // ---------- Phase 13-14 ----------
        'analytics'    => ['label' => 'एनालिटिक्स', 'icon' => 'fa-chart-line', 'group' => 'insights', 'phase' => 13, 'actions' => ['view', 'export']],
        'reports'      => ['label' => 'न्यूज़रूम रिपोर्ट', 'icon' => 'fa-chart-pie', 'group' => 'insights', 'phase' => 13, 'actions' => ['view', 'export']],
        'fact_checks'  => ['label' => 'फ़ैक्ट चेक', 'icon' => 'fa-circle-check', 'group' => 'special', 'phase' => 14, 'actions' => [...$crud, 'approve', 'publish']],
        'elections'    => ['label' => 'चुनाव केंद्र', 'icon' => 'fa-check-to-slot', 'group' => 'special', 'phase' => 14, 'actions' => [...$crud, 'manage']],
        'sports'       => ['label' => 'खेल केंद्र', 'icon' => 'fa-trophy', 'group' => 'special', 'phase' => 14, 'actions' => [...$crud, 'manage']],

        // ---------- Phase 15 ----------
        'backups'      => ['label' => 'बैकअप', 'icon' => 'fa-database', 'group' => 'system', 'phase' => 15, 'actions' => ['view', 'create', 'delete', 'manage']],
        'system'       => ['label' => 'सिस्टम / कैश', 'icon' => 'fa-server', 'group' => 'system', 'phase' => 15, 'actions' => ['view', 'manage']],
    ],
];
