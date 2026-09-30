<?php
$this->layout('layouts/admin');
$title = 'सुरक्षा';
$okN = count(array_filter($checks, static fn($c) => $c[1] === true));
$canManage = can('system.manage');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.system.index')) ?>">सिस्टम</a></li><li class="breadcrumb-item active" aria-current="page">सुरक्षा</li></ol></nav>
    <h1>सुरक्षा</h1>
    <p><?= num($okN) ?>/<?= num(count($checks)) ?> जाँच ठीक · आपका IP: <code><?= e($ip) ?></code></p>
  </div>
  <?php if (can('settings.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.settings', ['tab' => 'security'])) ?>"><i class="fa-solid fa-sliders me-1"></i> सुरक्षा सेटिंग</a><?php endif; ?>
</div>
<?= $this->insert('admin/system/_nav', ['active' => 'security']) ?>
<div class="row g-3">
  <div class="col-xl-7"><section class="panel h-100"><div class="panel-head"><h2>सुरक्षा चेकलिस्ट</h2></div>
    <ul class="health-list">
      <?php foreach ($checks as [$name, $ok, $hint, $critical]): $st = $ok === null ? 'unk' : ($ok ? 'ok' : ($critical ? 'bad' : 'warn')); ?>
        <li class="<?= $st ?>"><i class="fa-solid <?= ['ok' => 'fa-circle-check', 'bad' => 'fa-circle-exclamation', 'warn' => 'fa-triangle-exclamation', 'unk' => 'fa-circle-question'][$st] ?>"></i>
          <span><?= e($name) ?><?php if ($ok !== true): ?><small><?= $ok === null ? 'यहाँ से जाँच नहीं हो सकी (सर्वर ख़ुद को HTTP अनुरोध नहीं भेज पाया); ब्राउज़र में ख़ुद खोलकर देखें। ' : '' ?><?= e($hint) ?></small><?php endif; ?></span>
          <b><?= $ok === null ? '?' : ($ok ? 'ठीक' : ($critical ? 'ज़रूरी' : 'सलाह')) ?></b></li>
      <?php endforeach; ?>
    </ul>
    <div class="panel-body small text-body-secondary border-top">पहले से लागू: PDO prepared statements, हर फ़ॉर्म पर CSRF, आउटपुट escaping, HTML sanitizer, फ़ाइल की असली MIME जाँच, bcrypt पासवर्ड, सुरक्षित/HttpOnly/SameSite कुकी, लॉगिन पर सेशन रोटेशन, निष्क्रियता पर लॉगआउट, लॉगिन की सीमा, रोल-आधारित अनुमति, ऑडिट लॉग, सुरक्षा हेडर (CSP, X-Frame-Options, nosniff, Referrer-Policy, HTTPS पर HSTS)।</div>
  </section></div>
  <div class="col-xl-5 d-grid gap-3 align-content-start">
    <section class="panel"><div class="panel-head"><h2>अभी रुके हुए लॉगिन</h2>
      <?php if ($canManage && $blocked): ?><form method="post" action="<?= e(route('admin.system.unblock')) ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="all"><button class="btn btn-sm btn-outline-danger" type="submit">सब खोलें</button></form><?php endif; ?></div>
      <?php if ($blocked): ?><ul class="list-group list-group-flush"><?php foreach ($blocked as $b): ?>
        <li class="list-group-item d-flex gap-2 align-items-center"><span class="badge text-bg-light border"><?= $b['kind'] === 'ip' ? 'IP' : 'ईमेल' ?></span><div class="flex-grow-1 min-w-0"><div class="text-truncate font-monospace small"><?= e($b['k']) ?></div><small class="text-body-secondary"><?= num((int) $b['c']) ?> ग़लत प्रयास · <?= time_ago($b['last']) ?></small></div>
          <?php if ($canManage): ?><form method="post" action="<?= e(route('admin.system.unblock')) ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="<?= e($b['kind']) ?>"><input type="hidden" name="key" value="<?= e($b['k']) ?>"><button class="btn btn-sm btn-outline-secondary" type="submit">खोलें</button></form><?php endif; ?></li>
      <?php endforeach; ?></ul><?php else: ?><div class="panel-body small text-success"><i class="fa-solid fa-circle-check"></i> कोई IP/ईमेल रुका नहीं है।</div><?php endif; ?>
    </section>
    <section class="panel"><div class="panel-head"><h2>एडमिन IP allowlist</h2></div><div class="panel-body small">
      <?php if ($allow): ?><p class="mb-1">स्टाफ़ सिर्फ़ इन IP से:</p><ul class="mb-2"><?php foreach ($allow as $a): ?><li class="font-monospace"><?= e($a) ?></li><?php endforeach; ?></ul>
      <?php else: ?><p class="mb-2">बंद: स्टाफ़ कहीं से भी लॉगिन कर सकता है।</p><?php endif; ?>
      <p class="text-body-secondary mb-0">रिपोर्टर पर लागू नहीं (वे मैदान से काम करते हैं)। फँस जाएँ तो सर्वर पर <code>config/env.php</code> में <code>'ADMIN_IP_BYPASS' => 1</code> जोड़ें।</p>
    </div></section>
  </div>
</div>
