# Phase 7: ब्रेकिंग कंट्रोल रूम, लाइव ब्लॉग, लाइव टीवी, वीडियो, फ़ोटो गैलरी, वेब स्टोरी, ऑडियो/पॉडकास्ट

## 1. लक्ष्य

- **ब्रेकिंग कंट्रोल रूम (§10)**: ब्रेकिंग / फ़्लैश / अलर्ट, कई टिकर आइटम एक साथ। हर आइटम: प्राथमिकता (सामान्य, ज़रूरी, अति ज़रूरी), शुरू और ख़त्म होने का समय, अपने आप समाप्ति (डिफ़ॉल्ट घंटे सेटिंग से), कहाँ दिखे: टिकर / होमपेज अलर्ट बैनर / मोबाइल अलर्ट। पुश नोटिफ़िकेशन का निशान अभी कतार में दर्ज होगा; भेजना Phase 11 (नोटिफ़िकेशन सेंटर) में।
- **लाइव ब्लॉग (§34)**: किसी ख़बर को लाइव ब्लॉग बनाना। कंट्रोल पेज से बिना पेज रीलोड (AJAX) अपडेट डालना, बदलना, हटाना, पिन करना, "अहम" निशान। ख़बर के पेज पर समय वाली टाइमलाइन (10:35 PM — अपडेट), हर 30 सेकंड में नए अपडेट की जाँच, "N नए अपडेट" बटन। Google के लिए `LiveBlogPosting` schema।
- **लाइव टीवी (§35)**: कई चैनल: YouTube लाइव, एम्बेड (iframe का https पता), स्ट्रीमिंग URL (HLS `.m3u8` / MP4, जहाँ क़ानूनी रूप से अनुमति हो)। कार्यक्रम सूची (दिन + समय, एंकर)। "अभी लाइव" चालू/बंद। `/live-tv` पेज (प्लेयर, चैनल बदलें, आज का शेड्यूल, "अभी चल रहा")। होमपेज का लाइव टीवी ब्लॉक अब डिफ़ॉल्ट चैनल से।
- **वीडियो (§36)**: वीडियो, शॉर्ट वीडियो (9:16), इंटरव्यू, ग्राउंड रिपोर्ट, शो। स्रोत: YouTube, अपलोड (मीडिया लाइब्रेरी), एम्बेड। प्लेलिस्ट और शो। वीडियो श्रेणियाँ = साइट की श्रेणियाँ (एक ही taxonomy)। `VideoObject` schema, अवधि, थंबनेल।
- **फ़ोटो गैलरी (§37)**: एल्बम; हर फ़ोटो का कैप्शन, फ़ोटोग्राफ़र, स्थान, क्रेडिट, कॉपीराइट। एक साथ कई फ़ोटो अपलोड, क्रम बदलना। वेबसाइट पर फ़ुलस्क्रीन गैलरी (कीबोर्ड, मोबाइल पर स्वाइप)। `ImageGallery` schema।
- **वेब स्टोरी (§38)**: 9:16 स्लाइड बिल्डर (इमेज/वीडियो, शीर्षक, टेक्स्ट, CTA बटन, टेक्स्ट की जगह, थीम, अवधि) और साथ में लाइव प्रीव्यू। वेबसाइट पर मोबाइल-फ़र्स्ट फ़ुलस्क्रीन प्लेयर (टैप, स्वाइप, प्रोग्रेस बार, शेयर) + Google Web Stories के लिए AMP संस्करण (`rel=amphtml`)।
- **ऑडियो / पॉडकास्ट (§39)**: ऑडियो न्यूज़ और पॉडकास्ट एपिसोड, सीरीज़, अपलोड या बाहरी लिंक, थंबनेल, विवरण, ट्रांसक्रिप्ट। पॉडकास्ट RSS फ़ीड (Apple/Spotify में जोड़ने लायक)। Text-to-Speech के लिए ढाँचा तैयार (`TtsProvider` interface, `tts_status` कॉलम); कोई प्रदाता अभी जुड़ा नहीं।

## 2. डेटाबेस (migration `000007`)

