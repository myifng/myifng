<?php
use App\Models\News;
use App\Services\NewsService;
use App\Services\NewsWorkflow;
$this->layout('layouts/admin');
$isNew = $news === null;
$title = $isNew ? 'नई ख़बर' : 'ख़बर: ' . $news['title'];
$status = $news['status'] ?? 'draft';
$published = $status === 'published';
$desk = can('news.approve');
$oldTopics = array_map('intval', (array) old('topics', $rel['topics']));
$tagsVal = (string) old('tags', implode(', ', $rel['tags']));
$related = $rel['related'];
$gallery = $rel['gallery'];
$thenActions = array_intersect_key(['submit' => 'fa-paper-plane', 'approve' => 'fa-circle-check', 'publish' => 'fa-globe', 'schedule' => 'fa-calendar-check'], $actions);
$thenLabels = ['submit' => 'सेव करके डेस्क को भेजें', 'approve' => 'सेव करके मंज़ूर करें', 'publish' => $published ? '' : 'सेव करके प्रकाशित करें', 'schedule' => 'सेव करके शेड्यूल करें'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.news.index')) ?>">ख़बरें</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नई' : '#' . (int) $news['id'] ?></li></ol></nav>
    <h1><?= $isNew ? 'नई ख़बर' : 'ख़बर बदलें' ?> <?= NewsWorkflow::badge($status) ?></h1>
    <?php if (!$isNew): ?><p>आख़िरी बदलाव <?= time_ago($news['updated_at']) ?> · <?= num($news['word_count']) ?> शब्द · पढ़ने में <?= NewsService::readingTime((int) $news['word_count']) ?> मिनट</p><?php endif; ?>
  </div>
  <?php if (!$isNew): ?>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="<?= e(route('admin.news.history', ['id' => $news['id']])) ?>"><i class="fa-solid fa-clock-rotate-left me-1"></i> स्थिति और हिस्ट्री</a>
      <a class="btn btn-outline-secondary" href="<?= e(route('admin.news.preview', ['id' => $news['id']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-eye me-1"></i> प्रीव्यू</a>
    </div>
  <?php endif; ?>
</div>

<?php if (!$isNew && $status === 'rejected'): $fb = array_values(array_filter($remarks, fn($r) => $r['to_status'] === 'rejected'))[0] ?? null; ?>
  <div class="alert alert-danger"><b><i class="fa-solid fa-circle-xmark me-1"></i> डेस्क ने सुधार माँगा है:</b> <?= e($fb['message'] ?? '') ?> <span class="small d-block mt-1">— <?= e($fb['user'] ?? '') ?>, <?= $fb ? time_ago($fb['created_at']) : '' ?>। सुधार करके दोबारा भेजें।</span></div>
<?php endif; ?>
<?php if ($assignment): ?>
  <div class="alert alert-info"><i class="fa-solid fa-list-check me-1"></i> असाइनमेंट: <b><?= e($assignment['title']) ?></b><?= $assignment['deadline'] ? ' · डेडलाइन ' . hindi_date($assignment['deadline'], true) : '' ?><?= $assignment['instructions'] ? '<span class="d-block small mt-1">' . e($assignment['instructions']) . '</span>' : '' ?></div>
<?php endif; ?>

<form method="post" action="<?= e($isNew ? route('admin.news.store') : route('admin.news.update', ['id' => $news['id']])) ?>" novalidate data-unsaved class="news-form">
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <?php if (!$isNew): ?><input type="hidden" name="updated_at" value="<?= e($news['updated_at']) ?>"><?php endif; ?>
  <?php if ($assignment): ?><input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>"><?php endif; ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <?= field('text', 'title', 'शीर्षक', $news['title'] ?? ($assignment['title'] ?? ''), ['required' => true, 'class' => 'form-control-lg fw-bold', 'attrs' => ['maxlength' => 255, 'data-seo-title' => true, 'data-count' => 90]]) ?>
        <?= field('text', 'subtitle', 'उप-शीर्षक', $news['subtitle'] ?? '', ['attrs' => ['maxlength' => 255]]) ?>
        <?= field('text', 'slug', 'URL (स्लग)', $news['slug'] ?? '', ['prefix' => '/news/', 'help' => $published ? 'प्रकाशित ख़बर का URL बदलने से पुराने लिंक टूटेंगे।' : 'ख़ाली छोड़ें तो शीर्षक से अंग्रेज़ी में अपने आप बनेगा', 'attrs' => ['maxlength' => 200]]) ?>
        <?= field('textarea', 'summary', 'सार (शॉर्ट समरी)', $news['summary'] ?? '', ['rows' => 2, 'help' => 'लिस्ट, सोशल शेयर और Google में दिखता है', 'attrs' => ['maxlength' => 600, 'data-count' => 300]]) ?>
        <div class="mb-3">
          <span class="form-label d-block">पूरी ख़बर <?= error('content') ? '<span class="text-danger small">' . e(error('content')) . '</span>' : '' ?></span>
          <div class="rte" data-editor data-upload="<?= e(route('admin.editor.upload')) ?>">
            <textarea name="content" class="rte-source" aria-label="ख़बर का लेख"><?= e(old('content', $news['content'] ?? '')) ?></textarea>
          </div>
          <div class="form-text" data-word-count></div>
        </div>
      </div></section>

      <section class="panel mt-3">
        <div class="panel-head"><h2><i class="fa-solid fa-photo-film me-2 text-body-secondary"></i>इमेज, वीडियो, ऑडियो</h2></div>
        <div class="panel-body">
          <div class="row">
            <div class="col-md-6"><?= media_field('featured_image', 'मुख्य इमेज', $news['featured_image'] ?? '', ['help' => '16:9, कम से कम 1200px चौड़ी']) ?></div>
            <div class="col-md-6">
              <?= field('text', 'image_caption', 'इमेज कैप्शन', $news['image_caption'] ?? '', ['attrs' => ['maxlength' => 300]]) ?>
              <?= field('text', 'image_credit', 'इमेज क्रेडिट', $news['image_credit'] ?? '', ['placeholder' => 'फ़ोटो: संवाददाता', 'attrs' => ['maxlength' => 150]]) ?>
            </div>
          </div>
          <div class="mb-3">
            <span class="form-label d-block">फ़ोटो गैलरी</span>
            <div class="media-list" data-media-list data-name="gallery[]" data-kind="image" data-max="<?= NewsService::MAX_GALLERY ?>">
              <?php foreach ($gallery as $g): ?><span class="ml-item" data-id="<?= (int) $g['id'] ?>"><img src="<?= e(media_url($g, 'thumb')) ?>" alt=""><input type="hidden" name="gallery[]" value="<?= (int) $g['id'] ?>"><button type="button" class="ml-remove" aria-label="हटाएँ">×</button></span><?php endforeach; ?>
              <?php if (can('media.view')): ?><button type="button" class="ml-add" data-ml-add><i class="fa-solid fa-plus"></i><span>जोड़ें</span></button><?php endif; ?>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6"><?= field('url', 'video_url', 'वीडियो (YouTube लिंक)', $news['video_url'] ?? '', ['placeholder' => 'https://youtu.be/…', 'help' => 'या लाइब्रेरी का वीडियो: नीचे से चुनें', 'attrs' => ['maxlength' => 500]]) ?>
              <?php if (can('media.view')): ?><button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-media-pick="#f_video_url" data-kind="video" data-pick-value="file"><i class="fa-solid fa-film me-1"></i>लाइब्रेरी से वीडियो</button><?php endif; ?></div>
            <div class="col-md-6"><?= media_field('audio_file', 'ऑडियो', $news['audio_file'] ?? '', ['kind' => 'audio']) ?></div>
          </div>
        </div>
      </section>

      <section class="panel mt-3">
        <div class="panel-head"><h2><i class="fa-brands fa-google me-2 text-body-secondary"></i>SEO</h2><span class="small text-body-secondary">लिखते-लिखते जाँच</span></div>
        <div class="panel-body" data-seo-box>
          <?= field('text', 'focus_keyword', 'फ़ोकस कीवर्ड', $news['focus_keyword'] ?? '', ['placeholder' => 'जैसे: गोरखपुर बाढ़', 'attrs' => ['maxlength' => 100],
              'help' => 'वह शब्द जिससे लोग यह ख़बर Google पर खोजेंगे। शीर्षक, विवरण और पहले पैराग्राफ़ में आए।']) ?>
          <div class="seo-meter" aria-live="polite"><span class="small fw-semibold">SEO स्कोर</span><span class="bar"><i data-seo-bar></i></span><b data-seo-score>–</b></div>
          <ul class="seo-tips" data-seo-tips></ul>
          <div class="serp" aria-hidden="true">
            <span class="serp-url"><?= e(parse_url((string) config('app.url'), PHP_URL_HOST)) ?> › news › <span data-serp-slug><?= e($news['slug'] ?? '') ?></span></span>
            <span class="serp-title" data-serp-title><?= e(($news['meta_title'] ?? '') ?: ($news['title'] ?? 'ख़बर का शीर्षक')) ?></span>
            <span class="serp-desc" data-serp-desc><?= e(($news['meta_description'] ?? '') ?: 'ख़बर का विवरण यहाँ दिखेगा।') ?></span>
          </div>
          <?= field('text', 'meta_title', 'SEO शीर्षक', $news['meta_title'] ?? '', ['help' => 'ख़ाली हो तो ख़बर का शीर्षक', 'attrs' => ['maxlength' => 190, 'data-count' => 60]]) ?>
          <?= field('textarea', 'meta_description', 'SEO विवरण', $news['meta_description'] ?? '', ['rows' => 2, 'help' => 'ख़ाली हो तो सार', 'attrs' => ['maxlength' => 320, 'data-count' => 160]]) ?>
          <div class="row">
            <div class="col-md-6"><?= field('text', 'meta_keywords', 'कीवर्ड', $news['meta_keywords'] ?? '', ['placeholder' => 'ख़ाली हो तो टैग']) ?></div>
            <div class="col-md-6"><?= field('select', 'robots', 'सर्च इंजन', $news['robots'] ?? 'index,follow', ['options' => News::ROBOTS]) ?></div>
          </div>
          <?= field('switch', 'allow_comments', 'इस ख़बर पर पाठकों की टिप्पणियाँ', (int) ($news['allow_comments'] ?? 1)) ?>
          <?= field('url', 'canonical_url', 'Canonical URL (वैकल्पिक)', $news['canonical_url'] ?? '', ['help' => 'सिर्फ़ तब, जब यह ख़बर मूल रूप से किसी और वेबसाइट की हो']) ?>
          <?php $faqRows = \App\Services\SeoService::faqItems($news['faq'] ?? null);
          if (is_array(old('faq_q'))) {
              $faqRows = array_map(static fn($q, $a) => ['q' => $q, 'a' => $a], (array) old('faq_q'), (array) old('faq_a', []));
          } ?>
          <details class="mb-3"<?= ($news['og_title'] ?? '') || ($news['og_description'] ?? '') || ($news['og_image'] ?? '') || error('og_image') ? ' open' : '' ?>>
            <summary class="fw-semibold mb-2"><i class="fa-solid fa-share-nodes me-1 text-body-secondary"></i>सोशल शेयर (WhatsApp / Facebook / X) अलग से</summary>
            <?= field('text', 'og_title', 'शेयर शीर्षक', $news['og_title'] ?? '', ['help' => 'ख़ाली = SEO शीर्षक', 'attrs' => ['maxlength' => 190]]) ?>
            <?= field('textarea', 'og_description', 'शेयर विवरण', $news['og_description'] ?? '', ['rows' => 2, 'help' => 'ख़ाली = SEO विवरण', 'attrs' => ['maxlength' => 320]]) ?>
            <?= media_field('og_image', 'शेयर इमेज (1200×630)', $news['og_image'] ?? '', ['help' => 'ख़ाली = मुख्य इमेज']) ?>
          </details>
          <details<?= $faqRows || error('faq') ? ' open' : '' ?>>
            <summary class="fw-semibold mb-2"><i class="fa-solid fa-circle-question me-1 text-body-secondary"></i>FAQ (सवाल-जवाब) <span class="small fw-normal text-body-secondary">ख़बर के नीचे दिखेंगे + FAQ स्कीमा</span></summary>
            <?php if (error('faq')): ?><div class="alert alert-danger small py-2"><?= e(error('faq')) ?></div><?php endif; ?>
            <div class="faq-rows" data-faq-rows>
              <?php foreach ($faqRows as $f): ?>
                <div class="faq-row"><button type="button" class="btn-close" data-faq-remove aria-label="यह सवाल हटाएँ"></button>
                  <input class="form-control mb-2" name="faq_q[]" value="<?= e($f['q']) ?>" maxlength="300" placeholder="सवाल" aria-label="सवाल">
                  <textarea class="form-control" name="faq_a[]" rows="2" maxlength="2000" placeholder="जवाब" aria-label="जवाब"><?= e($f['a']) ?></textarea></div>
              <?php endforeach; ?>
            </div>
            <template data-faq-template><div class="faq-row"><button type="button" class="btn-close" data-faq-remove aria-label="यह सवाल हटाएँ"></button>
              <input class="form-control mb-2" name="faq_q[]" maxlength="300" placeholder="सवाल" aria-label="सवाल"><textarea class="form-control" name="faq_a[]" rows="2" maxlength="2000" placeholder="जवाब" aria-label="जवाब"></textarea></div></template>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-faq-add><i class="fa-solid fa-plus me-1"></i>सवाल जोड़ें</button>
          </details>
        </div>
      </section>
    </div>

    <div class="col-xl-4">
      <div class="sticky-xl news-side">
      <section class="panel">
        <div class="panel-head"><h2>प्रकाशन</h2><?= NewsWorkflow::badge($status) ?></div>
        <div class="panel-body">
          <?php if (!$isNew && $news['published_at']): ?><p class="small mb-2"><i class="fa-solid fa-globe me-1"></i>प्रकाशित: <?= hindi_date($news['published_at'], true) ?></p><?php endif; ?>
          <?php if (!$isNew && $news['scheduled_at']): ?><p class="small mb-2"><i class="fa-regular fa-clock me-1"></i>शेड्यूल: <?= hindi_date($news['scheduled_at'], true) ?></p><?php endif; ?>
          <?php if ($reporters): ?>
            <?= field('select', 'reporter_id', 'रिपोर्टर', $news['reporter_id'] ?? auth()->id(), ['options' => array_column($reporters, 'name', 'id')]) ?>
          <?php endif; ?>
          <?php if ($published): ?>
            <?= field('textarea', 'change_reason', 'बदलाव का कारण (ज़रूरी)', '', ['rows' => 2, 'required' => true, 'placeholder' => 'जैसे: मृतकों की संख्या अपडेट की', 'help' => 'हिस्ट्री में दर्ज होगा']) ?>
            <?= field('switch', 'is_correction', 'वेबसाइट पर “सुधार/अपडेट” सूचना दिखाएँ (कारण ही सूचना होगा)', 0) ?>
          <?php elseif (!$isNew): ?>
            <?= field('text', 'change_reason', 'बदलाव का नोट (वैकल्पिक)', '', ['placeholder' => 'हिस्ट्री में दिखेगा', 'attrs' => ['maxlength' => 500]]) ?>
          <?php endif; ?>
          <?php if (isset($thenActions['schedule'])): ?>
            <div class="schedule-box" data-schedule-box hidden>
              <label class="form-label" for="f_scheduled_at">कब प्रकाशित हो?</label>
              <input class="form-control mb-2" type="datetime-local" id="f_scheduled_at" name="scheduled_at" value="<?= e(old('scheduled_at', $news && $news['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($news['scheduled_at'])) : '')) ?>" min="<?= date('Y-m-d\TH:i', time() + 180) ?>">
            </div>
          <?php endif; ?>
          <div class="d-grid gap-2 mt-2">
            <button class="btn btn-dark" type="submit" name="then" value=""><i class="fa-solid fa-floppy-disk me-1"></i> <?= $isNew || in_array($status, ['draft', 'rejected'], true) ? 'ड्राफ़्ट सेव करें' : 'सेव करें' ?></button>
            <?php foreach ($thenActions as $a => $ic): if (!$thenLabels[$a]) continue; ?>
              <button class="btn <?= $a === 'publish' ? 'btn-brand' : 'btn-outline-secondary' ?>" type="submit" name="then" value="<?= e($a) ?>"<?= $a === 'schedule' ? ' data-schedule-btn' : '' ?><?= $a === 'publish' ? ' data-confirm-then="ख़बर तुरंत वेबसाइट पर प्रकाशित होगी।"' : '' ?>><i class="fa-solid <?= e($ic) ?> me-1"></i> <?= e($thenLabels[$a]) ?></button>
            <?php endforeach; ?>
          </div>
          <?php if (!$isNew): ?><p class="small text-body-secondary mt-2 mb-0">अस्वीकार, फ़ैक्ट चेक, बंद, आर्काइव जैसे काम <a href="<?= e(route('admin.news.history', ['id' => $news['id']])) ?>">स्थिति और हिस्ट्री</a> पेज पर।</p><?php endif; ?>
        </div>
      </section>

      <section class="panel mt-3">
        <div class="panel-head"><h2>श्रेणी, लोकेशन, टैग</h2></div>
        <div class="panel-body">
          <?= field('select', 'category_id', 'श्रेणी / उप-श्रेणी', $news['category_id'] ?? ($assignment['category_id'] ?? ''), ['options' => $categories, 'empty' => 'चुनें…', 'help' => 'प्रकाशन के लिए ज़रूरी']) ?>
          <div class="mb-3">
            <label class="form-label" for="locSearch">लोकेशन</label>
            <div class="loc-picker" data-location-picker data-search="<?= e(route('admin.locations.search')) ?>">
              <input type="hidden" name="location_id" value="<?= e(old('location_id', $location['id'] ?? '')) ?>">
              <input type="search" class="form-control<?= error('location_id') ? ' is-invalid' : '' ?>" id="locSearch" autocomplete="off" value="<?= e($location['name'] ?? '') ?>" placeholder="ज़िला, शहर, तहसील… खोजें" role="combobox" aria-expanded="false" aria-controls="locList">
              <ul class="loc-results list-group" id="locList" role="listbox" hidden></ul>
            </div>
            <div class="form-text"><?= $location ? e($location['chain']) : 'सबसे नीचे वाला स्तर चुनें (जैसे शहर); राज्य और ज़िला अपने आप जुड़ जाते हैं।' ?></div>
          </div>
          <?php if ($topics): ?>
            <span class="form-label d-block">टॉपिक / विशेष सेक्शन</span>
            <div class="check-scroll mb-3">
              <?php foreach ($topics as $t): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="topics[]" value="<?= (int) $t['id'] ?>"<?= checked(in_array((int) $t['id'], $oldTopics, true)) ?>> <span class="form-check-label"><?= e($t['name']) ?><?= $t['type'] === 'special' ? ' <small class="text-body-secondary">(विशेष)</small>' : '' ?></span></label><?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="mb-1">
            <label class="form-label" for="tagInput">टैग</label>
            <div class="tag-input" data-tag-input data-search="<?= can('tags.view') || can('news.create') ? e(route('admin.tags.search')) : '' ?>" data-max="<?= NewsService::MAX_TAGS ?>">
              <input type="hidden" name="tags" value="<?= e($tagsVal) ?>">
              <input type="text" class="form-control<?= error('tags') ? ' is-invalid' : '' ?>" id="tagInput" placeholder="टैग लिखें, Enter दबाएँ" autocomplete="off">
              <ul class="loc-results list-group" hidden></ul>
            </div>
            <div class="form-text"><?= error('tags') ? '<span class="text-danger">' . e(error('tags')) . '</span>' : 'ज़्यादा से ज़्यादा ' . NewsService::MAX_TAGS . '; नया टैग अपने आप बनेगा' ?></div>
          </div>
        </div>
      </section>

      <?php if ($desk): ?>
      <section class="panel mt-3">
        <div class="panel-head"><h2>फ़्लैग</h2></div>
        <div class="panel-body flag-grid">
          <?php foreach (News::FLAGS as $f => [$l, $ic]): ?>
            <label class="chip-check"><input type="hidden" name="<?= e($f) ?>" value="0"><input type="checkbox" name="<?= e($f) ?>" value="1"<?= checked((int) old($f, $news[$f] ?? 0)) ?>><span><i class="fa-solid <?= e($ic) ?> me-1"></i><?= e($l) ?></span></label>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section class="panel mt-3">
        <div class="panel-head"><h2>स्रोत और संबंधित</h2></div>
        <div class="panel-body">
          <?= field('text', 'source', 'स्रोत', $news['source'] ?? '', ['placeholder' => 'जैसे: पुलिस प्रेस नोट, PTI', 'attrs' => ['maxlength' => 150]]) ?>
          <?= field('text', 'news_credit', 'न्यूज़ क्रेडिट', $news['news_credit'] ?? '', ['placeholder' => 'इनपुट: …', 'attrs' => ['maxlength' => 150]]) ?>
          <label class="form-label" for="relSearch">संबंधित ख़बरें</label>
          <div class="news-picker" data-news-picker data-search="<?= e(route('admin.news.search')) ?>" data-name="related[]" data-exclude="<?= (int) ($news['id'] ?? 0) ?>" data-max="<?= NewsService::MAX_RELATED ?>">
            <ul class="np-list">
              <?php foreach ($related as $r): ?><li data-id="<?= (int) $r['id'] ?>"><span>#<?= (int) $r['id'] ?> <?= e($r['title']) ?></span><input type="hidden" name="related[]" value="<?= (int) $r['id'] ?>"><button type="button" class="ml-remove" aria-label="हटाएँ">×</button></li><?php endforeach; ?>
            </ul>
            <input type="search" class="form-control" id="relSearch" autocomplete="off" placeholder="शीर्षक या ID से खोजें">
            <ul class="loc-results list-group" hidden></ul>
          </div>
        </div>
      </section>

      <?php if (!$isNew): ?>
      <section class="panel mt-3" id="remarks">
        <div class="panel-head"><h2>टिप्पणियाँ</h2><a class="small" href="<?= e(route('admin.news.history', ['id' => $news['id']])) ?>">पूरी हिस्ट्री</a></div>
        <div class="panel-body">
          <?= $this->insert('admin/news/_remarks', ['remarks' => array_slice($remarks, 0, 5)]) ?>
        </div>
      </section>
      <?php endif; ?>
      </div>
    </div>
  </div>
</form>
<?php if (!$isNew): ?>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <?php if (can('news.create')): ?><form method="post" action="<?= e(route('admin.news.duplicate', ['id' => $news['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fa-regular fa-copy me-1"></i>कॉपी बनाएँ</button></form><?php endif; ?>
    <?php if (can('news.delete') && ($desk || in_array($status, NewsWorkflow::OWNER_EDITABLE, true))): ?><form method="post" action="<?= e(route('admin.news.trash', ['id' => $news['id']])) ?>" data-confirm="ख़बर ट्रैश में जाएगी<?= $published ? ' और वेबसाइट से हट जाएगी' : '' ?>।"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit"><i class="fa-regular fa-trash-can me-1"></i>ट्रैश</button></form><?php endif; ?>
  </div>
<?php endif; ?>
