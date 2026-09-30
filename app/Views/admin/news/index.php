<?php
use App\Models\News;
use App\Services\NewsService;
use App\Services\NewsWorkflow;
$this->layout('layouts/admin');
$title = 'ख़बरें';
$trash = $filters['status'] === 'trash';
$desk = NewsService::seesAll();
$q = fn(array $over) => '?' . http_build_query(array_filter(array_merge($filters, $over), fn($v) => $v !== '' && $v !== 0 && $v !== false && $v !== null));
$tabs = $desk
    ? ['' => 'सभी', 'desk' => 'डेस्क पर', 'draft' => 'ड्राफ़्ट', 'approved' => 'मंज़ूर', 'scheduled' => 'शेड्यूल', 'published' => 'प्रकाशित', 'rejected' => 'अस्वीकार', 'disabled' => 'बंद', 'archived' => 'आर्काइव', 'trash' => 'ट्रैश']
    : ['' => 'सभी', 'draft' => 'ड्राफ़्ट', 'desk' => 'डेस्क पर', 'rejected' => 'सुधार माँगे', 'published' => 'प्रकाशित', 'trash' => 'ट्रैश'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">ख़बरें</li></ol></nav>
    <h1><?= $filters['mine'] ? 'मेरी ख़बरें' : 'ख़बरें' ?></h1>
    <p><?= $desk ? 'न्यूज़रूम की सभी ख़बरें। “डेस्क पर” = भेजी गई, समीक्षा और फ़ैक्ट चेक वाली।' : 'आपकी लिखी ख़बरें। ड्राफ़्ट और “सुधार माँगे” वाली ख़बरें आप बदल सकते हैं।' ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($desk && can('news.create')): ?><a class="btn btn-outline-secondary" href="<?= e($q(['mine' => $filters['mine'] ? '' : 1, 'page' => ''])) ?>"><i class="fa-solid fa-user-pen me-1"></i> <?= $filters['mine'] ? 'सभी ख़बरें' : 'मेरी ख़बरें' ?></a><?php endif; ?>
    <?php if (can('news.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.news.export') . $q([])) ?>"><i class="fa-solid fa-file-csv me-1"></i> एक्सपोर्ट</a><?php endif; ?>
    <?php if (can('news.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.news.create')) ?>"><i class="fa-solid fa-pen-nib me-1"></i> नई ख़बर</a><?php endif; ?>
  </div>
</div>

<section class="panel">
  <div class="panel-tabs">
    <?php foreach ($tabs as $k => $l): ?>
      <a href="<?= e($q(['status' => $k, 'page' => ''])) ?>" class="<?= $filters['status'] === $k ? 'active' : '' ?>"><?= $k === 'trash' ? '<i class="fa-regular fa-trash-can me-1"></i>' : '' ?><?= e($l) ?> <span><?= num($counts[$k] ?? 0) ?></span></a>
    <?php endforeach; ?>
  </div>
  <form class="filter-bar" method="get">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php if ($filters['mine']): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="शीर्षक, URL या ख़बर ID" aria-label="ख़बर खोजें"></div>
    <select class="form-select w-auto" name="category" aria-label="श्रेणी"><option value="">सभी श्रेणियाँ</option><?php foreach ($categories as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected($id, $filters['category']) ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <?php if ($reporters): ?><select class="form-select w-auto" name="reporter" aria-label="रिपोर्टर"><option value="">सभी रिपोर्टर</option><?php foreach ($reporters as $r): ?><option value="<?= (int) $r['id'] ?>"<?= selected($r['id'], $filters['reporter']) ?>><?= e($r['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <div class="loc-picker loc-filter" data-location-picker data-search="<?= e(route('admin.locations.search')) ?>">
      <input type="hidden" name="location" value="<?= $filterLocation ? (int) $filterLocation['id'] : '' ?>">
      <input type="search" class="form-control" autocomplete="off" value="<?= e($filterLocation['name'] ?? '') ?>" placeholder="लोकेशन" aria-label="लोकेशन से छाँटें" role="combobox" aria-expanded="false">
      <ul class="loc-results list-group" role="listbox" hidden></ul>
    </div>
    <select class="form-select w-auto" name="flag" aria-label="फ़्लैग"><option value="">फ़्लैग</option><?php foreach (News::FLAGS as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($k, $filters['flag']) ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <input class="form-control w-auto" type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="इस तारीख़ से">
    <input class="form-control w-auto" type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="इस तारीख़ तक">
    <button class="btn btn-dark" type="submit">छाँटें</button>
  </form>

  <?php if ($news->items): ?>
  <form method="post" action="<?= e(route('admin.news.bulk')) ?>" data-bulk-form data-bulk-noun="ख़बरें">
    <?= csrf_field() ?>
    <div class="bulk-bar" hidden>
      <span data-bulk-count>0 चुने गए</span>
      <select class="form-select form-select-sm w-auto" name="action" aria-label="बल्क काम" required>
        <option value="">काम चुनें…</option>
        <?php if (!$trash): ?>
          <?php if (can('news.create')): ?><option value="submit">डेस्क को भेजें</option><?php endif; ?>
          <?php if (can('news.approve')): ?><option value="approve">मंज़ूर करें</option><?php endif; ?>
          <?php if (can('news.publish')): ?><option value="publish">प्रकाशित करें</option><option value="archive">आर्काइव करें</option><?php endif; ?>
          <?php if (can('news.delete')): ?><option value="trash">ट्रैश में डालें</option><?php endif; ?>
        <?php elseif (can('news.delete')): ?>
          <option value="restore">वापस लाएँ</option><option value="delete">स्थायी रूप से हटाएँ</option>
        <?php endif; ?>
      </select>
      <button class="btn btn-sm btn-dark" type="submit">लागू करें</button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table news-table">
        <thead><tr><th style="width:36px"><input class="form-check-input" type="checkbox" data-check-all aria-label="सभी चुनें"></th><th>ख़बर</th><th>श्रेणी / लोकेशन</th><th>रिपोर्टर</th><th>स्थिति</th><th>समय</th><th class="text-end">काम</th></tr></thead>
        <tbody>
        <?php foreach ($news->items as $n): $editable = NewsService::canEdit($n); ?>
          <tr>
            <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $n['id'] ?>" aria-label="चुनें: <?= e($n['title']) ?>"></td>
            <td>
              <div class="d-flex gap-2 align-items-start">
                <?php if ($n['featured_image']): ?><img class="thumb-sm" src="<?= e(media_url($n['featured_image'], 'thumb')) ?>" alt=""><?php else: ?><span class="thumb-sm thumb-empty"><i class="fa-regular fa-image"></i></span><?php endif; ?>
                <div class="min-w-0">
                  <a class="fw-bold text-reset news-title" href="<?= e(route($editable && !$trash ? 'admin.news.edit' : 'admin.news.history', ['id' => $n['id']])) ?>"><?= e($n['title']) ?></a>
                  <div class="small text-body-secondary">#<?= (int) $n['id'] ?> · <?= num($n['word_count']) ?> शब्द
                    <?php foreach (['is_breaking' => 'ब्रेकिंग', 'is_exclusive' => 'एक्सक्लूसिव', 'is_live' => 'लाइव', 'is_featured' => 'फ़ीचर्ड'] as $f => $l): if ($n[$f]): ?> <span class="chip-sm flag-<?= e(substr($f, 3)) ?>"><?= e($l) ?></span><?php endif; endforeach; ?>
                  </div>
                </div>
              </div>
            </td>
            <td class="small"><?= e($n['category'] ?? '—') ?><span class="d-block text-body-secondary"><?= e($n['location'] ?? '') ?></span></td>
            <td class="small"><?= e($n['reporter'] ?? '—') ?></td>
            <td><?= $trash ? '<span class="badge-status text-bg-danger"><i class="dot"></i>ट्रैश</span>' : NewsWorkflow::badge($n['status']) ?></td>
            <td class="small text-nowrap">
              <?php if ($n['status'] === 'scheduled' && $n['scheduled_at']): ?><i class="fa-regular fa-clock"></i> <?= hindi_date($n['scheduled_at'], true) ?>
              <?php elseif ($n['status'] === 'published' && $n['published_at']): ?><?= hindi_date($n['published_at'], true) ?><?php if (can('analytics.view')): ?><a class="d-block text-body-secondary" href="<?= e(route('admin.analytics.news', ['id' => $n['id']])) ?>" title="एनालिटिक्स"><i class="fa-solid fa-chart-line"></i> <?= num($n['views']) ?> व्यूज़</a><?php else: ?><span class="d-block text-body-secondary"><?= num($n['views']) ?> व्यूज़</span><?php endif; ?>
              <?php else: ?><?= time_ago($n['updated_at']) ?><?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <?php if (!$trash): ?>
                <?php if ($editable): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.news.edit', ['id' => $n['id']])) ?>" title="बदलें" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
                <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.news.history', ['id' => $n['id']])) ?>" title="स्थिति और हिस्ट्री" aria-label="स्थिति और हिस्ट्री"><i class="fa-solid fa-clock-rotate-left"></i></a>
                <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.news.preview', ['id' => $n['id']])) ?>" target="_blank" rel="noopener" title="प्रीव्यू" aria-label="प्रीव्यू"><i class="fa-solid fa-eye"></i></a>
              <?php elseif (can('news.delete')): ?>
                <button class="btn btn-sm btn-outline-secondary" type="submit" form="restore-<?= (int) $n['id'] ?>"><i class="fa-solid fa-rotate-left me-1"></i>वापस</button>
                <button class="btn btn-sm btn-icon btn-outline-danger" type="submit" form="del-<?= (int) $n['id'] ?>" title="स्थायी रूप से हटाएँ" aria-label="स्थायी रूप से हटाएँ"><i class="fa-solid fa-trash-can"></i></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
  <?php if ($trash): foreach ($news->items as $n): ?>
    <form id="restore-<?= (int) $n['id'] ?>" method="post" action="<?= e(route('admin.news.restore', ['id' => $n['id']])) ?>" hidden><?= csrf_field() ?></form>
    <form id="del-<?= (int) $n['id'] ?>" method="post" action="<?= e(route('admin.news.destroy', ['id' => $n['id']])) ?>" hidden data-confirm="“<?= e($n['title']) ?>” और उसकी पूरी हिस्ट्री हमेशा के लिए हट जाएगी।"><?= csrf_field() ?><?= method_field('DELETE') ?></form>
  <?php endforeach; endif; ?>
  <?= $this->insert('partials/admin/pagination', ['p' => $news]) ?>
  <?php else: ?>
    <div class="empty-state"><i class="fa-regular fa-newspaper"></i><p><?= $trash ? 'ट्रैश ख़ाली है।' : 'इस सूची में कोई ख़बर नहीं है।' ?></p><?php if (!$trash && can('news.create')): ?><a class="btn btn-brand mt-2" href="<?= e(route('admin.news.create')) ?>">पहली ख़बर लिखें</a><?php endif; ?></div>
  <?php endif; ?>
</section>
