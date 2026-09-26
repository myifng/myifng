<?php use App\Services\NewsWorkflow; ?>
<?php if (!$remarks): ?>
  <p class="small text-body-secondary mb-0">अभी कोई टिप्पणी नहीं।</p>
<?php else: ?>
  <ol class="remark-list">
    <?php foreach ($remarks as $r): ?>
      <li class="rm-<?= e($r['type']) ?>">
        <div class="rm-head">
          <b><?= e($r['user'] ?? 'सिस्टम') ?></b>
          <?php if ($r['type'] === 'status'): ?><span class="small"><?= $r['from_status'] ? e(NewsWorkflow::label($r['from_status'])) . ' → ' : '' ?><?= NewsWorkflow::badge((string) $r['to_status']) ?></span>
          <?php elseif ($r['type'] === 'note'): ?><span class="chip-sm"><i class="fa-solid fa-lock"></i> आंतरिक</span><?php endif; ?>
          <time class="small text-body-secondary ms-auto" datetime="<?= e($r['created_at']) ?>" title="<?= e(hindi_date($r['created_at'], true)) ?>"><?= time_ago($r['created_at']) ?></time>
        </div>
        <?php if ($r['message']): ?><p><?= nl2br(e($r['message'])) ?></p><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>
