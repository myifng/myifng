<?php
/** मोबाइल: नीचे की पट्टी (होम, ताज़ा, मेरा शहर, खोज) */
if (!app('router')->has('latest')) return;
$city = my_city();
$path = app('request')->path();
?>
<nav class="bottom-nav" aria-label="मोबाइल नेविगेशन">
  <a href="<?= e(url()) ?>"<?= $path === '/' ? ' aria-current="page"' : '' ?>><i class="fa-solid fa-house"></i><span>होम</span></a>
  <a href="<?= e(route('latest')) ?>"<?= $path === '/latest' ? ' aria-current="page"' : '' ?>><i class="fa-regular fa-clock"></i><span>ताज़ा</span></a>
  <?php if ($city && $city['path']): ?><a href="<?= e(url($city['path'])) ?>"><i class="fa-solid fa-location-dot"></i><span><?= e(\App\Helpers\Str::limit((string) $city['name'], 10)) ?></span></a>
  <?php else: ?><button type="button" data-open-mycity><i class="fa-solid fa-location-dot"></i><span>मेरा शहर</span></button><?php endif; ?>
  <a href="<?= e(route('search')) ?>" data-open-search<?= $path === '/search' ? ' aria-current="page"' : '' ?>><i class="fa-solid fa-magnifying-glass"></i><span>खोज</span></a>
</nav>
