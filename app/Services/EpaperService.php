<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\EpaperIssue;
use App\Models\EpaperPage;

/**
 * ई-पेपर: प्रकाशित अंक, फ़ाइलें (public/uploads/epaper/…), पेज इमेज सेव + थंबनेल, PDF, पहुँच (मुफ़्त/प्रीमियम)।
 * PDF → पेज इमेज एडमिन के ब्राउज़र में (pdf.js) बनती हैं; सर्वर पर सिर्फ़ इमेज की जाँच और GD से दोबारा सेव।
 */
final class EpaperService
{
    public const PUBLISHED = "i.status = 'published' AND i.publish_at IS NOT NULL AND i.publish_at <= NOW()";
    public const PAGE_MAX_WIDTH = 2400;
    public const THUMB_WIDTH = 360;
    public const MAX_PAGES = 200;
    private const IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function enabled(): bool
    {
        return setting('epaper_enabled', '1') === '1';
    }

    public static function root(): string
    {
        return BASE_PATH . '/public/uploads/';
    }

    /** नया अंक: फ़ाइलों का फ़ोल्डर (रैंडम, ताकि प्रीमियम पेज का पता अंदाज़ा न लगे) */
    public static function newDir(string $date): string
    {
        return 'epaper/' . date('Y/m', strtotime($date)) . '/' . bin2hex(random_bytes(8));
    }

    public static function editions(): array
    {
        return cache()->remember('home.epaper_editions', 600, static fn() => db()->all(
            "SELECT e.*, l.name AS location FROM {p}epaper_editions e LEFT JOIN {p}locations l ON l.id = e.location_id
             WHERE e.status = 'active' ORDER BY e.is_default DESC, e.sort_order, e.name"
        ));
    }

    public static function edition(string $slug): ?array
    {
        foreach (self::editions() as $e) {
            if ($e['slug'] === $slug) {
                return $e;
            }
        }
        return null;
    }

    /** संस्करण का ताज़ा प्रकाशित अंक */
    public static function latest(int $editionId): ?array
    {
        return db()->first('SELECT i.* FROM {p}epaper_issues i WHERE i.edition_id = ? AND ' . self::PUBLISHED . ' AND i.page_count > 0 ORDER BY i.issue_date DESC LIMIT 1', [$editionId]);
    }

    public static function issue(int $editionId, string $date): ?array
    {
        return db()->first('SELECT i.* FROM {p}epaper_issues i WHERE i.edition_id = ? AND i.issue_date = ? AND ' . self::PUBLISHED . ' AND i.page_count > 0', [$editionId, $date]);
    }

    /** पिछला / अगला प्रकाशित अंक (तारीख़ के हिसाब से) */
    public static function sibling(array $issue, int $dir): ?string
    {
        return db()->value('SELECT i.issue_date FROM {p}epaper_issues i WHERE i.edition_id = ? AND i.issue_date ' . ($dir < 0 ? '<' : '>') . ' ? AND ' . self::PUBLISHED
            . ' AND i.page_count > 0 ORDER BY i.issue_date ' . ($dir < 0 ? 'DESC' : 'ASC') . ' LIMIT 1', [$issue['edition_id'], $issue['issue_date']]) ?: null;
    }

    public static function pages(int $issueId): array
    {
        return db()->all('SELECT * FROM {p}epaper_pages WHERE issue_id = ? ORDER BY page_no', [$issueId]);
    }

    /** प्रीमियम: पहले N पेज सबके लिए; बाकी स्टाफ़ (आगे: सदस्यता वाले पाठक, Phase 11) */
    public static function freePages(array $issue): int
    {
        if ($issue['access'] === 'free' || (auth()->check() && can('epaper.view'))) {
            return PHP_INT_MAX;
        }
        return max(0, (int) setting('epaper_free_pages', '2'));
    }

    public static function canDownload(array $issue): bool
    {
        return $issue['pdf'] && $issue['access'] === 'free' && setting('epaper_pdf_download', '1') === '1';
    }

    public static function url(array $edition, array $issue, int $page = 1): string
    {
        return route('epaper.issue', ['edition' => $edition['slug'], 'date' => $issue['issue_date']]) . ($page > 1 ? '?page=' . $page : '');
    }

    // ---------- फ़ाइलें ----------

