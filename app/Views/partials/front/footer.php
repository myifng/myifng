<?php
/** वेबसाइट फ़ुटर: परिचय, 4 कॉलम (मेनू बिल्डर), सोशल, ऐप, लीगल मेनू, कॉपीराइट */
use App\Services\MenuService;

$cols = array_filter(array_map(fn($l) => MenuService::tree($l), ['footer_1', 'footer_2', 'footer_3', 'footer_4']), fn($m) => $m['items']);
$legal = MenuService::tree('legal');
$social = array_filter(['facebook' => 'fa-facebook-f', 'twitter' => 'fa-x-twitter', 'youtube' => 'fa-youtube', 'instagram' => 'fa-instagram', 'telegram' => 'fa-telegram', 'linkedin' => 'fa-linkedin-in', 'whatsapp_channel' => 'fa-whatsapp'], fn($i, $k) => setting($k) !== '', ARRAY_FILTER_USE_BOTH);
$words = preg_split('/\s+/u', (string) setting('site_name'), 2);
$copy = strtr((string) setting('copyright_text', '© {year} {site_name}'), ['{year}' => date('Y'), '{site_name}' => setting('site_name')]);
?>
<footer class="site-footer">
  <div class="wrap">
    <div class="fcols">
      <div class="fabout">
        <a class="logo logo-light" href="<?= e(url()) ?>">
          <?php if (setting('logo_dark')): ?><img src="<?= e(upload_url(setting('logo_dark'))) ?>" alt="<?= e(setting('site_name')) ?>"><?php else: ?><span class="la"><?= e($words[0]) ?></span><?php if (!empty($words[1])): ?><span class="lb"><?= e($words[1]) ?></span><?php endif; ?><?php endif; ?>
        </a>
        <?php if (setting('footer_about')): ?><p><?= e(setting('footer_about')) ?></p><?php endif; ?>
        <ul class="fcontact">
          <?php if (setting('address')): ?><li><i class="fa-solid fa-location-dot"></i><?= e(setting('address')) ?></li><?php endif; ?>
          <?php if (setting('contact_phone')): ?><li><i class="fa-solid fa-phone"></i><?= e(setting('contact_phone')) ?></li><?php endif; ?>
          <?php if (setting('contact_email')): ?><li><i class="fa-solid fa-envelope"></i><?= e(setting('contact_email')) ?></li><?php endif; ?>
        </ul>
        <?php if ($social): ?><div class="social"><?php foreach ($social as $k => $ic): ?><a href="<?= e(setting($k)) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($k)) ?>"><i class="fa-brands <?= e($ic) ?>"></i></a><?php endforeach; ?></div><?php endif; ?>
      </div>
      <?php foreach ($cols as $c): ?>
        <div><h2 class="fhead"><?= e($c['name']) ?></h2><ul><?php foreach ($c['items'] as $it): ?><li><a href="<?= e($it['href']) ?>"<?= $it['target_blank'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($it['title']) ?></a></li><?php endforeach; ?></ul></div>
      <?php endforeach; ?>
    </div>
    <?php if (setting('footer_apps', '1') === '1' && (setting('android_app') || setting('ios_app'))): ?>
      <div class="fapps"><span>हमारा ऐप डाउनलोड करें:</span>
        <?php if (setting('android_app')): ?><a href="<?= e(setting('android_app')) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-google-play"></i> Google Play</a><?php endif; ?>
        <?php if (setting('ios_app')): ?><a href="<?= e(setting('ios_app')) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-apple"></i> App Store</a><?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="fbottom">
      <span><?= e($copy) ?></span>
      <?php if ($legal['items']): ?><ul class="legal"><?php foreach ($legal['items'] as $it): ?><li><a href="<?= e($it['href']) ?>"><?= e($it['title']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
  </div>
</footer>