| टेबल | मुख्य कॉलम |
|---|---|
| `breaking_news` | title, type (breaking/flash/alert), news_id, url, priority 1-3, show_ticker, show_banner, mobile_alert, push, push_status, starts_at, ends_at, status |
| `live_blogs` | news_id (unique), status (live/paused/ended), started_at, ended_at |
| `live_updates` | live_blog_id, title, body (सादा टेक्स्ट), image, embed_url, is_pinned, is_key, author_id, posted_at |
| `live_tv_channels` | name, slug, source_type (youtube/embed/stream), source_url, logo, description, is_default, is_live, status, sort_order |
| `live_tv_programs` | channel_id, title, host, description, days (0-6), start_time, end_time, status |
| `video_playlists` | name, slug, type (playlist/show), description, cover, status, sort_order |
| `videos` | title, slug, type, source (youtube/upload/embed), source_url, file, duration, cover, playlist_id, category_id, location_id, credit, SEO, status, published_at, is_featured, views |
| `galleries` | title, slug, description, cover, photographer, category_id, location_id, SEO, status, published_at, is_featured, views |
| `gallery_photos` | gallery_id, image, caption, photographer, location, credit, copyright, sort_order |
| `web_stories` | title, slug, description, cover (पोस्टर 3:4), category_id, SEO, status, published_at, views |
| `web_story_slides` | story_id, sort_order, media, media_type, heading, body, cta_label, cta_url, text_position, theme, duration |
| `podcast_series` | title, slug, description, cover, author, category_id, status, sort_order |
| `audio_items` | title, slug, type (news/episode), series_id, episode_no, file, external_url, duration, cover, description, transcript, news_id, tts_status, category_id, SEO, status, published_at, views |

सेटिंग: "कंटेंट फ़ीचर" टैब में `breaking_expiry_hours` (अपने आप समाप्ति, डिफ़ॉल्ट 6 घंटे), `live_blog_refresh` (सेकंड)। मेनू: फ़ुटर "उपयोगी लिंक" में वीडियो, फ़ोटो, वेब स्टोरी, पॉडकास्ट, लाइव टीवी।

## 3. रूट

| पता | काम | अनुमति |
|---|---|---|
| `/live-tv`, `/live-tv/{slug}` | लाइव टीवी | सार्वजनिक |
| `/videos`, `/videos/playlist/{slug}`, `/video/{slug}` | वीडियो | सार्वजनिक |
| `/photos`, `/photos/{slug}` | फ़ोटो गैलरी | सार्वजनिक |
| `/web-stories`, `/web-stories/{slug}`, `/web-stories/{slug}/amp` | वेब स्टोरी | सार्वजनिक |
| `/audio`, `/audio/{slug}`, `/podcast/{slug}`, `/podcast/{slug}/feed` | ऑडियो, पॉडकास्ट, RSS | सार्वजनिक |
| `/live-updates/{id}` | लाइव ब्लॉग के नए अपडेट (JSON) | सार्वजनिक (सिर्फ़ प्रकाशित ख़बर) |
| `/admin/breaking…` | कंट्रोल रूम | breaking.* |
| `/admin/live-blogs…` | लाइव ब्लॉग, अपडेट (AJAX) | live_blogs.* |
| `/admin/live-tv…` | चैनल, कार्यक्रम | live_tv.view/edit |
| `/admin/videos…`, `/admin/video-playlists…` | वीडियो, प्लेलिस्ट | videos.* |
| `/admin/galleries…` | गैलरी, फ़ोटो | galleries.* |
| `/admin/web-stories…` | स्टोरी बिल्डर | web_stories.* |
| `/admin/audio…`, `/admin/podcasts…` | ऑडियो, सीरीज़ | audio.* |

## 4. कोड

