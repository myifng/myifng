<?php /** @var App\Core\Paginator $p */ if ($p->total > 0): ?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center p-3 border-top">
  <span class="text-body-secondary small"><?= num($p->from()) ?>–<?= num($p->to()) ?> / कुल <?= num($p->total) ?></span>
  <?php if ($p->pages > 1): ?>
  <nav aria-label="पेज">
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item<?= $p->page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($p->url($p->page - 1)) ?>" aria-label="पिछला">‹</a></li>
      <?php for ($i = max(1, $p->page - 2); $i <= min($p->pages, $p->page + 2); $i++): ?>
        <li class="page-item<?= $i === $p->page ? ' active' : '' ?>"><a class="page-link" href="<?= e($p->url($i)) ?>"<?= $i === $p->page ? ' aria-current="page"' : '' ?>><?= $i ?></a></li>
      <?php endfor; ?>
      <li class="page-item<?= $p->page >= $p->pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($p->url($p->page + 1)) ?>" aria-label="अगला">›</a></li>
    </ul>
  </nav>
  <?php endif; ?>
</div>
<?php endif; ?>
