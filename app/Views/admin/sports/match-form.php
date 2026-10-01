<?php
use App\Services\SportsService as SS;
$this->layout('layouts/admin');
$isNew = $m === null;
$title = $isNew ? 'नया मैच' : 'मैच बदलें';
$teamOpts = [];
foreach ($teams as $t) { $teamOpts[$t['id']] = $t['name'] . ' (' . $t['short_name'] . ') · ' . (SS::SPORTS[$t['sport']][0] ?? ''); }
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.sports.index')) ?>">खेल केंद्र</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($title) ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
  </div>
  <?php if (!$isNew): ?><a class="btn btn-outline-primary" href="<?= e(route('admin.sports.console', ['id' => $m['id']])) ?>"><i class="fa-solid fa-tower-broadcast me-1"></i> लाइव कंसोल</a><?php endif; ?>
</div>
<?php if (!$teams): ?><div class="alert alert-warning">पहले <?= can('sports.manage') ? '<a href="' . e(route('admin.sports.teams')) . '">टीमें</a>' : 'टीमें' ?> जोड़ें।</div><?php endif; ?>
<form method="post" action="<?= e($isNew ? route('admin.sports.matches.store') : route('admin.sports.matches.update', ['id' => $m['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <section class="panel"><div class="panel-body">
    <div class="row g-2">
      <div class="col-md-6"><?= field('select', 'tournament_id', 'टूर्नामेंट / सीरीज़', $m['tournament_id'] ?? ($prefill['tournament_id'] ?? ''), ['options' => array_column($tournaments, 'name', 'id'), 'empty' => '— (दोस्ताना / अकेला मैच)']) ?></div>
      <div class="col-md-3"><?= field('select', 'sport', 'खेल', $m['sport'] ?? 'cricket', ['options' => array_map(static fn($s) => $s[0], SS::SPORTS)]) ?></div>
      <div class="col-md-3"><?= field('select', 'status', 'स्थिति', $m['status'] ?? 'scheduled', ['options' => array_map(static fn($s) => $s[0], SS::MATCH_STATUSES)]) ?></div>
    </div>
    <div class="row g-2">
      <div class="col-md-6"><?= field('select', 'team1_id', 'पहली टीम (घरेलू)', $m['team1_id'] ?? '', ['options' => $teamOpts, 'empty' => 'चुनें…', 'required' => true]) ?></div>
      <div class="col-md-6"><?= field('select', 'team2_id', 'दूसरी टीम', $m['team2_id'] ?? '', ['options' => $teamOpts, 'empty' => 'चुनें…', 'required' => true]) ?></div>
    </div>
    <div class="row g-2">
      <div class="col-md-4"><?= field('text', 'title', 'मैच का नाम', $m['title'] ?? '', ['placeholder' => 'जैसे: पहला T20, मैच 12']) ?></div>
      <div class="col-md-4"><?= field('text', 'stage', 'चरण', $m['stage'] ?? '', ['placeholder' => 'ग्रुप / सेमीफ़ाइनल / फ़ाइनल']) ?></div>
      <div class="col-md-4"><?= field('text', 'group_name', 'ग्रुप (पॉइंट्स टेबल)', $m['group_name'] ?? '', ['placeholder' => 'A / B (ख़ाली = एक टेबल)']) ?></div>
    </div>
    <div class="row g-2">
      <div class="col-md-5"><?= field('datetime-local', 'start_at', 'शुरू होने का समय', isset($m['start_at']) ? date('Y-m-d\TH:i', strtotime($m['start_at'])) : '', ['required' => true]) ?></div>
      <div class="col-md-7"><?= field('text', 'venue', 'स्थान', $m['venue'] ?? '', ['placeholder' => 'जैसे: इकाना स्टेडियम, लखनऊ']) ?></div>
    </div>
    <div class="row g-2 align-items-end">
      <div class="col-md-4"><?= field('number', 'news_id', 'मैच रिपोर्ट (ख़बर ID)', $m['news_id'] ?? '') ?></div>
      <div class="col-md-8"><?= field('switch', 'is_featured', 'मुख्य मैच (होमपेज स्कोर ब्लॉक में सबसे ऊपर)', $m['is_featured'] ?? 0) ?></div>
    </div>
    <button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button>
  </div></section>
</form>
<?php if (!$isNew && can('sports.delete')): ?>
<div class="danger-zone mt-3"><div><b>मैच हटाएँ</b><p class="mb-0 small">कमेंट्री भी हटेगी।</p></div><?= delete_button(route('admin.sports.matches.destroy', ['id' => $m['id']]), 'मैच और उसकी कमेंट्री हट जाएगी।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
