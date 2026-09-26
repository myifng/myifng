<?php
use App\Models\LiveBlog;
$this->layout('layouts/admin');
$title = 'लाइव: ' . $news['title'];
$badge = ['live' => 'danger', 'paused' => 'warning', 'ended' => 'secondary'];
$published = $news['status'] === 'published';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.live_blogs.index')) ?>">लाइव ब्लॉग</a></li><li class="breadcrumb-item active" aria-current="page">कंट्रोल</li></ol></nav>
    <h1><span class="badge-status text-bg-<?= e($badge[$blog['status']]) ?> me-2 align-middle"><i class="dot"></i><?= e(LiveBlog::STATUSES[$blog['status']]) ?></span><?= e($news['title']) ?></h1>
    <p>शुरू: <?= hindi_date($blog['started_at'], true) ?><?= $blog['ended_at'] ? ' · ख़त्म: ' . hindi_date($blog['ended_at'], true) : '' ?> · <span data-lu-count><?= num(count($updates)) ?></span> अपडेट
      <?php if (!$published): ?> · <span class="text-warning-emphasis"><i class="fa-solid fa-triangle-exclamation"></i> ख़बर प्रकाशित नहीं, वेबसाइट पर नहीं दिखेगा</span><?php endif; ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($published): ?><a class="btn btn-outline-secondary" href="<?= e(route('news.show', ['slug' => $news['slug']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>वेबसाइट पर</a><?php endif; ?>
    <?php if (can('news.edit')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.news.edit', ['id' => $news['id']])) ?>"><i class="fa-solid fa-pen me-1"></i>ख़बर</a><?php endif; ?>
    <?php if (can('live_blogs.publish')): foreach (['live' => ['fa-play', 'लाइव करें', 'btn-danger'], 'paused' => ['fa-pause', 'रोकें', 'btn-outline-warning'], 'ended' => ['fa-flag-checkered', 'ख़त्म करें', 'btn-outline-secondary']] as $s => [$ic, $l, $cls]): if ($s === $blog['status']) continue; ?>
      <form method="post" action="<?= e(route('admin.live_blogs.status', ['id' => $blog['id']])) ?>"<?= $s === 'ended' ? ' data-confirm="लाइव ब्लॉग ख़त्म होगा; अपडेट पेज पर बने रहेंगे।"' : '' ?>><?= csrf_field() ?><input type="hidden" name="status" value="<?= e($s) ?>"><button class="btn <?= e($cls) ?>" type="submit"><i class="fa-solid <?= e($ic) ?> me-1"></i><?= e($l) ?></button></form>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="row g-3">
  <?php if (can('live_blogs.create')): ?>
  <div class="col-xl-5">
    <section class="panel sticky-xl">
      <div class="panel-head"><h2 data-lu-mode>नया अपडेट</h2></div>
      <div class="panel-body">
        <form method="post" action="<?= e(route('admin.live_blogs.updates.store', ['id' => $blog['id']])) ?>" data-lu-form data-store="<?= e(route('admin.live_blogs.updates.store', ['id' => $blog['id']])) ?>"
              data-update-base="<?= e(url(config('app.admin_path', 'admin') . '/live-blogs/' . $blog['id'] . '/updates/')) ?>" novalidate>
          <?= csrf_field() ?>
          <?= field('text', 'title', 'शीर्षक (वैकल्पिक)', '', ['attrs' => ['maxlength' => 255], 'placeholder' => 'जैसे: पहले रुझान आए']) ?>
          <?= field('textarea', 'body', 'अपडेट', '', ['rows' => 5, 'attrs' => ['maxlength' => 5000], 'help' => 'ख़ाली लाइन = नया पैराग्राफ़; **शब्द** = बोल्ड; लिंक अपने आप। Ctrl + Enter से भेजें।']) ?>
          <?= media_field('image', 'इमेज', '') ?>
          <?= field('url', 'embed_url', 'वीडियो / लिंक', '', ['placeholder' => 'YouTube या कोई https:// लिंक', 'attrs' => ['maxlength' => 500]]) ?>
          <div class="d-flex gap-3 flex-wrap mb-2">
            <?= field('switch', 'is_key', 'अहम अपडेट', 0, ['wrap' => '']) ?>
            <?= field('switch', 'is_pinned', 'ऊपर पिन करें', 0, ['wrap' => '']) ?>
          </div>
          <details class="mb-3"><summary class="small">समय बदलें</summary><?= field('datetime-local', 'posted_at', 'समय (ख़ाली = अभी)', '', ['wrap' => 'mt-2']) ?></details>
          <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-brand" type="submit" data-lu-submit data-no-lock><i class="fa-solid fa-paper-plane me-1"></i> <span>अपडेट डालें</span></button>
            <button class="btn btn-light" type="button" data-lu-cancel hidden>रद्द करें</button>
            <span class="small text-body-secondary ms-auto" data-lu-status role="status" aria-live="polite"></span>
          </div>
        </form>
      </div>
    </section>
  </div>
  <?php endif; ?>
  <div class="<?= can('live_blogs.create') ? 'col-xl-7' : 'col-12' ?>">
    <section class="panel">
      <div class="panel-head"><h2>टाइमलाइन</h2></div>
      <ul class="lu-list" data-lu-list>
        <?php foreach ($updates as $u): ?><?= $this->insert('admin/live-blogs/_update', ['u' => $u]) ?><?php endforeach; ?>
      </ul>
      <div class="empty-state" data-lu-empty<?= $updates ? ' hidden' : '' ?>><i class="fa-regular fa-clock"></i><p>अभी कोई अपडेट नहीं। बाईं ओर से पहला अपडेट डालें।</p></div>
    </section>
    <?php if (can('live_blogs.delete')): ?>
      <div class="danger-zone mt-3">
        <div><b>लाइव ब्लॉग हटाएँ</b><p class="mb-0 small">सारे अपडेट हट जाएँगे; ख़बर बनी रहेगी।</p></div>
        <?= delete_button(route('admin.live_blogs.destroy', ['id' => $blog['id']]), 'लाइव ब्लॉग और उसके सारे अपडेट हमेशा के लिए हट जाएँगे।', 'हटाएँ', 'btn btn-outline-danger') ?>
      </div>
    <?php endif; ?>
  </div>
</div>
