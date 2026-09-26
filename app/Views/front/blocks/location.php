<div class="box locbox">
  <?php if (!$loc): ?>
    <?= block_head($heading) ?>
    <div class="mycity-cta">
      <p><i class="fa-solid fa-location-dot"></i> अपना शहर चुनें और यहाँ सीधे अपने शहर की ख़बरें पढ़ें।</p>
      <button type="button" class="btn" data-open-mycity>शहर चुनें</button>
      <?php if ($popular): ?><div class="chips"><?php foreach ($popular as $p): ?><a href="<?= e(url($p['path'])) ?>"><?= e($p['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
    </div>
  <?php else: ?>
    <?= block_head($heading . ($mine ? ' · आपका शहर' : ''), $tabs[0]['url']) ?>
    <?php if (count($tabs) > 1): ?>
      <div class="tabs" role="tablist" data-tabs>
        <?php foreach ($tabs as $i => $t): ?><button type="button" role="tab" id="lt-<?= e($loc['id'] . '-' . $i) ?>" aria-controls="lp-<?= e($loc['id'] . '-' . $i) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i ? ' tabindex="-1"' : '' ?>><?= e($t['name']) ?></button><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php foreach ($tabs as $i => $t): ?>
      <div class="tab-panel" role="tabpanel" id="lp-<?= e($loc['id'] . '-' . $i) ?>" aria-labelledby="lt-<?= e($loc['id'] . '-' . $i) ?>"<?= $i ? ' hidden' : '' ?>>
        <div class="state-grid">
          <div><?= news_card($t['items'][0], 'card', ['summary' => true, 'size' => 'large']) ?></div>
          <div class="rows"><?php foreach (array_slice($t['items'], 1) as $n): ?><?= news_card($n, 'row') ?><?php endforeach; ?>
            <?php if ($i && $t['url']): ?><a class="more" href="<?= e($t['url']) ?>"><?= e($t['name']) ?> की सभी ख़बरें <i class="fa-solid fa-angle-right"></i></a><?php endif; ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
