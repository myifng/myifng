<?php
declare(strict_types=1);

namespace App\Services;

/**
 * "ख़बर सुनें": ख़बर का ऑडियो।
 *  1. ख़बर के साथ अपलोड की गई ऑडियो फ़ाइल हो तो वही
 *  2. सेटिंग में Google Cloud TTS (Neural/WaveNet) हो तो MP3 एक बार बनकर uploads/tts में सेव (ख़बर बदले तो नया)
 *  3. वरना पाठक के ब्राउज़र की आवाज़ (Web Speech API, मुफ़्त)
 */
final class ListenService
{
    private const API = 'https://texttospeech.googleapis.com/v1/text:synthesize';
    private const CHUNK_BYTES = 4500; // Google की सीमा 5000 bytes प्रति अनुरोध
    private static array $queue = [];

    public static function on(array $news): bool
    {
        return setting('listen_enabled', '1') === '1' && (int) ($news['allow_listen'] ?? 1) === 1;
    }

    public static function google(): bool
    {
        return setting('listen_engine', 'browser') === 'google' && trim((string) setting('google_tts_key', '')) !== '';
    }

    public static function rate(): float
    {
        $r = (float) setting('listen_rate', '1');
        return $r >= 0.5 && $r <= 2 ? $r : 1.0;
    }

    /** पेज के लिए: ['mode' => file|tts|speech, 'src' => url|null] */
    public static function source(array $news): array
    {
        if (!empty($news['audio_file'])) {
            return ['mode' => 'file', 'src' => upload_url($news['audio_file'])];
        }
        if (self::google()) {
            // बना हुआ और ताज़ा हो तो सीधे फ़ाइल; वरना रूट (पहली बार सुनने पर बनेगा)
            $fresh = self::fresh($news);
            return ['mode' => 'tts', 'src' => $fresh ? upload_url($news['tts_file']) : route('news.listen', ['slug' => $news['slug']])];
        }
        return ['mode' => 'speech', 'src' => null];
    }