- **Controllers (Admin)**: `BreakingController`, `LiveBlogController`, `LiveTvController`, `VideoController`, `PlaylistController`, `GalleryController`, `WebStoryController`, `AudioController`, `PodcastController`
- **Controllers (Front)**: `LiveTvController`, `VideoController`, `GalleryController`, `WebStoryController`, `AudioController`, `LiveBlogController` (JSON)
- **Models**: `BreakingNews`, `LiveBlog`, `LiveUpdate`, `LiveTvChannel`, `LiveTvProgram`, `Video`, `VideoPlaylist`, `Gallery`, `GalleryPhoto`, `WebStory`, `WebStorySlide`, `PodcastSeries`, `AudioItem`
- **Services**: `BreakingService` (सक्रिय आइटम, टिकर, बैनर, मोबाइल अलर्ट), `LiveBlogService` (अपडेट, ख़बर का `is_live`), `LiveTvService` (चैनल का प्लेयर, अभी का कार्यक्रम), `MultimediaService` (साझा: स्लग, प्रकाशन, स्रोत की जाँच, व्यू गिनती, सूची क्वेरी, URL), `EmbedService` (YouTube ID, सुरक्षित iframe src), `Tts\TtsProvider` + `TtsService`
- **Helpers**: `mm_card()` (वीडियो/गैलरी/स्टोरी/ऑडियो कार्ड), `duration_label()`
- **होमपेज ब्लॉक**: वीडियो, फ़ोटो गैलरी, वेब स्टोरी, ब्रेकिंग, लाइव टीवी (चैनल से), नया "ऑडियो / पॉडकास्ट"
- **JS**: लाइव ब्लॉग कंट्रोल (AJAX), स्टोरी बिल्डर + प्रीव्यू, गैलरी में कई फ़ोटो अपलोड और क्रम; वेबसाइट पर लाइटबॉक्स, स्टोरी प्लेयर, लाइव अपडेट पोलिंग, मोबाइल अलर्ट, HLS प्लेयर (लोकल `hls.js`, Apache-2.0, सिर्फ़ लाइव टीवी पेज पर)

## 5. अनुमतियाँ

- हर मॉड्यूल: `view` सूची, `create`, `edit`, `delete`; `publish` = प्रकाशित/सक्रिय करना। बिना `publish` वाला यूज़र सिर्फ़ ड्राफ़्ट सेव कर सकता है।
- `breaking.publish`: आइटम चालू करना; `live_blogs.publish`: ब्लॉग शुरू/रोकना/ख़त्म करना; `live_blogs.create`: अपडेट डालना; `live_tv.edit`: चैनल/कार्यक्रम।
- डिफ़ॉल्ट: Admin सब; Editor: `breaking.*`, `live_blogs.*`, `videos.*`, `galleries.*`, `web_stories.*`, `audio.*` (लाइव टीवी सिर्फ़ Admin; चाहें तो रोल स्क्रीन से दें); Reporter कुछ नहीं (ज़रूरत हो तो यूज़र की अलग अनुमति)।

## 6. सुरक्षा

| जोखिम | उपाय |
|---|---|
| एम्बेड से XSS | कच्चा HTML कभी नहीं; सिर्फ़ https iframe `src` (पेस्ट किए `<iframe>` से src निकालते हैं), `sandbox` और `referrerpolicy`; YouTube सिर्फ़ 11 अक्षर की ID से |
| लाइव अपडेट में HTML | टेक्स्ट सादा सेव होता है; दिखाते समय पूरा escape, सिर्फ़ **बोल्ड** और https लिंक बनते हैं |
| मीडिया पाथ से छेड़छाड़ | हर इमेज/वीडियो/ऑडियो पाथ `MediaService::validPath()` (लाइब्रेरी में होना ज़रूरी, सही प्रकार) |
| CTA / बाहरी लिंक | सिर्फ़ `http(s)://` या `/` से शुरू; `javascript:` नहीं; बाहरी पर `rel="noopener nofollow"` |
| बिना अनुमति प्रकाशन | `publish` अनुमति सर्वर पर जाँची जाती है (बटन छिपाना काफ़ी नहीं) |
| पोलिंग से लोड | JSON 10 सेकंड सर्वर कैश, `after` ID से सिर्फ़ नए अपडेट, छिपे टैब में पोलिंग बंद (रेट लिमिट नहीं, ताकि एक मोबाइल नेटवर्क के पीछे के कई पाठक न रुकें) |
| ड्राफ़्ट लीक | सार्वजनिक क्वेरी: `status = 'published' AND published_at <= NOW()` |
| CSRF | सारे POST/PUT/DELETE, AJAX में `X-CSRF-Token` |

## 7. जाँच के नतीजे

