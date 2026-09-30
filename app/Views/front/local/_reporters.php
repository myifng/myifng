<section class="box loc-reps"><?= block_head('यहाँ के रिपोर्टर') ?>
  <ul><?php foreach ($reporters as $r): $img = $r['photo'] ?: $r['avatar']; ?>
    <li><span class="el-ph"><?= $img ? media_img($img, 'thumb', $r['name']) : '<i class="fa-solid fa-user"></i>' ?></span><div><b><?= e($r['name']) ?></b><small><?= e(trim(($r['designation'] ?? 'रिपोर्टर') . ($r['area'] ? ' · ' . $r['area'] : ''), ' ·')) ?></small></div></li>
  <?php endforeach; ?></ul>
  <a class="more" href="<?= e(route('send_news')) ?>"><i class="fa-solid fa-camera-retro"></i> यहाँ से ख़बर भेजें</a>
</section>
