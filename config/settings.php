<?php
/**
 * सेटिंग schema: एडमिन → साइट सेटिंग के सभी टैब यहीं से बनते हैं।
 * नया सेटिंग = यहाँ एक पंक्ति। सिर्फ़ यहाँ लिखे key ही सेव होते हैं।
 *   type: text, textarea, email, url, tel, number, color, select, switch, image, code, checkboxes, timezone, font
 *   permission: 'edit' (settings.edit) या 'manage' (settings.manage: संवेदनशील)
 */
$fonts = ['Mukta' => 'Mukta', 'Noto Sans Devanagari' => 'Noto Sans Devanagari', 'Hind' => 'Hind', 'Baloo 2' => 'Baloo 2', 'Tiro Devanagari Hindi' => 'Tiro Devanagari Hindi (सेरिफ़)', 'Poppins' => 'Poppins', 'Inter' => 'Inter', 'Roboto' => 'Roboto'];

return [
    'general' => [
        'label' => 'सामान्य', 'icon' => 'fa-gear', 'permission' => 'edit',
        'fields' => [
            'site_name' => ['label' => 'वेबसाइट का नाम', 'type' => 'text', 'rules' => 'required|max:100', 'width' => 6],
            'tagline' => ['label' => 'टैगलाइन', 'type' => 'text', 'rules' => 'nullable|max:150', 'width' => 6],
            'site_description' => ['label' => 'वेबसाइट का विवरण', 'type' => 'textarea', 'rules' => 'nullable|max:300', 'help' => 'सर्च इंजन और सोशल मीडिया में दिखता है'],
            'language' => ['label' => 'मुख्य भाषा', 'type' => 'select', 'options' => ['hi' => 'हिंदी', 'en' => 'English'], 'default' => 'hi', 'rules' => 'required|in:hi,en', 'width' => 4],
            'timezone' => ['label' => 'टाइमज़ोन', 'type' => 'timezone', 'default' => 'Asia/Kolkata', 'rules' => 'required', 'width' => 4],
            'date_format' => ['label' => 'तारीख़ का प्रारूप', 'type' => 'select', 'options' => ['hindi' => '26 सितंबर 2026', 'd-m-Y' => '26-09-2026', 'd/m/Y' => '26/09/2026', 'M d, Y' => 'Sep 26, 2026'], 'default' => 'hindi', 'rules' => 'required', 'width' => 4],
        ],
    ],
    'branding' => [
        'label' => 'ब्रांडिंग', 'icon' => 'fa-palette', 'permission' => 'edit',
        'fields' => [
            'logo' => ['label' => 'लोगो', 'type' => 'image', 'help' => 'PNG/WebP, चौड़ाई 400-800px', 'width' => 4],
            'logo_dark' => ['label' => 'डार्क लोगो', 'type' => 'image', 'help' => 'गहरे बैकग्राउंड के लिए (सफ़ेद अक्षर)', 'width' => 4],
            'logo_mobile' => ['label' => 'मोबाइल लोगो', 'type' => 'image', 'help' => 'छोटा/चौकोर लोगो', 'width' => 4],
            'favicon' => ['label' => 'फ़ेविकॉन', 'type' => 'image', 'kind' => 'icon', 'help' => 'PNG 512×512', 'width' => 4],
            'primary_color' => ['label' => 'मुख्य रंग', 'type' => 'color', 'default' => '#d71920', 'rules' => 'required|color', 'width' => 4],
            'secondary_color' => ['label' => 'दूसरा रंग', 'type' => 'color', 'default' => '#15161a', 'rules' => 'required|color', 'width' => 4],
            'font_heading' => ['label' => 'शीर्षक का फ़ॉन्ट', 'type' => 'font', 'options' => $fonts, 'default' => 'Mukta', 'width' => 6],
            'font_body' => ['label' => 'लेख का फ़ॉन्ट', 'type' => 'font', 'options' => $fonts, 'default' => 'Noto Sans Devanagari', 'width' => 6],
        ],
    ],
    'contact' => [
        'label' => 'संपर्क', 'icon' => 'fa-address-card', 'permission' => 'edit',
        'fields' => [
            'contact_email' => ['label' => 'संपर्क ईमेल', 'type' => 'email', 'rules' => 'nullable|email|max:190', 'width' => 6],
            'contact_phone' => ['label' => 'फ़ोन नंबर', 'type' => 'tel', 'rules' => 'nullable|max:30', 'width' => 6],
            'whatsapp' => ['label' => 'WhatsApp नंबर', 'type' => 'tel', 'rules' => 'nullable|max:20', 'help' => 'देश कोड के साथ, जैसे 919876543210', 'width' => 6],
            'news_email' => ['label' => 'ख़बर भेजने का ईमेल', 'type' => 'email', 'rules' => 'nullable|email|max:190', 'width' => 6],
            'address' => ['label' => 'कार्यालय का पता', 'type' => 'textarea', 'rules' => 'nullable|max:300'],
            'map_url' => ['label' => 'Google Map लिंक', 'type' => 'url', 'rules' => 'nullable|url|max:500'],
        ],
    ],
    'social' => [
        'label' => 'सोशल मीडिया', 'icon' => 'fa-share-nodes', 'permission' => 'edit',
        'fields' => [
            'facebook' => ['label' => 'Facebook', 'type' => 'url', 'rules' => 'nullable|url|max:300', 'width' => 6, 'icon' => 'fa-brands fa-facebook'],
            'twitter' => ['label' => 'X (Twitter)', 'type' => 'url', 'rules' => 'nullable|url|max:300', 'width' => 6, 'icon' => 'fa-brands fa-x-twitter'],
            'youtube' => ['label' => 'YouTube', 'type' => 'url', 'rules' => 'nullable|url|max:300', 'width' => 6, 'icon' => 'fa-brands fa-youtube'],
            'instagram' => ['label' => 'Instagram', 'type' => 'url', 'rules' => 'nullable|url|max:300', 'width' => 6, 'icon' => 'fa-brands fa-instagram'],
            'telegram' => ['label' => 'Telegram', 'type' => 'url', 'rules' => 'nullable|url|max:300', 'width' => 6, 'icon' => 'fa-brands fa-telegram'],
            'linkedin' => ['label' => 'LinkedIn', 'type' => 'url', 'rules' => 'nullable|url|max:300', 'width' => 6, 'icon' => 'fa-brands fa-linkedin'],
            'whatsapp_channel' => ['label' => 'WhatsApp चैनल', 'type' => 'url', 'rules' => 'nullable|url|max:300', 'width' => 6, 'icon' => 'fa-brands fa-whatsapp'],
            'sharechat' => ['label' => 'ShareChat / अन्य', 'type' => 'url', 'rules' => 'nullable|url|max:300', 'width' => 6, 'icon' => 'fa-solid fa-link'],
            'android_app' => ['label' => 'Android ऐप (Play Store)', 'type' => 'url', 'rules' => 'nullable|url|max:300', 'width' => 6, 'icon' => 'fa-brands fa-google-play'],
            'ios_app' => ['label' => 'iOS ऐप (App Store)', 'type' => 'url', 'rules' => 'nullable|url|max:300', 'width' => 6, 'icon' => 'fa-brands fa-app-store-ios'],
        ],
    ],
    'layout' => [
        'label' => 'हेडर और फ़ुटर', 'icon' => 'fa-table-columns', 'permission' => 'edit',
        'fields' => [
            'show_topbar' => ['label' => 'ऊपर की पट्टी दिखाएँ (तारीख़, टॉप मेनू, सोशल)', 'type' => 'switch', 'default' => '1'],
            'show_date' => ['label' => 'आज की तारीख़ दिखाएँ', 'type' => 'switch', 'default' => '1'],
            'sticky_header' => ['label' => 'स्क्रॉल पर मेनू ऊपर चिपका रहे', 'type' => 'switch', 'default' => '1'],
            'show_header_ad' => ['label' => 'लोगो के पास विज्ञापन की जगह', 'type' => 'switch', 'default' => '1'],
            'footer_about' => ['label' => 'फ़ुटर में परिचय', 'type' => 'textarea', 'rules' => 'nullable|max:500'],
            'footer_newsletter' => ['label' => 'फ़ुटर में न्यूज़लेटर फ़ॉर्म', 'type' => 'switch', 'default' => '1'],
            'footer_apps' => ['label' => 'फ़ुटर में ऐप डाउनलोड लिंक', 'type' => 'switch', 'default' => '1'],
            'copyright_text' => ['label' => 'कॉपीराइट पंक्ति', 'type' => 'text', 'rules' => 'nullable|max:200', 'help' => '{year} और {site_name} अपने आप बदलेंगे', 'default' => '© {year} {site_name}। सर्वाधिकार सुरक्षित।'],
        ],
    ],
    'features' => [
        'label' => 'कंटेंट फ़ीचर', 'icon' => 'fa-toggle-on', 'permission' => 'edit',
        'fields' => [
            'breaking_ticker' => ['label' => 'ब्रेकिंग न्यूज़ पट्टी', 'type' => 'switch', 'default' => '1'],
            'trending_bar' => ['label' => 'ट्रेंडिंग पट्टी (फ़ीचर्ड टॉपिक + ट्रेंडिंग टैग)', 'type' => 'switch', 'default' => '1'],
            'ticker_speed' => ['label' => 'पट्टी की रफ़्तार', 'type' => 'select', 'options' => ['slow' => 'धीमी', 'medium' => 'सामान्य', 'fast' => 'तेज़'], 'default' => 'medium', 'width' => 6],
            'breaking_expiry_hours' => ['label' => 'ब्रेकिंग अपने आप कितने घंटे में हटे', 'type' => 'number', 'default' => '6', 'rules' => 'required|integer|min:0|max:720', 'width' => 6, 'help' => 'कंट्रोल रूम में नए आइटम का डिफ़ॉल्ट; 0 = हाथ से हटाने तक'],
            'live_blog_refresh' => ['label' => 'लाइव ब्लॉग: नए अपडेट की जाँच (सेकंड)', 'type' => 'number', 'default' => '30', 'rules' => 'required|integer|min:15|max:300', 'width' => 6],
            'live_show_author' => ['label' => 'लाइव अपडेट पर लिखने वाले का नाम', 'type' => 'switch', 'default' => '1', 'width' => 6],
            'comments_enabled' => ['label' => 'पाठकों की टिप्पणियाँ', 'type' => 'switch', 'default' => '1'],
            'comments_moderation' => ['label' => 'टिप्पणी मंज़ूरी के बाद दिखे', 'type' => 'switch', 'default' => '1'],
            'share_buttons' => ['label' => 'शेयर बटन', 'type' => 'checkboxes', 'options' => ['whatsapp' => 'WhatsApp', 'facebook' => 'Facebook', 'x' => 'X', 'telegram' => 'Telegram', 'linkedin' => 'LinkedIn', 'copy' => 'लिंक कॉपी', 'native' => 'मोबाइल शेयर'], 'default' => 'whatsapp,facebook,x,telegram,copy,native'],
            'author_box' => ['label' => 'ख़बर के नीचे लेखक बॉक्स', 'type' => 'switch', 'default' => '1'],
            'related_news' => ['label' => 'संबंधित ख़बरें', 'type' => 'switch', 'default' => '1'],
            'reading_time' => ['label' => 'पढ़ने का समय दिखाएँ', 'type' => 'switch', 'default' => '1'],
            'show_views' => ['label' => 'व्यूज़ की गिनती दिखाएँ', 'type' => 'switch', 'default' => '0'],
            'posts_per_page' => ['label' => 'एक पेज पर ख़बरें', 'type' => 'number', 'default' => '12', 'rules' => 'required|integer|min:5|max:60', 'width' => 6],
        ],
    ],
    'media' => [
        'label' => 'लाइव टीवी और ई-पेपर', 'icon' => 'fa-tv', 'permission' => 'edit',
        'fields' => [
            'live_tv_url' => ['label' => 'लाइव टीवी (YouTube लाइव लिंक)', 'type' => 'url', 'rules' => 'nullable|url|max:500', 'help' => 'सिर्फ़ तब इस्तेमाल होता है जब "लाइव टीवी" मॉड्यूल में कोई चालू चैनल न हो'],
            'epaper_enabled' => ['label' => 'ई-पेपर दिखाएँ', 'type' => 'switch', 'default' => '1'],
        ],
    ],
    'images' => [
        'label' => 'इमेज और वॉटरमार्क', 'icon' => 'fa-image', 'permission' => 'edit',
        'fields' => [
            'media_large_width' => ['label' => 'बड़ी इमेज की चौड़ाई (px)', 'type' => 'number', 'default' => '1600', 'rules' => 'required|integer|min:800|max:4000', 'width' => 4, 'help' => 'लेख में दिखने वाली सबसे बड़ी इमेज'],
            'media_quality' => ['label' => 'इमेज क्वालिटी (50-95)', 'type' => 'number', 'default' => '82', 'rules' => 'required|integer|min:50|max:95', 'width' => 4, 'help' => 'कम = हल्की फ़ाइल, तेज़ वेबसाइट'],
            'media_webp' => ['label' => 'WebP भी बनाएँ (तेज़ लोडिंग; सर्वर पर उपलब्ध हो तो)', 'type' => 'switch', 'default' => '1'],
            'watermark_enabled' => ['label' => 'नई इमेज पर वॉटरमार्क लगाएँ (मूल फ़ाइल पर नहीं, सिर्फ़ बड़ी/मध्यम कॉपी पर)', 'type' => 'switch', 'default' => '0'],
            'watermark_type' => ['label' => 'वॉटरमार्क का प्रकार', 'type' => 'select', 'options' => ['image' => 'लोगो (PNG)', 'text' => 'टेक्स्ट'], 'default' => 'image', 'rules' => 'required|in:image,text', 'width' => 4],
            'watermark_position' => ['label' => 'जगह', 'type' => 'select', 'options' => ['bottom-right' => 'नीचे दाएँ', 'bottom-left' => 'नीचे बाएँ', 'top-right' => 'ऊपर दाएँ', 'top-left' => 'ऊपर बाएँ', 'center' => 'बीच में'], 'default' => 'bottom-right', 'rules' => 'required|in:bottom-right,bottom-left,top-right,top-left,center', 'width' => 4],
            'watermark_opacity' => ['label' => 'दिखने की तीव्रता (10-100%)', 'type' => 'number', 'default' => '60', 'rules' => 'required|integer|min:10|max:100', 'width' => 4],
            'watermark_image' => ['label' => 'वॉटरमार्क लोगो', 'type' => 'image', 'help' => 'पारदर्शी PNG सबसे अच्छा', 'width' => 6],
            'watermark_text' => ['label' => 'वॉटरमार्क टेक्स्ट', 'type' => 'text', 'rules' => 'nullable|max:60|regex:/^[\x20-\x7E]*$/', 'placeholder' => 'www.example.com', 'help' => 'सिर्फ़ अंग्रेज़ी अक्षर/अंक (हिंदी के लिए लोगो वाला वॉटरमार्क चुनें)', 'width' => 6],
        ],
    ],
    'reporters' => [
        'label' => 'रिपोर्टर और ID कार्ड', 'icon' => 'fa-id-card', 'permission' => 'edit',
        'fields' => [
            'join_enabled' => ['label' => '“रिपोर्टर बनें” फ़ॉर्म चालू (/join-as-reporter)', 'type' => 'switch', 'default' => '1'],
            'join_intro' => ['label' => 'फ़ॉर्म के ऊपर परिचय', 'type' => 'textarea', 'rules' => 'nullable|max:1000', 'default' => 'हमारे साथ जुड़कर अपने क्षेत्र की ख़बरें दुनिया तक पहुँचाएँ। सभी खाने सही भरें और दस्तावेज़ साफ़ अपलोड करें।'],
            'join_declaration' => ['label' => 'घोषणा (आवेदक को स्वीकार करनी होगी)', 'type' => 'textarea', 'rules' => 'nullable|max:2000', 'default' => 'मैं घोषणा करता/करती हूँ कि दी गई सभी जानकारी और दस्तावेज़ सही हैं। मैं संस्थान की संपादकीय नीति, आचार संहिता और रिपोर्टर नीति का पालन करूँगा/करूँगी। ग़लत जानकारी पाए जाने पर आवेदन या नियुक्ति रद्द की जा सकती है।'],
            'reporter_id_prefix' => ['label' => 'रिपोर्टर ID का शुरुआती हिस्सा', 'type' => 'text', 'default' => 'RPT', 'rules' => 'required|max:8|regex:/^[A-Z]{2,8}$/', 'width' => 4, 'help' => 'जैसे RPT → RPT-2026-0001'],
            'reporter_validity_months' => ['label' => 'ID कार्ड की वैधता (महीने)', 'type' => 'number', 'default' => '12', 'rules' => 'required|integer|min:1|max:60', 'width' => 4],
            'signatory_name' => ['label' => 'हस्ताक्षरकर्ता का नाम', 'type' => 'text', 'rules' => 'nullable|max:100', 'width' => 6],
            'signatory_designation' => ['label' => 'हस्ताक्षरकर्ता का पद', 'type' => 'text', 'default' => 'प्रधान संपादक', 'rules' => 'nullable|max:100', 'width' => 6],
            'signature_image' => ['label' => 'हस्ताक्षर (पारदर्शी PNG)', 'type' => 'image', 'width' => 6],
            'stamp_image' => ['label' => 'मुहर (पारदर्शी PNG)', 'type' => 'image', 'width' => 6],
            'registration_no' => ['label' => 'पंजीकरण संख्या (पत्रों में)', 'type' => 'text', 'rules' => 'nullable|max:100', 'width' => 6],
            'id_card_note' => ['label' => 'ID कार्ड के पीछे का नोट', 'type' => 'textarea', 'rules' => 'nullable|max:300', 'default' => 'यह कार्ड संस्थान की संपत्ति है। मिलने पर ऊपर दिए पते पर लौटाएँ। सत्यापन के लिए QR स्कैन करें।'],
        ],
    ],
    'analytics' => [
        'label' => 'एनालिटिक्स और कोड', 'icon' => 'fa-code', 'permission' => 'manage',
        'fields' => [
            'ga_id' => ['label' => 'Google Analytics 4 ID', 'type' => 'text', 'rules' => 'nullable|regex:/^G-[A-Z0-9]{4,20}$/', 'placeholder' => 'G-XXXXXXXXXX', 'width' => 6],
            'gtm_id' => ['label' => 'Google Tag Manager ID', 'type' => 'text', 'rules' => 'nullable|regex:/^GTM-[A-Z0-9]{4,12}$/', 'placeholder' => 'GTM-XXXXXXX', 'width' => 6],
            'search_console' => ['label' => 'Google Search Console verification', 'type' => 'text', 'rules' => 'nullable|max:120', 'help' => 'सिर्फ़ content="…" वाला मान'],
            'header_code' => ['label' => '<head> में अतिरिक्त कोड', 'type' => 'code', 'help' => 'सिर्फ़ भरोसेमंद कोड (जैसे AdSense)। हर बदलाव ऑडिट लॉग में दर्ज होता है।'],
            'footer_code' => ['label' => '</body> से पहले अतिरिक्त कोड', 'type' => 'code'],
        ],
    ],
    'mail' => [
        'label' => 'ईमेल', 'icon' => 'fa-envelope', 'permission' => 'manage',
        'fields' => [
            'mail_from_name' => ['label' => 'भेजने वाले का नाम', 'type' => 'text', 'rules' => 'nullable|max:100', 'width' => 6],
            'mail_from_email' => ['label' => 'भेजने वाला ईमेल', 'type' => 'email', 'rules' => 'nullable|email|max:190', 'width' => 6, 'help' => 'अपने डोमेन का ईमेल रखें (जैसे no-reply@yoursite.com)'],
        ],
    ],
    'security' => [
        'label' => 'सुरक्षा', 'icon' => 'fa-shield-halved', 'permission' => 'manage',
        'fields' => [
            'session_timeout' => ['label' => 'निष्क्रियता पर लॉगआउट (मिनट)', 'type' => 'number', 'default' => '120', 'rules' => 'required|integer|min:5|max:1440', 'width' => 4],
            'login_max_attempts' => ['label' => 'ग़लत लॉगिन प्रयास की सीमा', 'type' => 'number', 'default' => '5', 'rules' => 'required|integer|min:3|max:20', 'width' => 4],
            'login_lockout_minutes' => ['label' => 'रोक का समय (मिनट)', 'type' => 'number', 'default' => '15', 'rules' => 'required|integer|min:1|max:1440', 'width' => 4],
        ],
    ],
    'maintenance' => [
        'label' => 'मेंटेनेंस', 'icon' => 'fa-screwdriver-wrench', 'permission' => 'manage',
        'fields' => [
            'maintenance_mode' => ['label' => 'मेंटेनेंस मोड चालू करें (वेबसाइट बंद, एडमिन चालू)', 'type' => 'switch', 'default' => '0'],
            'maintenance_message' => ['label' => 'पाठकों के लिए संदेश', 'type' => 'textarea', 'rules' => 'nullable|max:300'],
        ],
    ],
];
