<?php
declare(strict_types=1);

namespace App\Services;

/** सुरक्षा (§67): एडमिन IP allowlist, सुरक्षा चेकलिस्ट, ब्लॉक हुए IP/ईमेल */
final class SecurityService
{
    /** टेक्स्ट → ['list' => मान्य IP/CIDR, 'invalid' => ग़लत] */
    public static function parseAllowlist(string $text): array
    {
        $list = [];
        $bad = [];
        foreach (preg_split('/[\s,]+/', trim($text)) ?: [] as $x) {
            if ($x === '') {
                continue;
            }
            [$ip, $bits] = array_pad(explode('/', $x, 2), 2, null);
            $v6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
            $ok = filter_var($ip, FILTER_VALIDATE_IP) !== false && ($bits === null || (ctype_digit($bits) && (int) $bits <= ($v6 ? 128 : 32)));
            $ok ? $list[] = $x : $bad[] = $x;
        }
        return ['list' => array_values(array_unique($list)), 'invalid' => $bad];
    }

    public static function ipAllowed(string $ip, array $list): bool
    {
        if (!$list) {
            return true;
        }
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        foreach ($list as $rule) {
            [$net, $bits] = array_pad(explode('/', $rule, 2), 2, null);
            $nb = @inet_pton($net);
            if ($nb === false || strlen($nb) !== strlen($bin)) {
                continue;
            }
            $bits = $bits === null ? strlen($bin) * 8 : (int) $bits;
            $bytes = intdiv($bits, 8);
            $rem = $bits % 8;
            if (substr($bin, 0, $bytes) !== substr($nb, 0, $bytes)) {
                continue;
            }
            if ($rem === 0 || ((ord($bin[$bytes]) ^ ord($nb[$bytes])) & (0xFF << (8 - $rem)) & 0xFF) === 0) {
                return true;
            }
        }
        return false;
    }

    /** स्टाफ़ (रिपोर्टर नहीं) इस IP से एडमिन चला सकता है? */
    public static function staffIpOk(string $ip, ?string $roleSlug): bool
    {
        if ($roleSlug === 'reporter' || !empty(config('app.admin_ip_bypass'))) {
            return true;
        }
        $list = self::parseAllowlist((string) setting('admin_ip_allowlist', ''))['list'];
        return self::ipAllowed($ip, $list);
    }

    /** चेकलिस्ट: [नाम, ठीक?, सलाह, गंभीर?] */
    public static function checklist(): array
    {
        $base = BASE_PATH;
        $https = str_starts_with((string) config('app.url'), 'https://');
        $defaultPw = (int) db()->value("SELECT COUNT(*) FROM {p}users WHERE deleted_at IS NULL AND status = 'active' AND email IN ('admin@example.com','owner@example.com')");
        $probe = static function (string $rel): ?bool {
            // वेब से खुलना नहीं चाहिए: true = सुरक्षित (403/404), false = खुल रहा है, null = जाँच नहीं हो सकी
            $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 2, 'ignore_errors' => true, 'follow_location' => 0], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
            $body = @file_get_contents(rtrim((string) config('app.url'), '/') . '/' . $rel, false, $ctx, 0, 2048);
            $code = 0;
            foreach ($http_response_header ?? [] as $h) {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) {
                    $code = (int) $m[1];
                }
            }
            return $code === 0 ? null : !($code === 200 && $body !== false && $body !== '');
        };
        // वेब-जाँच धीमी हो सकती है: नतीजा 10 मिनट कैश (null = जाँच नहीं हो सकी, इसे कैश नहीं करते)
        $probes = cache()->get('system.probes');
        if (!is_array($probes)) {
            $probes = ['env' => $probe('config/env.php'), 'store' => $probe('storage/logs/'), 'git' => is_dir($base . '/.git') ? $probe('.git/config') : true];
            if (!in_array(null, $probes, true)) {
                cache()->set('system.probes', $probes, 600);
            }
        }
        ['env' => $env, 'store' => $store, 'git' => $git] = $probes;
        $failed = (int) db()->value("SELECT COUNT(*) FROM {p}login_history WHERE status = 'failed' AND created_at >= NOW() - INTERVAL 24 HOUR");
        return [
            ['डीबग मोड बंद', !config('app.debug'), 'config/env.php में DEBUG बंद करें; डीबग में एरर के साथ कोड की जानकारी दिखती है।', true],
            ['HTTPS (SSL) चालू', $https, 'hosting से मुफ़्त SSL (Let’s Encrypt) लें और APP_URL को https:// पर करें।', true],
            ['इंस्टॉलर हटाया गया', !is_dir($base . '/install'), 'इंस्टॉल के बाद install/ फ़ोल्डर हटा दें (या उसका नाम बदलें)।', true],
            ['एडमिन का पता /admin से अलग', config('app.admin_path', 'admin') !== 'admin', 'config/env.php में ADMIN_PATH बदलें (जैसे control-desk); बॉट /admin को सबसे पहले आज़माते हैं।', false],
            ['env.php वेब से नहीं खुलती', $env, 'Apache में .htaccess चालू करें (AllowOverride All) या config/ को public से बाहर रखें।', true],
            ['storage/ वेब से बंद', $store, 'storage/.htaccess में "Require all denied" होना चाहिए।', true],
            ['.git वेब से बंद', $git, '.git फ़ोल्डर सर्वर पर न रखें या .htaccess से बंद करें।', true],
            ['डेमो/डिफ़ॉल्ट एडमिन ईमेल नहीं', $defaultPw === 0, 'owner@example.com जैसे डेमो खाते बंद करें या ईमेल-पासवर्ड बदलें।', true],
            ['पिछले 24 घंटे में 20 से कम असफल लॉगिन', $failed < 20, "असफल लॉगिन: {$failed}। नीचे ब्लॉक हुए IP देखें; ज़रूरत हो तो एडमिन IP allowlist लगाएँ।", false],
            ['PHP 8.1 या नया', PHP_VERSION_ID >= 80100, 'hosting पैनल से PHP 8.2/8.3 चुनें।', true],
            ['एडमिन IP allowlist', trim((string) setting('admin_ip_allowlist', '')) !== '', 'वैकल्पिक: दफ़्तर का स्थिर IP हो तो सेटिंग → सुरक्षा में जोड़ें।', false],
        ];
    }

    /** अभी रुके हुए IP/ईमेल (लॉगिन सीमा पार) */
    public static function blocked(): array
    {
        $max = max(3, (int) setting('login_max_attempts', '5'));
        $min = max(1, (int) setting('login_lockout_minutes', '15'));
        $ips = db()->all("SELECT ip k, 'ip' kind, COUNT(*) c, MAX(attempted_at) last FROM {p}login_attempts WHERE attempted_at > NOW() - INTERVAL $min MINUTE GROUP BY ip HAVING c >= $max ORDER BY last DESC LIMIT 50");
        $emails = db()->all("SELECT email k, 'email' kind, COUNT(*) c, MAX(attempted_at) last FROM {p}login_attempts WHERE attempted_at > NOW() - INTERVAL $min MINUTE GROUP BY email HAVING c >= $max ORDER BY last DESC LIMIT 50");
        return array_merge($ips, $emails);
    }
}
