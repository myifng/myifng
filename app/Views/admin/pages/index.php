<?php
use App\Models\Page;
$this->layout('layouts/admin');
$title = 'पेज';
$trash = $filters['status'] === 'trash';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">पेज</li></ol></nav>
    <h1>पेज</h1>
    <p>हमारे बारे में, संपर्क, नीतियाँ और आपके अपने पेज</p>
  </div>
  <?php if (can('pages.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.pages.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया पेज</a><?php endif; ?>
</div>

<section class="panel">
  <div class="panel-tabs">
    <?php foreach (['' => 'सभी', 'published' => 'प्रकाशित', 'draft' => 'ड्राफ़्ट', 'trash' => 'ट्रैश'] as $k => $l): ?>
      <a href="?<?= e(http_build_query(array_filter(['status' => $k, 'q' => $filters['q']]))) ?>" class="<?= $filters['status'] === $k ? 'active' : '' ?>"><?= $k === 'trash' ? '<i class="fa-regular fa-trash-can me-1"></i>' : '' ?><?= e($l) ?> <span><?= num($counts[$k]) ?></span></a>
    <?php endforeach; ?>
  </div>
  <form class="filter-bar" method="get">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="शीर्षक या URL से खोजें" aria-label="खोजें"></div>
    <button class="btn btn-dark" type="submit">खोजें</button>
  </form>

  <?php if ($pages->items): ?>
  <form method="post" action="<?= e(route('admin.pages.bulk')) ?>" data-bulk-form>
    <?= csrf_field() ?>
    <div class="bulk-bar" hidden>
      <span data-bulk-count>0 चुने गए</span>
      <select class="form-select form-select-sm w-auto" name="action" aria-label="बल्क काम" required>
        <option value="">काम चुनें…</option>
        <?php if (!$trash): ?>
          <?php if (can('pages.publish')): ?><option value="publish">प्रकाशित करें</option><option value="draft">ड्राफ़्ट में डालें</option><?php endif; ?>
          <?php if (can('pages.delete')): ?><option value="trash">ट्रैश में डालें</option><?php endif; ?>
        <?php elseif (can('pages.delete')): ?>
          <option value="restore">वापस लाएँ</option><option value="delete">स्थायी रूप से हटाएँ</option>
        <?php endif; ?>
      </select>
      <button class="btn btn-sm btn-dark" type="submit">लागू करें</button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th style="width:36px"><input class="form-check-input" type="checkbox" data-check-all aria-label="सभी चुनें"></th><th>शीर्षक</th><th>टेम्पलेट</th><th>स्थिति</th><th>आख़िरी बदलाव</th><th class="text-end">काम</th></tr></thead>
        <tbody>
        <?php foreach ($pages->items as $p): ?>
          <tr>
            <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" aria-label="चुनें: <?= e($p['title']) ?>"></td>
            <td><b class="d-block"><?= !$trash && can('pages.edit') ? '<a class="text-reset" href="' . e(route('admin.pages.edit', ['id' => $p['id']])) . '">' . e($p['title']) . '</a>' : e($p['title']) ?></b><span class="small text-body-secondary font-monospace">/page/<?= e($p['slug']) ?></span></td>
            <td class="small"><?= e(Page::TEMPLATES[$p['template']] ?? $p['template']) ?></td>
            <td><?= $trash ? '<span class="badge-status text-bg-danger"><i class="dot"></i>ट्रैश</span>' : ($p['status'] === 'published' ? status_badge('active') : '<span class="badge-status text-bg-secondary"><i class="dot"></i>ड्राफ़्ट</span>') ?></td>
            <td class="small text-nowrap"><?= time_ago($p['updated_at']) ?><span class="d-block text-body-secondary"><?= e($p['editor'] ?? '—') ?></span></td>
            <td class="text-end text-nowrap">
              <?php if (!$trash): ?>
                <?php if (can('pages.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.pages.edit', ['id' => $p['id']])) ?>" title="बदलें" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
                <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e($p['status'] === 'published' ? url('page/' . $p['slug']) : route('admin.pages.preview', ['id' => $p['id']])) ?>" target="_blank" rel="noopener" title="देखें" aria-label="देखें"><i class="fa-solid fa-eye"></i></a>
                <?php if (can('pages.create')): ?><button class="btn btn-sm btn-icon btn-outline-secondary" type="submit" form="dup-<?= (int) $p['id'] ?>" title="कॉपी बनाएँ" aria-label="कॉपी बनाएँ"><i class="fa-regular fa-copy"></i></button><?php endif; ?>
                <?php if (can('pages.delete')): ?><button class="btn btn-sm btn-icon btn-outline-danger" type="submit" form="trash-<?= (int) $p['id'] ?>" title="ट्रैश में डालें" aria-label="ट्रैश में डालें"><i class="fa-regular fa-trash-can"></i></button><?php endif; ?>
              <?php elseif (can('pages.delete')): ?>
                <button class="btn btn-sm btn-outline-secondary" type="submit" form="restore-<?= (int) $p['id'] ?>"><i class="fa-solid fa-rotate-left me-1"></i>वापस लाएँ</button>
                <button class="btn btn-sm btn-icon btn-outline-danger" type="submit" form="del-<?= (int) $p['id'] ?>" title="स्थायी रूप से हटाएँ" aria-label="स्थायी रूप से हटाएँ"><i class="fa-solid fa-trash-can"></i></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
  <?php // एक-एक पंक्ति के फ़ॉर्म (बल्क फ़ॉर्म के बाहर, ताकि नेस्टिंग न हो) ?>
  <?php foreach ($pages->items as $p): ?>
    <form id="dup-<?= (int) $p['id'] ?>" method="post" action="<?= e(route('admin.pages.duplicate', ['id' => $p['id']])) ?>" hidden><?= csrf_field() ?></form>
    <form id="trash-<?= (int) $p['id'] ?>" method="post" action="<?= e(route('admin.pages.trash', ['id' => $p['id']])) ?>" hidden data-confirm="“<?= e($p['title']) ?>” ट्रैश में जाएगा और वेबसाइट से हट जाएगा।"><?= csrf_field() ?></form>
    <form id="restore-<?= (int) $p['id'] ?>" method="post" action="<?= e(route('admin.pages.restore', ['id' => $p['id']])) ?>" hidden><?= csrf_field() ?></form>
    <form id="del-<?= (int) $p['id'] ?>" method="post" action="<?= e(route('admin.pages.destroy', ['id' => $p['id']])) ?>" hidden data-confirm="“<?= e($p['title']) ?>” हमेशा के लिए हट जाएगा। यह वापस नहीं आएगा।"><?= csrf_field() ?><?= method_field('DELETE') ?></form>
  <?php endforeach; ?>
  <?= $this->insert('partials/admin/pagination', ['p' => $pages]) ?>
  <?php else: ?>
    <div class="empty-state"><i class="fa-regular fa-file-lines"></i><p><?= $trash ? 'ट्रैश ख़ाली है।' : 'कोई पेज नहीं मिला।' ?></p></div>
  <?php endif; ?>
</section>
