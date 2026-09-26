# डिजिटल न्यूज़ प्लेटफ़ॉर्म (Newsroom CMS)

Core PHP 8.1+ पर बना, अपने Custom MVC वाला न्यूज़ पोर्टल + न्यूज़रूम CMS + रिपोर्टर नेटवर्क।
यह साधारण cPanel / DirectAdmin शेयर होस्टिंग पर बिना Composer, Node.js, npm या SSH के चलता है।

> **स्थिति:** Phase 1 (नींव) पूरा। आगे के phase की योजना [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) में है।

## ज़रूरतें

| चीज़ | न्यूनतम |
|---|---|
| PHP | 8.1+ (8.2/8.3 सलाह) |
| डेटाबेस | MySQL 5.7+ या MariaDB 10.3+ |
| PHP एक्सटेंशन (ज़रूरी) | PDO, pdo_mysql, mbstring, json, fileinfo, dom |
| PHP एक्सटेंशन (सलाह) | gd, curl, openssl, zip, intl |
| सर्वर | Apache + mod_rewrite (ज़्यादातर होस्टिंग पर चालू) |

## इंस्टॉल (संक्षेप में)

1. ZIP अपलोड करके `public_html` (या किसी सब-फ़ोल्डर) में extract करें।
2. cPanel → MySQL Databases में डेटाबेस और यूज़र बनाएँ।
3. ब्राउज़र में अपना डोमेन खोलें। इंस्टॉल विज़ार्ड अपने आप खुलेगा।
4. 7 कदम पूरे करें: सर्वर जाँच → डेटाबेस → टेबल → Super Admin → वेबसाइट → डेमो डेटा → पूरा।
5. सुरक्षा के लिए सर्वर से `/install` फ़ोल्डर हटा दें।

पूरी गाइड: [`docs/INSTALL.md`](docs/INSTALL.md)

## Phase 1 में क्या है

- **Custom MVC**: Front Controller, Router (नामित रूट, ग्रुप, middleware), Request/Response, View (layout + section), Model, Repository, Service, Validator
- **इंस्टॉल विज़ार्ड**: 7 कदम, हर ज़रूरत पर PASS / WARNING / ERROR, लोगो/रंग/टाइमज़ोन/भाषा, डेमो डेटा, इंस्टॉल के बाद लॉक
- **Migrations + Seeds**: क्रम से चलने वाली migrations, टेबल प्रीफ़िक्स, भविष्य के अपडेट के लिए तैयार
- **लॉगिन**: पासवर्ड hash, रेट लिमिट (5 प्रयास / 15 मिनट), सेशन रोटेशन, निष्क्रियता टाइमआउट, ब्राउज़र फ़िंगरप्रिंट, लॉगिन हिस्ट्री, पासवर्ड भूलें / रीसेट
- **RBAC**: 48 मॉड्यूल × 8 action = 211 अनुमतियाँ; 5 system रोल + कस्टम रोल; अनुमति मैट्रिक्स; यूज़र-स्तर पर अनुमति देना/छीनना; स्तर (level) आधारित सुरक्षा
- **एडमिन UI**: साइडबार (मॉड्यूल रजिस्ट्री से), मेनू खोज (Ctrl+K), क्विक एक्शन, डार्क मोड, मोबाइल, Chart.js डैशबोर्ड
- **यूज़र प्रबंधन**: सूची/फ़िल्टर/पेज, बनाना/बदलना/हटाना (soft delete), स्थिति, फ़ोटो, CSV एक्सपोर्ट
- **ऑडिट लॉग**: हर बदलाव (कौन, क्या, कब, IP, डिवाइस, पुराना/नया मान), फ़िल्टर, CSV
- **सुरक्षा**: CSRF, XSS escape, HTML sanitizer, सुरक्षित अपलोड, सुरक्षा हेडर, संवेदनशील फ़ोल्डर बंद, त्रुटि पेज (403/404/419/429/500/503)

## फ़ोल्डर

```
index.php          Front Controller
app/               Core, Controllers, Models, Services, Repositories, Validators, Middleware, Helpers, Views
config/            app.php, database.php, modules.php (मॉड्यूल रजिस्ट्री), roles.php, env.php (इंस्टॉलर बनाता है)
routes/            web.php, admin.php
public/assets/     css, js, vendor (Bootstrap 5, Font Awesome 6, Chart.js 4: सब लोकल)
public/uploads/    अपलोड (PHP चलना बंद)
storage/           cache, logs, sessions, private, backups
database/          migrations, seeds
install/           इंस्टॉल विज़ार्ड
modules/           भविष्य के प्लगइन मॉड्यूल
demo/              शुरुआती HTML डिज़ाइन डेमो (संदर्भ के लिए)
```

## नया मॉड्यूल कैसे जोड़ें

1. `config/modules.php` में एंट्री (label, icon, group, phase, route, actions)
2. `database/migrations/` में नई migration फ़ाइल (पुरानी कभी न बदलें)
3. `app/Controllers/Admin/` में controller, `routes/admin.php` में रूट + `can:module.action`
4. `app/Views/admin/<module>/` में view
5. एडमिन → रोल → **Sync** से नई अनुमतियाँ जुड़ जाएँगी

## तीसरे पक्ष की लाइब्रेरी (लोकल, लाइसेंस के साथ)

- Bootstrap 5.3.3 (MIT)
- Font Awesome Free 6.6.0 (आइकन CC BY 4.0, फ़ॉन्ट SIL OFL 1.1, कोड MIT)
- Chart.js 4.4.4 (MIT)
