<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\ElectionService;
use App\Services\SportsService;

/**
 * डेटा API (Phase 14): चुनाव नतीजे और खेल स्कोर बाहर से (डेटा एजेंसी / अपनी स्क्रिप्ट)।
 * पहचान: सेटिंग → डेटा API का टोकन, हेडर "Authorization: Bearer <टोकन>"। कुकी/सत्र नहीं, इसलिए CSRF नहीं।
 */
final class DataController extends Controller
{
    public function electionResults(Request $request, string $slug): Response
    {
        if ($err = $this->auth($request)) {
            return $err;
        }
        $data = $this->body($request);
        if ($data === null) {
            return $this->fail('JSON सही नहीं (अधिकतम 2 MB)', 422);
        }
        $e = db()->first('SELECT * FROM {p}elections WHERE slug = ?', [$slug]);
        if (!$e) {
            return $this->fail('चुनाव नहीं मिला', 404);
        }
        $r = ElectionService::applyResults($e, $data);
        AuditService::log('api', 'elections', (int) $e['id'], 'API से नतीजे: ' . $r['updated'] . ' सीटें' . ($r['errors'] ? ', ' . count($r['errors']) . ' दिक्कतें' : ''));
        return $this->json(['ok' => true, 'updated' => $r['updated'], 'errors' => $r['errors']]);
    }

    public function matchUpdate(Request $request, int $id): Response
    {
        if ($err = $this->auth($request)) {
            return $err;
        }
        $data = $this->body($request);
        if ($data === null) {
            return $this->fail('JSON सही नहीं (अधिकतम 2 MB)', 422);
        }
        $m = db()->first('SELECT * FROM {p}sports_matches WHERE id = ?', [$id]);
        if (!$m) {
            return $this->fail('मैच नहीं मिला', 404);
        }
        $r = SportsService::applyUpdate($m, $data, null);
        if (isset($r['error'])) {
            return $this->fail($r['error'], 422);
        }
        AuditService::log('api', 'sports', $id, 'API से मैच अपडेट' . ($r['commentary'] ? ' + ' . $r['commentary'] . ' कमेंट्री' : ''));
        return $this->json(['ok' => true] + $r);
    }

    /** ठीक हो तो null, वरना JSON जवाब */
    private function auth(Request $request): ?Response
    {
        $token = (string) setting('data_api_token', '');
        if (setting('data_api_enabled', '0') !== '1' || strlen($token) < 32) {
            return $this->fail('API बंद है', 404);
        }
        // कुछ Apache सर्वर Authorization हटा देते हैं: REDIRECT_ वाला या X-Api-Token भी मान्य
        $h = (string) ($request->header('Authorization') ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        $given = preg_match('/^Bearer\s+(\S+)$/i', $h, $m) ? $m[1] : (string) ($request->header('X-Api-Token') ?? '');
        if ($given === '' || !hash_equals($token, $given)) {
            logger()->warning('Data API: ग़लत टोकन, IP ' . $request->ip());
            return $this->fail('टोकन सही नहीं', 401);
        }
        return null;
    }

    private function body(Request $request): ?array
    {
        $raw = (string) file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024 + 1);
        if (strlen($raw) > 2 * 1024 * 1024) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function fail(string $msg, int $code): Response
    {
        return $this->json(['ok' => false, 'message' => $msg], $code);
    }
}
