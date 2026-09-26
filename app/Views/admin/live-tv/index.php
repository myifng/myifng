<?php
use App\Models\LiveTvChannel;
use App\Services\LiveTvService;
$this->layout('layouts/admin');
$title = 'लाइव टीवी';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">लाइव टीवी</li></ol></nav>
    <h1>लाइव टीवी</h1>
    <p>YouTube लाइव, एम्बेड या स्ट्रीमिंग चैनल। डिफ़ॉल्ट चैनल वेबसाइट के <a href="<?= e(route('live_tv')) ?>" target="_blank" rel="noopener">/live-tv</a> पेज, हेडर के LIVE बटन और होमपेज ब्लॉक में दिखता है।</p>
  </div>
  <?php if (can('live_tv.edit')): ?><a class="btn btn-brand" href="<?= e(route('admin.live_tv.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया चैनल</a><?php endif; ?>
</div>
<?php if (!$channels): ?>
  <section class="panel"><div class="empty-state"><i class="fa-solid fa-tv"></i>
    <p>अभी कोई चैनल नहीं है।<?= $fallback ? ' अभी सेटिंग वाला YouTube लिंक इस्तेमाल हो रहा है।' : '' ?></p>
    <?php if (can('live_tv.edit')): ?><a class="btn btn-brand" href="<?= e(route('admin.live_tv.create')) ?>">पहला चैनल जोड़ें</a><?php endif; ?>
  </div></section>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($channels as $c): ?>
  <div class="col-md-6 col-xl-4">
    <section class="panel h-100 ltv-card<?= $c['status'] !== 'active' ? ' is-off' : '' ?>">
      <div class="panel-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <?php if ($c['logo']): ?><img class="thumb-sm" src="<?= e(media_url($c['logo'], 'thumb')) ?>" alt=""><?php else: ?><span class="ltv-logo"><i class="fa-solid fa-tv"></i></span><?php endif; ?>
          <div class="min-w-0">
            <b class="d-block text-truncate"><?= e($c['name']) ?></b>
            <span class="small text-body-secondary"><?= e(LiveTvChannel::SOURCES[$c['source_type']]) ?></span>
          </div>
        </div>
        <div class="d-flex gap-1 flex-wrap mb-2">
          <?php if ($c['is_default']): ?><span class="badge text-bg-dark">डिफ़ॉल्ट</span><?php endif; ?>
          <?= $c['status'] === 'active' ? ($c['is_live'] ? '<span class="badge text-bg-danger"><i class="fa-solid fa-circle fa-2xs me-1"></i>लाइव</span>' : '<span class="badge text-bg-secondary">ऑफ़-एयर</span>') : status_badge('inactive') ?>
          <?php if (!$c['valid']): ?><span class="badge text-bg-warning">लिंक ग़लत</span><?php endif; ?>
        </div>
        <p class="small mb-2"><?= $c['now'] ? '<b>अभी:</b> ' . e($c['now']['title']) . ' (' . e(LiveTvService::time($c['now']['start_time'])) . ' – ' . e(LiveTvService::time($c['now']['end_time'])) . ')' : 'अभी कोई तय कार्यक्रम नहीं' ?> · <?= num($c['programs']) ?> कार्यक्रम</p>
        <div class="d-flex gap-2 flex-wrap">
          <?php if (can('live_tv.edit')): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.live_tv.edit', ['id' => $c['id']])) ?>"><i class="fa-solid fa-pen me-1"></i>बदलें / कार्यक्रम</a>
            <form method="post" action="<?= e(route('admin.live_tv.toggle', ['id' => $c['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm <?= $c['is_live'] ? 'btn-outline-danger' : 'btn-danger' ?>" type="submit"><?= $c['is_live'] ? 'ऑफ़-एयर करें' : 'लाइव करें' ?></button></form>
          <?php endif; ?>
          <?php if ($c['status'] === 'active'): ?><a class="btn btn-sm btn-light" href="<?= e(route('live_tv.channel', ['slug' => $c['slug']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
        </div>
      </div>
    </section>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
