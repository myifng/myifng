# Phase 2: डैशबोर्ड, सेटिंग, ब्रांडिंग, पेज CMS, मेनू बिल्डर, होमपेज बिल्डर

## 1. लक्ष्य

- **डैशबोर्ड (§6)**: विजेट आधारित। हर विजेट किसी मॉड्यूल से जुड़ा है। मॉड्यूल तैयार होने और यूज़र के पास अनुमति होने पर ही विजेट दिखता है। आगे के phase (ख़बरें, रिपोर्टर, ई-पेपर, विज्ञापन) अपने विजेट यहीं जोड़ेंगे, डैशबोर्ड बदले बिना। साथ में "सेटअप चेकलिस्ट" (लोगो, SSL, /install हटाया या नहीं, DEBUG बंद…)।
- **सेटिंग और ब्रांडिंग (§20, §21, §72)**: एक schema (`config/settings.php`) से सारे टैब बनते हैं: सामान्य, ब्रांडिंग, संपर्क, सोशल, हेडर-फ़ुटर, कंटेंट फ़ीचर, एनालिटिक्स कोड, ईमेल, सुरक्षा, मेंटेनेंस। नया सेटिंग जोड़ना = schema में एक पंक्ति। डेवलपर के बिना सब बदला जा सके।
- **पेज CMS (§17)**: असीमित पेज; टेम्पलेट; SEO; ड्राफ़्ट/प्रकाशित; हेडर/फ़ुटर दिखे या नहीं; ट्रैश/रीस्टोर/स्थायी रूप से हटाना; कॉपी; बल्क एक्शन। 12 डिफ़ॉल्ट पेज (About, Contact, Disclaimer, Terms, Privacy, Editorial, Correction, Ethics, Grievance, Reporter Policy, Advertise, Careers)। कंटेंट में `[site_name]` जैसे shortcode, ताकि white-label रहे।
- **रिच एडिटर (§18)**: हेडिंग, बोल्ड, इटैलिक, सूची, उद्धरण, टेबल, इमेज अपलोड, वीडियो एम्बेड (YouTube), लिंक, बटन, कस्टम ब्लॉक (सूचना बॉक्स), HTML मोड। सेव करते समय सर्वर पर sanitize।
- **मेनू बिल्डर (§19)**: टॉप, मुख्य, मोबाइल, 4 फ़ुटर कॉलम, लीगल, क्विक लिंक। आइटम प्रकार: होम, पेज, श्रेणी*, लोकेशन*, टॉपिक*, ई-पेपर, लाइव टीवी, कस्टम URL, बाहरी URL। नेस्टेड (3 स्तर), ड्रैग से क्रम, कीबोर्ड से भी (ऊपर/नीचे/अंदर/बाहर)। (*Phase 3 में टेबल बनने पर अपने आप चालू)
- **होमपेज बिल्डर (§14)**: सेक्शन जोड़ना/हटाना/चालू-बंद/क्रम/कॉपी; हर ब्लॉक का लेआउट, हेडिंग, ख़बरों की संख्या, स्रोत (ऑटो/मैनुअल), डेस्कटॉप/मोबाइल पर दिखना। 17 ब्लॉक प्रकार। होमपेज का रेंडर Phase 5 में इसी कॉन्फ़िगरेशन से होगा; अभी एडमिन में ढाँचे (wireframe) का प्रीव्यू।
- **वेबसाइट लेआउट (बुनियादी)**: पेज दिखाने के लिए हेडर/फ़ुटर जो मेनू बिल्डर और सेटिंग से बनते हैं। Phase 5 में इसे पूरा होमपेज मिलेगा।
- **सिस्टम अपडेट**: नई migration हो तो Super Admin को डैशबोर्ड पर बटन, एक क्लिक से अपडेट (पहले से इंस्टॉल साइट के लिए)।

## 2. डेटाबेस बदलाव (migration `000002`)

