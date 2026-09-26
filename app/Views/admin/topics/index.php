<?php
use App\Models\Topic;
$this->layout('layouts/admin');
$title = 'टॉपिक और विशेष सेक्शन';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">टॉपिक</li></ol></nav>
    <h1>टॉपिक और विशेष सेक्शन</h1>
    <p>टॉपिक: किसी मुद्दे की सभी ख़बरें एक पेज पर (जैसे “लोकसभा चुनाव”)। विशेष सेक्शन: बैनर और रंग वाला अलग कवरेज पेज।</p>
  </div>
  <?php if (can('topics.create')): ?>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="<?= e(route('admin.topics.create')) ?>?type=special"><i class="fa-solid fa-star me-1"></i> विशेष सेक्शन</a>
      <a class="btn btn-brand" href="<?= e(route('admin.topics.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया टॉपिक</a>
    </div>
  <?php endif; ?>
</div>

<section class="panel">
  <div class="panel-tabs">
    <?php foreach (['' => 'सभी', 'topic' => 'टॉपिक', 'special' => 'विशेष सेक्शन', 'featured' => 'फ़ीचर्ड / ट्रेंडिंग'] as $k => $l): ?>
      <a href="?<?= e(http_build_query(array_filter(['type' => $k, 'q' => $q]))) ?>" class="<?= $type === $k ? 'active' : '' ?>"><?= e($l) ?> <span><?= num($counts[$k]) ?></span></a>
    <?php endforeach; ?>
  </div>
  <form class="filter-bar" method="get">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="नाम या URL से खोजें" aria-label="खोजें"></div>
    <button class="btn btn-dark" type="submit">खोजें</button>
  </form>
  <?php if ($topics->items): ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table">
      <thead><tr><th>नाम</th><th>प्रकार</th><th>फ़ीचर्ड</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
      <tbody>
      <?php foreach ($topics->items as $t): ?>
        <tr class="<?= $t['status'] !== 'active' ? 'is-off' : '' ?>">
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if ($t['image']): ?><img class="thumb-sm" src="<?= e(media_url($t['image'], 'thumb')) ?>" alt=""><?php else: ?><span class="cat-dot" style="--c:<?= e($t['color'] ?: '#9aa0a6') ?>"><i class="fa-solid <?= $t['type'] === 'special' ? 'fa-star' : 'fa-hashtag' ?>"></i></span><?php endif; ?>
              <div><b class="d-block"><?= can('topics.edit') ? '<a class="text-reset" href="' . e(route('admin.topics.edit', ['id' => $t['id']])) . '">' . e($t['name']) . '</a>' : e($t['name']) ?></b><span class="small text-body-secondary font-monospace">/topic/<?= e($t['slug']) ?></span></div>
            </div>
          </td>
          <td class="small"><?= e(Topic::TYPES[$t['type']]) ?></td>
          <td>
            <?php if (can('topics.edit')): ?>
              <form method="post" action="<?= e(route('admin.topics.feature', ['id' => $t['id']])) ?>" class="d-inline"><?= csrf_field() ?>
                <button class="btn btn-sm btn-icon <?= $t['is_featured'] ? 'btn-warning' : 'btn-outline-secondary' ?>" type="submit" data-no-lock title="<?= $t['is_featured'] ? 'फ़ीचर्ड से हटाएँ' : 'फ़ीचर्ड बनाएँ' ?>" aria-label="<?= $t['is_featured'] ? 'फ़ीचर्ड से हटाएँ' : 'फ़ीचर्ड बनाएँ' ?>: <?= e($t['name']) ?>" aria-pressed="<?= $t['is_featured'] ? 'true' : 'false' ?>"><i class="fa-<?= $t['is_featured'] ? 'solid' : 'regular' ?> fa-star"></i></button>
              </form>
            <?php else: ?><?= $t['is_featured'] ? '<i class="fa-solid fa-star text-warning"></i>' : '—' ?><?php endif; ?>
          </td>
          <td><?= status_badge($t['status']) ?></td>
          <td class="text-end text-nowrap">
            <?php if (can('topics.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.topics.edit', ['id' => $t['id']])) ?>" title="बदलें" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
            <?php if (can('topics.delete')): ?><?= delete_button(route('admin.topics.destroy', ['id' => $t['id']]), '“' . $t['name'] . '” हट जाएगा और मेनू से उसके लिंक भी।') ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $this->insert('partials/admin/pagination', ['p' => $topics]) ?>
  <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-hashtag"></i><p><?= $q !== '' ? 'कुछ नहीं मिला।' : 'अभी कोई टॉपिक नहीं है। चुनाव, बजट, मौसम जैसे बड़े मुद्दों के लिए टॉपिक बनाएँ।' ?></p></div>
  <?php endif; ?>
</section>
