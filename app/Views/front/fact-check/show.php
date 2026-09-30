<?php
use App\Services\FactCheckService as FC;
$this->layout('layouts/front');
[$vl, $vcls, $vic] = FC::verdict($f['verdict']);
?>
<div class="wrap page-wrap with-side">
  <article class="box article fc-article">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php foreach ($crumbs as $i => [$n, $cu]): if (!$cu) continue; ?><?= $i ? ' <span aria-hidden="true">›</span> ' : '' ?><a href="<?= e($cu) ?>"><?= e($n) ?></a><?php endforeach; ?></nav>
    <div class="art-flags"><span class="kicker">फ़ैक्ट चेक</span><?php if ($f['category']): ?><a class="kicker" href="<?= e(route('category', ['slug' => $f['category_slug']])) ?>"><?= e($f['category']) ?></a><?php endif; ?></div>
    <h1 class="page-title"><?= e($f['title']) ?></h1>
    <div class="byline"><div class="by-meta">
      <?php if ($f['author']): ?><b><?= e($f['author']) ?></b><?php endif; ?>
      <span><time datetime="<?= e(date('c', strtotime($f['published_at']))) ?>"><?= hindi_date($f['published_at'], true) ?></time></span>
      <?php if ($f['updated_verdict_at']): ?><span>अपडेट: <?= hindi_date($f['updated_verdict_at'], true) ?></span><?php endif; ?>
    </div></div>

    <div class="fc-claim <?= e($vcls) ?>">
      <div class="fc-claim-row"><span class="fc-lab">दावा</span>
        <blockquote><?= nl2br(e($f['claim'])) ?></blockquote>
        <p class="fc-src"><?= e(FC::MEDIUMS[$f['claim_medium']] ?? '') ?><?= $f['claim_by'] ? ' · ' . e($f['claim_by']) : '' ?><?= $f['claim_date'] ? ' · ' . hindi_date($f['claim_date']) : '' ?>
          <?php if ($f['claim_url']): ?> · <a href="<?= e($f['claim_url']) ?>" target="_blank" rel="nofollow noopener noreferrer">मूल पोस्ट</a><?php endif; ?></p></div>
      <div class="fc-verdict-row"><span class="fc-lab">फ़ैसला</span><span class="fc-verdict"><i class="fa-solid <?= e($vic) ?>" aria-hidden="true"></i> <?= e($vl) ?></span>
        <?php if ($f['summary']): ?><p><?= e($f['summary']) ?></p><?php endif; ?></div>
    </div>
    <?php if ($f['update_note']): ?><p class="fc-update"><i class="fa-solid fa-rotate"></i> <b>अपडेट<?= $f['updated_verdict_at'] ? ' (' . hindi_date($f['updated_verdict_at']) . ')' : '' ?>:</b> <?= e($f['update_note']) ?></p><?php endif; ?>
    <?= $this->insert('partials/front/share', ['shareUrl' => $shareUrl, 'shareTitle' => $f['title']]) ?>
    <?php if ($f['image']): ?><figure class="art-fig"><?= media_img($f['image'], 'large', $f['title'], ['loading' => 'eager']) ?></figure><?php endif; ?>
    <div class="content">
      <?php if ($f['evidence']): ?><h2>हमने क्या जाँचा</h2><?= $f['evidence'] /* HtmlSanitizer से साफ़ */ ?><?php endif; ?>
      <?php if ($f['explanation']): ?><h2>सच क्या है</h2><?= $f['explanation'] /* साफ़ */ ?><?php endif; ?>
    </div>
    <?php if ($sources): ?>
      <section class="fc-sources"><h2>स्रोत</h2><ol>
        <?php foreach ($sources as [$t, $u]): ?><li><?= $u ? '<a href="' . e($u) . '" target="_blank" rel="nofollow noopener noreferrer">' . e($t) . '</a>' : e($t) ?></li><?php endforeach; ?>
      </ol></section>
    <?php endif; ?>
    <?php if ($news): ?><section class="fc-related"><h2>जुड़ी ख़बर</h2><?= news_card($news, 'wide', ['h' => 'h3']) ?></section><?php endif; ?>
    <p class="fc-note"><i class="fa-solid fa-circle-info"></i> किसी दावे की जाँच करवानी है? <a href="<?= e(route('send_news')) ?>">हमें भेजें</a>। फ़ैसले: सच · आंशिक सच · भ्रामक · झूठ · अपुष्ट।</p>
    <?php if ($more): ?><section class="fc-more"><h2>और फ़ैक्ट चेक</h2><div class="fc-grid sm"><?php foreach ($more as $m): ?><?= $this->insert('front/fact-check/_card', ['f' => $m, 'h' => 3]) ?><?php endforeach; ?></div></section><?php endif; ?>
  </article>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
