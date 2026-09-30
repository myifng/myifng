<div class="nl-block">
  <div class="nl-text"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i>
    <div><h2><?= e($title ?: 'न्यूज़लेटर') ?></h2><p><?= e((string) ($set['text'] ?? 'हर सुबह दिन की बड़ी ख़बरें आपके ईमेल पर')) ?></p></div></div>
  <form method="post" action="<?= e(route('newsletter.subscribe')) ?>" class="nl-form" data-nl-form><?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>वेबसाइट <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <input type="email" name="email" required maxlength="190" placeholder="आपका ईमेल" aria-label="आपका ईमेल" autocomplete="email">
    <button class="btn" type="submit">सब्सक्राइब</button>
    <?php if (count($lists) > 1): ?><div class="nl-lists"><?php foreach ($lists as $l): ?><label class="check"><input type="checkbox" name="lists[]" value="<?= (int) $l['id'] ?>" checked> <?= e($l['name']) ?></label><?php endforeach; ?></div><?php endif; ?>
    <small class="nl-msg" data-nl-msg role="status"></small>
  </form>
</div>
