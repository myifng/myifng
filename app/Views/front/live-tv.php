<?php
use App\Services\LiveTvService;
$this->layout('layouts/front');
?>
<div class="wrap page-wrap with-side live-tv-page">
  <div class="main-col">
    <section class="box ltv">
      <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page">लाइव टीवी</span></nav>
      <h1 class="list-title"><?php if ($channel && $channel['is_live']): ?><span class="ltv-live"><i aria-hidden="true"></i>LIVE</span> <?php endif; ?><?= e($channel['name'] ?? 'लाइव टीवी') ?></h1>
      <?php if ($player): ?>
        <div class="ltv-player">
          <?php if ($player['type'] === 'iframe'): ?>
            <iframe src="<?= e($player['src']) ?>" title="<?= e($channel['name']) ?>" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"<?= $channel['source_type'] === 'embed' ? ' sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"' : '' ?>></iframe>
          <?php else: ?>
            <video controls autoplay muted playsinline preload="none"<?= $channel['logo'] ? ' poster="' . e(media_url($channel['logo'], 'large')) . '"' : '' ?> <?= $player['hls'] ? 'data-hls="' . e($player['src']) . '" data-hls-lib="' . e(asset('vendor/hls/hls.light.min.js')) . '"' : 'src="' . e($player['src']) . '"' ?>></video>
          <?php endif; ?>
        </div>
        <?php if ($now): ?><p class="ltv-now"><b>अभी:</b> <?= e($now['title']) ?><?= $now['host'] ? ' · ' . e($now['host']) : '' ?> <span>(<?= e(LiveTvService::time($now['start_time'])) ?> – <?= e(LiveTvService::time($now['end_time'])) ?>)</span></p><?php endif; ?>
        <?php if (!$channel['is_live']): ?><p class="notice notice-info">यह चैनल अभी ऑफ़-एयर है। शेड्यूल देखें।</p><?php endif; ?>
        <?php if (!empty($channel['description'])): ?><p class="list-desc"><?= e($channel['description']) ?></p><?php endif; ?>
      <?php else: ?>
        <div class="empty"><p>अभी लाइव टीवी उपलब्ध नहीं है। थोड़ी देर बाद देखें।</p></div>
      <?php endif; ?>
      <?php if (count($channels) > 1): ?>
        <nav class="chips mt" aria-label="चैनल"><?php foreach ($channels as $c): ?><a href="<?= e(route('live_tv.channel', ['slug' => $c['slug']])) ?>"<?= $channel && (int) $c['id'] === (int) $channel['id'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?><?= $c['is_live'] ? ' •' : '' ?></a><?php endforeach; ?></nav>
      <?php endif; ?>
    </section>
    <?php if ($videos): ?>
      <section class="box"><?= block_head('ताज़ा वीडियो', route('videos')) ?><div class="cgrid cols-4"><?php foreach ($videos as $v): ?><?= mm_card($v) ?><?php endforeach; ?></div></section>
    <?php endif; ?>
  </div>
  <aside class="side">
    <div class="box">
      <h2 class="box-title">आज का शेड्यूल</h2>
      <?php if ($today): ?>
        <ol class="ltv-schedule">
          <?php foreach ($today as $p): ?>
            <li class="<?= $p['now'] ? 'now' : ($p['done'] ? 'done' : '') ?>"<?= $p['now'] ? ' aria-current="true"' : '' ?>>
              <time><?= e(LiveTvService::time($p['start_time'])) ?></time>
              <div><b><?= e($p['title']) ?></b><?= $p['now'] ? ' <span class="ltv-tag">अभी</span>' : '' ?><?= $p['host'] ? '<small>' . e($p['host']) . '</small>' : '' ?></div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?><p class="small-note">आज का शेड्यूल जल्द।</p><?php endif; ?>
    </div>
  </aside>
</div>
