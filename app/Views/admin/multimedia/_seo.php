<section class="panel mt-3">
  <div class="panel-head"><h2><i class="fa-brands fa-google me-2 text-body-secondary"></i>SEO</h2></div>
  <div class="panel-body">
    <?= field('text', 'meta_title', 'SEO शीर्षक', $row['meta_title'] ?? '', ['attrs' => ['maxlength' => 190, 'data-count' => 60], 'help' => 'ख़ाली = शीर्षक']) ?>
    <?= field('textarea', 'meta_description', 'SEO विवरण', $row['meta_description'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 320, 'data-count' => 160], 'help' => 'ख़ाली = विवरण की शुरुआत']) ?>
  </div>
</section>
