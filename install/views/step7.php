<?php $d = $_SESSION['install_done'] ?? []; ?>
<section class="panel">
  <div class="panel-body p-4 text-center">
    <div class="confirm-icon" style="color:var(--bs-success);background:var(--bs-success-bg-subtle)"><i class="fa-solid fa-check"></i></div>
    <h1 class="h3 mt-3">इंस्टॉल पूरा हो गया</h1>
    <p class="text-body-secondary">आपका डिजिटल न्यूज़रूम तैयार है। इंस्टॉल विज़ार्ड अब लॉक हो गया है।</p>
  </div>
  <div class="px-4 pb-2">
    <dl class="kv border rounded">
      <dt>एडमिन पैनल</dt><dd><a href="<?= h($d['admin'] ?? '') ?>"><?= h($d['admin'] ?? '') ?></a></dd>
      <dt>लॉगिन ईमेल</dt><dd><?= h($d['email'] ?? '') ?></dd>
      <?php if (!empty($d['demo'])): ?>
        <dt>डेमो खाते</dt><dd>admin.demo@, editor.demo@, reporter.demo@, reporter2.demo@, employee.demo@ (सब <code>@example.com</code>)</dd>
        <dt>डेमो पासवर्ड</dt><dd><code class="fs-6"><?= h($d['demo']) ?></code> <span class="small text-body-secondary">(इसे अभी नोट कर लें)</span></dd>
      <?php endif; ?>
    </dl>
    <div class="alert alert-warning mt-3"><i class="fa-solid fa-shield-halved me-1"></i> <b>सुरक्षा:</b> सर्वर से <b>/install</b> फ़ोल्डर हटा दें (cPanel → File Manager)। config/env.php किसी को न दें।</div>
  </div>
  <div class="panel-foot"><a class="btn btn-light" href="<?= h($d['url'] ?? '../') ?>/" target="_blank" rel="noopener">वेबसाइट देखें</a><a class="btn btn-brand" href="<?= h($d['admin'] ?? '../') ?>">एडमिन पैनल में लॉगिन करें <i class="fa-solid fa-arrow-right ms-1"></i></a></div>
</section>
