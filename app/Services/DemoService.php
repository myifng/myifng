<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * डेमो डेटा: इंस्टॉलर (DemoSeeder) नमूना सामग्री जोड़ता है और हर पंक्ति/फ़ाइल demo_items में दर्ज करता है;
 * लाइव होने से पहले एडमिन → सिस्टम से एक क्लिक में सब हटाया जा सकता है।
 * यह क्लास पूरे ऐप के बिना भी चलती है (इंस्टॉलर में सिर्फ़ Database होता है)।
 */
final class DemoService
{
    /** हटाने का क्रम: पहले बच्चे, फिर माता-पिता (users सबसे आख़िर में) */
    private const ORDER = ['live_updates', 'live_blogs', 'breaking_news', 'ad_placements', 'ads', 'poll_options', 'polls', 'fact_checks', 'jobs',
        'web_story_slides', 'web_stories', 'gallery_photos', 'galleries', 'videos', 'live_tv_channels', 'epaper_pages', 'epaper_issues',
        'news_tags', 'news', 'tags', 'media', 'reporter_documents', 'reporters', 'users'];

    public static function track(Database $db, string $table, ?int $id = null, ?string $file = null): void
    {
        $db->insert('demo_items', ['tbl' => $table, 'row_id' => $id, 'file' => $file]);
    }

    public static function count(Database $db): int
    {
        try {
            return (int) $db->value("SELECT COUNT(*) FROM {p}demo_items WHERE tbl <> 'file'");
        } catch (\Throwable) {
            return 0;
        }
    }

    /** सारा डेमो डेटा और उसकी फ़ाइलें हटाएँ; लौटाए [टेबल => संख्या] */
    public static function remove(Database $db): array
    {
        $rows = $db->all('SELECT tbl, row_id, file FROM {p}demo_items ORDER BY id DESC');
        $by = [];
        $files = [];
        foreach ($rows as $r) {
            if ($r['file']) {
                $files[] = (string) $r['file'];
            }
            if ($r['row_id'] !== null && preg_match('/^[a-z_]+$/', (string) $r['tbl'])) {
                $by[$r['tbl']][] = (int) $r['row_id'];
            }
        }
        $done = [];
        $tables = array_values(array_unique(array_merge(self::ORDER, array_keys($by))));
        foreach ($tables as $t) {
            if (empty($by[$t])) {
                continue;
            }
            $ids = array_values(array_unique($by[$t]));
            $n = 0;
            foreach (array_chunk($ids, 200) as $chunk) {
                $in = implode(',', array_fill(0, count($chunk), '?'));
                if ($t === 'news_tags') {
                    continue; // news हटने पर अपने-आप (CASCADE)
                }
                if ($t === 'users') {
                    // जिन डेमो यूज़र ने बाद में असली काम किया हो (जैसे ख़बर लिखी), उन्हें छोड़ें नहीं: सिर्फ़ निष्क्रिय+हटाया
                    $n += $db->query("DELETE FROM {p}users WHERE id IN ($in) AND id NOT IN (SELECT DISTINCT reporter_id FROM {p}news WHERE reporter_id IS NOT NULL)", $chunk)->rowCount();
                    $db->query("UPDATE {p}users SET status = 'suspended', deleted_at = NOW() WHERE id IN ($in)", $chunk);
                    continue;
                }
                $n += $db->query("DELETE FROM {p}$t WHERE id IN ($in)", $chunk)->rowCount();
            }
            $done[$t] = $n;
        }
        $base = dirname(__DIR__, 2) . '/public/uploads/';
        $fileCount = 0;
        foreach (array_unique($files) as $f) {
            if (preg_match('~^(media|epaper)/[a-z0-9/._-]+$~i', $f) && !str_contains($f, '..') && is_file($base . $f)) {
                $fileCount += @unlink($base . $f) ? 1 : 0;
            }
        }
        $db->query('DELETE FROM {p}demo_items');
        $done['files'] = $fileCount;
        return $done;
    }

