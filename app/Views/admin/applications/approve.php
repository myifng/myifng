<?php
use App\Models\Reporter;
$this->layout('layouts/admin');
$title = 'मंज़ूरी: ' . $a['app_no'];
$months = (int) setting('reporter_validity_months', 12);
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.applications.index')) ?>">रिपोर्टर आवेदन</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.applications.show', ['id' => $a['id']])) ?>"><?= e($a['app_no']) ?></a></li><li class="breadcrumb-item active" aria-current="page">मंज़ूरी</li></ol></nav>
    <h1><?= e($a['full_name']) ?> को रिपोर्टर बनाएँ</h1>
    <p>मंज़ूरी पर: “Reporter” रोल वाला यूज़र खाता (ईमेल <?= e($a['email']) ?>), रिपोर्टर ID, और पासवर्ड बनाने का लिंक (72 घंटे)।</p>
  </div>
</div>
<?php if ($emailTaken): ?><div class="alert alert-danger">इस ईमेल से पहले से एक यूज़र खाता है, इसलिए मंज़ूरी नहीं हो सकती। उस खाते को “नया रिपोर्टर (मौजूदा यूज़र)” से रिपोर्टर बनाएँ।</div><?php endif; ?>
<section class="panel" style="max-width:820px">
  <form class="panel-body" method="post" action="<?= e(route('admin.applications.approve.run', ['id' => $a['id']])) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row">
      <div class="col-md-6"><?= field('text', 'designation', 'पद', ($a['reporter_type'] === 'district' ? 'ज़िला संवाददाता' : (Reporter::TYPES[$a['reporter_type']] ?? 'संवाददाता')), ['required' => true, 'attrs' => ['maxlength' => 100]]) ?></div>
      <div class="col-md-6"><?= field('select', 'reporter_type', 'प्रकार', $a['reporter_type'], ['options' => Reporter::TYPES, 'required' => true]) ?></div>
      <div class="col-md-6"><?= field('select', 'beat_category_id', 'बीट (श्रेणी)', '', ['options' => $categories, 'empty' => '—']) ?></div>
      <div class="col-md-6"><?= field('select', 'bureau_id', 'ब्यूरो', '', ['options' => $bureaus, 'empty' => '—']) ?></div>
      <div class="col-md-6"><?= field('date', 'joining_date', 'जॉइनिंग की तारीख़', date('Y-m-d'), ['required' => true]) ?></div>
      <div class="col-md-6"><?= field('date', 'valid_until', 'वैधता', date('Y-m-d', strtotime("+$months months")), ['required' => true, 'help' => "डिफ़ॉल्ट $months महीने (सेटिंग → रिपोर्टर)"]) ?></div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-brand" type="submit"<?= $emailTaken ? ' disabled' : '' ?>><i class="fa-solid fa-circle-check me-1"></i> मंज़ूर करके खाता बनाएँ</button>
      <a class="btn btn-light" href="<?= e(route('admin.applications.show', ['id' => $a['id']])) ?>">वापस</a>
    </div>
  </form>
</section>
