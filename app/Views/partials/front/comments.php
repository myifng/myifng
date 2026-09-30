<?php
/** $news, $c (open, why, thread, count), $reader */
$guests = setting('comments_who', 'guests') === 'guests';
$form = static function (int $parent = 0) use ($news, $reader, $guests): string {
    $h = '<form method="post" action="' . e(route('comments.store', ['slug' => $news['slug']])) . '" class="cm-form fgrid"' . ($parent ? ' data-reply-form hidden' : '') . '>' . csrf_field()
        . '<input type="hidden" name="ts" value="' . e(\App\Services\CommentService::stamp()) . '"><input type="hidden" name="parent_id" value="' . $parent . '">'
        . '<div class="hp" aria-hidden="true"><label>वेबसाइट <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
    if ($parent) { // जवाब: अलग id, पुराना इनपुट नहीं
        $id = 'cr' . $parent;
        if (!$reader) {
            $h .= '<div class="ff"><label for="' . $id . 'n">नाम</label><input id="' . $id . 'n" name="name" required maxlength="100" autocomplete="name"></div>'
                . '<div class="ff"><label for="' . $id . 'e">ईमेल (दिखाया नहीं जाएगा)</label><input id="' . $id . 'e" type="email" name="email" required maxlength="190" autocomplete="email"></div>';
        }
        $h .= '<div class="ff ff-wide"><label for="' . $id . 'b">आपका जवाब</label><textarea id="' . $id . 'b" name="body" rows="2" required minlength="3" maxlength="2000"></textarea></div>';
    } else {
        if (!$reader) {
            $h .= ff('text', 'name', 'नाम', ['required' => true, 'attrs' => ['maxlength' => 100, 'autocomplete' => 'name']]) . ff('email', 'email', 'ईमेल (दिखाया नहीं जाएगा)', ['required' => true, 'attrs' => ['maxlength' => 190, 'autocomplete' => 'email']]);
        }
        $h .= ff('textarea', 'body', 'आपकी टिप्पणी', ['required' => true, 'wide' => true, 'rows' => 3, 'attrs' => ['maxlength' => 2000, 'minlength' => 3]]);
    }
    $h .= '<div class="ff-wide cm-actions"><button class="btn" type="submit">' . ($parent ? 'जवाब भेजें' : 'टिप्पणी भेजें') . '</button><small class="muted">सभ्य भाषा रखें। टिप्पणियाँ मॉडरेशन के बाद दिख सकती हैं।</small></div></form>';
    return $h;
};
$item = static function (array $m, bool $reply = false): string {
    $staff = $m['user_id'] ? ' <span class="cm-staff">' . e((string) setting('site_name')) . '</span>' : '';
    return '<div class="cm-head"><span class="cm-av" aria-hidden="true">' . e(mb_substr((string) $m['name'], 0, 1)) . '</span><b>' . e($m['name']) . '</b>' . $staff
        . '<time datetime="' . e(date('c', strtotime((string) $m['created_at']))) . '">' . e(hindi_date((string) $m['created_at'], true)) . '</time></div><p class="cm-body">' . nl2br(e($m['body'])) . '</p>';
};
?>
<section class="comments" id="comments" aria-labelledby="cmHead">
  <?= block_head('टिप्पणियाँ' . ($c['count'] ? ' (' . num($c['count']) . ')' : '')) ?>
  <?php if ($c['open']): ?>
    <?php if (!$reader && !$guests): ?>
      <p class="cm-login"><a class="btn" href="<?= e(route('account.login')) ?>?next=<?= e(rawurlencode('/news/' . $news['slug'])) ?>">टिप्पणी के लिए लॉगिन करें</a></p>
    <?php else: ?>
      <?php if ($reader): ?><p class="muted cm-as">आप <b><?= e($reader['name']) ?></b> के रूप में टिप्पणी कर रहे हैं।</p><?php endif; ?>
      <?= $form() ?>
    <?php endif; ?>
  <?php elseif ($c['why']): ?><p class="muted"><?= e($c['why']) ?></p><?php endif; ?>
  <?php if ($c['thread']): ?>
    <ol class="cm-list">
      <?php foreach ($c['thread'] as $m): ?>
        <li id="comment-<?= (int) $m['id'] ?>"><?= $item($m) ?>
          <?php if ($c['open'] && ($reader || $guests)): ?><button type="button" class="cm-reply" data-reply>जवाब दें</button><?= $form((int) $m['id']) ?><?php endif; ?>
          <?php if ($m['replies']): ?><ol class="cm-replies"><?php foreach ($m['replies'] as $r): ?><li id="comment-<?= (int) $r['id'] ?>"><?= $item($r, true) ?></li><?php endforeach; ?></ol><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php elseif ($c['open']): ?><p class="muted">अभी कोई टिप्पणी नहीं। पहली टिप्पणी आप करें।</p><?php endif; ?>
</section>
