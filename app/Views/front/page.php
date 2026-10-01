<?php
$this->layout('layouts/front');
$tpl = $page['template'];
$side = $tpl === 'default' || $tpl === 'contact';
?>
<div class="wrap page-wrap<?= $side ? ' with-side' : '' ?>">
  <article class="card article-page tpl-<?= e($tpl) ?>">
    <?php if ($tpl !== 'landing'): ?>
      <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page"><?= e($page['title']) ?></span></nav>
      <h1 class="page-title"><?= e($page['title']) ?></h1>
      <?php if ($page['excerpt']): ?><p class="dek"><?= e($page['excerpt']) ?></p><?php endif; ?>
    <?php endif; ?>
    <?php if ($page['featured_image']): ?><figure class="feature"><img src="<?= e(upload_url($page['featured_image'])) ?>" alt="<?= e($page['title']) ?>"></figure><?php endif; ?>
    <div class="prose<?= prose_align_class() ?>"><?= $content /* सेव करते समय sanitize, दिखाते समय shortcode */ ?></div>
    <?php if ($tpl !== 'landing'): ?><p class="updated">आख़िरी अपडेट: <?= hindi_date($page['updated_at']) ?></p><?php endif; ?>
  </article>
  <?php if ($side): ?>
    <aside class="side">
      <?php if ($tpl === 'contact'): ?>
        <section class="card contact-card">
          <h2 class="box-title">संपर्क जानकारी</h2>
          <ul class="fcontact dark">
            <?php if (setting('address')): ?><li><i class="fa-solid fa-location-dot"></i><?= e(setting('address')) ?></li><?php endif; ?>
            <?php if (setting('contact_phone')): ?><li><i class="fa-solid fa-phone"></i><?= e(setting('contact_phone')) ?></li><?php endif; ?>
            <?php if (setting('whatsapp')): ?><li><i class="fa-brands fa-whatsapp"></i><a href="https://wa.me/<?= e(preg_replace('/\D/', '', (string) setting('whatsapp'))) ?>" target="_blank" rel="noopener"><?= e(setting('whatsapp')) ?></a></li><?php endif; ?>
            <?php if (setting('contact_email')): ?><li><i class="fa-solid fa-envelope"></i><?= e(setting('contact_email')) ?></li><?php endif; ?>
            <?php if (setting('news_email')): ?><li><i class="fa-solid fa-newspaper"></i>ख़बर भेजें: <?= e(setting('news_email')) ?></li><?php endif; ?>
          </ul>
          <?php if (setting('map_url')): ?><a class="btn-cta" href="<?= e(setting('map_url')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-map-location-dot"></i> नक्शे पर देखें</a><?php endif; ?>
        </section>
      <?php endif; ?>
      <?php $quick = App\Services\MenuService::tree('quick'); if ($quick['items']): ?>
        <section class="card"><h2 class="box-title"><?= e($quick['name']) ?></h2><ul class="side-links"><?php foreach ($quick['items'] as $it): ?><li><a href="<?= e($it['href']) ?>"><?= e($it['title']) ?></a></li><?php endforeach; ?></ul></section>
      <?php endif; ?>
    </aside>
  <?php endif; ?>
</div>
