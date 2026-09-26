<?php
use App\Models\BreakingNews;
use App\Services\BreakingService;
$this->layout('layouts/admin');
$title = 'ब्रेकिंग कंट्रोल रूम';
$tabs = ['live' => 'अभी चल रहे', 'scheduled' => 'शेड्यूल', 'ended' => 'समाप्त / बंद', 'all' => 'सभी'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">ब्रेकिंग</li></ol></nav>
    <h1><i class="fa-solid fa-bolt text-danger me-2"></i>ब्रेकिंग कंट्रोल रूम</h1>
    <p>ब्रेकिंग, फ़्लैश और अलर्ट: टिकर, होमपेज बैनर और मोबाइल अलर्ट। तय समय पर अपने आप हट जाते हैं।</p>
  </div>
</div>

<?php if ($ticker['items']): ?>
<div class="brk-preview mb-3" aria-label="वेबसाइट का टिकर (प्रीव्यू)">
  <span class="brk-preview-label"><?= $ticker['breaking'] ? 'ब्रेकिंग' : 'ताज़ा' ?></span>
  <div class="brk-preview-run"><?php foreach ($ticker['items'] as $t): ?><span class="<?= $t['priority'] >= 3 ? 'urgent' : '' ?>"><?= e($t['title']) ?></span><?php endforeach; ?></div>
</div>
<?php endif; ?>

<div class="row g-3">
  <?php if (can('breaking.create')): ?>
  <div class="col-xl-4 order-xl-2">
    <section class="panel sticky-xl">
      <div class="panel-head"><h2><i class="fa-solid fa-plus me-2 text-body-secondary"></i>नया ब्रेकिंग</h2></div>
      <div class="panel-body"><?= $this->insert('admin/breaking/_form', ['item' => null]) ?></div>
    </section>
  </div>
  <?php endif; ?>
  <div class="<?= can('breaking.create') ? 'col-xl-8' : 'col-12' ?>">
    <section class="panel">
      <div class="panel-tabs">
        <?php foreach ($tabs as $k => $l): ?><a href="?tab=<?= e($k) ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= e($l) ?> <span><?= num($counts[$k]) ?></span></a><?php endforeach; ?>
      </div>
      <?php if ($items->items): ?>
      <ul class="brk-list">
        <?php foreach ($items->items as $b): $st = BreakingService::state($b); [$sc, $sl] = BreakingService::STATES[$st]; ?>
          <li class="brk-item p<?= (int) $b['priority'] ?><?= $st !== 'live' ? ' is-off' : '' ?>">
            <div class="brk-main">
              <div class="brk-meta">
                <span class="brk-type t-<?= e($b['type']) ?>"><?= e(BreakingNews::TYPES[$b['type']]) ?></span>
                <?php if ($b['priority'] > 1): ?><span class="badge text-bg-<?= $b['priority'] == 3 ? 'danger' : 'warning' ?>"><?= e(BreakingNews::PRIORITIES[$b['priority']]) ?></span><?php endif; ?>
                <span class="badge-status text-bg-<?= e($sc) ?>"><i class="dot"></i><?= e($sl) ?></span>
              </div>
              <b class="brk-title"><?= e($b['title']) ?></b>
              <div class="small text-body-secondary">
                <?= hindi_date($b['starts_at'], true) ?> → <?= $b['ends_at'] ? hindi_date($b['ends_at'], true) : 'हाथ से बंद होने तक' ?>
                <?php if ($st === 'live' && $b['ends_at']): $left = (int) ceil((strtotime($b['ends_at']) - time()) / 60); ?> · बचा: <?= $left >= 120 ? num(intdiv($left, 60)) . ' घंटे' : num(max(1, $left)) . ' मिनट' ?><?php endif; ?>
                · <?= implode(', ', array_filter([$b['show_ticker'] ? 'टिकर' : '', $b['show_banner'] ? 'होम बैनर' : '', $b['mobile_alert'] ? 'मोबाइल' : '', $b['push'] ? 'पुश' : ''])) ?>
                <?php if ($b['news_title']): ?> · <i class="fa-solid fa-link"></i> <?= e(\App\Helpers\Str::limit($b['news_title'], 50)) ?><?php endif; ?>
                <?php if ($b['author']): ?> · <?= e($b['author']) ?><?php endif; ?>
              </div>
            </div>
            <div class="brk-actions">
              <?php if ($st === 'live' && can('breaking.publish')): ?>
                <form method="post" action="<?= e(route('admin.breaking.stop', ['id' => $b['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit"><i class="fa-solid fa-stop me-1"></i>अभी हटाएँ</button></form>
              <?php elseif (in_array($st, ['expired', 'off'], true) && can('breaking.publish')): ?>
                <form method="post" action="<?= e(route('admin.breaking.restart', ['id' => $b['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fa-solid fa-rotate-right me-1"></i>दोबारा चलाएँ</button></form>
              <?php endif; ?>
              <?php if (can('breaking.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.breaking.edit', ['id' => $b['id']])) ?>" title="बदलें" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
              <?php if (can('breaking.delete')): ?><?= delete_button(route('admin.breaking.destroy', ['id' => $b['id']]), 'यह आइटम हमेशा के लिए हट जाएगा।') ?><?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
      <?php else: ?>
        <div class="empty-state"><i class="fa-solid fa-bolt"></i><p><?= $tab === 'live' ? 'अभी कोई ब्रेकिंग नहीं चल रहा। टिकर में ब्रेकिंग फ़्लैग वाली ख़बरें या ताज़ा ख़बरें दिखती हैं।' : 'यहाँ कुछ नहीं है।' ?></p></div>
      <?php endif; ?>
    </section>
  </div>
</div>
