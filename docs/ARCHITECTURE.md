# न्यूज़ प्लेटफ़ॉर्म: आर्किटेक्चर और Phase 1 प्लान

यह दस्तावेज़ पूरे प्रोजेक्ट की नींव बताता है। हर नया phase इसी ढाँचे में जुड़ेगा।

---

## 1. अंतिम आर्किटेक्चर (Final Architecture)

- **Core PHP 8.1+ पर अपना MVC**, कोई framework नहीं, Composer ज़रूरी नहीं।
- **Front Controller**: हर अनुरोध `index.php` पर आता है। वहाँ से `App` → `Router` → Middleware → Controller → View।
- **Shared hosting पहले**: प्रोजेक्ट की root ही document root (public_html) हो सकती है। `.htaccess` संवेदनशील फ़ोल्डर (`app`, `config`, `storage`, `database`, `routes`, `modules`) को बाहर से बंद रखता है। स्टैटिक फ़ाइलें `public/` से सीधे परोसी जाती हैं।
- **परतें (layers)**:

| परत | काम |
|---|---|
| `Controllers` | अनुरोध लेना, वैलिडेशन बुलाना, सर्विस बुलाना, व्यू लौटाना। पतले रखे जाते हैं। |
| `Services` | बिज़नेस लॉजिक (लॉगिन, अनुमति, ऑडिट, सेटिंग)। |
| `Repositories` | जटिल क्वेरी, फ़िल्टर, पेजिनेशन। |
| `Models` | एक टेबल का साधारण डेटा एक्सेस (find, create, update, soft delete)। |
| `Validators` | फ़ॉर्म के नियम, दोबारा इस्तेमाल होने वाले। |
| `Views` | सिर्फ़ HTML, कोई SQL या बिज़नेस लॉजिक नहीं। |
| `Middleware` | लॉगिन, CSRF, अनुमति, मेंटेनेंस जाँच। |
| `Core` | Router, Request, Response, View, Database, Session, Auth, Gate, Validator, Cache, Logger, ErrorHandler, Migrator। |

- **White-label**: ब्रांड का नाम, लोगो, रंग, संपर्क, सब `settings` टेबल से आते हैं। कोड में कोई ब्रांड नाम हार्डकोड नहीं।
- **Module Registry** (`config/modules.php`): हर मॉड्यूल (अभी का और आगे का) एक जगह दर्ज है: नाम, आइकन, मेनू, अनुमतियाँ (actions), phase। अनुमति मैट्रिक्स, साइडबार और RBAC यहीं से बनते हैं। नया मॉड्यूल जोड़ना = registry में एक एंट्री + migration + controller।

## 2. पूरा मॉड्यूल मैप (Module Map)

