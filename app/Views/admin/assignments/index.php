<?php
use App\Models\Assignment;
use App\Repositories\AssignmentRepository;
use App\Services\NewsWorkflow;
$this->layout('layouts/admin');
$title = 'असाइनमेंट डेस्क';
$q = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['tab' => $tab, 'q' => $filters['q'], 'reporter' => $filters['reporter'], 'mine' => $all ? '' : (can('assignments.create') ? 1 : '')], $o)));
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">असाइनमेंट</li></ol></nav>
    <h1><?= $all ? 'असाइनमेंट डेस्क' : 'मेरे असाइनमेंट' ?></h1>
    <p><?= $all ? 'रिपोर्टरों को सौंपी गई ख़बरें, डेडलाइन के हिसाब से।' : 'आपको सौंपी गई ख़बरें। “लिखना शुरू करें” से सीधे ड्राफ़्ट बनता है।' ?></p>
  </div>
  <div class="d-flex gap-2">
    <?php if (can('assignments.create')): ?>
      <a class="btn btn-outline-secondary" href="<?= e($q(['mine' => $all ? 1 : '', 'page' => ''])) ?>"><?= $all ? 'मेरे असाइनमेंट' : 'सभी असाइनमेंट' ?></a>
      <a class="btn btn-brand" href="<?= e(route('admin.assignments.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया असाइनमेंट</a>
    <?php endif; ?>
  </div>
</div>

<section class="panel">
  <div class="panel-tabs">
    <?php foreach (AssignmentRepository::TABS as $k => $l): ?>
      <a href="<?= e($q(['tab' => $k, 'page' => ''])) ?>" class="<?= $tab === $k ? 'active' : '' ?><?= $k === 'overdue' && $counts[$k] ? ' text-danger' : '' ?>"><?= e($l) ?> <span><?= num($counts[$k]) ?></span></a>
    <?php endforeach; ?>
  </div>
  <?php if ($all): ?>
  <form class="filter-bar" method="get">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="असाइनमेंट खोजें" aria-label="खोजें"></div>
    <select class="form-select w-auto" name="reporter" aria-label="रिपोर्टर"><option value="">सभी रिपोर्टर</option><?php foreach ($reporters as $r): ?><option value="<?= (int) $r['id'] ?>"<?= selected($r['id'], $filters['reporter']) ?>><?= e($r['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-dark" type="submit">छाँटें</button>
  </form>
  <?php endif; ?>
  <?php if ($items->items): ?>
  <ul class="asg-list">
    <?php foreach ($items->items as $a):
      [$pl, $pc] = Assignment::PRIORITIES[$a['priority']]; [$sl, $sc] = Assignment::STATUSES[$a['status']];
      $late = $a['deadline'] && strtotime($a['deadline']) < time() && in_array($a['status'], ['open', 'accepted', 'in_progress'], true);
      $mineRow = (int) $a['reporter_id'] === (int) auth()->id(); ?>
      <li class="asg-item<?= $late ? ' is-late' : '' ?>">
        <div class="asg-main">
          <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
            <span class="badge text-bg-<?= e($pc) ?>"><?= e($pl) ?></span>
            <span class="badge-status text-bg-<?= e($sc) ?>"><i class="dot"></i><?= e($sl) ?></span>
            <?php if ($a['deadline']): ?><span class="small<?= $late ? ' text-danger fw-bold' : ' text-body-secondary' ?>"><i class="fa-regular fa-clock"></i> <?= hindi_date($a['deadline'], true) ?><?= $late ? ' (देर)' : '' ?></span><?php endif; ?>
          </div>
          <b class="d-block asg-title"><?= e($a['title']) ?></b>
          <?php if ($a['description']): ?><p class="small mb-1 text-body-secondary"><?= e(\App\Helpers\Str::limit((string) $a['description'], 180)) ?></p><?php endif; ?>
          <div class="small text-body-secondary">
            <?php if ($all): ?><i class="fa-regular fa-user"></i> <?= e($a['reporter'] ?? '—') ?> · <?php endif; ?>
            <?= e($a['category'] ?? 'बीट नहीं') ?><?= $a['location'] ? ' · <i class="fa-solid fa-location-dot"></i> ' . e($a['location']) : '' ?>
            <?php if ($a['news_title']): ?> · ख़बर: <a href="<?= e(route('admin.news.history', ['id' => $a['news_id']])) ?>"><?= e(\App\Helpers\Str::limit((string) $a['news_title'], 50)) ?></a> <?= NewsWorkflow::badge((string) $a['news_status']) ?><?php endif; ?>
          </div>
        </div>
        <div class="asg-actions">
          <?php if ($mineRow && in_array($a['status'], ['open', 'accepted', 'in_progress'], true) && can('news.create')): ?>
            <form method="post" action="<?= e(route('admin.assignments.start', ['id' => $a['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-brand" type="submit"><i class="fa-solid fa-pen-nib me-1"></i><?= $a['news_id'] ? 'ख़बर खोलें' : 'लिखना शुरू करें' ?></button></form>
          <?php endif; ?>
          <?php if ($mineRow && $a['status'] === 'open'): ?>
            <form method="post" action="<?= e(route('admin.assignments.status', ['id' => $a['id']])) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="accepted"><button class="btn btn-sm btn-outline-secondary" type="submit">स्वीकार करें</button></form>
          <?php endif; ?>
          <?php if (can('assignments.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.assignments.edit', ['id' => $a['id']])) ?>" title="बदलें" aria-label="बदलें: <?= e($a['title']) ?>"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
          <?php if (can('assignments.delete')): ?><?= delete_button(route('admin.assignments.destroy', ['id' => $a['id']]), '“' . $a['title'] . '” असाइनमेंट हट जाएगा।') ?><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-list-check"></i><p>इस टैब में कोई असाइनमेंट नहीं।</p></div>
  <?php endif; ?>
</section>
