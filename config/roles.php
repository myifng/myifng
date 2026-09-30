<?php
/**
 * डिफ़ॉल्ट (system) रोल और उनकी शुरुआती अनुमतियाँ। इंस्टॉल के समय लगती हैं;
 * बाद में एडमिन "रोल और अनुमतियाँ" स्क्रीन से बदल सकता है।
 * '*' = सभी अनुमतियाँ, 'news.*' = news मॉड्यूल की सभी।
 */
return [
    'super-admin' => ['name' => 'Super Admin', 'description' => 'पूरे सिस्टम पर नियंत्रण। हर अनुमति अपने आप।', 'level' => 100, 'permissions' => ['*']],
    'admin'       => ['name' => 'Admin', 'description' => 'वेबसाइट, CMS और न्यूज़रूम का प्रबंधन।', 'level' => 80, 'permissions' => ['*']],
    'editor'      => ['name' => 'Editor', 'description' => 'ख़बरों की समीक्षा, संपादन, मंज़ूरी, शेड्यूल और प्रकाशन।', 'level' => 60, 'permissions' => [
        'dashboard.view', 'news.*', 'assignments.*', 'breaking.*', 'live_blogs.*', 'videos.*', 'galleries.*', 'web_stories.*', 'audio.*',
        'categories.view', 'topics.*', 'tags.*', 'locations.view', 'media.*', 'comments.*', 'fact_checks.*', 'news_tips.*',
        'epaper.view', 'reporters.view', 'applications.view', 'analytics.view', 'reports.view', 'polls.*',
        'elections.view', 'elections.create', 'elections.edit', 'sports.view', 'sports.create', 'sports.edit',
    ]],
    'reporter'    => ['name' => 'Reporter', 'description' => 'अपनी ख़बरें लिखना, असाइनमेंट देखना, अपना प्रदर्शन देखना।', 'level' => 30, 'permissions' => [
        'dashboard.view', 'news.view', 'news.create', 'news.edit', 'assignments.view', 'media.view', 'media.create',
    ]],
    'employee'    => ['name' => 'Employee', 'description' => 'अनुमति के आधार पर अंदरूनी काम।', 'level' => 20, 'permissions' => ['dashboard.view']],
];
