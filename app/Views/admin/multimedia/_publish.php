<?php
/** फ़ॉर्म का दायाँ पैनल: स्थिति, प्रकाशन समय, श्रेणी, फ़ीचर्ड, सेव */
use App\Services\MultimediaService;
$isNew = $row === null;
$canPublish = can($module . '.publish');
$st = $row ? MultimediaService::state($row) : 'draft';
$publicRoute = MultimediaService::KINDS[$kind]['route'];
?>
<section class="panel sticky-xl">
  <div class="panel-head"><h2>प्रकाशन</h2>
    <?php if (!$isNew): ?><span class="badge-status text-bg-<?= ['draft' => 'secondary', 'scheduled' => 'info', 'published' => 'success'][$st] ?>"><i class="dot"></i><?= ['draft' => 'ड्राफ़्ट', 'scheduled' => 'शेड्यूल', 'published' => 'प्रकाशित'][$st] ?></span><?php endif; ?>
  </div>
  <div class="panel-body">
    <?php if ($canPublish): ?>
      <?= field('select', 'status', 'स्थिति', $row['status'] ?? 'draft', ['options' => MultimediaService::STATUSES]) ?>
      <?= field('datetime-local', 'published_at', 'प्रकाशन का समय', !empty($row['published_at']) ? date('Y-m-d\TH:i', strtotime($row['published_at'])) : '', ['help' => 'ख़ाली = अभी; आगे का समय = शेड्यूल']) ?>
    <?php else: ?>
      <input type="hidden" name="status" value="<?= e($row['status'] ?? 'draft') ?>">
      <p class="small text-body-secondary"><i class="fa-solid fa-circle-info me-1"></i>प्रकाशित करने की अनुमति नहीं है; बदलाव सेव होंगे, स्थिति वही रहेगी।</p>
    <?php endif; ?>
    <?= field('select', 'category_id', 'श्रेणी', $row['category_id'] ?? '', ['options' => $categories, 'empty' => '— कोई नहीं —']) ?>
    <?= $publishExtra ?? '' ?>
    <?= field('switch', 'is_featured', 'फ़ीचर्ड', $row['is_featured'] ?? 0) ?>
    <div class="d-grid gap-2 mt-3">
      <?php if (can($module . ($isNew ? '.create' : '.edit'))): ?><button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> <?= $isNew ? 'बनाएँ' : 'सेव करें' ?></button><?php endif; ?>
      <?php if (!$isNew && $st === 'published'): ?><a class="btn btn-outline-secondary" href="<?= e(route($publicRoute, ['slug' => $row['slug']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> वेबसाइट पर देखें</a><?php endif; ?>
      <a class="btn btn-light" href="<?= e(route('admin.' . $module . '.index')) ?>">वापस</a>
    </div>
    <?php if (!$isNew): ?><p class="small text-body-secondary mt-3 mb-0"><?= num($row['views']) ?> व्यूज़ · बदला <?= e(time_ago($row['updated_at'])) ?></p><?php endif; ?>
  </div>
</section>
