<?php
declare(strict_types=1);

namespace App\Services;

/**
 * सुरक्षित फ़ाइल अपलोड: MIME जाँच (finfo), एक्सटेंशन allowlist, रैंडम नाम, बड़ी इमेज छोटी।
 * फ़ाइलें public/uploads/{folder}/साल/महीना/ में जाती हैं। मूल फ़ाइल कभी बिना नीति के नहीं मिटती।
 * इंस्टॉलर में भी चलता है, इसलिए सिर्फ़ PHP पर निर्भर।
 */
final class UploadService
{
    public const TYPES = [
        'image' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'],
        'icon' => ['image/png' => 'png', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'],
        'pdf' => ['application/pdf' => 'pdf'],
        'document' => ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'],
    ];
    public const MAX_MB = ['image' => 10, 'icon' => 1, 'pdf' => 50, 'document' => 10];

    /**
     * @return array{ok: bool, path?: string, error?: string, mime?: string, size?: int, width?: int|null, height?: int|null}
     */
    public static function store(array $file, string $kind, string $folder, string $uploadsDir, int $maxWidth = 2000): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'कोई फ़ाइल नहीं चुनी गई।'];
        }
        if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return ['ok' => false, 'error' => 'फ़ाइल बहुत बड़ी है। सर्वर की सीमा ' . ini_get('upload_max_filesize') . ' है।'];
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => 'फ़ाइल अपलोड नहीं हो सकी। दोबारा कोशिश करें।'];
        }
        $types = self::TYPES[$kind] ?? [];
        $max = self::MAX_MB[$kind] ?? 5;
        if ((int) $file['size'] > $max * 1024 * 1024) {
            return ['ok' => false, 'error' => "फ़ाइल {$max} MB से छोटी होनी चाहिए।"];
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!isset($types[$mime])) {
            return ['ok' => false, 'error' => 'यह फ़ाइल प्रकार मान्य नहीं है। मान्य: ' . strtoupper(implode(', ', array_unique($types)))];
        }
        if ($mime === 'image/svg+xml' && preg_match('/<script|on\w+\s*=|javascript:/i', (string) file_get_contents((string) $file['tmp_name']))) {
            return ['ok' => false, 'error' => 'इस SVG में स्क्रिप्ट है, इसलिए अपलोड नहीं हो सकती।'];
        }
        $w = $h = null;
        if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml' && $mime !== 'image/x-icon' && $mime !== 'image/vnd.microsoft.icon') {
            $info = @getimagesize((string) $file['tmp_name']);
            if (!$info) {
                return ['ok' => false, 'error' => 'यह इमेज ख़राब है या खुल नहीं रही।'];
            }
            [$w, $h] = $info;
        }

        $folder = trim(preg_replace('/[^a-z0-9_\-]/i', '', $folder), '/') ?: 'misc';
        $sub = $folder . '/' . date('Y/m');
        $dir = rtrim($uploadsDir, '/') . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'uploads फ़ोल्डर में लिखने की अनुमति नहीं है।'];
        }
        $name = bin2hex(random_bytes(10)) . '.' . $types[$mime];
        $dest = $dir . '/' . $name;

        $saved = false;
        if ($w && in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) && extension_loaded('gd')) {
            $saved = self::resize((string) $file['tmp_name'], $dest, $mime, $maxWidth);
            if ($saved && ($i = @getimagesize($dest))) {
                [$w, $h] = $i;
            }
        }
        if (!$saved && !move_uploaded_file((string) $file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => 'फ़ाइल सेव नहीं हो सकी।'];
        }
        @chmod($dest, 0644);
        return ['ok' => true, 'path' => $sub . '/' . $name, 'mime' => $mime, 'size' => (int) filesize($dest), 'width' => $w, 'height' => $h];
    }

    /** बड़ी इमेज छोटी करके दोबारा सेव (इससे इमेज में छुपा कोड भी हट जाता है) */
    public static function resize(string $src, string $dest, string $mime, int $max): bool
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png' => @imagecreatefrompng($src),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default => false,
        };
        if (!$img) {
            return false;
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($src)['Orientation'] ?? 0);
            $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($rot) {
                $img = imagerotate($img, $rot, 0) ?: $img;
            }
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > $max) {
            $nh = (int) round($h * $max / $w);
            $dst = imagecreatetruecolor($max, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $max, $nh, $w, $h);
            unset($img);
            $img = $dst;
        }
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($img, $dest, 84),
            'image/png' => imagepng($img, $dest, 7),
            'image/webp' => imagewebp($img, $dest, 82),
        };
        unset($img);
        return $ok;
    }
}
