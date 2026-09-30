<?php
/** $p (options के साथ), $voted, $open [bool, कारण], $results, $full */
[$isOpen, $why] = $open;
$total = max(1, (int) $p['total_votes']);
$max = max(array_map(static fn($o) => (int) $o['votes'], $p['options']) ?: [0]);
?>
<div class="poll-box" data-poll="<?= (int) $p['id'] ?>">
  <div class="poll-kicker"><i class="fa-solid fa-square-poll-vertical"></i> पोल<?= !$isOpen ? ' · बंद' : '' ?></div>
  <h3 class="poll-q"><?= $full ? e($p['question']) : '<a href="' . e(route('poll', ['id' => $p['id']])) . '">' . e($p['question']) . '</a>' ?></h3>
  <?php if ($p['description']): ?><p class="poll-desc"><?= e($p['description']) ?></p><?php endif; ?>
  <?php if ($isOpen && !$voted): ?>
    <form method="post" action="<?= e(route('poll.vote', ['id' => $p['id']])) ?>" class="poll-form" data-poll-form><?= csrf_field() ?><?= $full ? '<input type="hidden" name="full" value="1">' : '' ?>
      <fieldset><legend class="visually-hidden"><?= e($p['question']) ?></legend>
        <?php foreach ($p['options'] as $o): ?><label class="poll-opt"><input type="<?= (int) $p['multiple'] ? 'checkbox' : 'radio' ?>" name="options[]" value="<?= (int) $o['id'] ?>"<?= (int) $p['multiple'] ? '' : ' required' ?>> <span><?= e($o['label']) ?></span></label><?php endforeach; ?>
      </fieldset>
      <?php if ((int) $p['require_login'] && !\App\Services\ReaderAuth::check()): ?>
        <a class="btn" href="<?= e(route('account.login')) ?>">वोट के लिए लॉगिन करें</a>
      <?php else: ?><button class="btn" type="submit">वोट दें</button><?php endif; ?>
      <small class="poll-msg" data-poll-msg role="status"></small>
    </form>
  <?php endif; ?>
  <?php if ($results): ?>
    <ul class="poll-res">
      <?php foreach ($p['options'] as $o): $pc = round(100 * (int) $o['votes'] / $total); ?>
        <li class="<?= (int) $o['votes'] === $max && $max > 0 ? 'lead' : '' ?>"><div class="pr-top"><span><?= e($o['label']) ?></span><b><?= $pc ?>%</b></div><div class="pr-bar"><i style="width:<?= $pc ?>%"></i></div></li>
      <?php endforeach; ?>
    </ul>
    <p class="poll-meta"><?= num($p['voters']) ?> लोगों ने वोट दिया<?= $voted ? ' · आपका वोट दर्ज है' : '' ?><?= $p['end_at'] && $isOpen ? ' · ' . e(hindi_date((string) $p['end_at'], true)) . ' तक' : '' ?></p>
  <?php elseif ($voted): ?>
    <p class="poll-meta">धन्यवाद! आपका वोट दर्ज है। नतीजे <?= $p['show_results'] === 'after_close' ? 'पोल ख़त्म होने पर' : 'जल्द' ?> दिखेंगे।</p>
  <?php elseif (!$isOpen): ?><p class="poll-meta"><?= e((string) $why) ?></p><?php endif; ?>
</div>
