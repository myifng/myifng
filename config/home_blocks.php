<?php
/**
 * होमपेज बिल्डर के ब्लॉक। हर ब्लॉक की सेटिंग के खाने यहीं से बनते हैं।
 * 'needs' = किस मॉड्यूल की सामग्री चाहिए (वह तैयार न हो तो बिल्डर में सूचना दिखती है)।
 * field type: text, number, select, switch, textarea, code, category, location, stories (मैनुअल ख़बरें)
 */
$count = fn(int $d, int $max = 30) => ['label' => 'कितनी ख़बरें', 'type' => 'number', 'default' => $d, 'min' => 1, 'max' => $max];
$source = ['label' => 'ख़बरें कैसे चुनें', 'type' => 'select', 'options' => ['auto' => 'अपने आप (नई ख़बरें)', 'manual' => 'हाथ से चुनें'], 'default' => 'auto'];
$more = ['label' => '"और पढ़ें" लिंक दिखाएँ', 'type' => 'switch', 'default' => 1];

return [
    'hero' => ['label' => 'हीरो (मुख्य ख़बरें)', 'icon' => 'fa-star', 'needs' => 'news', 'desc' => 'सबसे ऊपर बड़ी ख़बर + साथ की ख़बरें', 'fields' => [
        'layout' => ['label' => 'लेआउट', 'type' => 'select', 'options' => ['hero-3col' => 'बड़ी + सूची + साइडबार', 'hero-grid' => 'बड़ी + 4 छोटी', 'hero-slider' => 'स्लाइडर'], 'default' => 'hero-3col'],
        'filter' => ['label' => 'कौन-सी ख़बरें', 'type' => 'select', 'options' => ['featured' => 'फ़ीचर्ड', 'latest' => 'सबसे नई', 'editor_pick' => 'संपादक की पसंद'], 'default' => 'featured'],
        'source' => $source, 'count' => $count(5, 12), 'stories' => ['label' => 'चुनी गई ख़बरें', 'type' => 'stories'],
    ]],
    'grid' => ['label' => 'ग्रिड', 'icon' => 'fa-table-cells', 'needs' => 'news', 'desc' => 'कार्ड की जाली', 'fields' => [
        'columns' => ['label' => 'कॉलम', 'type' => 'select', 'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '4'],
        'category' => ['label' => 'श्रेणी (ख़ाली = सभी)', 'type' => 'category'], 'source' => $source, 'count' => $count(8), 'stories' => ['label' => 'चुनी गई ख़बरें', 'type' => 'stories'], 'more' => $more,
    ]],
    'slider' => ['label' => 'स्लाइडर', 'icon' => 'fa-images', 'needs' => 'news', 'desc' => 'बाएँ-दाएँ खिसकने वाली ख़बरें', 'fields' => [
        'category' => ['label' => 'श्रेणी (ख़ाली = सभी)', 'type' => 'category'], 'source' => $source, 'count' => $count(6, 15), 'stories' => ['label' => 'चुनी गई ख़बरें', 'type' => 'stories'],
        'autoplay' => ['label' => 'अपने आप चले', 'type' => 'switch', 'default' => 1],
    ]],
    'latest' => ['label' => 'ताज़ा ख़बरें', 'icon' => 'fa-clock', 'needs' => 'news', 'desc' => 'समय के साथ ताज़ा ख़बरों की सूची', 'fields' => [
        'layout' => ['label' => 'लेआउट', 'type' => 'select', 'options' => ['list' => 'समय वाली सूची', 'cards' => 'कार्ड'], 'default' => 'list'], 'count' => $count(10), 'more' => $more,
    ]],
    'category' => ['label' => 'श्रेणी सेक्शन', 'icon' => 'fa-folder-tree', 'needs' => 'news', 'desc' => 'किसी एक श्रेणी की ख़बरें', 'fields' => [
        'category' => ['label' => 'श्रेणी', 'type' => 'category', 'required' => true],
        'layout' => ['label' => 'लेआउट', 'type' => 'select', 'options' => ['lead-list' => '1 बड़ी + सूची', 'grid' => 'ग्रिड', 'half' => 'आधी चौड़ाई (दो श्रेणी साथ)'], 'default' => 'lead-list'],
        'count' => $count(5), 'more' => $more,
    ]],
    'location' => ['label' => 'लोकेशन / मेरा शहर', 'icon' => 'fa-map-location-dot', 'needs' => 'news', 'desc' => 'राज्य और ज़िलों के टैब', 'fields' => [
        'location' => ['label' => 'राज्य / लोकेशन (ख़ाली = पाठक का चुना शहर)', 'type' => 'location'],
        'layout' => ['label' => 'लेआउट', 'type' => 'select', 'options' => ['tabs' => 'ज़िलों के टैब', 'lead-list' => '1 बड़ी + सूची'], 'default' => 'tabs'], 'count' => $count(6),
    ]],
    'videos' => ['label' => 'वीडियो', 'icon' => 'fa-video', 'needs' => 'videos', 'desc' => 'वीडियो की पट्टी', 'fields' => [
        'layout' => ['label' => 'लेआउट', 'type' => 'select', 'options' => ['dark-strip' => 'काली पट्टी', 'grid' => 'ग्रिड', 'shorts' => 'शॉर्ट्स (9:16)'], 'default' => 'dark-strip'],
        'filter' => ['label' => 'कौन-से वीडियो', 'type' => 'select', 'options' => ['' => 'सभी', 'featured' => 'फ़ीचर्ड', 'short' => 'शॉर्ट वीडियो', 'interview' => 'इंटरव्यू', 'ground_report' => 'ग्राउंड रिपोर्ट', 'show' => 'शो'], 'default' => ''],
        'category' => ['label' => 'श्रेणी (ख़ाली = सभी)', 'type' => 'category'], 'count' => $count(5, 12), 'more' => $more,
    ]],
    'gallery' => ['label' => 'फ़ोटो गैलरी', 'icon' => 'fa-images', 'needs' => 'galleries', 'desc' => 'ताज़ा फ़ोटो गैलरी', 'fields' => ['count' => $count(4, 12), 'more' => $more]],
    'web_stories' => ['label' => 'वेब स्टोरी', 'icon' => 'fa-mobile-screen', 'needs' => 'web_stories', 'desc' => '9:16 वेब स्टोरी की पट्टी', 'fields' => ['count' => $count(8, 20), 'more' => $more]],
    'audio' => ['label' => 'ऑडियो / पॉडकास्ट', 'icon' => 'fa-podcast', 'needs' => 'audio', 'desc' => 'ताज़ा ऑडियो न्यूज़ और पॉडकास्ट एपिसोड', 'fields' => [
        'filter' => ['label' => 'क्या दिखाएँ', 'type' => 'select', 'options' => ['' => 'सभी', 'news' => 'ऑडियो न्यूज़', 'episode' => 'पॉडकास्ट एपिसोड'], 'default' => ''], 'count' => $count(4, 12), 'more' => $more,
    ]],
    'epaper' => ['label' => 'ई-पेपर', 'icon' => 'fa-book-open', 'needs' => 'epaper', 'desc' => 'आज के ई-पेपर का कवर', 'fields' => []],
    'live_tv' => ['label' => 'लाइव टीवी', 'icon' => 'fa-tv', 'needs' => 'live_tv', 'desc' => 'डिफ़ॉल्ट चैनल का प्लेयर + अभी का कार्यक्रम', 'fields' => [
        'autoplay' => ['label' => 'अपने आप चले (म्यूट)', 'type' => 'switch', 'default' => 0],
    ]],
    'breaking' => ['label' => 'ब्रेकिंग', 'icon' => 'fa-bolt', 'needs' => 'breaking', 'desc' => 'कंट्रोल रूम के चालू आइटम (सूची या बैनर)', 'fields' => [
        'style' => ['label' => 'शैली', 'type' => 'select', 'options' => ['banner' => 'लाल अलर्ट बैनर (होमपेज अलर्ट वाले आइटम)', 'list' => 'समय वाली सूची (सभी चालू आइटम)'], 'default' => 'banner'],
    ]],
    'trending' => ['label' => 'ट्रेंडिंग', 'icon' => 'fa-fire', 'needs' => 'news', 'desc' => 'ट्रेंडिंग टैग और ख़बरें', 'fields' => ['count' => $count(6)]],
    'most_read' => ['label' => 'सबसे ज़्यादा पढ़ी गईं', 'icon' => 'fa-ranking-star', 'needs' => 'news', 'desc' => 'नंबर वाली सूची', 'fields' => [
        'count' => $count(5, 10), 'period' => ['label' => 'कितने दिन की', 'type' => 'select', 'options' => ['1' => 'आज', '7' => '7 दिन', '30' => '30 दिन'], 'default' => '7'],
    ]],
    'ads' => ['label' => 'विज्ञापन', 'icon' => 'fa-rectangle-ad', 'needs' => 'ads', 'desc' => 'विज्ञापन स्लॉट (विज्ञापन → स्लॉट में बने होमपेज/कस्टम स्लॉट)', 'fields' => [
        'slot' => ['label' => 'विज्ञापन स्लॉट', 'type' => 'adslot', 'default' => 'home_middle'],
    ]],
    'newsletter' => ['label' => 'न्यूज़लेटर', 'icon' => 'fa-envelope-open-text', 'needs' => null, 'desc' => 'ईमेल सब्सक्राइब फ़ॉर्म', 'fields' => [
        'text' => ['label' => 'संदेश', 'type' => 'text', 'default' => 'हर सुबह दिन की बड़ी ख़बरें आपके ईमेल पर'],
    ]],
    'custom_html' => ['label' => 'कस्टम HTML', 'icon' => 'fa-code', 'needs' => null, 'manage' => true, 'desc' => 'अपना HTML/एम्बेड (सिर्फ़ प्रबंधक)', 'fields' => [
        'html' => ['label' => 'HTML कोड', 'type' => 'code'],
        'boxed' => ['label' => 'सफ़ेद बॉक्स में दिखाएँ', 'type' => 'switch', 'default' => 1],
    ]],
];
