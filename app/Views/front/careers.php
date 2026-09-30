<?php
$this->layout('layouts/front');
$types = ['full_time' => 'फ़ुल-टाइम', 'part_time' => 'पार्ट-टाइम', 'internship' => 'इंटर्नशिप', 'freelance' => 'फ़्रीलांस', 'contract' => 'कॉन्ट्रैक्ट'];
?>
<div class="wrap page-wrap with-side">
  <div>
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page">करियर</span></nav>
    <div class="box">
      <h1 class="list-title"><i class="fa-solid fa-briefcase"></i> करियर और इंटर्नशिप</h1>
      <p class="list-desc"><?= e(setting('site_name')) ?> की टीम से जुड़ें: पत्रकारिता, वीडियो, डिज़ाइन, टेक्नोलॉजी और मार्केटिंग।</p>
      <?php if ($jobs): ?>
        <ul class="job-list"><?php foreach ($jobs as $j): ?>
          <li><a href="<?= e(route('careers.show', ['slug' => $j['slug']])) ?>"><h2><?= e($j['title']) ?></h2>
            <span class="job-meta"><span class="job-type t-<?= e($j['job_type']) ?>"><?= e($types[$j['job_type']]) ?></span><?= $j['department'] ? '<span><i class="fa-solid fa-building"></i> ' . e($j['department']) . '</span>' : '' ?><?= $j['location'] ? '<span><i class="fa-solid fa-location-dot"></i> ' . e($j['location']) . '</span>' : '' ?><?= $j['deadline'] ? '<span><i class="fa-regular fa-calendar"></i> अंतिम तारीख़: ' . e(hindi_date($j['deadline'])) . '</span>' : '' ?></span></a></li>
        <?php endforeach; ?></ul>
      <?php else: ?><div class="empty"><p>अभी कोई वैकेंसी खुली नहीं है। जल्द दोबारा देखें।</p></div><?php endif; ?>
    </div>
  </div>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
