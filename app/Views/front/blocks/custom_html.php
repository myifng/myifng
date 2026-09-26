<?php /* सिर्फ़ homepage.manage वाले यूज़र का भरोसेमंद HTML (ऑडिट लॉग में दर्ज) */ ?>
<div class="<?= !empty($set['boxed']) ? 'box' : '' ?>"><?php if ($title && !empty($set['boxed'])): ?><?= block_head($title) ?><?php endif; ?><?= $set['html'] ?></div>