| समूह | मॉड्यूल | Phase |
|---|---|---|
| सिस्टम | इंस्टॉलर, लॉगिन, यूज़र, रोल/अनुमति, ऑडिट लॉग | 1 |
| सिस्टम | डैशबोर्ड, सेटिंग/ब्रांडिंग, पेज CMS, मेनू बिल्डर, होमपेज बिल्डर | 2 |
| कंटेंट की नींव | श्रेणी, उप-श्रेणी, टॉपिक, टैग, लोकेशन (देश→मोहल्ला), मीडिया लाइब्रेरी | 3 |
| न्यूज़रूम | न्यूज़ CMS, एडिटर, वर्कफ़्लो, असाइनमेंट डेस्क, वर्ज़न हिस्ट्री | 4 |
| वेबसाइट | होमपेज, श्रेणी/लोकेशन/ख़बर पेज, खोज, मोबाइल UI | 5 |
| रिपोर्टर नेटवर्क | भर्ती, आवेदन, रिपोर्टर पोर्टल, सत्यापन, ID कार्ड, पत्र, ब्यूरो | 6 |
| मल्टीमीडिया | ब्रेकिंग कंट्रोल रूम, लाइव ब्लॉग, लाइव टीवी, वीडियो, गैलरी, वेब स्टोरी, ऑडियो | 7 |
| ई-पेपर | संस्करण, पेज, रीडर | 8 |
| राजस्व | विज्ञापन, विज्ञापनदाता CRM | 9 |
| SEO | SEO कमांड सेंटर, साइटमैप, स्कीमा, रीडायरेक्ट, Google News | 10 |
| पाठक | टिप्पणी, पोल, न्यूज़लेटर, पाठक खाता, नोटिफ़िकेशन | 11 |
| फ़ॉर्म | फ़ॉर्म बिल्डर, न्यूज़ टिप, शिकायत/ग्रीवांस, करियर | 12 |
| एनालिटिक्स | एनालिटिक्स, ट्रेंडिंग, रिपोर्टर परफ़ॉर्मेंस, न्यूज़रूम रिपोर्ट | 13 |
| विशेष | फ़ैक्ट चेक, चुनाव केंद्र, खेल केंद्र, उन्नत लोकल न्यूज़ | 14 |
| सुरक्षा/ऑप्स | सुरक्षा सख़्ती, ऑडिट, बैकअप, कैश, परफ़ॉर्मेंस | 15 |
| मोबाइल | PWA, मोबाइल, सुलभता, UI पॉलिश | 16 |
| रिलीज़ | प्रोडक्शन इंस्टॉलर, डेमो डेटा, QA, ZIP | 17 |
| HR | कर्मचारी, विभाग, हाज़िरी, छुट्टी, वेतन रिकॉर्ड | 6/12 के साथ |

## 3. डेटाबेस रणनीति (Database Strategy)

- MySQL 5.7+ / MariaDB 10.3+, `InnoDB`, `utf8mb4_unicode_ci` (हिंदी और इमोजी के लिए)।
- **टेबल प्रीफ़िक्स** (इंस्टॉल के समय चुनें): एक डेटाबेस में कई साइट।
- **Migrations**: `database/migrations/` में क्रम से PHP फ़ाइलें। `migrations` टेबल बताती है कौन-सी चल चुकी है। इंस्टॉलर और भविष्य के अपडेट दोनों यही इस्तेमाल करते हैं। हर phase अपनी migration जोड़ता है, पुरानी कभी नहीं बदली जाती।
- **Seeds**: `database/seeds/`: ज़रूरी डेटा (रोल, अनुमतियाँ, भाषाएँ, सेटिंग) और वैकल्पिक डेमो डेटा।
- नियम: हर टेबल में `created_at`/`updated_at`; कंटेंट वाली टेबल में `deleted_at` (soft delete); जहाँ व्यावहारिक हो foreign key; खोज/फ़िल्टर वाले कॉलम पर index; स्लग/ईमेल पर unique।
- **बहुभाषी**: `languages` टेबल अभी से। आगे ख़बरें `language_id` + `translation_group_id` से जुड़ेंगी, एक फ़ील्ड में दो भाषाएँ कभी नहीं।

### Phase 1 की टेबल

`migrations`, `roles`, `permissions`, `role_permissions`, `users`, `user_permissions`, `password_resets`, `login_attempts`, `login_history`, `settings`, `audit_logs`, `languages`, `modules`

### आगे की टेबल (अपने phase में)

reporters, employees, departments, designations, reporter_applications, reporter_documents, bureaus, news, news_versions, news_remarks, categories, tags, topics, locations (एक टेबल, `type` + `parent_id` से देश→मोहल्ला), assignments, breaking_news, live_blogs, live_updates, videos, galleries, gallery_images, web_stories, web_story_slides, audio, epapers, epaper_editions, epaper_pages, ads, advertisers, campaigns, invoices, pages, menus, menu_items, home_sections, forms, form_fields, form_submissions, comments, polls, poll_options, poll_votes, subscribers, newsletter_campaigns, notifications, media, media_folders, seo_meta, redirects, not_found_log, page_views, complaints, contacts, jobs, job_applications, fact_checks, elections…, sports…, backups।

## 4. डायरेक्टरी संरचना (Directory Structure)

