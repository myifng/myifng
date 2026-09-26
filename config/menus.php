<?php
/**
 * मेनू की जगहें और आइटम के प्रकार।
 * प्रकार का 'table' हो तो वह टेबल बनने (उसके phase) के बाद ही चुनने के लिए मिलता है।
 */
return [
    'locations' => [
        'top' => 'टॉप बार मेनू',
        'main' => 'मुख्य नेविगेशन',
        'mobile' => 'मोबाइल मेनू',
        'footer_1' => 'फ़ुटर कॉलम 1',
        'footer_2' => 'फ़ुटर कॉलम 2',
        'footer_3' => 'फ़ुटर कॉलम 3',
        'footer_4' => 'फ़ुटर कॉलम 4',
        'legal' => 'लीगल मेनू',
        'quick' => 'क्विक लिंक्स',
    ],
    'max_depth' => 3,
    'types' => [
        'home' => ['label' => 'होम पेज', 'icon' => 'fa-house'],
        'page' => ['label' => 'पेज', 'icon' => 'fa-file-lines', 'table' => 'pages', 'title_col' => 'title'],
        // 'where': चुनने की सूची में कौन-सी पंक्तियाँ; 'order': क्रम
        'category' => ['label' => 'श्रेणी', 'icon' => 'fa-folder-tree', 'table' => 'categories', 'title_col' => 'name', 'order' => 'COALESCE(parent_id, id), parent_id IS NOT NULL, sort_order'],
        'location' => ['label' => 'लोकेशन', 'icon' => 'fa-map-location-dot', 'table' => 'locations', 'title_col' => 'name', 'where' => "path IS NOT NULL AND type IN ('state','district','city')", 'order' => "FIELD(type, 'state','district','city'), name"],
        'topic' => ['label' => 'टॉपिक', 'icon' => 'fa-hashtag', 'table' => 'topics', 'title_col' => 'name'],
        'epaper' => ['label' => 'ई-पेपर', 'icon' => 'fa-book-open'],
        'live_tv' => ['label' => 'लाइव टीवी', 'icon' => 'fa-tv'],
        'custom' => ['label' => 'साइट का पता', 'icon' => 'fa-link'],
        'external' => ['label' => 'बाहरी वेबसाइट', 'icon' => 'fa-arrow-up-right-from-square'],
    ],
];