| टेबल | मुख्य कॉलम |
|---|---|
| `pages` | title, slug (unique), excerpt, content, featured_image, template, status (draft/published), show_header, show_footer, meta_title, meta_description, meta_keywords, og_image, robots, language_id, published_at, created_by, updated_by, timestamps, deleted_at |
| `menus` | name, location (unique), description, timestamps |
| `menu_items` | menu_id (FK), parent_id, title, type, reference_id, url, target_blank, icon, is_active, sort_order |
| `home_sections` | block_type, title, settings (JSON), show_desktop, show_mobile, is_active, sort_order, created_by, timestamps |
| `settings` | नई डिफ़ॉल्ट पंक्तियाँ (सोशल, हेडर-फ़ुटर, फ़ीचर, एनालिटिक्स…) |

migration ख़ुद डिफ़ॉल्ट डेटा (12 पेज, 9 मेनू, होमपेज सेक्शन) डालती है। दोबारा चलने पर दोहराव नहीं।

## 3. रूट

| Method | पता | काम | अनुमति |
|---|---|---|---|
| GET | /admin/settings/{tab?} | सेटिंग टैब | settings.view |
| POST | /admin/settings/{tab} | सेव | settings.edit (संवेदनशील टैब: settings.manage) |
| GET/POST | /admin/pages … | सूची, बनाना, बदलना, कॉपी, प्रीव्यू | pages.view/create/edit |
| POST | /admin/pages/{id}/trash, restore, DELETE force | ट्रैश, रीस्टोर, स्थायी | pages.delete |
| POST | /admin/pages/bulk | बल्क | pages.edit / pages.delete / pages.publish |
| GET | /admin/menus, /admin/menus/{id} | मेनू सूची, बिल्डर | menus.view |
| POST | /admin/menus/{id} | पूरा ढाँचा सेव (JSON) | menus.edit |
| GET | /admin/homepage | होमपेज बिल्डर | homepage.view |
| POST/PUT/DELETE | /admin/homepage/sections… | सेक्शन जोड़ें/बदलें/हटाएँ/कॉपी/चालू-बंद | homepage.edit |
| POST | /admin/homepage/reorder | क्रम (AJAX) | homepage.edit |
| POST | /admin/editor/upload | एडिटर में इमेज (AJAX) | pages.create या pages.edit |
| POST | /admin/system/migrate | अपडेट चलाएँ | सिर्फ़ Super Admin |
| GET | /page/{slug} | सार्वजनिक पेज | सबके लिए (ड्राफ़्ट सिर्फ़ स्टाफ़ को) |

## 4. कोड

- **Controllers**: `Admin\SettingsController`, `Admin\PageController`, `Admin\MenuController`, `Admin\HomepageController`, `Admin\EditorController`, `Admin\SystemController`, `Front\PageController`
- **Models**: `Page`, `Menu`, `MenuItem`, `HomeSection`
- **Services**: `SettingsSchema`, `MenuService` (ट्री, URL, कैश), `HomepageService` (ब्लॉक रजिस्ट्री), `ContentRenderer` (shortcode + एम्बेड), `DashboardService` (विजेट), `HtmlSanitizer` (बढ़ाया गया)
- **Repositories**: `PageRepository`
- **Validators**: `PageValidator`
- **Config**: `config/settings.php`, `config/home_blocks.php`, `config/menus.php`, `config/dashboard.php`

## 5. स्क्रीन

डैशबोर्ड (विजेट + सेटअप चेकलिस्ट) · सेटिंग (10 टैब) · पेज सूची (टैब: सभी/प्रकाशित/ड्राफ़्ट/ट्रैश) · पेज एडिटर (रिच एडिटर + SEO + प्रकाशन) · मेनू सूची · मेनू बिल्डर (ड्रैग-ड्रॉप ट्री) · होमपेज बिल्डर (ब्लॉक पैलेट + सेक्शन सूची + सेटिंग दराज़ + wireframe) · सार्वजनिक पेज (हेडर/फ़ुटर के साथ)

## 6. अनुमतियाँ

- `settings.view` देखें · `settings.edit` सामान्य टैब · `settings.manage` संवेदनशील टैब (सुरक्षा, ईमेल, मेंटेनेंस, हेडर/फ़ुटर कोड)
- `pages.view/create/edit/delete/publish`: बिना `publish` के यूज़र पेज सिर्फ़ ड्राफ़्ट में रख सकता है
- `menus.view/create/edit/delete`
- `homepage.view/edit` · `homepage.manage` कस्टम HTML ब्लॉक के लिए
- सिस्टम अपडेट: सिर्फ़ Super Admin