| जाँच | नतीजा |
|---|---|
| ब्रेकिंग | 3 आइटम (अति ज़रूरी, फ़्लैश, बंद); ग़लत लिंक (`javascript:`), ख़त्म का समय न देना, कोई जगह न चुनना: हिंदी संदेश के साथ रुके; टिकर में प्राथमिकता से क्रम, फ़्लैश निशान; होमपेज पर एक ही अलर्ट बैनर; मोबाइल अलर्ट दिखा, बंद करने पर दोबारा नहीं; पुश = "कतार में" |
| लाइव ब्लॉग | ख़बर से शुरू → `is_live`; AJAX से अपडेट डालना/बदलना/पिन/हटाना बिना रीलोड (Ctrl+Enter भी); `<script>` escape; ख़ाली अपडेट पर 422 JSON; वेबसाइट पर टाइमलाइन + `LiveBlogPosting`; `/live-updates/1?after=1` सिर्फ़ नए |
| लाइव टीवी | YouTube / एम्बेड (पेस्ट किए `<iframe>` से सिर्फ़ src, `sandbox`) / HLS (लोकल hls.js सिर्फ़ ज़रूरत पर); ग़लत YouTube, `javascript:` iframe, http स्ट्रीम: रुके; रात 10 से 12 वाला कार्यक्रम "अभी" में; हेडर का LIVE बटन |
| वीडियो | YouTube, शॉर्ट (9:16), एम्बेड; प्लेलिस्ट पेज; ड्राफ़्ट और आगे की तारीख़ वाले 404; `VideoObject` (PT4M35S); ग़लत अवधि पर संदेश |
| गैलरी | कई फ़ोटो एक साथ अपलोड, लाइब्रेरी से, क्रम ऊपर/नीचे; बिना फ़ोटो प्रकाशन रुका; `../../etc/passwd` जैसा पाथ रुका; लाइटबॉक्स (←/→/Esc, `#photo-2` लिंक); `ImageGallery` |
| वेब स्टोरी | स्लाइड जोड़ें/कॉपी/क्रम, लाइव 9:16 प्रीव्यू; ग़लत CTA लिंक रुका; ख़ाली स्लाइड छूटी; प्लेयर: टैप, अपने आप आगे, दबाकर रोकें; AMP पेज (`amp-story`, outlink) |
| ऑडियो | एपिसोड बिना सीरीज़, बिना फ़ाइल प्रकाशन, http लिंक: रुके; पेज पर प्लेयर, ट्रांसक्रिप्ट, ख़बर का लिंक, `PodcastEpisode`; RSS फ़ीड XML सही (enclosure length) |
| होमपेज बिल्डर | वीडियो, गैलरी, वेब स्टोरी, ऑडियो, लाइव टीवी, ब्रेकिंग ब्लॉक चालू (सिर्फ़ ई-पेपर Phase 8 का इंतज़ार) |
| अनुमति | Reporter: सभी नए पेज 403, POST 403; बिना `publish` वाला यूज़र: नया = ड्राफ़्ट/बंद, स्थिति नहीं बदल सकता, हटाना 403 |
| CSRF | 9 नए POST रूट पर ग़लत टोकन → 419 |
| मोबाइल (390px) | होम, लाइव टीवी, वीडियो, गैलरी, ऑडियो, वेब स्टोरी, लाइव ब्लॉग और एडमिन के नए पेज: क्षैतिज स्क्रॉल नहीं; JS त्रुटि नहीं |
| नया इंस्टॉल | 7 माइग्रेशन, फ़ुटर में 5 नए लिंक; दोबारा चलाने पर कुछ नहीं बदला |

**साथ में सुधार:** एक पेज पर कई फ़ॉर्म (जैसे लाइव टीवी के कार्यक्रम) हों तो ग़लत भरे फ़ॉर्म का पुराना मान दूसरे फ़ॉर्म में नहीं दिखता (`field(..., ['fresh' => true])`)। व्यू गिनती अब ख़बर और मल्टीमीडिया में साझा (`NewsQuery::hit()`)।

**ध्यान दें:** AMP वैलिडेटर इस टेस्ट मशीन से डाउनलोड नहीं हो सका; लाइव साइट पर https://search.google.com/test/amp से `/web-stories/…/amp` एक बार जाँच लें।