    /** सुनाने लायक सादा टेक्स्ट: शीर्षक, सार, ख़बर (टेबल/कोड/एम्बेड/कैप्शन नहीं) */
    public static function text(array $news): string
    {
        $html = (string) ($news['content'] ?? '');
        $html = preg_replace('~<(script|style|table|figure|figcaption|iframe|video|audio|pre|code|object|embed|form)\b[^>]*>.*?</\1>~is', ' ', $html) ?? '';
        $html = preg_replace('~<(br|/p|/h[1-6]|/li|/blockquote|/div)\b[^>]*>~i', "$0\n", $html) ?? '';
        $body = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = [trim((string) $news['title']), trim((string) ($news['summary'] ?? '')), $body];
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $p));
            $p = (string) preg_replace('/\s*\n\s*/u', "\n", $p);
            if ($p !== '') {
                $out[] = preg_match('/[।.!?]$/u', $p) ? $p : $p . '।';
            }
        }
        return mb_substr(implode("\n", $out), 0, 20000);
    }

    public static function hash(array $news): string
    {
        return sha1(self::text($news) . '|' . setting('google_tts_voice', 'hi-IN-Neural2-A') . '|' . self::rate());
    }

    public static function fresh(array $news): bool
    {
        return !empty($news['tts_file']) && $news['tts_hash'] === self::hash($news) && is_file(BASE_PATH . '/public/uploads/' . $news['tts_file']);
    }

    /** MP3 तैयार करें (ताज़ा हो तो कुछ नहीं); लौटाए uploads वाला path */
    public static function ensure(array $news): string
    {
        if (self::fresh($news)) {
            return (string) $news['tts_file'];
        }
        // पिछली कोशिश फ़ेल हुई हो (ग़लत key, कोटा) तो 10 मिनट तक दोबारा API न बुलाएँ
        $failKey = 'tts.fail.' . (int) $news['id'];
        if (($why = cache()->get($failKey)) !== null) {
            throw new \RuntimeException((string) $why);
        }
        $lock = BASE_PATH . '/storage/cache/tts-' . (int) $news['id'] . '.lock';
        $fh = @fopen($lock, 'c');
        if ($fh) {
            flock($fh, LOCK_EX); // एक ही ख़बर दो बार साथ में न बने
        }
        try {
            $row = db()->first('SELECT * FROM {p}news WHERE id = ?', [$news['id']]) ?: $news;
            if (self::fresh($row)) {
                return (string) $row['tts_file'];
            }
            $mp3 = '';
            foreach (self::chunks(self::text($row)) as $c) {
                $mp3 .= self::synthesize($c);
            }
            $rel = 'tts/' . date('Y/m') . '/news-' . (int) $row['id'] . '-' . substr(self::hash($row), 0, 10) . '.mp3';
            $abs = BASE_PATH . '/public/uploads/' . $rel;
            if (!is_dir(dirname($abs)) && !@mkdir(dirname($abs), 0775, true) && !is_dir(dirname($abs))) {
                throw new \RuntimeException('uploads/tts फ़ोल्डर नहीं बन सका');
            }
            if (@file_put_contents($abs, $mp3, LOCK_EX) === false) {
                throw new \RuntimeException('ऑडियो फ़ाइल सेव नहीं हुई');
            }
            if (!empty($row['tts_file']) && $row['tts_file'] !== $rel && str_starts_with((string) $row['tts_file'], 'tts/')) {
                @unlink(BASE_PATH . '/public/uploads/' . $row['tts_file']);
            }
            db()->update('news', ['tts_file' => $rel, 'tts_hash' => self::hash($row)], 'id = ?', [$row['id']]);
            return $rel;
        } catch (\Throwable $e) {
            cache()->set($failKey, $e->getMessage(), 600);
            throw $e;
        } finally {
            if ($fh) {
                flock($fh, LOCK_UN);
                fclose($fh);
            }
        }
    }

    /** वाक्य की सीमा पर टुकड़े, हर टुकड़ा CHUNK_BYTES से छोटा */
    public static function chunks(string $text): array
    {
        $sentences = preg_split('/(?<=[।.!?\n])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        $cur = '';
        foreach ($sentences as $s) {
            while (strlen($s) > self::CHUNK_BYTES) { // बहुत लंबा वाक्य: शब्दों पर काटें
                $cut = mb_strcut($s, 0, self::CHUNK_BYTES, 'UTF-8');
                $sp = mb_strrpos($cut, ' ');
                $cut = $sp ? mb_substr($cut, 0, $sp) : $cut;
                $out[] = $cut;
                $s = ltrim(mb_substr($s, mb_strlen($cut)));
            }
            if ($cur !== '' && strlen($cur) + strlen($s) + 1 > self::CHUNK_BYTES) {
                $out[] = $cur;
                $cur = '';
            }
            $cur .= ($cur === '' ? '' : ' ') . $s;
        }
        if (trim($cur) !== '') {
            $out[] = $cur;
        }
        return $out;
    }

    private static function synthesize(string $text): string
    {
        $voice = (string) setting('google_tts_voice', 'hi-IN-Neural2-A');
        $body = json_encode([
            'input' => ['text' => $text],
            'voice' => ['languageCode' => substr($voice, 0, 5), 'name' => $voice],
            'audioConfig' => ['audioEncoding' => 'MP3', 'speakingRate' => self::rate(), 'sampleRateHertz' => 24000],
        ], JSON_UNESCAPED_UNICODE);
        $url = (getenv('NP_TTS_API') ?: self::API) . '?key=' . rawurlencode(trim((string) setting('google_tts_key')));
        [$code, $resp] = self::post($url, (string) $body);
        $j = json_decode($resp, true);
        if ($code !== 200 || empty($j['audioContent'])) {
            $msg = is_array($j) ? (string) ($j['error']['message'] ?? '') : '';
            throw new \RuntimeException('Google TTS: ' . ($code ?: 'कनेक्शन नहीं') . ' ' . mb_substr($msg, 0, 160));
        }
        return (string) base64_decode((string) $j['audioContent']);
    }

    /** [status, body] */
    private static function post(string $url, string $body): array
    {
        $headers = ['Content-Type: application/json; charset=utf-8'];
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 8]);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return [$code, is_string($resp) ? $resp : ''];
        }
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 40, 'ignore_errors' => true]]);
        $resp = @file_get_contents($url, false, $ctx);
        $code = isset($http_response_header[0]) && preg_match('~\s(\d{3})\s~', $http_response_header[0] . ' ', $m) ? (int) $m[1] : 0;
        return [$code, (string) $resp];
    }

    // ---------- प्रकाशन के बाद पीछे से (एडमिन को इंतज़ार नहीं) ----------
    public static function queue(int $newsId): void
    {
        cache()->forget('tts.fail.' . $newsId); // एडमिन ने ख़बर सेव की: नई कोशिश
        self::$queue[$newsId] = true;
    }

    public static function flush(): void
    {
        if (!self::$queue || !self::google()) {
            return;
        }
        @set_time_limit(180);
        foreach (array_keys(self::$queue) as $id) {
            $n = db()->first("SELECT * FROM {p}news WHERE id = ? AND status = 'published' AND deleted_at IS NULL", [$id]);
            if ($n && self::on($n) && empty($n['audio_file'])) {
                try {
                    self::ensure($n);
                } catch (\Throwable $e) {
                    logger()->warning('TTS news ' . $id . ': ' . $e->getMessage());
                }
            }
        }
        self::$queue = [];
    }

    /** मोटा अनुमान: मिनट (हिंदी ~ 130 शब्द प्रति मिनट) */
    public static function minutes(array $news): int
    {
        $w = (int) ($news['word_count'] ?? 0) ?: count(preg_split('/\s+/u', self::text($news)) ?: []);
        return max(1, (int) round($w / (130 * self::rate())));
    }
}
