<?php
use App\Models\NewsletterCampaign as NC;
$this->layout('layouts/admin');
$title = $c['subject'];
$pc = $c['recipients'] ? round(100 * ($c['sent'] + $c['failed']) / $c['recipients']) : 0;
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.newsletter.index')) ?>">न्यूज़लेटर</a></li><li class="breadcrumb-item active" aria-current="page">कैंपेन</li></ol></nav>
    <h1><?= e($c['subject']) ?></h1>
    <p><span class="badge text-bg-<?= NC::BADGE[$c['status']] ?>"><?= e(NC::STATUSES[$c['status']]) ?></span> <?= $c['status'] === 'scheduled' ? e(hindi_date($c['scheduled_at'], true)) . ' पर' : '' ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (in_array($c['status'], ['draft', 'scheduled'], true) && can('newsletter.edit')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.newsletter.edit', ['id' => $c['id']])) ?>"><i class="fa-solid fa-pen me-1"></i> बदलें</a><?php endif; ?>
    <?php if (in_array($c['status'], ['scheduled', 'sending'], true) && can('newsletter.manage')): ?><form method="post" action="<?= e(route('admin.newsletter.cancel', ['id' => $c['id']])) ?>" data-confirm="भेजना रुक जाएगा।"><?= csrf_field() ?><button class="btn btn-outline-danger" type="submit"><i class="fa-solid fa-stop me-1"></i> रोकें</button></form><?php endif; ?>
    <?php if ($queued && can('newsletter.manage')): ?><form method="post" action="<?= e(route('admin.newsletter.process')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-play me-1"></i> अभी कुछ भेजें</button></form><?php endif; ?>
    <?php if (can('newsletter.create')): ?><form method="post" action="<?= e(route('admin.newsletter.duplicate', ['id' => $c['id']])) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit"><i class="fa-regular fa-copy me-1"></i> कॉपी</button></form><?php endif; ?>
  </div>
</div>
<div class="row g-3">
  <div class="col-xl-4">
    <section class="panel"><div class="panel-body">
      <div class="kpi mb-2"><span>भेजे गए</span><b><?= num($c['sent']) ?> / <?= num($c['recipients']) ?></b></div>
      <div class="progress mb-2" style="height:8px" role="progressbar" aria-valuenow="<?= $pc ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width:<?= $pc ?>%"></div></div>
      <p class="small mb-1">कतार में: <?= num($queued) ?> · विफल: <?= num($c['failed']) ?></p>
      <?php if ($c['started_at']): ?><p class="small mb-1">शुरू: <?= e(hindi_date($c['started_at'], true)) ?></p><?php endif; ?>
      <?php if ($c['finished_at']): ?><p class="small mb-0">पूरा: <?= e(hindi_date($c['finished_at'], true)) ?></p><?php endif; ?>
    </div></section>
    <?php if ($failed): ?><section class="panel mt-3"><div class="panel-head"><h2>विफल (पहले 50)</h2></div><ul class="list-group list-group-flush small"><?php foreach ($failed as $f): ?><li class="list-group-item"><?= e($f['email']) ?> <span class="text-body-secondary">· <?= e((string) $f['error']) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
  </div>
  <div class="col-xl-8"><section class="panel"><div class="panel-head"><h2>प्रीव्यू</h2></div>
    <iframe class="nl-preview" src="<?= e(route('admin.newsletter.preview', ['id' => $c['id']])) ?>" title="न्यूज़लेटर प्रीव्यू" sandbox></iframe></section></div>
</div>
