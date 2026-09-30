<?php
$this->layout('layouts/front');
$types = ['full_time' => 'फ़ुल-टाइम', 'part_time' => 'पार्ट-टाइम', 'internship' => 'इंटर्नशिप', 'freelance' => 'फ़्रीलांस', 'contract' => 'कॉन्ट्रैक्ट'];
?>
<div class="wrap page-wrap with-side">
  <div>
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <a href="<?= e(route('careers')) ?>">करियर</a> <span aria-hidden="true">›</span> <span aria-current="page"><?= e($job['title']) ?></span></nav>
    <article class="box job-page">
      <h1 class="list-title"><?= e($job['title']) ?></h1>
      <p class="job-meta"><span class="job-type t-<?= e($job['job_type']) ?>"><?= e($types[$job['job_type']]) ?></span><?= $job['department'] ? '<span><i class="fa-solid fa-building"></i> ' . e($job['department']) . '</span>' : '' ?><?= $job['location'] ? '<span><i class="fa-solid fa-location-dot"></i> ' . e($job['location']) . '</span>' : '' ?>
        <span><i class="fa-solid fa-users"></i> <?= (int) $job['vacancies'] ?> पद</span><?= $job['salary'] ? '<span><i class="fa-solid fa-indian-rupee-sign"></i> ' . e($job['salary']) . '</span>' : '' ?><?= $job['deadline'] ? '<span><i class="fa-regular fa-calendar"></i> अंतिम तारीख़: ' . e(hindi_date($job['deadline'])) . '</span>' : '' ?></p>
      <?php if ($job['description']): ?><div class="prose"><?= $job['description'] /* सेव करते समय sanitize */ ?></div><?php endif; ?>
      <?php if ($job['requirements']): ?><h2 class="box-title">योग्यता</h2><div class="prose"><p><?= nl2br(e($job['requirements'])) ?></p></div><?php endif; ?>
      <section id="apply" class="job-apply">
        <?php if ($open && $formHtml !== ''): ?><h2 class="box-title">आवेदन करें</h2><?= $formHtml ?>
        <?php else: ?><div class="notice">इस पद के लिए आवेदन बंद हो चुके हैं।</div><?php endif; ?>
      </section>
    </article>
  </div>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
