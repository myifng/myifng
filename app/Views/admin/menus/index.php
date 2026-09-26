<?php $this->layout('layouts/admin'); $title = 'मेनू बिल्डर'; ?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">मेनू बिल्डर</li></ol></nav>
    <h1>मेनू बिल्डर</h1>
    <p>वेबसाइट के हर हिस्से के लिंक: ऊपर की पट्टी, मुख्य मेनू, मोबाइल, फ़ुटर के कॉलम और लीगल लिंक</p>
  </div>
</div>
<div class="menu-map">
  <?php foreach ($locations as $loc => $label):
      $m = $menus[$loc]; ?>
    <a class="menu-card loc-<?= e(preg_replace('/_\d$/', '', $loc)) ?>" href="<?= e(route('admin.menus.edit', ['id' => $m['id']])) ?>">
      <span class="mc-where"><?= e($label) ?></span>
      <b><?= e($m['name']) ?></b>
      <span class="mc-count"><?= num($m['item_count']) ?> लिंक</span>
      <span class="mc-edit"><?= can('menus.edit') ? 'बदलें' : 'देखें' ?> <i class="fa-solid fa-arrow-right"></i></span>
    </a>
  <?php endforeach; ?>
</div>
<p class="small text-body-secondary mt-3"><i class="fa-solid fa-circle-info me-1"></i> फ़ुटर कॉलम का नाम ही वेबसाइट पर कॉलम की हेडिंग बनता है। श्रेणी, लोकेशन और टॉपिक वाले लिंक Phase 3 के बाद जोड़ सकेंगे।</p>
