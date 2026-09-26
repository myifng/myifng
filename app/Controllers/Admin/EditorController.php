<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\MediaService;

/** रिच एडिटर: लेख के बीच इमेज अपलोड (AJAX)। इमेज सीधे मीडिया लाइब्रेरी में जाती है (वेरिएंट, WebP, वॉटरमार्क के साथ)। */
final class EditorController extends Controller
{
    public function upload(Request $request): Response
    {
        $file = $request->file('image');
        if (!$file) {
            return $this->json(['ok' => false, 'message' => 'कोई इमेज नहीं चुनी गई।'], 422);
        }
        $r = MediaService::upload($file, null, [], 'image');
        if (!$r['ok']) {
            return $this->json(['ok' => false, 'message' => $r['error']], 422);
        }
        $m = $r['media'];
        AuditService::log('upload', 'media', $m['id'], 'एडिटर से इमेज अपलोड: ' . $m['original_name']);
        $item = MediaService::toJson($m);
        return $this->json(['ok' => true, 'url' => $item['large'], 'width' => $item['width'], 'height' => $item['height'], 'item' => $item]);
    }
}
