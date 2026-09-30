<div class="box el-block">
  <?= block_head($title ?: $e['name'], \App\Services\ElectionService::url($e)) ?>
  <?= $this->insert('front/elections/_tally', ['e' => $e, 'tally' => $tally, 'progress' => $progress, 'compact' => true]) ?>
</div>
