<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Media;

/**
 * मीडिया लाइब्रेरी: अपलोड, जाँच, वेरिएंट (large/medium/thumb + WebP), वॉटरमार्क, बदलना (replace), स्थायी रूप से हटाना।
 *   - मूल फ़ाइल जैसी आई वैसी ही सेव होती है (कभी बदली नहीं जाती); वेबसाइट पर वेरिएंट दिखते हैं।
 *   - वेरिएंट के नाम तय हैं: abc.jpg → abc-large.jpg, abc-medium.jpg, abc-thumb.jpg (+ .webp)
 *   - फ़ाइलें public/uploads/media/साल/महीना/ में; uploads में PHP बंद है (.htaccess)।
 */
final class MediaService
{
    public const TYPES = [
        'image' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'],
        'video' => ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'],
        'audio' => ['audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav'],
        'document' => [
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt', 'text/csv' => 'csv',
        ],
    ];
    public const MAX_MB = ['image' => 15, 'video' => 200, 'audio' => 50, 'document' => 25];
    /** वेरिएंट: [चौड़ाई, ऊँचाई (0 = अनुपात के हिसाब से), crop?] ; large की चौड़ाई सेटिंग से */
    public const SIZES = ['large' => [1600, 0, false], 'medium' => [800, 0, false], 'thumb' => [400, 225, true]];
    /** इससे ज़्यादा पिक्सेल वाली इमेज के वेरिएंट नहीं बनते (shared hosting की मेमोरी) */
    private const MAX_PIXELS = 50_000_000;

    public static function dir(): string
    {
        return BASE_PATH . '/public/uploads';
    }

    /** सभी मान्य MIME → [kind, ext] */
    public static function allowed(): array
    {
        $out = [];
        foreach (self::TYPES as $kind => $types) {
            foreach ($types as $mime => $ext) {
                $out[$mime] = [$kind, $ext];
            }
        }
        return $out;
    }

    /** input के accept="" के लिए */
    public static function accept(?string $kind = null): string
    {
        $exts = [];
        foreach ($kind ? [$kind => self::TYPES[$kind]] : self::TYPES as $types) {
            foreach ($types as $ext) {
                $exts['.' . $ext] = true;
            }
        }
        return implode(',', array_keys($exts));
    }

