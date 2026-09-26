<?php $this->layout('layouts/auth'); $title = 'सिस्टम अपडेट'; ?>
<h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-circle-arrow-up text-primary me-1"></i> सिस्टम अपडेट ज़रूरी है</h1>
<p class="text-body-secondary">सॉफ़्टवेयर का नया वर्ज़न (<?= e(config('app.version')) ?>) अपलोड हुआ है। डेटाबेस को इसके हिसाब से अपडेट करना है। तब तक एडमिन पैनल और वेबसाइट अस्थायी रूप से बंद हैं (पाठकों को “थोड़ी देर में लौटें” संदेश दिखता है), इसलिए अभी अपडेट करें। इसमें कुछ सेकंड लगते हैं।</p>
<ul class="list-group mig-list mb-3">
  <?php foreach ($pending as $m): ?><li class="list-group-item small"><i class="fa-regular fa-file-code me-2 text-body-secondary"></i><?= e($m) ?></li><?php endforeach; ?>
</ul>
<div class="alert alert-warning small"><i class="fa-solid fa-triangle-exclamation me-1"></i> अपडेट से पहले cPanel → Backup से डेटाबेस का बैकअप ले लें।</div>
<form method="post" action="<?= e(route('admin.system.migrate')) ?>">
  <?= csrf_field() ?>
  <button class="btn btn-brand w-100 btn-lg" type="submit">अभी अपडेट करें</button>
</form>
<form method="post" action="<?= e(route('admin.logout')) ?>" class="text-center mt-3"><?= csrf_field() ?><button class="btn btn-link" type="submit">लॉगआउट</button></form>
