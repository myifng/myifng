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
    <h2 class="h6 mt-4 mb-2"><i class="fa-solid fa-list-check me-1"></i> लाइव करने से पहले की चेकलिस्ट</h2>
    <ol class="small ps-3 mb-0 install-check">
      <li><b>/install फ़ोल्डर हटाएँ</b> (cPanel → File Manager)। विज़ार्ड लॉक है, फिर भी हटाना सबसे सुरक्षित है।</li>
      <li><b>SSL लगाएँ</b> (cPanel → SSL/TLS या AutoSSL) और साइट <code>https://</code> से खोलें।</li>
      <li><b>ईमेल (SMTP) सेट करें</b>: एडमिन → सेटिंग → ईमेल, फिर “टेस्ट मेल भेजें”। पासवर्ड रीसेट और OTP इसी से जाते हैं।</li>
      <li>सेटिंग में <b>लोगो, संपर्क, सोशल लिंक</b> और ज़रूरत हो तो <b>दो-चरण लॉगिन (OTP)</b> चालू करें।</li>
      <?php if (!empty($d['demo'])): ?><li><b>डेमो डेटा हटाएँ</b>: एडमिन → सिस्टम → “डेमो डेटा हटाएँ” (असली ख़बरें डालने से पहले)।</li><?php endif; ?>
      <li><b>बैकअप</b>: एडमिन → बैकअप से पहला बैकअप बनाकर डाउनलोड करें।</li>
      <li><code>config/env.php</code> किसी को न दें; इसमें डेटाबेस पासवर्ड और गुप्त कुंजी है।</li>
    </ol>
  </div>
  <div class="panel-foot"><a class="btn btn-light" href="<?= h($d['url'] ?? '../') ?>/" target="_blank" rel="noopener">वेबसाइट देखें</a><a class="btn btn-brand" href="<?= h($d['admin'] ?? '../') ?>">एडमिन पैनल में लॉगिन करें <i class="fa-solid fa-arrow-right ms-1"></i></a></div>
</section>
