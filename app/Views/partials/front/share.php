<?php
/** शेयर बटन (सेटिंग → कंटेंट फ़ीचर): $shareUrl, $shareTitle */
$share = array_filter(explode(',', (string) setting('share_buttons', 'whatsapp,facebook,x,telegram,copy,native')));
if (!$share) return;
$u = rawurlencode($shareUrl);
$t = rawurlencode($shareTitle);
$links = [
    'whatsapp' => ['https://wa.me/?text=' . $t . '%20' . $u, 'fa-brands fa-whatsapp', 'WhatsApp', 's-wa'],
    'facebook' => ['https://www.facebook.com/sharer/sharer.php?u=' . $u, 'fa-brands fa-facebook-f', 'Facebook', 's-fb'],
    'x' => ['https://twitter.com/intent/tweet?url=' . $u . '&text=' . $t, 'fa-brands fa-x-twitter', 'X', 's-x'],
    'telegram' => ['https://t.me/share/url?url=' . $u . '&text=' . $t, 'fa-brands fa-telegram', 'Telegram', 's-tg'],
    'linkedin' => ['https://www.linkedin.com/sharing/share-offsite/?url=' . $u, 'fa-brands fa-linkedin-in', 'LinkedIn', 's-in'],
];
?>
<div class="share" aria-label="शेयर करें">
  <?php foreach ($share as $k): if (isset($links[$k])): [$href, $ic, $lab, $cls] = $links[$k]; ?><a class="<?= e($cls) ?>" href="<?= e($href) ?>" target="_blank" rel="noopener" aria-label="<?= e($lab) ?> पर शेयर करें"><i class="<?= e($ic) ?>"></i></a><?php endif; endforeach; ?>
  <?php if (in_array('copy', $share, true)): ?><button type="button" class="s-cp" data-copy-link="<?= e($shareUrl) ?>" aria-label="लिंक कॉपी करें"><i class="fa-solid fa-link"></i></button><?php endif; ?>
  <?php if (in_array('native', $share, true)): ?><button type="button" class="s-native" data-native-share data-title="<?= e($shareTitle) ?>" data-url="<?= e($shareUrl) ?>" aria-label="शेयर करें" hidden><i class="fa-solid fa-share-nodes"></i></button><?php endif; ?>
</div>
