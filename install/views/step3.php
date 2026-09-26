<form method="post" class="panel">
  <input type="hidden" name="_t" value="<?= h(itoken()) ?>">
  <div class="panel-head"><h2>कदम 3: डेटाबेस टेबल इंस्टॉल करें</h2><span class="badge text-bg-success"><i class="fa-solid fa-plug me-1"></i><?= h(st('db_version')) ?></span></div>
  <div class="panel-body">
    <p>डेटाबेस <b><?= h(st('db')['name'] ?? '') ?></b> से कनेक्शन हो गया। अब ये migrations चलेंगी, और रोल, अनुमतियाँ, भाषाएँ व डिफ़ॉल्ट सेटिंग डाली जाएँगी:</p>
    <?php if ($pending): ?>
      <ul class="list-group mig-list mb-0"><?php foreach ($pending as $m): ?><li class="list-group-item"><i class="fa-regular fa-file-code me-2 text-body-secondary"></i><?= h($m) ?></li><?php endforeach; ?></ul>
    <?php else: ?>
      <div class="alert alert-info mb-0">सभी टेबल पहले से मौजूद हैं। आगे बढ़ने पर ज़रूरी डेटा जाँचा जाएगा।</div>
    <?php endif; ?>
  </div>
  <div class="panel-foot"><a class="btn btn-light" href="?step=2">← पीछे</a><button class="btn btn-brand" type="submit"><i class="fa-solid fa-database me-1"></i>टेबल इंस्टॉल करें</button></div>
</form>
