<form method="post" class="panel">
  <input type="hidden" name="_t" value="<?= h(itoken()) ?>">
  <div class="panel-head"><h2>कदम 1: सर्वर की जाँच</h2></div>
  <div class="panel-body">
    <p class="text-body-secondary">इंस्टॉल से पहले देखते हैं कि आपकी होस्टिंग तैयार है या नहीं। <b class="text-success">PASS</b> = ठीक, <b class="text-warning">WARNING</b> = चल जाएगा पर सुधारना अच्छा, <b class="text-danger">ERROR</b> = ठीक किए बिना आगे नहीं बढ़ सकते।</p>
    <?php foreach ($reqs as $group => $items): ?>
      <h3 class="h6 fw-bold mt-4 mb-2"><?= h($group) ?></h3>
      <ul class="checks">
        <?php foreach ($items as [$label, $state, $note]): ?>
          <li class="<?= $state ?>">
            <span class="ic"><i class="fa-solid <?= $state === 'pass' ? 'fa-check' : ($state === 'warn' ? 'fa-exclamation' : 'fa-xmark') ?>"></i></span>
            <div><b><?= h($label) ?></b><?php if ($note): ?><small><?= h($note) ?></small><?php endif; ?></div>
            <span class="tag"><?= $state === 'pass' ? 'PASS' : ($state === 'warn' ? 'WARNING' : 'ERROR') ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
    <?php if ($hasFail): ?>
      <div class="alert alert-danger mt-3 mb-0">ERROR वाली चीज़ें ठीक करें: cPanel → <b>Select PHP Version</b> से PHP 8.1+ चुनें और एक्सटेंशन चालू करें। फ़ोल्डर की अनुमति (permission) 755 करें। ज़रूरत हो तो होस्टिंग सपोर्ट से मदद लें।</div>
    <?php endif; ?>
  </div>
  <div class="panel-foot"><a class="btn btn-light" href="?step=1"><i class="fa-solid fa-rotate me-1"></i>दोबारा जाँचें</a><button class="btn btn-brand" type="submit"<?= $hasFail ? ' disabled' : '' ?>>आगे बढ़ें <i class="fa-solid fa-arrow-right ms-1"></i></button></div>
</form>
