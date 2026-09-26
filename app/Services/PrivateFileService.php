<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Response;

/**
 * निजी फ़ाइलें (KYC, पहचान पत्र): storage/private में, वेब से सीधे नहीं खुलतीं।
 * सिर्फ़ अनुमति जाँचने वाले रूट से send() के ज़रिए।
 */
final class PrivateFileService
{
    public const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

    public static function root(): string
    {
        return BASE_PATH . '/storage/private';
    }

    /**
     * @param string[] $allowed MIME (TYPES में से)
     * @return array{ok: bool, path?: string, error?: string, mime?: string}
     */
    public static function store(array $file, string $dir, array $allowed, int $maxMb): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'फ़ाइल नहीं चुनी गई।'];
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'फ़ाइल बहुत बड़ी है।' : 'फ़ाइल अपलोड नहीं हो सकी।'];
        }
        if ((int) $file['size'] > $maxMb * 1048576) {
            return ['ok' => false, 'error' => "फ़ाइल {$maxMb} MB से छोटी हो।"];
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mime, $allowed, true) || !isset(self::TYPES[$mime])) {
            return ['ok' => false, 'error' => 'सिर्फ़ ' . strtoupper(implode(', ', array_map(static fn($m) => self::TYPES[$m], $allowed))) . ' फ़ाइल।'];
        }
        if (str_starts_with($mime, 'image/') && !@getimagesize((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => 'इमेज ख़राब है या खुल नहीं रही।'];
        }
        $dir = trim(preg_replace('~[^a-z0-9/_-]~', '', $dir), '/');
        $abs = self::root() . '/' . $dir;
        if (!is_dir($abs) && !@mkdir($abs, 0750, true)) {
            return ['ok' => false, 'error' => 'फ़ाइल सेव करने की जगह नहीं मिली।'];
        }
        $name = bin2hex(random_bytes(12)) . '.' . self::TYPES[$mime];
        if (!move_uploaded_file((string) $file['tmp_name'], "$abs/$name")) {
            return ['ok' => false, 'error' => 'फ़ाइल सेव नहीं हो सकी।'];
        }
        @chmod("$abs/$name", 0640);
        return ['ok' => true, 'path' => "$dir/$name", 'mime' => $mime];
    }

    /** path सुरक्षित है (.. नहीं, private के अंदर) तो पूरा रास्ता */
    public static function absolute(?string $rel): ?string
    {
        if (!$rel || !preg_match('~^[a-z0-9/_-]+\.(jpg|png|pdf)$~', $rel) || str_contains($rel, '..')) {
            return null;
        }
        $abs = self::root() . '/' . $rel;
        return is_file($abs) ? $abs : null;
    }

    public static function send(?string $rel, string $downloadName, bool $inline = true): Response
    {
        $abs = self::absolute($rel);
        if (!$abs) {
            throw new \App\Core\HttpException(404);
        }
        $ext = pathinfo($abs, PATHINFO_EXTENSION);
        $mime = array_search($ext, self::TYPES, true) ?: 'application/octet-stream';
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) . '.' . $ext;
        return (new Response((string) file_get_contents($abs)))
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, no-store')
            ->header('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    }

    /** छोटा base64 data URI (प्रिंट पेज पर फ़ोटो/हस्ताक्षर; निजी फ़ाइल को सार्वजनिक किए बिना) */
    public static function dataUri(?string $rel): ?string
    {
        $abs = self::absolute($rel);
        if (!$abs || filesize($abs) > 3 * 1048576 || str_ends_with($abs, '.pdf')) {
            return null;
        }
        return 'data:' . (str_ends_with($abs, '.png') ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode((string) file_get_contents($abs));
    }
}
