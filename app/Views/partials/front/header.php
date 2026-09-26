<?php
/** वेबसाइट हेडर: टॉप बार, लोगो, मुख्य मेनू (नेस्टेड), मोबाइल ड्रॉअर */
$social = array_filter(['facebook' => 'fa-facebook-f', 'twitter' => 'fa-x-twitter', 'youtube' => 'fa-youtube', 'instagram' => 'fa-instagram', 'telegram' => 'fa-telegram', 'linkedin' => 'fa-linkedin-in', 'whatsapp_channel' => 'fa-whatsapp'], fn($i, $k) => setting($k) !== '', ARRAY_FILTER_USE_BOTH);
$words = preg_split('/\s+/u', (string) setting('site_name'), 2);

$renderMenu = function (array $items, int $depth = 0) use (&$renderMenu): string {
    $h = '';
    foreach ($items as $it) {
        $kids = !empty($it['children']);
        $attrs = $it['target_blank'] ? ' target="_blank" rel="noopener"' : '';
        $h .= '<li' . ($kids ? ' class="has-sub"' : '') . '><a href="' . e($it['href']) . '"' . $attrs . '>' . e($it['title']) . ($kids ? ' <i class="fa-solid fa-chevron-down caret" aria-hidden="true"></i>' : '') . '</a>';
        if ($kids) {
            $h .= '<button class="sub-toggle" type="button" aria-label="' . e($it['title']) . ' का उप-मेनू" aria-expanded="false"><i class="fa-solid fa-chevron-down"></i></button><ul class="sub">' . $renderMenu($it['children'], $depth + 1) . '</ul>';
        }
        $h .= '</li>';
    }
    return $h;
};
?>
<header class="site-header">
  <?php if (setting('show_topbar', '1') === '1'): ?>
  <div class="topbar">
    <div class="wrap">
      <?php if (setting('show_date', '1') === '1'): ?><span class="today"><?= hindi_date(time(), false, true) ?></span><?php endif; ?>
      <?php if ($top['items']): ?><ul class="top-links"><?php foreach ($top['items'] as $it): ?><li><a href="<?= e($it['href']) ?>"<?= $it['target_blank'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($it['title']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($social): ?><div class="social"><?php foreach ($social as $k => $ic): ?><a href="<?= e(setting($k)) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($k)) ?>"><i class="fa-brands <?= e($ic) ?>"></i></a><?php endforeach; ?></div><?php endif; ?>
      <button type="button" class="theme-btn js-theme" aria-label="डार्क या लाइट मोड"><i class="fa-solid fa-circle-half-stroke"></i></button>
    </div>
  </div>
  <?php endif; ?>
  <div class="masthead">
    <div class="wrap">
      <a class="logo" href="<?= e(url()) ?>" aria-label="<?= e(setting('site_name')) ?> होम">
        <?php if (setting('logo')): ?>
          <picture><?php if (setting('logo_mobile')): ?><source media="(max-width: 600px)" srcset="<?= e(upload_url(setting('logo_mobile'))) ?>"><?php endif; ?><img src="<?= e(upload_url(setting('logo'))) ?>" alt="<?= e(setting('site_name')) ?>"></picture>
        <?php else: ?>
          <span class="la"><?= e($words[0]) ?></span><?php if (!empty($words[1])): ?><span class="lb"><?= e($words[1]) ?></span><?php endif; ?>
        <?php endif; ?>
      </a>
      <?php if (setting('tagline')): ?><span class="tagline"><?= e(setting('tagline')) ?></span><?php endif; ?>
      <?php if (app('router')->has('api.my_city')): $city = my_city(); ?>
      <div class="mycity" data-mycity data-search="<?= e(route('api.locations')) ?>" data-save="<?= e(route('api.my_city')) ?>">
        <button type="button" class="mycity-btn" aria-expanded="false" aria-controls="mycityPanel" data-mycity-toggle>
          <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
          <span><small>मेरा शहर</small><b data-mycity-name><?= $city ? e($city['name']) : 'चुनें' ?></b></span>
        </button>
        <div class="mycity-panel" id="mycityPanel" role="dialog" aria-label="अपना शहर चुनें" hidden>
          <label class="mycity-label" for="mycityInput">अपना शहर या ज़िला खोजें</label>
          <input type="search" id="mycityInput" autocomplete="off" placeholder="जैसे: गोरखपुर, Lucknow" data-mycity-input>
          <p class="mycity-hint" data-mycity-hint>लोकप्रिय शहर</p>
          <ul class="mycity-list" data-mycity-list role="listbox" aria-label="शहर"></ul>
          <button type="button" class="mycity-clear" data-mycity-clear<?= $city ? '' : ' hidden' ?>>शहर हटाएँ</button>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <nav class="mainnav" aria-label="मुख्य मेनू">
    <div class="wrap">
      <button class="nav-toggle js-drawer" type="button" aria-label="मेनू खोलें" aria-controls="drawer" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
      <ul class="menu"><?= $renderMenu($main['items']) ?></ul>
      <?php if (setting('live_tv_url')): ?><a class="live" href="<?= e(app('router')->has('live_tv') ? route('live_tv') : setting('live_tv_url')) ?>"<?= app('router')->has('live_tv') ? '' : ' target="_blank" rel="noopener"' ?>><i></i>LIVE</a><?php endif; ?>
    </div>
  </nav>
  <div class="drawer" id="drawer" hidden>
    <div class="drawer-panel" role="dialog" aria-modal="true" aria-label="मेनू">
      <div class="drawer-head"><b><?= e(setting('site_name')) ?></b><button type="button" class="js-drawer-close" aria-label="मेनू बंद करें"><i class="fa-solid fa-xmark"></i></button></div>
      <ul class="drawer-menu"><?= $renderMenu(($mobile['items'] ?: $main['items'])) ?></ul>
      <?php if ($social): ?><div class="social drawer-social"><?php foreach ($social as $k => $ic): ?><a href="<?= e(setting($k)) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($k)) ?>"><i class="fa-brands <?= e($ic) ?>"></i></a><?php endforeach; ?></div><?php endif; ?>
    </div>
  </div>
</header>