    // ---------- नमूना तस्वीरें (GD; कोई बाहरी फ़ाइल नहीं) ----------

    /** रंगीन ग्रेडिएंट + आकृतियों वाली JPEG; $kind: news|avatar|banner|page */
    public static function image(int $w, int $h, int $seed, string $kind = 'news'): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        mt_srand($seed);
        $im = imagecreatetruecolor($w, $h);
        $hue = $seed * 47 % 360;
        [$r1, $g1, $b1] = self::hsl($hue, 0.55, 0.42);
        [$r2, $g2, $b2] = self::hsl(($hue + 40) % 360, 0.6, 0.62);
        for ($y = 0; $y < $h; $y++) { // ऊपर से नीचे ग्रेडिएंट
            $t = $y / max(1, $h - 1);
            $c = imagecolorallocate($im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imageline($im, 0, $y, $w, $y, $c);
        }
        imagealphablending($im, true);
        if ($kind === 'avatar') {
            $skin = imagecolorallocate($im, 236, 196, 160);
            $shirt = imagecolorallocatealpha($im, 255, 255, 255, 30);
            imagefilledellipse($im, (int) ($w / 2), (int) ($h * 0.38), (int) ($w * 0.42), (int) ($w * 0.5), $skin);
            imagefilledellipse($im, (int) ($w / 2), (int) ($h * 1.02), (int) ($w * 0.95), (int) ($h * 0.75), $shirt);
        } elseif ($kind === 'page') {
            $white = imagecolorallocate($im, 250, 250, 248);
            imagefilledrectangle($im, 0, 0, $w, $h, $white);
            $ink = imagecolorallocate($im, 40, 40, 46);
            $head = imagecolorallocate($im, $r1, $g1, $b1);
            $grey = imagecolorallocate($im, 205, 205, 210);
            imagefilledrectangle($im, (int) ($w * .05), (int) ($h * .03), (int) ($w * .95), (int) ($h * .09), $head); // मास्टहेड
            $y = (int) ($h * .12);
            for ($col = 0; $col < 3; $col++) {
                $x0 = (int) ($w * (.05 + $col * .31));
                $x1 = $x0 + (int) ($w * .28);
                $yy = $y;
                for ($b = 0; $b < 4; $b++) {
                    imagefilledrectangle($im, $x0, $yy, $x1, $yy + (int) ($h * .018), $ink); // शीर्षक
                    $yy += (int) ($h * .03);
                    if ($b % 2 === 0) {
                        [$pr, $pg, $pb] = self::hsl(($hue + $b * 60) % 360, .45, .6);
                        imagefilledrectangle($im, $x0, $yy, $x1, $yy + (int) ($h * .09), imagecolorallocate($im, $pr, $pg, $pb)); // फ़ोटो
                        $yy += (int) ($h * .1);
                    }
                    for ($l = 0; $l < 6; $l++) {
                        imagefilledrectangle($im, $x0, $yy, $x1 - ($l === 5 ? (int) ($w * .1) : 0), $yy + (int) ($h * .006), $grey);
                        $yy += (int) ($h * .014);
                    }
                    $yy += (int) ($h * .02);
                }
            }
        } else {
            for ($i = 0; $i < 7; $i++) { // नरम गोले
                $c = imagecolorallocatealpha($im, 255, 255, 255, mt_rand(88, 116));
                $d = mt_rand((int) ($h * .3), (int) ($h * 1.1));
                imagefilledellipse($im, mt_rand(0, $w), mt_rand(0, $h), $d, $d, $c);
            }
            $dark = imagecolorallocatealpha($im, 0, 0, 0, 100);
            for ($i = -$h; $i < $w; $i += 26) { // तिरछी धारियाँ
                imageline($im, $i, $h, $i + $h, 0, $dark);
            }
            if ($kind === 'news') { // नीचे हल्की परछाईं (टेक्स्ट ओवरले के लिए)
                for ($y = (int) ($h * .6); $y < $h; $y++) {
                    $a = (int) (127 - 60 * (($y - $h * .6) / ($h * .4)));
                    imageline($im, 0, $y, $w, $y, imagecolorallocatealpha($im, 0, 0, 0, max(0, min(127, $a))));
                }
            }
        }
        ob_start();
        imagejpeg($im, null, 78);
        return (string) ob_get_clean();
    }

