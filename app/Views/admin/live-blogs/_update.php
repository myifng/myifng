<?php
/** कंट्रोल पेज पर एक अपडेट (AJAX से भी यही HTML लौटता है) */
use App\Services\LiveBlogService;
$json = json_encode(['id' => (int) $u['id'], 'title' => (string) $u['title'], 'body' => (string) $u['body'], 'image' => (string) $u['image'], 'image_thumb' => $u['image'] ? media_url($u['image'], 'thumb') : '',
    'embed_url' => (string) $u['embed_url'], 'is_key' => (int) $u['is_key'], 'is_pinned' => (int) $u['is_pinned'], 'posted_at' => date('Y-m-d\TH:i', strtotime($u['posted_at']))], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$blogId = (int) $u['live_blog_id'];
?>
<li class="lu-admin" data-update-id="<?= (int) $u['id'] ?>" data-json="<?= e($json) ?>">
  <?= LiveBlogService::render($u, true) ?>
  <div class="lu-tools">
    <?php if (can('live_blogs.edit')): ?>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-lu-edit><i class="fa-solid fa-pen me-1"></i>बदलें</button>
      <form method="post" action="<?= e(route('admin.live_blogs.updates.pin', ['id' => $blogId, 'uid' => $u['id']])) ?>" data-lu-ajax><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit" data-no-lock><i class="fa-solid fa-thumbtack me-1"></i><?= $u['is_pinned'] ? 'पिन हटाएँ' : 'पिन करें' ?></button></form>
    <?php endif; ?>
    <?php if (can('live_blogs.delete')): ?>
      <form method="post" action="<?= e(route('admin.live_blogs.updates.destroy', ['id' => $blogId, 'uid' => $u['id']])) ?>" data-lu-ajax data-lu-confirm="यह अपडेट हट जाएगा।"><?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn btn-sm btn-outline-danger" type="submit" data-no-lock><i class="fa-solid fa-trash me-1"></i>हटाएँ</button></form>
    <?php endif; ?>
  </div>
</li>
