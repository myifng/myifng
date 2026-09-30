<?php /** लोकेशन पेज के ऊपर/नीचे: $local = [top, media, nearby, reporters] */ ?>
<?php if (!empty($local['top'])): ?>
  <div class="box loc-top"><span class="loc-top-tag"><i class="fa-solid fa-bolt"></i> यहाँ की बड़ी ख़बर</span><?= news_card($local['top'], 'wide', ['h' => 'h2']) ?></div>
<?php endif; ?>
