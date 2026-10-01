<?php
/** मोबाइल: नीचे की पट्टी (होम, ई-पेपर, लाइव टीवी, अकाउंट); मॉड्यूल/रूट न हो तो वह आइटम नहीं दिखता */
$path = '/' . ltrim(app('request')->path(), '/');
$router = app('router');
$items = [['होम', 'fa-solid fa-house', url(), $path === '/']];
if ($router->has('epaper')) {
    $items[] = ['ई-पेपर', 'fa-solid fa-book-open', route('epaper'), str_starts_with($path, '/epaper')];
}
if ($router->has('live_tv')) {
    $items[] = ['लाइव टीवी', 'fa-solid fa-tv', route('live_tv'), str_starts_with($path, '/live-tv')];
}
$acct = null;
if ($router->has('account') && \App\Services\ReaderAuth::enabled()) {
    $rd = \App\Services\ReaderAuth::user();
    $unread = $rd ? \App\Services\NotificationService::unread('reader', (int) $rd['id']) : 0;
    $acct = [$rd ? 'मेरा खाता' : 'अकाउंट', 'fa-regular fa-user', route($rd ? 'account' : 'account.login'), str_starts_with($path, '/account'), $rd, $unread];
}
?>
<nav class="bottom-nav" aria-label="मोबाइल नेविगेशन" style="--bn-cols:<?= count($items) + ($acct ? 1 : 0) ?>">
  <?php foreach ($items as [$label, $icon, $href, $on]): ?>
    <a href="<?= e($href) ?>"<?= $on ? ' aria-current="page"' : '' ?>><i class="<?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span></a>
  <?php endforeach; ?>
  <?php if ($acct): [$label, $icon, $href, $on, $rd, $unread] = $acct; ?>
    <a href="<?= e($href) ?>"<?= $on ? ' aria-current="page"' : '' ?> aria-label="<?= e($label . ($unread ? " ($unread नई सूचना)" : '')) ?>">
      <span class="bn-ico"><?= $rd ? '<span class="acct-dot">' . e(mb_substr((string) $rd['name'], 0, 1)) . '</span>' : '<i class="' . e($icon) . '" aria-hidden="true"></i>' ?><?= $unread ? '<b class="bn-badge">' . min(99, $unread) . '</b>' : '' ?></span><span><?= e($label) ?></span>
    </a>
  <?php endif; ?>
</nav>
