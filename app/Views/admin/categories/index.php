<?php
$this->layout('layouts/admin');
$title = 'श्रेणियाँ';
$canEdit = can('categories.edit');
$row = function (array $c, bool $child, int $i, int $n) use ($canEdit): string {
    ob_start(); ?>
    <tr class="<?= $child ? 'tree-child' : 'tree-parent' ?><?= $c['status'] !== 'active' ? ' is-off' : '' ?>">
      <td>
        <div class="d-flex align-items-center gap-2">
          <?php if ($child): ?><span class="tree-elbow" aria-hidden="true"></span><?php endif; ?>
          <span class="cat-dot" style="--c:<?= e($c['color'] ?: '#9aa0a6') ?>"><i class="fa-solid <?= e(preg_replace('/^fa-(solid|regular|brands)\s+/', '', (string) ($c['icon'] ?: 'fa-folder'))) ?>"></i></span>
          <div>
            <b class="d-block"><?= $canEdit ? '<a class="text-reset" href="' . e(route('admin.categories.edit', ['id' => $c['id']])) . '">' . e($c['name']) . '</a>' : e($c['name']) ?></b>
            <span class="small text-body-secondary font-monospace">/category/<?= e($c['slug']) ?></span>
          </div>
        </div>
      </td>
      <td class="text-nowrap small">
        <?= $c['show_in_menu'] ? '<span class="chip-sm" title="मेनू में दिखे"><i class="fa-solid fa-bars"></i> मेनू</span>' : '' ?>
        <?= $c['show_on_home'] ? '<span class="chip-sm" title="होमपेज पर सेक्शन"><i class="fa-solid fa-house"></i> होम</span>' : '' ?>
      </td>
      <td><?= status_badge($c['status']) ?></td>
      <td class="text-end text-nowrap">
        <?php if ($canEdit): ?>
          <form method="post" action="<?= e(route('admin.categories.move', ['id' => $c['id']])) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="dir" value="up"><button class="btn btn-sm btn-icon btn-outline-secondary" type="submit" data-no-lock title="ऊपर" aria-label="<?= e($c['name']) ?> ऊपर"<?= $i === 0 ? ' disabled' : '' ?>><i class="fa-solid fa-arrow-up"></i></button></form>
          <form method="post" action="<?= e(route('admin.categories.move', ['id' => $c['id']])) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="dir" value="down"><button class="btn btn-sm btn-icon btn-outline-secondary" type="submit" data-no-lock title="नीचे" aria-label="<?= e($c['name']) ?> नीचे"<?= $i === $n - 1 ? ' disabled' : '' ?>><i class="fa-solid fa-arrow-down"></i></button></form>
          <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.categories.edit', ['id' => $c['id']])) ?>" title="बदलें" aria-label="बदलें: <?= e($c['name']) ?>"><i class="fa-solid fa-pen"></i></a>
        <?php endif; ?>
        <?php if (!$child && can('categories.create')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.categories.create')) ?>?parent=<?= (int) $c['id'] ?>" title="उप-श्रेणी जोड़ें" aria-label="<?= e($c['name']) ?> में उप-श्रेणी जोड़ें"><i class="fa-solid fa-plus"></i></a><?php endif; ?>
        <?php if (can('categories.delete')): ?><?= delete_button(route('admin.categories.destroy', ['id' => $c['id']]), '“' . $c['name'] . '” श्रेणी हट जाएगी और मेनू से उसके लिंक भी।') ?><?php endif; ?>
      </td>
    </tr>
    <?php return (string) ob_get_clean();
};
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">श्रेणियाँ</li></ol></nav>
    <h1>श्रेणियाँ</h1>
    <p>मुख्य श्रेणी और उसके नीचे उप-श्रेणी (दो स्तर)। क्रम यहीं से बदलें<?php if (can('menus.view')): ?>; मेनू में जोड़ने के लिए <a href="<?= e(route('admin.menus.index')) ?>">मेनू बिल्डर</a><?php endif; ?>।</p>
  </div>
  <?php if (can('categories.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.categories.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नई श्रेणी</a><?php endif; ?>
</div>

<section class="panel">
  <form class="filter-bar" method="get">
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="नाम या URL से खोजें" aria-label="खोजें"></div>
    <button class="btn btn-dark" type="submit">खोजें</button>
    <span class="small text-body-secondary ms-auto">कुल <?= num($total) ?></span>
  </form>
  <?php if ($tree): ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table tree-table">
      <thead><tr><th>श्रेणी</th><th>दिखे</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
      <tbody>
      <?php foreach ($tree as $i => $c): ?>
        <?= $row($c, false, $i, count($tree)) ?>
        <?php foreach ($c['children'] as $j => $ch): ?><?= $row($ch, true, $j, count($c['children'])) ?><?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-folder-tree"></i><p><?= $q !== '' ? 'कोई श्रेणी नहीं मिली।' : 'अभी कोई श्रेणी नहीं है।' ?></p></div>
  <?php endif; ?>
</section>
