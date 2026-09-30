<?php use App\Services\FactCheckService as FC; /** $f */ ?>
<article class="fc-card">
  <a class="fc-img" href="<?= e(FC::url($f)) ?>" tabindex="-1" aria-hidden="true"><?= $f['image'] ? media_img($f['image'], 'medium', $f['title']) : '<span class="fc-img-ph"><i class="fa-solid fa-magnifying-glass"></i></span>' ?><?= FC::badge($f['verdict'], 'on-img') ?></a>
  <div class="fc-body">
    <h<?= (int) ($h ?? 2) ?> class="fc-title"><a href="<?= e(FC::url($f)) ?>"><?= e($f['title']) ?></a></h<?= (int) ($h ?? 2) ?>>
    <?php if (!empty($f['summary'])): ?><p class="fc-sum"><?= e($f['summary']) ?></p><?php endif; ?>
    <?php if (!empty($f['published_at'])): ?><time datetime="<?= e(date('c', strtotime($f['published_at']))) ?>"><?= hindi_date($f['published_at']) ?></time><?php endif; ?>
  </div>
</article>
