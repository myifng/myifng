<?php
use App\Controllers\Admin\AnalyticsController as AC;
$this->layout('layouts/admin');
$title = 'ट्रेंडिंग इंजन';
$maxScore = max(1, (float) (reset($scores) ?: 1));
$list = static function (array $rows, string $empty, ?string $extra = null): string {
    if (!$rows) {
        return '<div class="panel-body small text-body-secondary">' . e($empty) . '</div>';
    }
    $h = '<ol class="rank-admin">';
    foreach ($rows as $n) {
        $h .= '<li><a href="' . e(route('admin.analytics.news', ['id' => $n['id']])) . '">' . e($n['title']) . '</a>' . ($extra && isset($n[$extra]) ? ' <small class="text-body-secondary">· ' . num((int) $n[$extra]) . ' शेयर</small>' : '') . '</li>';
    }
    return $h . '</ol>';
};
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.analytics.index')) ?>">एनालिटिक्स</a></li><li class="breadcrumb-item active" aria-current="page">ट्रेंडिंग</li></ol></nav>
    <h1>ट्रेंडिंग इंजन</h1>
    <p>स्कोर = पिछले <?= (int) setting('trending_hours', '24') ?> घंटे के यूनीक पाठक (हाल के 3 घंटे ज़्यादा वज़न) + शेयर × 5, ख़बर की उम्र के साथ घटता। हर 10 मिनट ताज़ा।</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= e(route('trending')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> वेबसाइट पर देखें</a>
</div>
<?= $this->insert('admin/analytics/_nav', ['active' => 'trending']) ?>

<div class="row g-3 mb-3">
  <div class="col-xl-8">
    <section class="panel h-100">
      <div class="panel-head"><h2><i class="fa-solid fa-fire me-2 text-body-secondary"></i>अभी ट्रेंडिंग (वेबसाइट पर यही क्रम)</h2></div>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th style="width:40px">#</th><th>ख़बर</th><th style="width:26%" class="d-none d-md-table-cell">स्कोर</th><?php if ($canOverride): ?><th class="text-end">नियंत्रण</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($items as $i => $n): $s = $scores[(int) $n['id']] ?? null; ?>
          <tr>
            <td class="text-body-secondary"><?= num($i + 1) ?></td>
            <td><a class="fw-semibold" href="<?= e(route('admin.analytics.news', ['id' => $n['id']])) ?>"><?= e($n['title']) ?></a>
              <div class="small text-body-secondary"><?= e((string) ($n['category'] ?? '')) ?> · <?= hindi_date($n['published_at']) ?><?= !empty($n['pinned']) ? ' · <span class="text-success"><i class="fa-solid fa-thumbtack"></i> पिन</span>' : ($s === null ? ' · <span>(बैकअप: सबसे ज़्यादा पढ़ी)</span>' : '') ?></div></td>
            <td class="d-none d-md-table-cell"><?php if ($s !== null): ?><div class="bl-bar"><i style="width:<?= (int) round($s * 100 / $maxScore) ?>%"></i></div><small class="text-body-secondary"><?= e((string) round($s, 1)) ?></small><?php else: ?>—<?php endif; ?></td>
            <?php if ($canOverride): ?><td class="text-end text-nowrap">
              <form method="post" action="<?= e(route('admin.analytics.trending.override')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="news" value="<?= (int) $n['id'] ?>"><input type="hidden" name="hours" value="24">
                <?php if (empty($n['pinned'])): ?><button class="btn btn-sm btn-outline-success" name="action" value="pin" title="24 घंटे के लिए पिन" aria-label="पिन करें"><i class="fa-solid fa-thumbtack"></i></button><?php endif; ?>
                <button class="btn btn-sm btn-outline-secondary" name="action" value="hide" title="24 घंटे के लिए छुपाएँ" aria-label="छुपाएँ"><i class="fa-solid fa-eye-slash"></i></button></form>
            </td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="4" class="text-body-secondary p-4">अभी कोई ख़बर नहीं।</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </section>
  </div>
  <div class="col-xl-4 d-grid gap-3 align-content-start">
    <?php if ($canOverride): ?>
    <section class="panel">
      <div class="panel-head"><h2><i class="fa-solid fa-sliders me-2 text-body-secondary"></i>पिन / छुपाएँ</h2></div>
      <form class="panel-body d-grid gap-2" method="post" action="<?= e(route('admin.analytics.trending.override')) ?>">
        <?= csrf_field() ?>
        <label class="form-label mb-0" for="tr-news">ख़बर (ID, पूरा पता या स्लग)</label>
        <input id="tr-news" name="news" class="form-control" required maxlength="500" value="<?= e((string) old('news')) ?>" placeholder="जैसे 42 या https://…/news/…">
        <label class="form-label mb-0" for="tr-h">कितनी देर</label>
        <select id="tr-h" name="hours" class="form-select"><?php foreach (AC::HOURS as $k => $l): ?><option value="<?= e((string) $k) ?>"<?= selected($k, '24') ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <div class="d-flex gap-2 flex-wrap"><button class="btn btn-success" name="action" value="pin"><i class="fa-solid fa-thumbtack me-1"></i>ऊपर पिन करें</button><button class="btn btn-outline-secondary" name="action" value="hide"><i class="fa-solid fa-eye-slash me-1"></i>छुपाएँ</button></div>
        <p class="small text-body-secondary mb-0">पिन = ट्रेंडिंग में सबसे ऊपर। छुपाएँ = ट्रेंडिंग, सबसे ज़्यादा पढ़ी/शेयर से बाहर (जैसे संवेदनशील ख़बर)। ख़बर पर "ट्रेंडिंग" फ़्लैग भी पिन माना जाता है।</p>
      </form>
    </section>
    <?php endif; ?>
    <section class="panel">
      <div class="panel-head"><h2>चालू ओवरराइड</h2><span class="badge text-bg-light border"><?= num(count($overrides)) ?></span></div>
      <?php if ($overrides): ?><ul class="list-group list-group-flush">
        <?php foreach ($overrides as $o): ?><li class="list-group-item d-flex gap-2 align-items-start">
          <span class="badge <?= $o['action'] === 'pin' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $o['action'] === 'pin' ? 'पिन' : 'छुपी' ?></span>
          <div class="flex-grow-1 min-w-0"><div class="text-truncate"><?= e($o['title']) ?></div><small class="text-body-secondary"><?= $o['until'] ? 'तक ' . hindi_date($o['until'], true) : 'हमेशा' ?> · <?= e((string) $o['by_name']) ?></small></div>
          <?php if ($canOverride): ?><form method="post" action="<?= e(route('admin.analytics.trending.remove', ['id' => $o['news_id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0" aria-label="ओवरराइड हटाएँ" title="हटाएँ"><i class="fa-solid fa-xmark"></i></button></form><?php endif; ?>
        </li><?php endforeach; ?>
      </ul><?php else: ?><div class="panel-body small text-body-secondary">कोई नहीं।</div><?php endif; ?>
      <?php if ($flagged): ?><div class="panel-body border-top small"><b>"ट्रेंडिंग" फ़्लैग वाली ख़बरें:</b> <?= implode(', ', array_map(static fn($f) => e($f['title']), $flagged)) ?></div><?php endif; ?>
    </section>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6 col-xl-4"><section class="panel h-100"><div class="panel-head"><h2>आज सबसे ज़्यादा पढ़ी</h2></div><?= $list($read, 'डेटा नहीं।') ?></section></div>
  <div class="col-md-6 col-xl-4"><section class="panel h-100"><div class="panel-head"><h2>सबसे ज़्यादा शेयर (7 दिन)</h2></div><?= $list($shared, 'अभी शेयर दर्ज नहीं हुए।', 'share_count') ?></section></div>
  <div class="col-md-12 col-xl-4"><section class="panel h-100"><div class="panel-head"><h2>ट्रेंडिंग विषय (पट्टी)</h2></div>
    <div class="panel-body d-flex flex-wrap gap-2"><?php foreach ($tags as $t): ?><span class="badge text-bg-light border">#<?= e($t['name']) ?> <small class="text-body-secondary"><?= $t['kind'] === 'topic' ? 'टॉपिक' : 'टैग' ?></small></span><?php endforeach; ?><?php if (!$tags): ?><span class="small text-body-secondary">कोई नहीं।</span><?php endif; ?></div></section></div>
</div>