    private static function hsl(int $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (intdiv($h, 60)) { 0 => [$c, $x, 0], 1 => [$x, $c, 0], 2 => [0, $c, $x], 3 => [0, $x, $c], 4 => [$x, 0, $c], default => [$c, 0, $x] };
        return [(int) (($r + $m) * 255), (int) (($g + $m) * 255), (int) (($b + $m) * 255)];
    }

    /**
     * तस्वीर uploads/media में सेव + मीडिया लाइब्रेरी की तरह large/medium/thumb (+WebP) बनाकर media पंक्ति;
     * लौटाए uploads वाला path (news.featured_image जैसा)
     */
    public static function media(Database $db, string $jpeg, string $title, ?int $userId = null): ?string
    {
        $base = dirname(__DIR__, 2) . '/public/uploads/';
        $sub = 'media/' . date('Y/m');
        if (!is_dir($base . $sub) && !@mkdir($base . $sub, 0755, true)) {
            return null;
        }
        $name = 'demo-' . bin2hex(random_bytes(8));
        $rel = "$sub/$name.jpg";
        if (@file_put_contents($base . $rel, $jpeg) === false) {
            return null;
        }
        self::track($db, 'file', null, $rel);
        $img = @imagecreatefromstring($jpeg);
        $variants = [];
        if ($img) {
            $w = imagesx($img);
            $h = imagesy($img);
            foreach (['large' => [1600, 0, false], 'medium' => [800, 0, false], 'thumb' => [400, 225, true]] as $size => [$tw, $th, $crop]) {
                [$nw, $nh] = $crop ? [$tw, $th] : [min($w, $tw), (int) round($h * min($w, $tw) / $w)];
                if (!$crop && $nw === $w) { // छोटी तस्वीर: मूल फ़ाइल ही यह साइज़ (दोहरी फ़ाइल नहीं)
                    $variants[$size] = ['file' => $rel, 'w' => $w, 'h' => $h];
                    continue;
                }
                $v = imagecreatetruecolor($nw, $nh);
                if ($crop) {
                    $k = max($nw / $w, $nh / $h);
                    $sw = (int) round($nw / $k);
                    $sh = (int) round($nh / $k);
                    imagecopyresampled($v, $img, 0, 0, (int) (($w - $sw) / 2), (int) (($h - $sh) / 2), $nw, $nh, $sw, $sh);
                } else {
                    imagecopyresampled($v, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                }
                $file = "$sub/$name-$size.jpg";
                imagejpeg($v, $base . $file, 78);
                self::track($db, 'file', null, $file);
                $row = ['file' => $file, 'w' => $nw, 'h' => $nh];
                if (function_exists('imagewebp') && @imagewebp($v, $base . "$sub/$name-$size.webp", 80)) {
                    $row['webp'] = "$sub/$name-$size.webp";
                    self::track($db, 'file', null, $row['webp']);
                }
                $variants[$size] = $row;
                unset($v);
            }
            $id = $db->insert('media', ['file' => $rel, 'original_name' => $name . '.jpg', 'mime' => 'image/jpeg', 'kind' => 'image', 'size' => strlen($jpeg),
                'width' => $w, 'height' => $h, 'variants' => json_encode($variants), 'title' => mb_substr($title, 0, 190), 'alt' => mb_substr($title, 0, 255),
                'credit' => 'डेमो', 'uploaded_by' => $userId]);
            self::track($db, 'media', $id);
        }
        return $rel;
    }
}
