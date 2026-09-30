<?php
use App\Models\Redirect;
$this->layout('layouts/admin');
$isNew = $r === null;
$title = $isNew ? 'नया रीडायरेक्ट' : 'रीडायरेक्ट बदलें';
$src = $r ? $r['source'] . ($r['match_type'] === 'prefix' ? '/*' : '') : ($prefill['source'] ?? '');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.redirects.index')) ?>">रीडायरेक्ट</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
    <?php if ($r && $r['is_auto']): ?><p class="text-warning-emphasis"><i class="fa-solid fa-wand-magic-sparkles"></i> यह स्लग बदलने पर अपने आप बना था। सेव करने पर हाथ वाला नियम बन जाएगा (पहले चलेगा)।</p><?php endif; ?>
  </div>
</div>
<div class="row"><div class="col-xl-8">
<form class="panel" method="post" action="<?= e($isNew ? route('admin.redirects.store') : route('admin.redirects.update', ['id' => $r['id']])) ?>" novalidate>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <?php if (!empty($prefill['nf'])): ?><input type="hidden" name="nf" value="<?= (int) $prefill['nf'] ?>"><?php endif; ?>
  <?php if (!empty($prefill['return'])): ?><input type="hidden" name="return" value="<?= e($prefill['return']) ?>"><?php endif; ?>
  <div class="panel-body">
    <?= field('text', 'source', 'पुराना पता', $src, ['required' => true, 'class' => 'font-monospace', 'placeholder' => '/news/purani-khabar', 'attrs' => ['maxlength' => 500],
        'help' => 'अपनी साइट का पाथ या पूरा URL। आख़िर में /* लगाएँ तो उससे शुरू होने वाले सारे पते (जैसे /old-section/*)।']) ?>
    <?= field('select', 'code', 'प्रकार', $r['code'] ?? 301, ['options' => Redirect::CODES]) ?>
    <div data-when="code:301,302,307"><?= field('text', 'target', 'नया पता', $r['target'] ?? '', ['class' => 'font-monospace', 'placeholder' => '/news/nayi-khabar या https://…', 'attrs' => ['maxlength' => 1000],
        'help' => 'पुराने पते में /* हो तो नए के आख़िर में /* लगाने पर बाकी हिस्सा साथ जाएगा (/old/* → /new/*)।']) ?></div>
    <div class="row g-2">
      <div class="col-md-8"><?= field('text', 'note', 'नोट (वैकल्पिक)', $r['note'] ?? '', ['attrs' => ['maxlength' => 255]]) ?></div>
      <div class="col-md-4"><?= field('select', 'status', 'स्थिति', $r['status'] ?? 'active', ['options' => ['active' => 'चालू', 'inactive' => 'बंद']]) ?></div>
    </div>
    <div class="d-flex gap-2"><button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button><a class="btn btn-light" href="<?= e(route('admin.redirects.index')) ?>">रद्द</a></div>
  </div>
</form>
</div>
<div class="col-xl-4 mt-3 mt-xl-0"><section class="panel"><div class="panel-body small">
  <h2 class="h6">कौन-सा चुनें?</h2>
  <p><b>301</b>: पता हमेशा के लिए बदला (स्लग, श्रेणी का नाम)। Google पुराने पते की रैंकिंग नए को देता है।</p>
  <p><b>302 / 307</b>: थोड़े समय के लिए (जैसे चुनाव लाइव पेज)।</p>
  <p class="mb-0"><b>410</b>: सामग्री जान-बूझकर हटाई गई; Google इसे जल्दी हटाता है।</p>
</div></section></div>
</div>