    /**
     * फ़ाइल की जाँच: अपलोड त्रुटि, MIME (finfo, असली सामग्री से), एक्सटेंशन, आकार, इमेज खुलती है या नहीं
     * @return array{ok: bool, error?: string, mime?: string, kind?: string, ext?: string, width?: ?int, height?: ?int}
     */
    public static function inspect(array $file, ?string $onlyKind = null): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'कोई फ़ाइल नहीं चुनी गई।'];
        }
        if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return ['ok' => false, 'error' => 'फ़ाइल बहुत बड़ी है। सर्वर की सीमा ' . ini_get('upload_max_filesize') . ' है।'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'फ़ाइल अपलोड नहीं हो सकी। दोबारा कोशिश करें।'];
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $allowed = self::allowed();
        if (!isset($allowed[$mime]) || ($onlyKind && $allowed[$mime][0] !== $onlyKind)) {
            $list = $onlyKind ? self::TYPES[$onlyKind] : array_merge(...array_values(self::TYPES));
            return ['ok' => false, 'error' => '“' . self::cleanName((string) ($file['name'] ?? '')) . '”: यह फ़ाइल प्रकार मान्य नहीं है। मान्य: ' . strtoupper(implode(', ', array_unique($list)))];
        }
        [$kind, $ext] = $allowed[$mime];
        // नाम का एक्सटेंशन भी उसी परिवार का हो (photo.php.jpg जैसी चाल नहीं)
        $nameExt = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $aliases = ['jpg' => ['jpg', 'jpeg', 'jfif'], 'mov' => ['mov', 'qt'], 'txt' => ['txt', 'text'], 'm4a' => ['m4a', 'mp4']];
        if ($nameExt !== '' && !in_array($nameExt, $aliases[$ext] ?? [$ext], true)) {
            return ['ok' => false, 'error' => '“' . self::cleanName((string) $file['name']) . '”: फ़ाइल का नाम और उसकी असली सामग्री मेल नहीं खाते।'];
        }
        $max = self::MAX_MB[$kind];
        if ((int) $file['size'] > $max * 1024 * 1024) {
            return ['ok' => false, 'error' => self::KIND_LABEL[$kind] . " {$max} MB से छोटी होनी चाहिए।"];
        }
        $w = $h = null;
        if ($kind === 'image') {
            $info = @getimagesize($tmp);
            if (!$info || $info[0] < 1) {
                return ['ok' => false, 'error' => 'यह इमेज ख़राब है या खुल नहीं रही।'];
            }
            [$w, $h] = $info;
        }
        return ['ok' => true, 'mime' => $mime, 'kind' => $kind, 'ext' => $ext, 'width' => $w, 'height' => $h];
    }

    private const KIND_LABEL = ['image' => 'इमेज', 'video' => 'वीडियो', 'audio' => 'ऑडियो', 'document' => 'दस्तावेज़'];

    /**
     * नई फ़ाइल लाइब्रेरी में
     * @return array{ok: bool, error?: string, media?: array, warning?: string}
     */
    public static function upload(array $file, ?int $folderId = null, array $meta = [], ?string $onlyKind = null): array
    {
        $info = self::inspect($file, $onlyKind);
        if (!$info['ok']) {
            return $info;
        }
        $sub = 'media/' . date('Y/m');
        $dir = self::dir() . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'uploads फ़ोल्डर में लिखने की अनुमति नहीं है।'];
        }
        $rel = $sub . '/' . bin2hex(random_bytes(10)) . '.' . $info['ext'];
        if (!move_uploaded_file((string) $file['tmp_name'], self::dir() . '/' . $rel)) {
            return ['ok' => false, 'error' => 'फ़ाइल सेव नहीं हो सकी।'];
        }
        @chmod(self::dir() . '/' . $rel, 0644);
        [$variants, $warning] = $info['kind'] === 'image' ? self::makeVariants($rel, $info['mime']) : [[], null];

        $original = self::cleanName((string) ($file['name'] ?? ''));
        $title = trim((string) ($meta['title'] ?? '')) ?: mb_substr(preg_replace('/[_\-]+/', ' ', pathinfo($original, PATHINFO_FILENAME)), 0, 190);
        $id = Media::create([
            'folder_id' => $folderId, 'file' => $rel, 'original_name' => $original, 'mime' => $info['mime'], 'kind' => $info['kind'],
            'size' => (int) filesize(self::dir() . '/' . $rel), 'width' => $info['width'], 'height' => $info['height'],
            'variants' => $variants ? json_encode($variants) : null, 'title' => $title,
            'alt' => mb_substr((string) ($meta['alt'] ?? ''), 0, 255) ?: null, 'caption' => mb_substr((string) ($meta['caption'] ?? ''), 0, 500) ?: null,
            'credit' => mb_substr((string) ($meta['credit'] ?? ''), 0, 150) ?: null, 'uploaded_by' => auth()->id(),
        ]);
        return ['ok' => true, 'media' => Media::find($id), 'warning' => $warning];
    }

    /**
     * फ़ाइल बदलें: पुरानी मूल फ़ाइल storage/private/replaced/ में सुरक्षित, नाम वही (एक्सटेंशन बदले तो नया),
     * ताकि ख़बरों में लगे लिंक न टूटें।
     */
    public static function replace(array $media, array $file): array
    {
        $info = self::inspect($file, $media['kind']);
        if (!$info['ok']) {
            return $info;
        }
        $archive = BASE_PATH . '/storage/private/replaced';
        if (!is_dir($archive) && !@mkdir($archive, 0750, true)) {
            return ['ok' => false, 'error' => 'storage/private में लिखने की अनुमति नहीं है।'];
        }
        $old = self::dir() . '/' . $media['file'];
        if (is_file($old) && !@rename($old, $archive . '/' . $media['id'] . '-' . date('Ymd-His') . '-' . basename($media['file']))) {
            return ['ok' => false, 'error' => 'पुरानी फ़ाइल सुरक्षित नहीं हो सकी, इसलिए बदली नहीं गई।'];
        }
        self::deleteVariants($media);
        $rel = preg_replace('/\.[a-z0-9]+$/', '', $media['file']) . '.' . $info['ext'];
        if ($rel !== $media['file'] && Media::firstWhere('file', $rel)) {
            $rel = dirname($media['file']) . '/' . bin2hex(random_bytes(10)) . '.' . $info['ext'];
        }
        if (!move_uploaded_file((string) $file['tmp_name'], self::dir() . '/' . $rel)) {
            return ['ok' => false, 'error' => 'नई फ़ाइल सेव नहीं हो सकी।'];
        }
        @chmod(self::dir() . '/' . $rel, 0644);
        [$variants, $warning] = $info['kind'] === 'image' ? self::makeVariants($rel, $info['mime']) : [[], null];
        Media::update((int) $media['id'], [
            'file' => $rel, 'mime' => $info['mime'], 'size' => (int) filesize(self::dir() . '/' . $rel), 'width' => $info['width'], 'height' => $info['height'],
            'variants' => $variants ? json_encode($variants) : null, 'original_name' => self::cleanName((string) ($file['name'] ?? '')),
        ]);
        return ['ok' => true, 'media' => Media::find((int) $media['id'], true), 'warning' => $warning, 'renamed' => $rel !== $media['file']];
    }

    /** स्थायी रूप से: मूल + सभी वेरिएंट */
    public static function purge(array $media): void
    {
        self::deleteVariants($media);
        $f = self::dir() . '/' . $media['file'];
        if (is_file($f)) {
            @unlink($f);
        }
        Media::forceDelete((int) $media['id']);
    }

    /** सभी इमेज के वेरिएंट दोबारा (वॉटरमार्क/आकार की सेटिंग बदलने के बाद) */
    public static function regenerate(array $media): ?string
    {
        if ($media['kind'] !== 'image' || !is_file(self::dir() . '/' . $media['file'])) {
            return 'मूल फ़ाइल नहीं मिली।';
        }
        self::deleteVariants($media);
        [$variants, $warning] = self::makeVariants($media['file'], $media['mime']);
        Media::update((int) $media['id'], ['variants' => $variants ? json_encode($variants) : null]);
        return $warning;
    }

    private static function deleteVariants(array $media): void
    {
        foreach ((array) json_decode((string) $media['variants'], true) as $v) {
            foreach (['file', 'webp'] as $k) {
                if (!empty($v[$k]) && is_file(self::dir() . '/' . $v[$k])) {
                    @unlink(self::dir() . '/' . $v[$k]);
                }
            }
        }
    }

    /**
     * large/medium/thumb (+ WebP) बनाएँ। GIF (एनिमेशन) के वेरिएंट नहीं बनते।
     * @return array{0: array, 1: ?string} [variants, चेतावनी]
     */
    public static function makeVariants(string $rel, string $mime): array
    {
        if (!extension_loaded('gd')) {
            return [[], 'सर्वर पर GD नहीं है, इसलिए छोटे आकार (thumbnail) नहीं बने। मूल इमेज इस्तेमाल होगी।'];
        }
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return [[], null];
        }
        $src = self::dir() . '/' . $rel;
        $info = @getimagesize($src);
        if (!$info || $info[0] * $info[1] > self::MAX_PIXELS) {
            return [[], 'इमेज बहुत बड़ी (मेगापिक्सेल) है, इसलिए छोटे आकार नहीं बने।'];
        }
        @ini_set('memory_limit', '512M');
        $img = self::load($src, $mime);
        if (!$img) {
            return [[], 'इमेज प्रोसेस नहीं हो सकी; मूल इमेज इस्तेमाल होगी।'];
        }
        $quality = max(50, min(95, (int) setting('media_quality', 82)));
        $webp = setting('media_webp', '1') === '1' && function_exists('imagewebp');
        $sizes = self::SIZES;
        $sizes['large'][0] = max(800, min(4000, (int) setting('media_large_width', 1600)));
        $base = preg_replace('/\.[a-z0-9]+$/', '', $rel);
        $ext = pathinfo($rel, PATHINFO_EXTENSION);
        $wm = setting('watermark_enabled') === '1' ? self::watermark() : null;
        $out = [];
        foreach ($sizes as $name => [$w, $h, $crop]) {
            $v = self::resize($img, $w, $h, $crop);
            if ($wm && $name !== 'thumb' && imagesx($v) >= 300) {
                self::applyWatermark($v, $wm);
            }
            $file = "$base-$name.$ext";
            if (!self::save($v, self::dir() . '/' . $file, $mime, $quality)) {
                imagedestroy($v);
                continue;
            }
            $row = ['file' => $file, 'w' => imagesx($v), 'h' => imagesy($v)];
            if ($webp && $mime !== 'image/webp' && @imagewebp($v, self::dir() . "/$base-$name.webp", $quality)) {
                // WebP तभी रखें जब सच में हल्की हो (छोटे PNG लोगो में कभी-कभी बड़ी बनती है)
                if (filesize(self::dir() . "/$base-$name.webp") < filesize(self::dir() . '/' . $file)) {
                    $row['webp'] = "$base-$name.webp";
                } else {
                    @unlink(self::dir() . "/$base-$name.webp");
                }
            }
            imagedestroy($v);
            $out[$name] = $row;
        }
        imagedestroy($img);
        if ($wm) {
            imagedestroy($wm['img']);
        }
        return [$out, null];
    }

    private static function load(string $src, string $mime): \GdImage|false
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png' => @imagecreatefrompng($src),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default => false,
        };
        if ($img && $mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $rot = [3 => 180, 6 => -90, 8 => 90][(int) (@exif_read_data($src)['Orientation'] ?? 0)] ?? 0;
            if ($rot) {
                $img = imagerotate($img, $rot, 0) ?: $img;
            }
        }
        if ($img && !imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        return $img;
    }

    /** नया आकार; crop = बीच से काटकर ठीक w×h (thumbnail) */
    private static function resize(\GdImage $img, int $w, int $h, bool $crop): \GdImage
    {
        $sw = imagesx($img);
        $sh = imagesy($img);
        $sx = $sy = 0;
        if ($crop) {
            $ratio = $w / $h;
            if ($sw / $sh > $ratio) {
                $cw = (int) round($sh * $ratio);
                $sx = (int) (($sw - $cw) / 2);
                $sw = $cw;
            } else {
                $ch = (int) round($sw / $ratio);
                $sy = (int) (($sh - $ch) / 3); // चेहरे अक्सर ऊपर की ओर: बीच से थोड़ा ऊपर
                $sh = $ch;
            }
            $tw = min($w, $sw);
            $th = (int) round($tw / $ratio);
        } else {
            $tw = min($w, $sw);
            $th = (int) round($sh * $tw / $sw);
        }
        $dst = imagecreatetruecolor(max(1, $tw), max(1, $th));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, $sx, $sy, $tw, $th, $sw, $sh);
        imagealphablending($dst, true);
        return $dst;
    }

    private static function save(\GdImage $img, string $dest, string $mime, int $quality): bool
    {
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($img, $dest, $quality),
            'image/png' => imagepng($img, $dest, 7),
            'image/webp' => imagewebp($img, $dest, $quality),
            default => false,
        };
        if ($ok) {
            @chmod($dest, 0644);
        }
        return $ok;
    }

    /** सेटिंग से वॉटरमार्क की इमेज (लोगो PNG या सादा टेक्स्ट) */
    private static function watermark(): ?array
    {
        $opacity = max(10, min(100, (int) setting('watermark_opacity', 60)));
        $pos = (string) setting('watermark_position', 'bottom-right');
        if (setting('watermark_type', 'image') === 'image') {
            $f = (string) setting('watermark_image');
            $path = self::dir() . '/' . $f;
            if ($f === '' || !is_file($path) || !($i = @getimagesize($path))) {
                return null;
            }
            $img = self::load($path, $i['mime']);
            if (!$img) {
                return null;
            }
            imagesavealpha($img, true);
            return ['img' => $img, 'opacity' => $opacity, 'pos' => $pos, 'scale' => 0.2];
        }
        $text = trim((string) setting('watermark_text'));
        if ($text === '' || !preg_match('/^[\x20-\x7E]+$/', $text)) {
            return null;
        }
        // GD का अपना फ़ॉन्ट (सिर्फ़ अंग्रेज़ी अक्षर); छोटी इमेज पर लिखकर बाद में बड़ा किया जाता है
        $font = 5;
        $tw = imagefontwidth($font) * strlen($text) + 12;
        $th = imagefontheight($font) + 8;
        $img = imagecreatetruecolor($tw, $th);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 80));
        imagealphablending($img, true);
        imagestring($img, $font, 6, 4, $text, imagecolorallocate($img, 255, 255, 255));
        return ['img' => $img, 'opacity' => $opacity, 'pos' => $pos, 'scale' => min(0.45, 0.012 * strlen($text) + 0.1)];
    }

    private static function applyWatermark(\GdImage $dst, array $wm): void
    {
        $W = imagesx($dst);
        $H = imagesy($dst);
        $src = $wm['img'];
        $w = max(40, (int) round($W * $wm['scale']));
        $h = (int) round(imagesy($src) * $w / imagesx($src));
        if ($h > $H / 3) {
            $h = (int) ($H / 3);
            $w = (int) round(imagesx($src) * $h / imagesy($src));
        }
        $mark = imagecreatetruecolor($w, $h);
        imagealphablending($mark, false);
        imagesavealpha($mark, true);
        imagecopyresampled($mark, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        // पारदर्शिता: हर पिक्सेल का alpha घटाएँ (PNG की अपनी पारदर्शिता बनी रहती है)
        if ($wm['opacity'] < 100) {
            $f = $wm['opacity'] / 100;
            for ($x = 0; $x < $w; $x++) {
                for ($y = 0; $y < $h; $y++) {
                    $c = imagecolorat($mark, $x, $y);
                    $a = ($c >> 24) & 0x7F;
                    $na = (int) round(127 - (127 - $a) * $f);
                    imagesetpixel($mark, $x, $y, ($na << 24) | ($c & 0xFFFFFF));
                }
            }
        }
        $m = (int) round(min($W, $H) * 0.03);
        [$x, $y] = match ($wm['pos']) {
            'top-left' => [$m, $m],
            'top-right' => [$W - $w - $m, $m],
            'bottom-left' => [$m, $H - $h - $m],
            'center' => [(int) (($W - $w) / 2), (int) (($H - $h) / 2)],
            default => [$W - $w - $m, $H - $h - $m],
        };
        imagealphablending($dst, true);
        imagecopy($dst, $mark, $x, $y, 0, 0, $w, $h);
        imagedestroy($mark);
    }

    /** फ़ाइल नाम से ख़तरनाक अक्षर हटाएँ (सिर्फ़ दिखाने के लिए; डिस्क पर रैंडम नाम) */
    public static function cleanName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F<>:"\/\\\\|?*]+/u', '', $name);
        return mb_substr(trim($name), 0, 200) ?: 'file';
    }

    /** किसी आकार का URL (वेरिएंट न हो तो मूल) */
    public static function url(array $media, string $size = 'original', bool $webp = false): string
    {
        $v = (array) json_decode((string) ($media['variants'] ?? ''), true);
        if ($size !== 'original' && isset($v[$size])) {
            return upload_url($webp && !empty($v[$size]['webp']) ? $v[$size]['webp'] : $v[$size]['file']);
        }
        return upload_url($media['file']);
    }

    /** पिकर/इमेज खाने से आया path लाइब्रेरी की असली (ट्रैश में नहीं) इमेज है? */
    public static function validImagePath(?string $path): bool
    {
        if ($path === null || $path === '') {
            return true;
        }
        if (!preg_match('~^media/\d{4}/\d{2}/[a-f0-9]{20}\.(jpg|png|webp|gif)$~', $path)) {
            return false;
        }
        return (bool) db()->value("SELECT id FROM {p}media WHERE file = ? AND kind = 'image' AND deleted_at IS NULL", [$path]);
    }

    /** किसी भी प्रकार की लाइब्रेरी फ़ाइल का path (ऑडियो/वीडियो/दस्तावेज़) */
    public static function validPath(?string $path, string $kind): bool
    {
        if ($path === null || $path === '') {
            return true;
        }
        if (!preg_match('~^media/\d{4}/\d{2}/[a-f0-9]{20}\.[a-z0-9]{2,5}$~', $path)) {
            return false;
        }
        return (bool) db()->value('SELECT id FROM {p}media WHERE file = ? AND kind = ? AND deleted_at IS NULL', [$path, $kind]);
    }

    /** पिकर (JSON) के लिए एक आइटम */
    public static function toJson(array $m): array
    {
        return [
            'id' => (int) $m['id'], 'kind' => $m['kind'], 'file' => $m['file'], 'title' => (string) $m['title'], 'alt' => (string) $m['alt'],
            'caption' => (string) $m['caption'], 'credit' => (string) $m['credit'], 'name' => (string) $m['original_name'],
            'url' => upload_url($m['file']), 'thumb' => $m['kind'] === 'image' ? self::url($m, 'thumb') : null,
            'large' => $m['kind'] === 'image' ? self::url($m, 'large') : upload_url($m['file']),
            'width' => $m['width'] ? (int) $m['width'] : null, 'height' => $m['height'] ? (int) $m['height'] : null,
            'size' => self::humanSize((int) $m['size']), 'mime' => $m['mime'],
        ];
    }

    public static function humanSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1) . ' MB',
            $bytes >= 1024 => number_format($bytes / 1024) . ' KB',
            default => $bytes . ' B',
        };
    }
}