## 7. सुरक्षा

| जोखिम | बचाव |
|---|---|
| पेज कंटेंट में XSS | सर्वर पर allowlist sanitizer; YouTube एम्बेड iframe की जगह `data-youtube` से सेव, दिखाते समय सुरक्षित iframe |
| मेनू में `javascript:` लिंक | URL सिर्फ़ http(s), `/`, `#`, `mailto:`, `tel:` |
| हेडर/फ़ुटर में स्क्रिप्ट (Analytics) | सिर्फ़ `settings.manage` वाले; हर बदलाव ऑडिट लॉग में |
| कस्टम HTML ब्लॉक | सिर्फ़ `homepage.manage`; ऑडिट |
| सेटिंग में मनमाने key | सिर्फ़ schema में लिखे key सेव होते हैं (mass assignment बचाव) |
| फ़ाइल अपलोड | Phase 1 का UploadService (MIME, आकार, रैंडम नाम) |
| AJAX | CSRF हेडर; JSON जवाब; अनुमति जाँच |
| ड्राफ़्ट पेज | सार्वजनिक रूप से 404; स्टाफ़ को प्रीव्यू |
| कैश | सेटिंग/मेनू/होमपेज बदलते ही कैश साफ़ |

## 8. टेस्ट चेकलिस्ट (सब पास)

- [x] PHP syntax
- [x] migration (नया इंस्टॉल + मौजूदा इंस्टॉल पर अपडेट)
- [x] CRUD: पेज, मेनू, होमपेज सेक्शन, सेटिंग
- [x] RBAC: अलग-अलग रोल
- [x] CSRF (फ़ॉर्म + AJAX)
- [x] वैलिडेशन
- [x] XSS: पेज कंटेंट और मेनू URL
- [x] डेस्कटॉप + मोबाइल UI
- [x] त्रुटि पेज

## 9. टेस्ट के नतीजे

| जाँच | नतीजा |
|---|---|
| मौजूदा इंस्टॉल पर अपडेट | अपडेट बाकी रहते एडमिन/वेबसाइट 503; Super Admin को "अभी अपडेट करें" पेज; एक क्लिक में migration + ऑडिट |
| सेटिंग | 12 टैब खुले; ग़लत ईमेल/URL/GA ID/सीमा से बाहर संख्या पर हिंदी संदेश; दूसरे टैब का key या अनजान key सेव नहीं हुआ (mass assignment बचाव); मेंटेनेंस मोड में पाठकों को 503, स्टाफ़ को साइट |
| पेज | हिंदी शीर्षक से अंग्रेज़ी slug, दोहराव पर `-2`; script/onmouseover/javascript:/iframe हटे; YouTube एम्बेड और shortcode सही; ड्राफ़्ट पाठकों को 404, प्रीव्यू noindex; ट्रैश/रीस्टोर/कॉपी/बल्क; ट्रैश में न हो तो स्थायी delete नहीं |
| मेनू | ग़लत प्रकार, `javascript:` और `//` वाले URL, न मिलने वाला पेज: हर आइटम का अलग संदेश; CSRF के बिना 419; 3 स्तर तक नेस्टेड; बंद और अप्रकाशित लिंक वेबसाइट पर छिपे |
| होमपेज | ग़लत ब्लॉक 422; संख्या/कॉलम सीमा में; अनजान सेटिंग हटी; ज़रूरी श्रेणी का संदेश; क्रम (AJAX) और ग़लत क्रम पर मना |
| RBAC | Editor (बिना अनुमति) को Phase 2 के सभी पेज 403; बिना `pages.publish` पेज ड्राफ़्ट ही रहा; बिना `homepage.manage` कस्टम HTML न दिखा, न जुड़ा |
| UI | डेस्कटॉप + मोबाइल स्क्रीनशॉट; ब्राउज़र में कोई JavaScript त्रुटि नहीं |
