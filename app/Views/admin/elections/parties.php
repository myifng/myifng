<?php
$this->layout('layouts/admin');
$title = 'पार्टियाँ';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.elections.index')) ?>">चुनाव केंद्र</a></li><li class="breadcrumb-item active" aria-current="page">पार्टियाँ</li></ol></nav>
    <h1>पार्टियाँ और गठबंधन</h1>
    <p>रंग टैली के ग्राफ़ में दिखता है; गठबंधन का नाम एक जैसा लिखें (जैसे NDA, INDIA)।</p>
  </div>
</div>
<?= $this->insert('admin/elections/_nav', ['active' => 'parties']) ?>
<div class="row g-3">
  <div class="col-xl-8"><section class="panel">
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>पार्टी</th><th>गठबंधन</th><th class="text-end">उम्मीदवार</th><th class="text-end">काम</th></tr></thead>
      <tbody><?php foreach ($items as $p): ?>
        <tr><td><span class="party-dot" style="background:<?= e($p['color']) ?>"></span> <b><?= e($p['short_name']) ?></b> <span class="text-body-secondary"><?= e($p['name']) ?></span></td>
          <td><?= e((string) $p['alliance']) ?: '—' ?></td><td class="text-end"><?= num((int) $p['used']) ?></td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-party-edit='<?= e(json_encode(['id' => $p['id'], 'name' => $p['name'], 'short_name' => $p['short_name'], 'color' => $p['color'], 'alliance' => $p['alliance'], 'sort_order' => $p['sort_order']], JSON_UNESCAPED_UNICODE)) ?>' title="बदलें" aria-label="बदलें"><i class="fa-solid fa-pen"></i></button>
            <?php if (!(int) $p['used']): ?><?= delete_button(route('admin.elections.parties.delete', ['id' => $p['id']]), 'पार्टी हट जाएगी।') ?><?php endif; ?>
          </td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </section></div>
  <div class="col-xl-4"><section class="panel"><div class="panel-head"><h2 data-party-title data-new-label="नई पार्टी">नई पार्टी</h2></div>
    <form class="panel-body" method="post" action="<?= e(route('admin.elections.parties.save')) ?>" novalidate data-party-form>
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) old('id', '')) ?>">
      <?= field('text', 'name', 'पूरा नाम', '', ['required' => true]) ?>
      <div class="row g-2"><div class="col-7"><?= field('text', 'short_name', 'छोटा नाम', '', ['required' => true, 'attrs' => ['maxlength' => 20]]) ?></div>
        <div class="col-5"><?= field('color', 'color', 'रंग', '#777777') ?></div></div>
      <div class="row g-2"><div class="col-7"><?= field('text', 'alliance', 'गठबंधन', '') ?></div><div class="col-5"><?= field('number', 'sort_order', 'क्रम', '0') ?></div></div>
      <div class="d-flex gap-2"><button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button><button class="btn btn-outline-secondary" type="reset" data-party-reset>नई</button></div>
    </form>
  </section></div>
</div>