```
/index.php               ← Front Controller (सब अनुरोध यहीं)
/.htaccess               ← URL rewrite + संवेदनशील फ़ोल्डर बंद
/app
  /Core                  ← App, Router, Request, Response, View, Database, Model, Session,
                           Auth, Gate, Validator, Csrf, Cache, Logger, ErrorHandler, Migrator, Mailer
  /Controllers/Admin     ← एडमिन पैनल
  /Controllers/Front     ← वेबसाइट
  /Middleware
  /Models
  /Services
  /Repositories
  /Validators
  /Helpers               ← helpers.php (e, url, route, asset, csrf_field…), Str (slug)
  /Views
    /layouts             ← admin, auth, front, error
    /partials
    /admin, /auth, /front, /errors
/config                  ← app.php, database.php, modules.php, env.php (इंस्टॉलर बनाता है)
/routes                  ← web.php, admin.php
/public
  /assets/css, /js, /img, /vendor (Bootstrap 5, Font Awesome, Chart.js: लोकल)
  /uploads               ← PHP चलना बंद
/storage                 ← cache, logs, sessions, private, backups (बाहर से बंद)
/database/migrations, /seeds
/install                 ← इंस्टॉल विज़ार्ड
/modules                 ← भविष्य के अलग प्लगइन मॉड्यूल
/docs
```

## 5. ऑथेंटिकेशन (Authentication)

- ईमेल + पासवर्ड। `password_hash()` (bcrypt/argon), कभी सादा पासवर्ड नहीं।
- सेशन: `storage/sessions` में, `HttpOnly`, `SameSite=Lax`, HTTPS पर `Secure`। लॉगिन पर `session_regenerate_id()`। निष्क्रियता पर टाइमआउट। ब्राउज़र फ़िंगरप्रिंट जाँच।
- लॉगिन रेट लिमिट: एक IP + ईमेल पर 15 मिनट में 5 ग़लत प्रयास → रोक।
- हर लॉगिन `login_history` में (IP, डिवाइस, सफल/असफल)।
- पासवर्ड भूलें: एक बार का टोकन (डेटाबेस में सिर्फ़ hash), 60 मिनट वैध, ईमेल से। मेल न जाए तो `storage/logs/mail.log` में।
- खाता स्थिति: active / inactive / suspended। बंद खाते से लॉगिन नहीं।

## 6. RBAC (रोल और अनुमति)

- **अनुमति = मॉड्यूल + action**, जैसे `news.publish`, `users.edit`। Actions: `view, create, edit, delete, approve, publish, export, manage`।
- सभी मॉड्यूल की अनुमतियाँ `config/modules.php` से `permissions` टेबल में sync होती हैं।
- **रोल**: Super Admin, Admin, Editor, Reporter, Employee (system रोल, हटाए नहीं जा सकते) + एडमिन के बनाए कस्टम रोल।
- `role_permissions`: रोल को मिली अनुमतियाँ (मैट्रिक्स स्क्रीन से)।
- `user_permissions`: किसी एक यूज़र को अलग से अनुमति देना या छीनना (जैसे किसी रिपोर्टर को Direct Publish)।
- **Super Admin** हर जाँच पार करता है। आख़िरी Super Admin न हटाया जा सकता है, न बंद।
- कोड में: `can('news.publish')`, रूट पर `can:users.edit` middleware, व्यू में `@can` जैसा `<?php if (can(...)): ?>`।

## 7. रूटिंग (Routing)

- `routes/web.php` (वेबसाइट) और `routes/admin.php` (एडमिन, प्रीफ़िक्स `/admin`, config से बदल सकते हैं)।
- `$router->get('/users/{id:\d+}/edit', [UserController::class, 'edit'])->name('admin.users.edit')->middleware('can:users.edit')`।
- ग्रुप: प्रीफ़िक्स + middleware। नामित रूट से URL: `route('admin.users.edit', ['id' => 5])`, हार्डकोड URL नहीं।
- सब-फ़ोल्डर इंस्टॉल (`example.com/news/`) अपने आप काम करता है।
- फ़ॉर्म में `_method` से PUT/DELETE।

## 8. इंस्टॉलर (Installer)

`/install/`, 7 कदम:

