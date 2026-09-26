<?php
use App\Models\WebStorySlide;
$this->layout('layouts/admin');
$isNew = $row === null;
$title = $isNew ? 'नई वेब स्टोरी' : $row['title'];
$list = is_array(old('slides')) ? array_values(old('slides')) : $slides;
if (!$list) {
    $list = [['text_position' => 'bottom', 'theme' => 'dark', 'duration' => 7]];
}
$slideCard = function (array $s, string $i): string {
    $media = (string) ($s['media'] ?? '');
    $media = preg_match('~^media/[\w/.-]+$~', $media) ? $media : ''; // दोबारा दिखाते समय सिर्फ़ असली लाइब्रेरी पाथ
    $isVideo = $media !== '' && preg_match('/\.(mp4|webm|mov)$/i', $media);
    $prev = $media === '' ? '<i class="fa-regular fa-image"></i>' : ($isVideo ? '<video src="' . e(upload_url($media)) . '" muted preload="metadata"></video>' : '<img src="' . e(media_url($media, 'medium')) . '" alt="">');
    $sel = function (string $k, array $opts, string $def) use ($s, $i) {
        $o = '';
        foreach ($opts as $v => $l) {
            $o .= '<option value="' . e($v) . '"' . selected($v, $s[$k] ?? $def) . '>' . e($l) . '</option>';
        }
        return '<select class="form-select form-select-sm" name="slides[' . $i . '][' . $k . ']" data-sl="' . $k . '">' . $o . '</select>';
    };
    $in = fn($k, $max, $ph = '') => '<input class="form-control form-control-sm" name="slides[' . $i . '][' . $k . ']" value="' . e($s[$k] ?? '') . '" maxlength="' . $max . '" placeholder="' . e($ph) . '" data-sl="' . $k . '">';
    return '<li class="sl-card" data-sl-item tabindex="-1">'
        . '<div class="sl-head"><b data-sl-no>स्लाइड</b><span class="ms-auto d-flex gap-1">'
        . '<button type="button" class="btn btn-sm btn-icon btn-light" data-sl-up aria-label="ऊपर"><i class="fa-solid fa-arrow-up"></i></button>'
        . '<button type="button" class="btn btn-sm btn-icon btn-light" data-sl-down aria-label="नीचे"><i class="fa-solid fa-arrow-down"></i></button>'
        . '<button type="button" class="btn btn-sm btn-icon btn-light" data-sl-copy aria-label="कॉपी बनाएँ"><i class="fa-regular fa-copy"></i></button>'
        . '<button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-sl-remove aria-label="हटाएँ"><i class="fa-solid fa-xmark"></i></button></span></div>'
        . '<div class="sl-body"><div class="sl-media"><div class="sl-thumb" data-sl-thumb>' . $prev . '</div>'
        . '<input type="hidden" name="slides[' . $i . '][media]" value="' . e($media) . '" data-sl="media">'
        . '<div class="d-flex gap-1 flex-wrap"><button type="button" class="btn btn-sm btn-outline-secondary" data-sl-pick="image"><i class="fa-regular fa-image me-1"></i>इमेज</button>'
        . '<button type="button" class="btn btn-sm btn-outline-secondary" data-sl-pick="video"><i class="fa-solid fa-film me-1"></i>वीडियो</button>'
        . '<button type="button" class="btn btn-sm btn-link text-danger" data-sl-clear>हटाएँ</button></div></div>'
        . '<div class="sl-fields"><label>शीर्षक' . $in('heading', 200, 'बड़ा, छोटा शीर्षक') . '</label>'
        . '<label>टेक्स्ट<textarea class="form-control form-control-sm" rows="2" name="slides[' . $i . '][body]" maxlength="600" data-sl="body">' . e($s['body'] ?? '') . '</textarea></label>'
        . '<div class="sl-row"><label>टेक्स्ट कहाँ' . $sel('text_position', WebStorySlide::POSITIONS, 'bottom') . '</label><label>थीम' . $sel('theme', WebStorySlide::THEMES, 'dark') . '</label>'
        . '<label>सेकंड<input class="form-control form-control-sm" type="number" min="3" max="20" name="slides[' . $i . '][duration]" value="' . (int) ($s['duration'] ?? 7) . '" data-sl="duration"></label></div>'
        . '<div class="sl-row"><label>बटन' . $in('cta_label', 60, 'पूरी ख़बर पढ़ें') . '</label><label class="flex-grow-1">बटन का लिंक' . $in('cta_url', 500, 'https://… या /news/…') . '</label></div>'
        . '</div></div></li>';
};
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.web_stories.index')) ?>">वेब स्टोरी</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नई' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
    <p>Google के लिए 5 से 30 स्लाइड अच्छी रहती हैं; हर स्लाइड पर कम शब्द, बड़ी खड़ी (9:16) फ़ोटो।</p>
  </div>
</div>
<form method="post" action="<?= e($isNew ? route('admin.web_stories.store') : route('admin.web_stories.update', ['id' => $row['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <?= field('text', 'title', 'शीर्षक', $row['title'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 255, 'data-count' => 70]]) ?>
        <?= field('text', 'slug', 'URL (स्लग)', $row['slug'] ?? '', ['prefix' => '/web-stories/', 'attrs' => ['maxlength' => 190]]) ?>
        <?= field('textarea', 'description', 'छोटा विवरण', $row['description'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 500]]) ?>
      </div></section>

      <section class="panel mt-3 ws-builder" data-ws>
        <div class="panel-head"><h2><i class="fa-solid fa-clone me-2 text-body-secondary"></i>स्लाइड <span class="badge text-bg-light" data-sl-count><?= count($list) ?></span></h2>
          <button type="button" class="btn btn-sm btn-brand" data-sl-add><i class="fa-solid fa-plus me-1"></i>स्लाइड जोड़ें</button></div>
        <div class="panel-body">
          <?php if (error('slides')): ?><div class="alert alert-danger py-2"><?= e(error('slides')) ?></div><?php endif; ?>
          <div class="ws-grid">
            <ol class="sl-list" data-sl-list data-max="<?= WebStorySlide::MAX ?>"><?php foreach ($list as $i => $s): ?><?= $slideCard((array) $s, (string) $i) ?><?php endforeach; ?></ol>
            <div class="ws-preview-wrap" aria-hidden="true">
              <div class="ws-preview" data-ws-preview>
                <div class="wsp-bars" data-wsp-bars></div>
                <div class="wsp-media" data-wsp-media></div>
                <div class="wsp-text" data-wsp-text><b data-wsp-h></b><p data-wsp-p></p><span class="wsp-cta" data-wsp-cta hidden></span></div>
              </div>
              <p class="small text-body-secondary text-center mt-2 mb-0">प्रीव्यू: जिस स्लाइड पर काम कर रहे हैं</p>
            </div>
          </div>
          <template data-sl-template><?= $slideCard(['text_position' => 'bottom', 'theme' => 'dark', 'duration' => 7], '__i__') ?></template>
        </div>
      </section>
      <?= $this->insert('admin/multimedia/_seo', ['row' => $row]) ?>
    </div>
    <div class="col-xl-4">
      <?php ob_start(); ?>
        <?= media_field('cover', 'पोस्टर (खड़ी 3:4 इमेज; ख़ाली = पहली स्लाइड)', $row['cover'] ?? '') ?>
      <?php $publishExtra = ob_get_clean(); ?>
      <?= $this->insert('admin/multimedia/_publish', get_defined_vars()) ?>
    </div>
  </div>
</form>
<?= $this->insert('admin/multimedia/_danger', ['row' => $row, 'module' => $module]) ?>
