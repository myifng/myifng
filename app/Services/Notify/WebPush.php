<?php
declare(strict_types=1);

namespace App\Services\Notify;

use App\Services\SettingService;

/**
 * वेब पुश (RFC 8030/8291/8292): VAPID + aes128gcm एन्क्रिप्शन, सिर्फ़ PHP openssl से।
 * किसी कंपनी का SDK नहीं; Chrome, Firefox, Edge, Safari (iOS 16.4+ होम-स्क्रीन ऐप) के पुश सर्वर पर चलता है।
 */
final class WebPush
{
    /** P-256 सार्वजनिक कुंजी का DER हेडर (SubjectPublicKeyInfo) */
    private const SPKI = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64u(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    /** नई P-256 कुंजी: [निजी PEM, सार्वजनिक 65 बाइट] */
    public static function newKey(): array
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$k) {
            throw new \RuntimeException('openssl EC कुंजी नहीं बन सकी');
        }
        openssl_pkey_export($k, $pem);
        $d = openssl_pkey_get_details($k)['ec'];
        return [$pem, "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT)];
    }

    /** VAPID कुंजियाँ (पहली बार अपने आप बनकर सेटिंग में) */
    public static function keys(): ?array
    {
        $pub = (string) setting('vapid_public');
        $priv = (string) setting('vapid_private');
        if ($pub === '' || $priv === '') {
            try {
                [$pem, $raw] = self::newKey();
            } catch (\Throwable) {
                return null;
            }
            $pub = self::b64u($raw);
            $priv = base64_encode($pem);
            SettingService::set('vapid_public', $pub, 'push');
            SettingService::set('vapid_private', $priv, 'push');
        }
        return ['public' => $pub, 'private' => (string) base64_decode($priv)];
    }

    public static function available(): bool
    {
        return function_exists('openssl_pkey_derive') && function_exists('hash_hkdf') && in_array('aes-128-gcm', openssl_get_cipher_methods(), true);
    }

    private static function pubPem(string $raw65): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(self::SPKI) . $raw65), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** RFC 8291: पेलोड को ग्राहक की कुंजी से एन्क्रिप्ट (aes128gcm बॉडी) */
    public static function encrypt(string $payload, string $p256dh, string $auth): string
    {
        $uaPub = self::unb64u($p256dh);
        $authSecret = self::unb64u($auth);
        if (strlen($uaPub) !== 65 || strlen($authSecret) < 16) {
            throw new \InvalidArgumentException('ग़लत सब्सक्रिप्शन कुंजी');
        }
        [$asPem, $asPub] = self::newKey();
        $shared = openssl_pkey_derive(openssl_pkey_get_public(self::pubPem($uaPub)), openssl_pkey_get_private($asPem));
        if ($shared === false) {
            throw new \RuntimeException('ECDH विफल');
        }
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPub . $asPub, $authSecret);
        $salt = random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $tag = '';
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        return $salt . pack('N', 4096) . chr(65) . $asPub . $cipher . $tag;
    }

    /** VAPID JWT (ES256) */
    public static function vapid(string $endpoint, array $keys): string
    {
        $u = parse_url($endpoint);
        $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
        $sub = setting('contact_email') ? 'mailto:' . setting('contact_email') : rtrim((string) config('app.url'), '/');
        $data = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.' . self::b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $sub], JSON_UNESCAPED_SLASHES));
        openssl_sign($data, $der, $keys['private'], OPENSSL_ALGO_SHA256);
        return $data . '.' . self::b64u(self::derToRaw($der));
    }

    /** DER ECDSA हस्ताक्षर → r||s (64 बाइट) */
    private static function derToRaw(string $der): string
    {
        $pos = 2;
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }
        return $out;
    }

    /**
     * एक सब्सक्रिप्शन पर भेजें। true = भेजा; 'gone' = सब्सक्रिप्शन हटाएँ; और कुछ = त्रुटि
     * @param array{endpoint:string,p256dh:string,auth:string} $sub
     */
    public static function send(array $sub, array $message, string $urgency = 'normal'): bool|string
    {
        $keys = self::keys();
        if (!$keys || !preg_match('~^https://~', $sub['endpoint'])) {
            return 'पुश कॉन्फ़िगर नहीं';
        }
        try {
            $body = self::encrypt(json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $sub['p256dh'], $sub['auth']);
        } catch (\Throwable $e) {
            return 'gone';
        }
        $headers = ['Authorization: vapid t=' . self::vapid($sub['endpoint'], $keys) . ', k=' . $keys['public'], 'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream', 'TTL: 86400', 'Urgency: ' . $urgency, 'Content-Length: ' . strlen($body)];
        [$code, $err] = Http::post($sub['endpoint'], $body, $headers);
        if ($code >= 200 && $code < 300) {
            return true;
        }
        if ($code === 404 || $code === 410) {
            return 'gone';
        }
        return 'HTTP ' . $code . ($err ? ': ' . $err : '');
    }
}
