<?php
use App\Models\LiveBlog;
$this->layout('layouts/admin');
$title = 'लाइव ब्लॉग';
$badge = ['live' => 'danger', 'paused' => 'warning', 'ended' => 'secondary'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">लाइव ब्लॉग</li></ol></nav>
    <h1>लाइव ब्लॉग</h1>
    <p>चुनाव नतीजे, बजट, मैच जैसी चलती ख़बरें: ख़बर के पेज पर समय वाली टाइमलाइन, पाठक के पेज पर अपने आप नए अपडेट।</p>
  </div>
</div>
<div class="row g-3">
  <?php if (can('live_blogs.publish')): ?>
  <div class="col-xl-4 order-xl-2">
    <section class="panel">
      <div class="panel-head"><h2><i class="fa-solid fa-tower-broadcast me-2 text-danger"></i>नया लाइव ब्लॉग</h2></div>
      <div class="panel-body">
        <form method="post" action="<?= e(route('admin.live_blogs.store')) ?>" novalidate>
          <?= csrf_field() ?>
          <label class="form-label" for="lbNews">ख़बर चुनें <span class="text-danger" aria-hidden="true">*</span></label>
          <div class="news-picker mb-2" data-news-picker data-search="<?= e(route('admin.news.search')) ?>" data-name="news_id" data-max="1">
            <ul class="np-list"></ul>
            <input type="search" class="form-control<?= error('news_id') ? ' is-invalid' : '' ?>" id="lbNews" autocomplete="off" placeholder="ख़बर का शीर्षक या ID">
            <ul class="loc-results list-group" hidden></ul>
          </div>
          <?php if (error('news_id')): ?><div class="invalid-feedback d-block"><?= e(error('news_id')) ?></div><?php endif; ?>
          <p class="form-text">पहले ख़बर बनाकर प्रकाशित करें (शीर्षक, सार, इमेज), फिर यहाँ चुनें। अपडेट उसी ख़बर के पेज पर दिखेंगे।</p>
          <div class="d-flex gap-2">
            <button class="btn btn-brand" type="submit"><i class="fa-solid fa-play me-1"></i> लाइव शुरू करें</button>
            <?php if (can('news.create')): ?><a class="btn btn-light" href="<?= e(route('admin.news.create')) ?>">नई ख़बर</a><?php endif; ?>
          </div>
        </form>
      </div>
    </section>
  </div>
  <?php endif; ?>
  <div class="<?= can('live_blogs.publish') ? 'col-xl-8' : 'col-12' ?>">
    <section class="panel">
      <?php if ($items->items): ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 data-table">
          <thead><tr><th>ख़बर</th><th>स्थिति</th><th>अपडेट</th><th>आख़िरी अपडेट</th><th class="text-end">काम</th></tr></thead>
          <tbody>
          <?php foreach ($items->items as $b): ?>
            <tr class="<?= $b['status'] === 'ended' ? 'is-off' : '' ?>">
              <td><a class="fw-semibold text-reset" href="<?= e(route('admin.live_blogs.show', ['id' => $b['id']])) ?>"><?= e($b['title']) ?></a>
                <?php if ($b['news_status'] !== 'published'): ?><span class="badge text-bg-warning ms-1">ख़बर अप्रकाशित</span><?php endif; ?>
                <div class="small text-body-secondary">शुरू: <?= hindi_date($b['started_at'], true) ?></div></td>
              <td><span class="badge-status text-bg-<?= e($badge[$b['status']]) ?>"><i class="dot"></i><?= e(LiveBlog::STATUSES[$b['status']]) ?></span></td>
              <td><?= num($b['updates']) ?></td>
              <td class="small"><?= $b['last_update'] ? e(time_ago($b['last_update'])) : '—' ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-brand" href="<?= e(route('admin.live_blogs.show', ['id' => $b['id']])) ?>"><i class="fa-solid fa-tower-broadcast me-1"></i>कंट्रोल</a>
                <?php if ($b['news_status'] === 'published'): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('news.show', ['slug' => $b['slug']])) ?>" target="_blank" rel="noopener" title="वेबसाइट पर देखें" aria-label="वेबसाइट पर देखें"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
      <?php else: ?>
        <div class="empty-state"><i class="fa-solid fa-tower-broadcast"></i><p>अभी कोई लाइव ब्लॉग नहीं है।</p></div>
      <?php endif; ?>
    </section>
  </div>
</div>
