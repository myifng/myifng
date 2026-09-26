<?php
use App\Services\NewsService;
$this->layout('layouts/front');
$yt = $news['video_url'] && preg_match('~(?:youtu\.be/|v=|shorts/|live/|embed/)([A-Za-z0-9_-]{11})~', $news['video_url'], $m) ? $m[1] : null;
?>
<div class="wrap page-wrap with-side">
  <article class="card article-page news-article">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a>
      <?php if ($category): ?> <span aria-hidden="true">›</span> <span><?= e($category['name']) ?></span><?php endif; ?>
      <?php foreach ($locationChain as $l): if ($l['type'] === 'country') continue; ?> <span aria-hidden="true">›</span> <span><?= e($l['name']) ?></span><?php endforeach; ?>
    </nav>
    <?php if ($previewNote): ?><p class="updated"><i class="fa-solid fa-eye"></i> <?= e($previewNote) ?></p><?php endif; ?>
    <div class="art-flags">
      <?php foreach (\App\Models\News::FLAGS as $f => [$label, $icon]): if (!empty($news[$f]) && $f !== 'is_trending' && $f !== 'is_editor_pick'): ?><span class="flag flag-<?= e(substr($f, 3)) ?>"><i class="fa-solid <?= e($icon) ?>"></i> <?= e($label) ?></span><?php endif; endforeach; ?>
    </div>
    <h1 class="page-title"><?= e($news['title']) ?></h1>
    <?php if ($news['subtitle']): ?><p class="dek"><?= e($news['subtitle']) ?></p><?php endif; ?>
    <div class="byline">
      <?php if ($reporter): ?><b><?= e($reporter['name']) ?></b><?php endif; ?>
      <?php if ($locationChain): ?><span><i class="fa-solid fa-location-dot"></i> <?= e(end($locationChain)['name']) ?></span><?php endif; ?>
      <span><?= $news['published_at'] ? 'प्रकाशित: ' . hindi_date($news['published_at'], true) : 'अभी प्रकाशित नहीं' ?></span>
      <?php if ($news['corrected_at']): ?><span>अपडेट: <?= hindi_date($news['corrected_at'], true) ?></span><?php endif; ?>
      <span><?= NewsService::readingTime((int) $news['word_count']) ?> मिनट में पढ़ें</span>
    </div>
    <?php if ($news['summary']): ?><p class="summary"><?= e($news['summary']) ?></p><?php endif; ?>
    <?php if ($news['featured_image']): ?>
      <figure class="feature"><?= media_img($news['featured_image'], 'large', $news['image_caption'] ?: $news['title'], ['loading' => 'eager']) ?>
        <?php if ($news['image_caption'] || $news['image_credit']): ?><figcaption><?= e($news['image_caption']) ?><?= $news['image_credit'] ? ' <small>(' . e($news['image_credit']) . ')</small>' : '' ?></figcaption><?php endif; ?>
      </figure>
    <?php endif; ?>
    <?php if ($news['correction_note']): ?><aside class="callout callout-warning correction"><b>सुधार/अपडेट:</b> <?= e($news['correction_note']) ?></aside><?php endif; ?>
    <?php if ($yt): ?><div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($yt) ?>" title="वीडियो" loading="lazy" allowfullscreen></iframe></div>
    <?php elseif ($news['video_url']): ?><video class="w-100" src="<?= e(upload_url($news['video_url'])) ?>" controls preload="metadata"></video><?php endif; ?>
    <?php if ($news['audio_file']): ?><audio class="w-100" src="<?= e(upload_url($news['audio_file'])) ?>" controls preload="none"></audio><?php endif; ?>
    <div class="prose"><?= $content /* सेव करते समय sanitize */ ?></div>
    <?php if ($rel['gallery']): ?>
      <div class="art-gallery"><?php foreach ($rel['gallery'] as $g): ?><figure><?= media_img($g, 'medium') ?><?php if ($g['caption']): ?><figcaption><?= e($g['caption']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($news['source'] || $news['news_credit']): ?><p class="updated"><?= $news['source'] ? 'स्रोत: ' . e($news['source']) : '' ?><?= $news['news_credit'] ? ' · ' . e($news['news_credit']) : '' ?></p><?php endif; ?>
    <?php if ($rel['tags']): ?><div class="art-tags"><?php foreach ($rel['tags'] as $t): ?><span>#<?= e($t) ?></span><?php endforeach; ?></div><?php endif; ?>
  </article>
  <aside class="side">
    <?php if ($rel['related']): ?>
      <section class="card"><h2 class="box-title">संबंधित ख़बरें</h2><ul class="side-links"><?php foreach ($rel['related'] as $r): ?><li><?= e($r['title']) ?></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
  </aside>
</div>