1. **स्वागत + सर्वर जाँच**: PHP, PDO, PDO MySQL, cURL, OpenSSL, JSON, GD, FileInfo, Mbstring, ZIP, लिखने योग्य फ़ोल्डर, अपलोड सीमा। हर एक PASS / WARNING / ERROR।
2. **डेटाबेस**: होस्ट, पोर्ट, नाम, यूज़र, पासवर्ड, प्रीफ़िक्स → कनेक्शन टेस्ट।
3. **टेबल इंस्टॉल**: सभी migrations + ज़रूरी seeds, हर एक का नतीजा दिखे।
4. **Super Admin**: नाम, ईमेल, मोबाइल, पासवर्ड।
5. **वेबसाइट**: नाम, टैगलाइन, URL, लोगो अपलोड, मुख्य/दूसरा रंग, टाइमज़ोन, भाषा।
6. **डेमो डेटा** (वैकल्पिक)।
7. **पूरा**: `config/env.php` लिखी जाती है, `storage/installed.lock` बनता है, इंस्टॉलर बंद।

इंस्टॉल के बाद `/install` खोलने पर सिर्फ़ "पहले से इंस्टॉल है" दिखता है।

## 9. सुरक्षा की नींव (Security Foundation)

| ख़तरा | बचाव |
|---|---|
| SQL Injection | हर क्वेरी PDO prepared statement से |
| XSS | व्यू में हर आउटपुट `e()` से escape; रिच HTML सिर्फ़ allowlist sanitizer से |
| CSRF | हर POST फ़ॉर्म में टोकन, `CsrfMiddleware` जाँचता है, ग़लत पर 419 पेज |
| सेशन चोरी | HttpOnly/SameSite कुकी, लॉगिन पर ID बदलना, टाइमआउट, फ़िंगरप्रिंट |
| Brute force | लॉगिन रेट लिमिट + लॉग |
| फ़ाइल अपलोड | MIME जाँच (finfo), extension allowlist, रैंडम नाम, uploads में PHP बंद |
| संवेदनशील फ़ाइलें | `.htaccess` से app/config/storage बंद, हर PHP फ़ाइल में सीधे खुलने से रोक |
| त्रुटि दिखना | प्रोडक्शन में stack trace कभी नहीं; लॉग फ़ाइल में; साफ़ 500 पेज |
| Clickjacking आदि | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` हेडर |
| जवाबदेही | हर अहम बदलाव `audit_logs` में (कौन, क्या, कब, IP, डिवाइस, पुराना/नया मान) |

## 10. Phase 1 चेकलिस्ट

- [x] डायरेक्टरी संरचना, `.htaccess`, Front Controller
- [x] Core: App, Router, Request, Response, View, Database, Model, Session, Csrf, Validator, Auth, Gate, Cache, Logger, ErrorHandler, Migrator, Mailer
- [x] Helpers: `e()`, `url()`, `route()`, `asset()`, `csrf_field()`, `old()`, `can()`, `setting()`, हिंदी → अंग्रेज़ी slug
- [x] Config + Module Registry
- [x] Migrations: Phase 1 की सभी टेबल
- [x] Seeds: रोल, अनुमतियाँ, भाषाएँ, सेटिंग, डेमो यूज़र
- [x] इंस्टॉलर: 7 कदम, लॉक
- [x] लॉगिन, लॉगआउट, पासवर्ड भूलें/रीसेट, रेट लिमिट, लॉगिन हिस्ट्री
- [x] RBAC: रोल CRUD + अनुमति मैट्रिक्स, यूज़र CRUD, यूज़र-स्तर अनुमति
- [x] Base Admin UI: साइडबार, टॉपबार, सर्च, क्विक एक्शन, प्रोफ़ाइल, डैशबोर्ड (शुरुआती)
- [x] ऑडिट लॉग लिखना + देखना
- [x] त्रुटि पेज: 403, 404, 419, 500, 503 (मेंटेनेंस)
- [x] टेस्ट: PHP सिंटैक्स, migration, CRUD, RBAC, CSRF, वैलिडेशन, मोबाइल, त्रुटियाँ