    /**
     * पेज की इमेज: MIME + getimagesize, GD से दोबारा सेव (अंदर छिपा कोड हटता है), थंबनेल
     * @return array{ok: bool, error?: string, image?: string, thumb?: string, width?: int, height?: int}
     */
    public static function storePage(array $issue, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'फ़ाइल सर्वर की सीमा से बड़ी है।' : 'फ़ाइल अपलोड नहीं हुई।'];
        }
        if ($file['size'] > 25 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'एक पेज 25 MB से छोटा हो।'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $info = @getimagesize($file['tmp_name']);
        if (!isset(self::IMAGE_MIMES[$mime]) || !$info || $info['mime'] !== $mime) {
            return ['ok' => false, 'error' => 'सिर्फ़ JPG, PNG या WebP इमेज।'];
        }
        if ($info[0] * $info[1] > 60_000_000) {
            return ['ok' => false, 'error' => 'इमेज बहुत बड़ी है (60 मेगापिक्सल तक)।'];
        }
        if (!extension_loaded('gd')) {
            return ['ok' => false, 'error' => 'सर्वर पर GD एक्सटेंशन नहीं है।'];
        }
        $dir = self::root() . $issue['dir'];
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'अपलोड फ़ोल्डर नहीं बन सका।'];
        }
        $name = bin2hex(random_bytes(8));
        $ext = $mime === 'image/png' ? 'png' : 'jpg';
        $outMime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        if ($mime === 'image/webp') {
            // WebP → JPG (हर जगह चले)
            $tmp = @imagecreatefromwebp($file['tmp_name']);
            if (!$tmp) {
                return ['ok' => false, 'error' => 'WebP इमेज पढ़ी नहीं जा सकी।'];
            }
            $jpgTmp = tempnam(sys_get_temp_dir(), 'ep');
            imagejpeg($tmp, $jpgTmp, 90);
            unset($tmp);
            $src = $jpgTmp;
            $srcMime = 'image/jpeg';
        } else {
            $src = $file['tmp_name'];
            $srcMime = $mime;
        }
        $image = $issue['dir'] . '/' . $name . '.' . $ext;
        $thumb = $issue['dir'] . '/' . $name . '-t.' . $ext;
        $ok = UploadService::resize($src, self::root() . $image, $srcMime === 'image/png' ? 'image/png' : 'image/jpeg', self::PAGE_MAX_WIDTH)
            && UploadService::resize(self::root() . $image, self::root() . $thumb, $outMime, self::THUMB_WIDTH);
        if (isset($jpgTmp)) {
            @unlink($jpgTmp);
        }
        if (!$ok) {
            @unlink(self::root() . $image);
            return ['ok' => false, 'error' => 'इमेज सेव नहीं हो सकी।'];
        }
        [$w, $h] = getimagesize(self::root() . $image) ?: [0, 0];
        return ['ok' => true, 'image' => $image, 'thumb' => $thumb, 'width' => $w, 'height' => $h];
    }

    /** @return array{ok: bool, error?: string, path?: string, size?: int} */
    public static function storePdf(array $issue, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'PDF सर्वर की अपलोड सीमा (' . self::serverLimitMb() . ' MB) से बड़ी है।' : 'PDF अपलोड नहीं हुई।'];
        }
        $max = self::maxPdfMb();
        if ($file['size'] > $max * 1024 * 1024) {
            return ['ok' => false, 'error' => "PDF $max MB से छोटी हो।"];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5);
        if ($mime !== 'application/pdf' || $head !== '%PDF-') {
            return ['ok' => false, 'error' => 'यह PDF फ़ाइल नहीं है।'];
        }
        $dir = self::root() . $issue['dir'];
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'अपलोड फ़ोल्डर नहीं बन सका।'];
        }
        $path = $issue['dir'] . '/' . bin2hex(random_bytes(8)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], self::root() . $path)) {
            return ['ok' => false, 'error' => 'PDF सेव नहीं हो सकी।'];
        }
        @chmod(self::root() . $path, 0644);
        if ($issue['pdf']) {
            self::unlink($issue['pdf']);
        }
        return ['ok' => true, 'path' => $path, 'size' => (int) $file['size']];
    }

    public static function maxPdfMb(): int
    {
        return max(1, min((int) setting('epaper_max_pdf_mb', '50'), self::serverLimitMb()));
    }

    /** php.ini की upload_max_filesize / post_max_size में से छोटी */
    public static function serverLimitMb(): int
    {
        $toMb = static function (string $v): int {
            $n = (float) $v;
            return (int) floor(match (strtolower(substr(trim($v), -1))) { 'g' => $n * 1024, 'm' => $n, 'k' => $n / 1024, default => $n / 1048576 });
        };
        return max(1, min($toMb((string) ini_get('upload_max_filesize')), $toMb((string) ini_get('post_max_size'))));
    }

    /** पेज नंबर 1..N फिर से, कवर और गिनती */
    public static function renumber(int $issueId): void
    {
        $pages = db()->all('SELECT id, thumb FROM {p}epaper_pages WHERE issue_id = ? ORDER BY page_no, id', [$issueId]);
        foreach ($pages as $i => $p) {
            db()->query('UPDATE {p}epaper_pages SET page_no = ? WHERE id = ?', [$i + 1, $p['id']]);
        }
        EpaperIssue::update($issueId, ['page_count' => count($pages), 'cover' => $pages[0]['thumb'] ?? null]);
    }

    public static function deletePage(array $page): void
    {
        EpaperPage::delete((int) $page['id']);
        self::unlink($page['image']);
        self::unlink($page['thumb']);
    }

    /** अंक की सारी फ़ाइलें (फ़ोल्डर) हटाएँ */
    public static function purge(array $issue): void
    {
        $dir = self::root() . $issue['dir'];
        if ($issue['dir'] && str_starts_with($issue['dir'], 'epaper/') && is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    private static function unlink(?string $rel): void
    {
        if ($rel && str_starts_with($rel, 'epaper/') && !str_contains($rel, '..')) {
            @unlink(self::root() . $rel);
        }
    }

    public static function changed(): void
    {
        cache()->flush('home');
    }
}
