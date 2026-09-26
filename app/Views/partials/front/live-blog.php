<?php
/** ख़बर के पेज पर लाइव ब्लॉग: स्थिति, पिन, टाइमलाइन; JS हर N सेकंड में नए अपडेट लाता है */
use App\Services\LiveBlogService;
$pinned = array_values(array_filter($liveUpdates, static fn($u) => $u['is_pinned']));
$refresh = max(15, min(300, (int) setting('live_blog_refresh', '30')));
$label = ['live' => 'लाइव', 'paused' => 'कुछ देर के लिए रुका', 'ended' => 'लाइव कवरेज ख़त्म'][$live['status']];
?>
<section class="live-blog is-<?= e($live['status']) ?>" id="live" aria-label="लाइव अपडेट"
  <?= !$isPreview && $live['status'] !== 'ended' ? 'data-live-poll="' . e(route('live.updates', ['id' => $live['id']])) . '" data-interval="' . $refresh . '"' : '' ?> data-last="<?= (int) max(array_column($liveUpdates, 'id') ?: [0]) ?>">
  <div class="lb-head">
    <span class="lb-status"><i aria-hidden="true"></i><?= e($label) ?></span>
    <span class="lb-count"><span data-live-count><?= num(count($liveUpdates)) ?></span> अपडेट</span>
    <?php if ($liveUpdates): ?><span class="lb-last">आख़िरी: <?= e(LiveBlogService::timeLabel($liveUpdates[0]['posted_at'])) ?></span><?php endif; ?>
  </div>
  <?php if ($pinned): ?><div class="lb-pinned"><?php foreach ($pinned as $u): ?><?= LiveBlogService::render($u) ?><?php endforeach; ?></div><?php endif; ?>
  <button type="button" class="lb-new" data-live-new hidden></button>
  <div class="lb-timeline" data-live-list>
    <?php foreach ($liveUpdates as $u): if ($u['is_pinned']) continue; ?><?= LiveBlogService::render($u) ?><?php endforeach; ?>
    <?php if (!$liveUpdates): ?><p class="lb-empty">जल्द ही यहाँ ताज़ा अपडेट आएँगे।</p><?php endif; ?>
  </div>
</section>
