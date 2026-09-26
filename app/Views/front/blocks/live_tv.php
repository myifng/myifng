<?php use App\Services\LiveTvService; ?>
<div class="box livebox">
  <?= block_head($title ?: 'लाइव टीवी', app('router')->has('live_tv') ? route('live_tv') : null) ?>
  <div class="video-embed">
    <?php if ($player['type'] === 'iframe'): ?>
      <iframe src="<?= e($player['src']) ?>" title="<?= e($channel['name']) ?>" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"<?= $channel['source_type'] === 'embed' ? ' sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"' : '' ?>></iframe>
    <?php else: ?>
      <video controls muted playsinline preload="none" <?= !empty($player['hls']) ? 'data-hls="' . e($player['src']) . '" data-hls-lib="' . e(asset('vendor/hls/hls.light.min.js')) . '"' : 'src="' . e($player['src']) . '"' ?>></video>
    <?php endif; ?>
  </div>
  <?php if ($now): ?><p class="ltv-now"><?= $channel['is_live'] ? '<span class="ltv-live"><i aria-hidden="true"></i>LIVE</span> ' : '' ?><b><?= e($now['title']) ?></b> <span>(<?= e(LiveTvService::time($now['start_time'])) ?> – <?= e(LiveTvService::time($now['end_time'])) ?>)</span></p><?php endif; ?>
</div>
