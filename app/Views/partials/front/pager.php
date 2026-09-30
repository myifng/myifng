<?php if ($p->pages > 1): ?>
  <nav class="pager" aria-label="पेज">
    <?php if ($p->page > 1): ?><a href="<?= e($p->url($p->page - 1)) ?>" rel="prev"><i class="fa-solid fa-angle-left"></i> पिछला</a><?php endif; ?>
    <span>पेज <?= num($p->page) ?> / <?= num($p->pages) ?></span>
    <?php if ($p->page < $p->pages): ?><a href="<?= e($p->url($p->page + 1)) ?>" rel="next">अगला <i class="fa-solid fa-angle-right"></i></a><?php endif; ?>
  </nav>
<?php endif; ?>
