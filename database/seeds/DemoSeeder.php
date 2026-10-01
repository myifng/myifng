<?php
/**
 * वैकल्पिक डेमो डेटा (इंस्टॉलर का कदम 6)। लाइव साइट पर एडमिन → सिस्टम → "डेमो डेटा हटाएँ" से एक क्लिक में हटता है।
 *   - हर रोल का एक डेमो यूज़र (पासवर्ड $ctx['demo_password']), 2 रिपोर्टर प्रोफ़ाइल
 *   - हर मुख्य श्रेणी में ख़बरें (तस्वीर, टैग, लोकेशन, ब्रेकिंग/फ़ीचर्ड/ट्रेंडिंग), एक लाइव ब्लॉग
 *   - ब्रेकिंग टिकर, वीडियो, फ़ोटो गैलरी, वेब स्टोरी, लाइव टीवी, ई-पेपर अंक, पोल, फ़ैक्ट चेक, नौकरियाँ, विज्ञापन
 * तस्वीरें GD से यहीं बनती हैं (कोई बाहरी फ़ाइल/इंटरनेट नहीं)। दोबारा चलाने पर दोहराव नहीं (स्लग जाँच)।
 */
use App\Core\Database;
use App\Helpers\Str;
use App\Services\DemoService as D;

return new class {
    private Database $db;
    private int $seed = 11;

    public function run(Database $db, array $ctx): void
    {
        $this->db = $db;
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        $admin = $ctx['admin_id'] ?? null;
        $lang = $db->value("SELECT id FROM {p}languages WHERE is_default = 1 LIMIT 1") ?: null;
        $now = time();

        // ---------- यूज़र ----------
        $pass = password_hash((string) ($ctx['demo_password'] ?? 'Demo@12345'), PASSWORD_DEFAULT);
        $users = [];
        foreach ([
            ['admin', 'डेमो एडमिन', 'admin.demo@example.com', '9876500001'],
            ['editor', 'रवि शर्मा', 'editor.demo@example.com', '9876500002'],
            ['reporter', 'अमित वर्मा', 'reporter.demo@example.com', '9876500003'],
            ['reporter', 'नेहा अग्रवाल', 'reporter2.demo@example.com', '9876500004'],
            ['employee', 'सुनील कुमार', 'employee.demo@example.com', '9876500005'],
        ] as [$role, $name, $email, $mobile]) {
            $roleId = (int) $db->value('SELECT id FROM {p}roles WHERE slug = ?', [$role]);
            $id = (int) $db->value('SELECT id FROM {p}users WHERE email = ?', [$email]);
            if ($roleId && !$id) {
                $id = $db->insert('users', ['name' => $name, 'email' => $email, 'mobile' => $mobile, 'password' => $pass, 'role_id' => $roleId, 'status' => 'active', 'bio' => 'डेमो खाता', 'created_by' => $admin]);
                D::track($db, 'users', $id);
            }
            $users[$email] = $id;
        }
        $editor = $users['editor.demo@example.com'] ?: $admin;
        $rep1 = $users['reporter.demo@example.com'] ?: $admin;
        $rep2 = $users['reporter2.demo@example.com'] ?: $admin;

        // लोकेशन (माइग्रेशन से आए उत्तर प्रदेश के ज़िले)
        $loc = fn(string $name) => $db->value('SELECT id FROM {p}locations WHERE name = ? LIMIT 1', [$name]) ?: null;
        $state = $loc('उत्तर प्रदेश');

        // ---------- रिपोर्टर प्रोफ़ाइल ----------
        $bureau = $db->value("SELECT id FROM {p}bureaus ORDER BY id LIMIT 1") ?: null;
        foreach ([[$rep1, 'ज़िला संवाददाता', 'गोरखपुर', 'male', 'रमेश वर्मा', 'B+'], [$rep2, 'वरिष्ठ संवाददाता', 'लखनऊ', 'female', 'सुरेश अग्रवाल', 'O+']] as $i => [$uid, $desig, $dist, $g, $guard, $blood]) {
            if (!$uid || $uid === $admin || $db->value('SELECT id FROM {p}reporters WHERE user_id = ?', [$uid])) {
                continue;
            }
            $photo = $this->img(600, 750, 'avatar', 'रिपोर्टर फ़ोटो', $admin);
            $rid = $db->insert('reporters', ['user_id' => $uid, 'reporter_code' => 'RPT-' . date('Y') . '-D' . ($i + 1), 'designation' => $desig, 'reporter_type' => 'full_time',
                'district_id' => $loc($dist), 'state_id' => $state, 'bureau_id' => $bureau, 'joining_date' => date('Y-m-d', strtotime('-8 months')), 'valid_until' => date('Y-m-d', strtotime('+1 year')),
                'status' => 'active', 'photo' => $photo, 'guardian_name' => $guard, 'mobile' => '98765000' . (13 + $i), 'blood_group' => $blood, 'address' => $dist . ', उत्तर प्रदेश', 'created_by' => $admin]);
            D::track($db, 'reporters', $rid);
        }

        // ---------- ख़बरें ----------
        $cat = fn(string $slug) => $db->value('SELECT id FROM {p}categories WHERE slug = ?', [$slug]) ?: null;
        $stories = [
            // [श्रेणी, शीर्षक, सार, ज़िला, फ़्लैग]
            ['desh', 'संसद के विशेष सत्र में डिजिटल इंडिया बिल पेश, विपक्ष ने मांगी विस्तृत चर्चा', 'सरकार ने डेटा सुरक्षा और साइबर अपराध से जुड़े नए प्रावधानों वाला विधेयक सदन के पटल पर रखा।', null, ['is_featured', 'is_breaking']],
            ['desh', 'मानसून की विदाई से पहले कई राज्यों में भारी बारिश का अलर्ट', 'मौसम विभाग ने अगले 48 घंटों के लिए पूर्वी और मध्य भारत में ऑरेंज अलर्ट जारी किया है।', null, ['is_trending']],
            ['desh', 'रेलवे ने त्योहारों के लिए 150 विशेष ट्रेनें चलाने की घोषणा की', 'दीपावली और छठ पर घर लौटने वालों की भीड़ को देखते हुए अतिरिक्त ट्रेनें और कोच जोड़े जाएँगे।', null, []],
            ['pradesh', 'लखनऊ मेट्रो के दूसरे चरण को कैबिनेट की मंज़ूरी, 11 नए स्टेशन बनेंगे', 'चारबाग से वसंतकुंज तक के रूट पर काम अगले साल की शुरुआत में शुरू होने की उम्मीद है।', 'लखनऊ', ['is_featured']],
            ['pradesh', 'गोरखपुर में बाढ़ राहत कार्य तेज़, 20 गाँवों में नावें तैनात', 'राप्ती नदी का जलस्तर घटने लगा है; प्रशासन ने राहत शिविरों में दवा और भोजन की व्यवस्था बढ़ाई।', 'गोरखपुर', ['is_breaking']],
            ['pradesh', 'वाराणसी में देव दीपावली की तैयारियाँ पूरी, 21 लाख दीये जलेंगे', 'घाटों की सजावट और सुरक्षा के लिए विशेष टीमें बनाई गई हैं; पर्यटकों के लिए अतिरिक्त नावें चलेंगी।', 'वाराणसी', ['is_trending']],
            ['pradesh', 'प्रयागराज में माघ मेले के लिए विशेष ट्रेनें और बसें चलेंगी', 'मेला प्रशासन ने श्रद्धालुओं की सुविधा के लिए नए पांटून पुल और स्वास्थ्य शिविरों की योजना बनाई है।', 'प्रयागराज', []],
            ['pradesh', 'कानपुर में नया आईटी पार्क, 5 हज़ार युवाओं को मिलेगा रोज़गार', 'राज्य सरकार और निजी कंपनियों के बीच हुए समझौते पर अगले महीने से काम शुरू होगा।', 'कानपुर नगर', []],
            ['world', 'जलवायु सम्मेलन में 120 देशों ने कार्बन उत्सर्जन घटाने का संकल्प लिया', 'विकासशील देशों के लिए हरित तकनीक कोष बढ़ाने पर भी सहमति बनी।', null, ['is_featured']],
            ['world', 'अंतरिक्ष एजेंसियों ने मिलकर चंद्रमा पर स्थायी स्टेशन की योजना पेश की', 'अगले दशक में शुरू होने वाले इस मिशन में भारत की भी अहम भूमिका होगी।', null, []],
            ['sports', 'टीम इंडिया ने रोमांचक मुकाबले में ऑस्ट्रेलिया को 5 विकेट से हराया', 'अंतिम ओवर में जीत के लिए 9 रन चाहिए थे; युवा बल्लेबाज़ ने छक्का लगाकर मैच ख़त्म किया।', null, ['is_featured', 'is_trending']],
            ['sports', 'राष्ट्रीय खेलों में उत्तर प्रदेश के पहलवानों ने जीते 4 स्वर्ण पदक', 'महिला कुश्ती में प्रदेश की खिलाड़ियों का प्रदर्शन सबसे शानदार रहा।', 'लखनऊ', []],
            ['sports', 'हॉकी विश्व कप के लिए भारतीय टीम घोषित, अनुभवी कप्तान पर भरोसा', 'चयनकर्ताओं ने 18 सदस्यीय टीम में पाँच नए चेहरों को मौका दिया है।', null, []],
            ['politics', 'नगर निकाय चुनाव की तारीख़ों का ऐलान, तीन चरणों में होगा मतदान', 'राज्य निर्वाचन आयोग के मुताबिक़ आदर्श आचार संहिता आज से लागू हो गई है।', 'लखनऊ', ['is_breaking', 'is_editor_pick']],
            ['politics', 'विपक्ष ने महंगाई पर सरकार को घेरा, सदन में ज़ोरदार हंगामा', 'सत्ता पक्ष ने कहा कि ज़रूरी चीज़ों की क़ीमतें पिछले साल के मुक़ाबले स्थिर रही हैं।', null, []],
            ['entertainment', 'हिंदी फ़िल्म ने पहले हफ़्ते में तोड़े कमाई के सारे रिकॉर्ड', 'छोटे शहरों के सिनेमाघरों में भी फ़िल्म को ज़बरदस्त प्रतिक्रिया मिल रही है।', null, ['is_trending']],
            ['entertainment', 'लखनऊ महोत्सव में लोक कलाकारों ने बाँधा समां', 'अवधी लोकगीतों और कथक की प्रस्तुतियों ने दर्शकों का मन मोह लिया।', 'लखनऊ', []],
            ['business', 'शेयर बाज़ार में लगातार तीसरे दिन तेज़ी, सेंसेक्स नई ऊँचाई पर', 'बैंकिंग और आईटी शेयरों में ख़रीदारी से निवेशकों की संपत्ति में बड़ा इज़ाफ़ा हुआ।', null, ['is_featured']],
            ['business', 'त्योहारी सीज़न में ऑनलाइन बिक्री 30 प्रतिशत बढ़ने का अनुमान', 'छोटे शहरों से आने वाले ऑर्डर में सबसे तेज़ बढ़ोतरी देखी जा रही है।', null, []],
            ['technology', 'हिंदी में काम करने वाला नया एआई सहायक लॉन्च, किसानों को मिलेगी मदद', 'मौसम, फ़सल और मंडी भाव की जानकारी अब बोलकर भी पूछी जा सकेगी।', null, ['is_editor_pick']],
            ['technology', '5G नेटवर्क अब प्रदेश के सभी ज़िला मुख्यालयों तक पहुँचा', 'दूरसंचार कंपनियों ने ग्रामीण इलाक़ों में भी नेटवर्क बढ़ाने का लक्ष्य रखा है।', null, []],
            ['education', 'गोरखपुर विश्वविद्यालय में दाख़िले की तारीख़ बढ़ी, अब 15 तक आवेदन', 'स्नातक और परास्नातक दोनों पाठ्यक्रमों के लिए ऑनलाइन फ़ॉर्म भरे जा सकेंगे।', 'गोरखपुर', []],
            ['education', 'बोर्ड परीक्षा का टाइम टेबल जारी, फ़रवरी के तीसरे हफ़्ते से परीक्षाएँ', 'छात्रों को प्रैक्टिकल परीक्षाएँ जनवरी में ही पूरी करनी होंगी।', null, ['is_trending']],
            ['health', 'सर्दियों में बढ़ते वायरल बुख़ार से बचने के लिए डॉक्टरों की सलाह', 'खुले में रखे भोजन से बचें, पानी उबालकर पिएँ और बुज़ुर्गों का विशेष ध्यान रखें।', null, []],
            ['health', 'ज़िला अस्पताल में नई डायलिसिस यूनिट शुरू, मरीज़ों को राहत', 'अब किडनी के मरीज़ों को इलाज के लिए बड़े शहरों में नहीं जाना पड़ेगा।', 'वाराणसी', []],
            ['religion', 'अयोध्या में दीपोत्सव की तैयारियाँ अंतिम चरण में', 'सरयू तट पर लेज़र शो और सांस्कृतिक कार्यक्रमों की रिहर्सल शुरू हो गई है।', 'अयोध्या', ['is_featured']],
            ['crime', 'साइबर ठगी के बड़े गिरोह का भंडाफोड़, 12 आरोपी गिरफ़्तार', 'पुलिस ने बताया कि गिरोह ने फ़र्ज़ी लोन ऐप के ज़रिए सैकड़ों लोगों को ठगा था।', 'लखनऊ', ['is_exclusive']],
            ['crime', 'चेन स्नैचिंग की घटनाओं पर लगाम के लिए शहर में 200 नए सीसीटीवी', 'मुख्य बाज़ारों और चौराहों पर कैमरे लगाए जाएँगे; कंट्रोल रूम से होगी निगरानी।', 'कानपुर नगर', []],
            ['lifestyle', 'त्योहारों में घर सजाने के आसान और सस्ते तरीक़े', 'पुराने सामान से बनी सजावट इस साल सबसे ज़्यादा पसंद की जा रही है।', null, []],
            ['agriculture', 'गेहूँ की बुवाई से पहले मिट्टी जाँच कराएँ, कृषि विभाग की सलाह', 'ज़िले के सभी विकास खंडों में मुफ़्त मिट्टी जाँच शिविर लगाए जा रहे हैं।', 'गोरखपुर', []],
            ['agriculture', 'धान की सरकारी ख़रीद शुरू, किसानों को 48 घंटे में भुगतान', 'ख़रीद केंद्रों पर किसानों के बैठने और पानी की व्यवस्था करने के निर्देश दिए गए हैं।', 'प्रयागराज', ['is_trending']],
        ];
        $paras = [
            'स्थानीय लोगों ने इस फ़ैसले का स्वागत किया है। उनका कहना है कि लंबे समय से इसकी माँग की जा रही थी और अब काम तेज़ी से पूरा होने की उम्मीद है।',
            'अधिकारियों ने बताया कि पूरी प्रक्रिया पर नज़र रखने के लिए एक निगरानी समिति बनाई गई है, जो हर हफ़्ते अपनी रिपोर्ट देगी।',
            'विशेषज्ञों के मुताबिक़ इस क़दम का असर आने वाले महीनों में साफ़ दिखाई देगा। उन्होंने आम लोगों से अफ़वाहों पर ध्यान न देने की अपील की है।',
            'प्रशासन ने हेल्पलाइन नंबर भी जारी किया है, जिस पर लोग अपनी शिकायत और सुझाव दर्ज करा सकते हैं।',
        ];
        $tagPool = ['उत्तर प्रदेश', 'लखनऊ', 'गोरखपुर', 'मौसम', 'चुनाव', 'क्रिकेट', 'शिक्षा', 'किसान', 'त्योहार', 'टेक्नोलॉजी', 'स्वास्थ्य', 'बाज़ार'];
        $tagIds = [];
        foreach ($tagPool as $t) {
            $slug = Str::slug($t);
            $id = (int) $db->value('SELECT id FROM {p}tags WHERE slug = ?', [$slug]);
            if (!$id) {
                $id = $db->insert('tags', ['name' => $t, 'slug' => $slug]);
                D::track($db, 'tags', $id);
            }
            $tagIds[] = $id;
        }
        $newsIds = [];
        foreach ($stories as $i => [$c, $title, $summary, $dist, $flags]) {
            $slug = Str::slug($title);
            if ($db->value('SELECT id FROM {p}news WHERE slug = ?', [$slug])) {
                continue;
            }
            $img = $this->img(1200, 675, 'news', $title, $admin);
            $p = $paras;
            shuffle($p);
            $content = '<p>' . htmlspecialchars($summary) . ' ' . htmlspecialchars($p[0]) . '</p><p>' . htmlspecialchars($p[1]) . '</p><h2>क्या है पूरा मामला</h2><p>' . htmlspecialchars($p[2]) . '</p><p>' . htmlspecialchars($p[3]) . '</p><p>आगे की जानकारी के लिए बने रहिए।</p>';
            $pub = date('Y-m-d H:i:s', $now - ($i * 5400) - mt_rand(0, 3000));
            $row = ['title' => $title, 'slug' => $slug, 'summary' => $summary, 'content' => $content, 'featured_image' => $img, 'image_caption' => $title, 'image_credit' => 'डेमो फ़ोटो',
                'category_id' => $cat($c), 'location_id' => $dist ? $loc($dist) : null, 'reporter_id' => $i % 3 === 0 ? $rep2 : $rep1, 'editor_id' => $editor, 'status' => 'published',
                'published_at' => $pub, 'views' => mt_rand(80, 6000), 'shares' => mt_rand(0, 300), 'word_count' => 160, 'language_id' => $lang, 'meta_description' => $summary,
                'created_by' => $admin, 'created_at' => $pub, 'updated_at' => $pub];
            foreach ($flags as $f) {
                $row[$f] = 1;
            }
            $id = $db->insert('news', $row);
            D::track($db, 'news', $id);
            $newsIds[] = $id;
            foreach (array_rand(array_flip($tagIds), 2) as $tid) {
                $db->query('INSERT IGNORE INTO {p}news_tags (news_id, tag_id) VALUES (?, ?)', [$id, $tid]);
            }
        }
        $db->query('UPDATE {p}tags t SET usage_count = (SELECT COUNT(*) FROM {p}news_tags nt WHERE nt.tag_id = t.id)');

        // लाइव ब्लॉग (एक ख़बर पर)
        if ($newsIds && $db->first("SHOW TABLES LIKE " . $db->pdo()->quote($db->table('live_blogs')))) {
            $nid = $newsIds[4] ?? $newsIds[0];
            $db->update('news', ['is_live' => 1], 'id = ?', [$nid]);
            $lb = $db->insert('live_blogs', ['news_id' => $nid, 'status' => 'live', 'started_at' => date('Y-m-d H:i:s', $now - 7200), 'created_by' => $editor]);
            D::track($db, 'live_blogs', $lb);
            foreach (['राहत शिविरों में 2,000 से ज़्यादा लोग पहुँचे', 'ज़िलाधिकारी ने प्रभावित गाँवों का दौरा किया', 'नदी का जलस्तर ख़तरे के निशान से नीचे आया', 'स्वास्थ्य विभाग ने 15 मेडिकल टीमें भेजीं'] as $k => $t) {
                $u = $db->insert('live_updates', ['live_blog_id' => $lb, 'title' => $t, 'body' => 'हमारे संवाददाता के मुताबिक़ ' . $t . '। प्रशासन लगातार हालात पर नज़र रखे हुए है।', 'is_key' => $k === 2 ? 1 : 0,
                    'author_id' => $rep1, 'posted_at' => date('Y-m-d H:i:s', $now - 7000 + $k * 1500)]);
                D::track($db, 'live_updates', $u);
            }
        }

        // ---------- ब्रेकिंग टिकर ----------
        foreach (array_slice($newsIds, 0, 3) as $k => $nid) {
            $t = $db->value('SELECT title FROM {p}news WHERE id = ?', [$nid]);
            $b = $db->insert('breaking_news', ['title' => $t, 'type' => $k === 0 ? 'breaking' : 'flash', 'news_id' => $nid, 'priority' => 3 - $k, 'show_ticker' => 1, 'show_banner' => $k === 0 ? 1 : 0,
                'mobile_alert' => 0, 'push' => 0, 'push_status' => 'none', 'starts_at' => date('Y-m-d H:i:s', $now - 3600), 'ends_at' => date('Y-m-d H:i:s', $now + 30 * 86400), 'status' => 'active', 'created_by' => $editor]);
            D::track($db, 'breaking_news', $b);
        }

        // ---------- वीडियो (YouTube) ----------
        foreach ([['महराजगंज में बाढ़: ग्राउंड रिपोर्ट', 'ground_report'], ['खेती की नई तकनीक पर विशेषज्ञ से बातचीत', 'interview'], ['शहर की बड़ी ख़बरें: 5 मिनट में', 'show'], ['त्योहारों पर बाज़ार की रौनक', 'video']] as $k => [$t, $type]) {
            $slug = Str::slug($t);
            if ($db->value('SELECT id FROM {p}videos WHERE slug = ?', [$slug])) {
                continue;
            }
            $v = $db->insert('videos', ['title' => $t, 'slug' => $slug, 'description' => $t . ' — डेमो वीडियो।', 'type' => $type, 'source' => 'youtube', 'source_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
                'duration' => 300 + $k * 60, 'cover' => $this->img(1280, 720, 'news', $t, $admin), 'category_id' => $cat($k % 2 ? 'agriculture' : 'pradesh'), 'status' => 'published', 'is_featured' => $k === 0 ? 1 : 0,
                'published_at' => date('Y-m-d H:i:s', $now - $k * 9000), 'views' => mt_rand(100, 3000), 'created_by' => $editor]);
            D::track($db, 'videos', $v);
        }

        // ---------- फ़ोटो गैलरी ----------
        foreach (['बाढ़ की तस्वीरें: राहत और बचाव', 'देव दीपावली की मनमोहक झलकियाँ'] as $k => $t) {
            $slug = Str::slug($t);
            if ($db->value('SELECT id FROM {p}galleries WHERE slug = ?', [$slug])) {
                continue;
            }
            $g = $db->insert('galleries', ['title' => $t, 'slug' => $slug, 'description' => $t . ' (डेमो गैलरी)', 'cover' => $this->img(1200, 800, 'news', $t, $admin), 'photographer' => 'डेमो फ़ोटोग्राफ़र',
                'category_id' => $cat('pradesh'), 'status' => 'published', 'is_featured' => 1, 'published_at' => date('Y-m-d H:i:s', $now - $k * 20000), 'created_by' => $editor]);
            D::track($db, 'galleries', $g);
            for ($p = 1; $p <= 5; $p++) {
                $ph = $db->insert('gallery_photos', ['gallery_id' => $g, 'image' => $this->img(1200, 800, 'news', $t . ' ' . $p, $admin), 'caption' => $t . ': तस्वीर ' . $p, 'photographer' => 'डेमो', 'sort_order' => $p]);
                D::track($db, 'gallery_photos', $ph);
            }
        }

        // ---------- वेब स्टोरी ----------
        $ws = 'त्योहारों में सेहत का ध्यान: 5 आसान टिप्स';
        if (!$db->value('SELECT id FROM {p}web_stories WHERE slug = ?', [Str::slug($ws)])) {
            $s = $db->insert('web_stories', ['title' => $ws, 'slug' => Str::slug($ws), 'description' => 'मिठाई, नींद और पानी: त्योहारों में भी सेहत ठीक रखने के तरीक़े।', 'cover' => $this->img(720, 1280, 'news', $ws, $admin),
                'category_id' => $cat('health'), 'status' => 'published', 'is_featured' => 1, 'published_at' => date('Y-m-d H:i:s', $now - 4000), 'created_by' => $editor]);
            D::track($db, 'web_stories', $s);
            foreach (['मीठा कम, पानी ज़्यादा', 'नींद पूरी करें', 'घर का बना खाना', 'रोज़ 30 मिनट टहलें', 'बुज़ुर्गों का ख़ास ख़याल'] as $k => $h) {
                $sl = $db->insert('web_story_slides', ['story_id' => $s, 'sort_order' => $k + 1, 'media' => $this->img(720, 1280, 'news', $h, $admin), 'media_type' => 'image', 'heading' => $h,
                    'body' => 'त्योहारों में भी छोटी आदतें बड़ा फ़र्क़ लाती हैं।', 'text_position' => 'bottom', 'theme' => 'dark', 'duration' => 6]);
                D::track($db, 'web_story_slides', $sl);
            }
        }

        // ---------- लाइव टीवी ----------
        if (!$db->value('SELECT id FROM {p}live_tv_channels LIMIT 1')) {
            $ch = $db->insert('live_tv_channels', ['name' => 'डेमो लाइव', 'slug' => 'demo-live', 'source_type' => 'youtube', 'source_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
                'description' => 'डेमो चैनल: एडमिन → लाइव टीवी में अपने चैनल का लिंक डालें।', 'is_default' => 1, 'is_live' => 1, 'status' => 'active', 'sort_order' => 1]);
            D::track($db, 'live_tv_channels', $ch);
        }

        // ---------- ई-पेपर (आज का अंक, 4 पेज) ----------
        $ed = $db->value("SELECT id FROM {p}epaper_editions WHERE status = 'active' ORDER BY is_default DESC, id LIMIT 1");
        if ($ed && !$db->value('SELECT id FROM {p}epaper_issues WHERE edition_id = ? AND issue_date = ?', [$ed, date('Y-m-d')])) {
            $dir = 'epaper/' . date('Y/m') . '/' . bin2hex(random_bytes(8));
            $abs = dirname(__DIR__, 2) . '/public/uploads/' . $dir;
            if (@mkdir($abs, 0755, true) || is_dir($abs)) {
                $iss = $db->insert('epaper_issues', ['edition_id' => $ed, 'issue_date' => date('Y-m-d'), 'title' => 'डेमो अंक', 'dir' => $dir, 'page_count' => 0, 'access' => 'free', 'status' => 'published',
                    'publish_at' => date('Y-m-d H:i:s', $now - 3600), 'created_by' => $admin]);
                D::track($db, 'epaper_issues', $iss);
                $cover = null;
                for ($p = 1; $p <= 4; $p++) {
                    $jpg = D::image(1200, 1800, 300 + $p, 'page');
                    $name = bin2hex(random_bytes(8));
                    file_put_contents("$abs/$name.jpg", $jpg);
                    $t = imagecreatefromstring((string) $jpg);
                    $th = imagecreatetruecolor(300, 450);
                    imagecopyresampled($th, $t, 0, 0, 0, 0, 300, 450, 1200, 1800);
                    imagejpeg($th, "$abs/$name-t.jpg", 80);
                    D::track($db, 'file', null, "$dir/$name.jpg");
                    D::track($db, 'file', null, "$dir/$name-t.jpg");
                    $pg = $db->insert('epaper_pages', ['issue_id' => $iss, 'page_no' => $p, 'image' => "$dir/$name.jpg", 'thumb' => "$dir/$name-t.jpg", 'width' => 1200, 'height' => 1800,
                        'label' => [1 => 'मुख पृष्ठ', 2 => 'प्रदेश', 3 => 'देश-दुनिया', 4 => 'खेल'][$p]]);
                    D::track($db, 'epaper_pages', $pg);
                    $cover ??= "$dir/$name-t.jpg";
                }
                $db->update('epaper_issues', ['page_count' => 4, 'cover' => $cover], 'id = ?', [$iss]);
            }
        }

        // ---------- पोल ----------
        $q = 'क्या आपके शहर में सार्वजनिक परिवहन की सुविधा पर्याप्त है?';
        if (!$db->value('SELECT id FROM {p}polls WHERE question = ?', [$q])) {
            $poll = $db->insert('polls', ['question' => $q, 'description' => 'डेमो पोल', 'multiple' => 0, 'show_results' => 'after_vote', 'require_login' => 0, 'status' => 'active',
                'start_at' => date('Y-m-d H:i:s', $now - 86400), 'end_at' => date('Y-m-d H:i:s', $now + 30 * 86400), 'total_votes' => 0, 'voters' => 0, 'created_by' => $editor]);
            D::track($db, 'polls', $poll);
            $total = 0;
            foreach (['हाँ, पर्याप्त है', 'कुछ हद तक', 'नहीं, बहुत कमी है', 'पता नहीं'] as $k => $o) {
                $v = [42, 87, 131, 15][$k];
                $total += $v;
                $op = $db->insert('poll_options', ['poll_id' => $poll, 'label' => $o, 'votes' => $v, 'sort_order' => $k + 1]);
                D::track($db, 'poll_options', $op);
            }
            $db->update('polls', ['total_votes' => $total, 'voters' => $total], 'id = ?', [$poll]);
        }

        // ---------- फ़ैक्ट चेक ----------
        $fc = 'फ़ैक्ट चेक: क्या 1 तारीख़ से सभी बैंक 3 दिन बंद रहेंगे?';
        if (!$db->value('SELECT id FROM {p}fact_checks WHERE slug = ?', [Str::slug($fc)])) {
            $f = $db->insert('fact_checks', ['title' => $fc, 'slug' => Str::slug($fc), 'claim' => 'सोशल मीडिया पर दावा किया जा रहा है कि अगले महीने की 1 तारीख़ से लगातार तीन दिन सभी बैंक बंद रहेंगे।',
                'claim_by' => 'वायरल व्हाट्सएप संदेश', 'claim_medium' => 'WhatsApp', 'claim_date' => date('Y-m-d', $now - 3 * 86400), 'verdict' => 'false',
                'summary' => 'दावा ग़लत है। बैंकों की आधिकारिक छुट्टियों की सूची में ऐसी कोई लगातार तीन दिन की बंदी नहीं है।',
                'evidence' => '<p>भारतीय रिज़र्व बैंक की छुट्टियों की सूची और बैंक यूनियनों से बातचीत में ऐसी किसी बंदी की पुष्टि नहीं हुई।</p>',
                'explanation' => '<p>वायरल संदेश पिछले साल की एक पुरानी सूचना को काट-छाँटकर बनाया गया है।</p>', 'sources' => "भारतीय रिज़र्व बैंक की छुट्टियों की सूची\nबैंक यूनियन का बयान",
                'image' => $this->img(1200, 675, 'news', $fc, $admin), 'category_id' => $cat('business'), 'author_id' => $editor, 'status' => 'published', 'published_at' => date('Y-m-d H:i:s', $now - 20000), 'created_by' => $editor]);
            D::track($db, 'fact_checks', $f);
        }

        // ---------- नौकरियाँ ----------
        foreach ([['डिजिटल सब-एडिटर (हिंदी)', 'डेस्क', 'लखनऊ', 'full_time'], ['ज़िला संवाददाता', 'रिपोर्टिंग', 'उत्तर प्रदेश के सभी ज़िले', 'part_time']] as [$t, $dep, $l, $type]) {
            if ($db->value('SELECT id FROM {p}jobs WHERE slug = ?', [Str::slug($t)])) {
                continue;
            }
            $j = $db->insert('jobs', ['title' => $t, 'slug' => Str::slug($t), 'department' => $dep, 'location' => $l, 'job_type' => $type,
                'description' => '<p>हमें तेज़, तथ्यपरक और ज़िम्मेदार पत्रकारों की तलाश है। (डेमो विज्ञापन)</p>', 'requirements' => "हिंदी में साफ़ लिखने की क्षमता\nख़बर की पुष्टि का अनुभव",
                'salary' => 'योग्यता अनुसार', 'vacancies' => 2, 'deadline' => date('Y-m-d', $now + 30 * 86400), 'status' => 'open', 'created_by' => $admin]);
            D::track($db, 'jobs', $j);
        }

        // ---------- विज्ञापन (बैनर) ----------
        foreach ([['header', 728, 90], ['sidebar_top', 300, 250], ['article_inline', 300, 250], ['home_middle', 728, 90]] as $k => [$slot, $w, $h]) {
            $slotId = $db->value('SELECT id FROM {p}ad_slots WHERE slot_key = ?', [$slot]);
            if (!$slotId) {
                continue;
            }
            $ad = $db->insert('ads', ['name' => 'डेमो विज्ञापन: ' . $slot, 'type' => 'image', 'image' => $this->img($w, $h, 'banner', 'विज्ञापन', $admin), 'target_url' => '/page/advertise-with-us',
                'text' => 'यहाँ आपका विज्ञापन', 'devices' => 'all', 'priority' => 5, 'status' => 'active', 'created_by' => $admin]);
            D::track($db, 'ads', $ad);
            $db->query('INSERT IGNORE INTO {p}ad_placements (ad_id, slot_id) VALUES (?, ?)', [$ad, $slotId]);
        }
    }

    /** तस्वीर बनाकर मीडिया लाइब्रेरी में; uploads path */
    private function img(int $w, int $h, string $kind, string $title, ?int $user): ?string
    {
        $jpg = D::image($w, $h, $this->seed++, $kind);
        return $jpg ? D::media($this->db, $jpg, $title, $user) : null;
    }
};
