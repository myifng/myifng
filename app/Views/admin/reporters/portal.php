<?php
use App\Models\ReporterDocument;
use App\Services\NewsWorkflow;
$this->layout('layouts/admin');
$title = 'मेरा रिपोर्टर प्रोफ़ाइल';
$active = array_filter($documents, fn($d) => $d['status'] === 'active');
?>
<div class="page-head">
  <div><h1>मेरा रिपोर्टर प्रोफ़ाइल</h1><p>आपका ID, वैधता, प्रदर्शन और जारी दस्तावेज़। जानकारी बदलवानी हो तो डेस्क से संपर्क करें।</p></div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (can('news.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.news.create')) ?>"><i class="fa-solid fa-pen-nib me-1"></i> नई ख़बर</a><?php endif; ?>
    <a class="btn btn-outline-secondary" href="<?= e(route('admin.news.index')) ?>?status=rejected"><i class="fa-solid fa-circle-xmark me-1"></i> सुधार माँगी गई</a>
    <?php if (can('assignments.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.assignments.index')) ?>"><i class="fa-solid fa-list-check me-1"></i> मेरे असाइनमेंट</a><?php endif; ?>
  </div>
</div>
<?= $this->insert('admin/reporters/_profile', get_defined_vars()) ?>
<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <section class="panel">
      <div class="panel-head"><h2>मेरे दस्तावेज़</h2></div>
      <div class="panel-body">
        <?php if ($active): ?><ul class="list-unstyled mb-0 simple-list"><?php foreach ($active as $d): ?><li><b><?= e(ReporterDocument::TYPES[$d['type']][0]) ?></b> <span class="font-monospace small"><?= e($d['doc_no']) ?></span> · <a href="<?= e(route('admin.portal.document', ['doc' => $d['id']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print me-1"></i>प्रिंट / PDF</a></li><?php endforeach; ?></ul>
        <?php else: ?><p class="small text-body-secondary mb-0">अभी कोई दस्तावेज़ जारी नहीं हुआ। ID कार्ड के लिए डेस्क से संपर्क करें।</p><?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-lg-6">
    <section class="panel">
      <div class="panel-head"><h2>मेरी हाल की ख़बरें</h2></div>
      <div class="panel-body">
        <?php if ($recent): ?><ul class="list-unstyled mb-0 simple-list"><?php foreach ($recent as $n): ?><li><a href="<?= e(route('admin.news.edit', ['id' => $n['id']])) ?>"><?= e($n['title']) ?></a> <?= NewsWorkflow::badge($n['status']) ?></li><?php endforeach; ?></ul>
        <?php else: ?><p class="small text-body-secondary mb-0">अभी कोई ख़बर नहीं। <a href="<?= e(route('admin.news.create')) ?>">पहली ख़बर लिखें</a></p><?php endif; ?>
      </div>
    </section>
  </div>
</div>
