<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\UploadService;

/** रिच एडिटर: लेख के बीच इमेज अपलोड (AJAX)। Phase 3 में यह मीडिया लाइब्रेरी से जुड़ेगा। */
final class EditorController extends Controller
{
    public function upload(Request $request): Response
    {
        $file = $request->file('image');
        if (!$file) {
            return $this->json(['ok' => false, 'message' => 'कोई इमेज नहीं चुनी गई।'], 422);
        }
        $r = UploadService::store($file, 'image', 'content', BASE_PATH . '/public/uploads', 1600);
        if (!$r['ok']) {
            return $this->json(['ok' => false, 'message' => $r['error']], 422);
        }
        AuditService::log('upload', 'editor', null, 'एडिटर में इमेज अपलोड: ' . $r['path']);
        return $this->json(['ok' => true, 'url' => upload_url($r['path']), 'width' => $r['width'], 'height' => $r['height']]);
    }
}
