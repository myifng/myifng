<?php
/**
 * इंस्टॉल विज़ार्ड (7 कदम)
 *  1. सर्वर जाँच  2. डेटाबेस  3. टेबल इंस्टॉल  4. Super Admin  5. वेबसाइट  6. डेमो डेटा  7. पूरा
 * पूरा होने पर config/env.php और storage/installed.lock बनते हैं; उसके बाद यह विज़ार्ड बंद हो जाता है।
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    http_response_code(500);
    exit('इस सॉफ़्टवेयर के लिए PHP 8.1 या नया चाहिए। आपका वर्ज़न: ' . PHP_VERSION . '। cPanel में "Select PHP Version" से बदलें।');
}
require BASE_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register(BASE_PATH);

use App\Core\App;
use App\Core\Database;
use App\Core\Migrator;
use App\Services\UploadService;

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Kolkata');
session_name('np_install');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

const STEPS = [1 => 'सर्वर जाँच', 2 => 'डेटाबेस', 3 => 'टेबल', 4 => 'Super Admin', 5 => 'वेबसाइट', 6 => 'डेमो डेटा', 7 => 'पूरा'];

/* ---------------- सहायक ---------------- */
function h(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function itoken(): string
{
    return $_SESSION['itok'] ??= bin2hex(random_bytes(16));
}
function token_ok(): bool
{
    return hash_equals(itoken(), (string) ($_POST['_t'] ?? ''));
}
function go(int $step): never
{
    header('Location: ?step=' . $step);
    exit;
}
function st(string $key, mixed $default = null): mixed
{
    return $_SESSION['install'][$key] ?? $default;
}
function st_set(string $key, mixed $value): void
{
    $_SESSION['install'][$key] = $value;
}
function detected_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $dir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'))), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}
function connect(array $c): Database
{
    return new Database(['host' => $c['host'], 'port' => $c['port'], 'name' => $c['name'], 'user' => $c['user'], 'pass' => $c['pass'], 'prefix' => $c['prefix']]);
}
function bytes(string $v): int
{
    $n = (int) $v;
    return match (strtolower(substr(trim($v), -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
}

/** सर्वर जाँच: [नाम, स्थिति pass|warn|fail, नोट] */
function requirements(): array
{
    $w = static fn(string $p) => is_dir(BASE_PATH . '/' . $p) && is_writable(BASE_PATH . '/' . $p);
    $ext = static fn(string $e, string $level, string $note) => [$e, extension_loaded($e) ? 'pass' : $level, $note];
    $rewrite = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules(), true) : null;
    $upload = min(bytes((string) ini_get('upload_max_filesize')), bytes((string) ini_get('post_max_size')));
    $memory = (string) ini_get('memory_limit');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    return [
        'PHP' => [
            ['PHP 8.1 या नया', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'pass' : 'fail', 'आपका वर्ज़न: ' . PHP_VERSION],
            $ext('pdo', 'fail', 'डेटाबेस के लिए ज़रूरी'),
            $ext('pdo_mysql', 'fail', 'MySQL/MariaDB के लिए ज़रूरी'),
            $ext('mbstring', 'fail', 'हिंदी टेक्स्ट के लिए ज़रूरी'),
            $ext('json', 'fail', 'ज़रूरी'),
            $ext('fileinfo', 'fail', 'अपलोड फ़ाइल की सुरक्षित जाँच'),
            $ext('dom', 'fail', 'ख़बर के HTML की सफ़ाई (XSS से बचाव)'),
            $ext('openssl', 'warn', 'सुरक्षित कनेक्शन और ईमेल'),
            $ext('curl', 'warn', 'बाहरी सेवाएँ (SMS, नोटिफ़िकेशन, API)'),
            $ext('gd', 'warn', 'इमेज छोटी करना, थंबनेल, WebP'),
            $ext('zip', 'warn', 'बैकअप और अपडेट'),
            $ext('intl', 'warn', 'तारीख़ और भाषा (सलाह)'),
        ],
        'फ़ोल्डर (लिखने की अनुमति)' => [
            ['config/', $w('config') ? 'pass' : 'fail', 'env.php यहीं बनेगी'],
            ['storage/cache/', $w('storage/cache') ? 'pass' : 'fail', 'कैश'],
            ['storage/logs/', $w('storage/logs') ? 'pass' : 'fail', 'त्रुटि लॉग'],
            ['storage/sessions/', $w('storage/sessions') ? 'pass' : 'fail', 'लॉगिन सेशन'],
            ['storage/private/', $w('storage/private') ? 'pass' : 'fail', 'निजी दस्तावेज़ (KYC आदि)'],
            ['storage/backups/', $w('storage/backups') ? 'pass' : 'warn', 'बैकअप'],
            ['public/uploads/', $w('public/uploads') ? 'pass' : 'fail', 'तस्वीरें, PDF'],
        ],
        'सर्वर' => [
            ['अपलोड सीमा', $upload >= 8 * 1048576 ? 'pass' : 'warn', 'अभी: ' . ini_get('upload_max_filesize') . ' (post: ' . ini_get('post_max_size') . '), ई-पेपर के लिए 32M+ सलाह'],
            ['मेमोरी सीमा', ($memory === '-1' || bytes($memory) >= 128 * 1048576) ? 'pass' : 'warn', 'अभी: ' . $memory . ', 128M+ सलाह'],
            ['फ़ाइल अपलोड चालू', ini_get('file_uploads') ? 'pass' : 'fail', ''],
            ['mod_rewrite (सुंदर URL)', $rewrite === false ? 'warn' : 'pass', $rewrite === null ? 'जाँचा नहीं जा सका (ज़्यादातर होस्टिंग पर चालू रहता है)' : ''],
            ['HTTPS (SSL)', $https ? 'pass' : 'warn', $https ? '' : 'लाइव साइट पर SSL ज़रूर लगाएँ (cPanel → SSL/TLS)'],
        ],
    ];
}

/* ---------------- पहले से इंस्टॉल? ---------------- */
$step = max(1, min(7, (int) ($_GET['step'] ?? 1)));
if (App::isInstalled(BASE_PATH)) {
    // इंस्टॉल के बाद: सिर्फ़ उसी सेशन में "पूरा" पेज, बाकी सब बंद
    $view = (!empty($_SESSION['install_done']) && $step === 7) ? 'step7' : 'installed';
    $errors = [];
    require __DIR__ . '/views/layout.php';
    exit;
}
$errors = [];
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($post && !token_ok()) {
    $errors[] = 'फ़ॉर्म की समय-सीमा ख़त्म हो गई। दोबारा भरें।';
    $post = false;
}

// कदम का क्रम: पिछला कदम पूरा किए बिना आगे नहीं
$done = (int) st('done', 0);
if ($step > $done + 1 && $step !== 7) {
    go($done + 1);
}
if ($step === 7 && $done < 6 && empty($_SESSION['install_done'])) {
    go($done + 1);
}

/* ---------------- कदम 1: सर्वर जाँच ---------------- */
$reqs = requirements();
$hasFail = (bool) array_filter(array_merge(...array_values($reqs)), static fn($r) => $r[1] === 'fail');
if ($step === 1 && $post) {
    if ($hasFail) {
        $errors[] = 'लाल (ERROR) वाली चीज़ें ठीक किए बिना आगे नहीं बढ़ सकते।';
    } else {
        st_set('done', max($done, 1));
        go(2);
    }
}

/* ---------------- कदम 2: डेटाबेस ---------------- */
if ($step === 2 && $post) {
    $c = [
        'host' => trim((string) ($_POST['host'] ?? '')) ?: 'localhost',
        'port' => (int) ($_POST['port'] ?? 3306) ?: 3306,
        'name' => trim((string) ($_POST['name'] ?? '')),
        'user' => trim((string) ($_POST['user'] ?? '')),
        'pass' => (string) ($_POST['pass'] ?? ''),
        'prefix' => strtolower(trim((string) ($_POST['prefix'] ?? 'np_'))),
    ];
    st_set('db_form', array_diff_key($c, ['pass' => 1]));
    if ($c['name'] === '' || $c['user'] === '') {
        $errors[] = 'डेटाबेस का नाम और यूज़र लिखना ज़रूरी है।';
    } elseif (!preg_match('/^[a-z0-9_]{0,20}$/', $c['prefix'])) {
        $errors[] = 'टेबल प्रीफ़िक्स में सिर्फ़ छोटे अंग्रेज़ी अक्षर, अंक और _ हों (जैसे np_)।';
    } else {
        try {
            $db = connect($c);
            $ver = (string) $db->value('SELECT VERSION()');
            $maria = stripos($ver, 'mariadb') !== false;
            if ((!$maria && version_compare($ver, '5.7.0', '<')) || ($maria && version_compare($ver, '10.3.0', '<'))) {
                $errors[] = "MySQL 5.7+ या MariaDB 10.3+ चाहिए। आपका वर्ज़न: $ver";
            } else {
                $existing = (bool) $db->value('SHOW TABLES LIKE ' . $db->pdo()->quote($c['prefix'] . 'migrations'));
                $hasUsers = $existing && (bool) $db->value('SHOW TABLES LIKE ' . $db->pdo()->quote($c['prefix'] . 'users')) && (int) $db->value('SELECT COUNT(*) FROM {p}users') > 0;
                if ($hasUsers && empty($_POST['resume'])) {
                    $errors[] = "इस डेटाबेस में “{$c['prefix']}” प्रीफ़िक्स वाला एक इंस्टॉलेशन पहले से है। नया इंस्टॉल करना हो तो दूसरा प्रीफ़िक्स या ख़ाली डेटाबेस चुनें। अधूरा इंस्टॉल जारी रखना हो तो नीचे “अधूरा इंस्टॉल जारी रखें” चुनें।";
                    st_set('can_resume', true);
                } else {
                    st_set('db', $c);
                    st_set('db_version', $ver);
                    st_set('done', 2);
                    go(3);
                }
            }
        } catch (PDOException $e) {
            $m = $e->getMessage();
            $errors[] = match (true) {
                str_contains($m, '1045') => 'यूज़रनेम या पासवर्ड ग़लत है। cPanel → MySQL Databases में जाँचें।',
                str_contains($m, '1044'), str_contains($m, '1049') => 'यह डेटाबेस नहीं मिला या यूज़र को इसकी अनुमति नहीं है। cPanel में डेटाबेस बनाएँ और यूज़र को "All Privileges" के साथ जोड़ें।',
                str_contains($m, '2002'), str_contains($m, '2005'), str_contains($m, '2006') => 'डेटाबेस सर्वर से कनेक्ट नहीं हो सका। होस्ट जाँचें (ज़्यादातर "localhost")।',
                default => 'कनेक्शन नहीं हुआ: ' . $m,
            };
        }
    }
}

/* ---------------- कदम 3: टेबल इंस्टॉल ---------------- */
$pending = [];
if ($step === 3) {
    try {
        $db = connect(st('db'));
        $migrator = new Migrator($db, BASE_PATH . '/database/migrations');
        $pending = array_map(static fn($f) => basename($f, '.php'), $migrator->pending());
        if ($post) {
            $ran = $migrator->migrate();
            $seeder = require BASE_PATH . '/database/seeds/CoreSeeder.php';
            $seeder->run($db, ['site_name' => 'News Portal', 'timezone' => 'Asia/Kolkata', 'language' => 'hi']);
            st_set('migrated', $ran);
            st_set('done', 3);
            go(4);
        }
    } catch (Throwable $e) {
        $errors[] = 'टेबल इंस्टॉल नहीं हो सकीं: ' . $e->getMessage();
    }
}

/* ---------------- कदम 4: Super Admin ---------------- */
if ($step === 4 && $post) {
    $f = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
        'mobile' => preg_replace('/\D/', '', (string) ($_POST['mobile'] ?? '')),
    ];
    st_set('admin_form', $f);
    $pass = (string) ($_POST['password'] ?? '');
    if (mb_strlen($f['name']) < 2) {
        $errors[] = 'नाम लिखें।';
    }
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'सही ईमेल लिखें। इसी से लॉगिन होगा।';
    }
    if ($f['mobile'] !== '' && !preg_match('/^[6-9]\d{9}$/', substr($f['mobile'], -10))) {
        $errors[] = 'मोबाइल नंबर 10 अंकों का सही नंबर हो।';
    }
    if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        $errors[] = 'पासवर्ड कम से कम 8 अक्षर का हो और उसमें अक्षर व अंक दोनों हों।';
    } elseif ($pass !== (string) ($_POST['password_confirmation'] ?? '')) {
        $errors[] = 'दोनों पासवर्ड एक जैसे नहीं हैं।';
    }
    if (!$errors) {
        try {
            $db = connect(st('db'));
            $roleId = (int) $db->value("SELECT id FROM {p}roles WHERE slug = 'super-admin'");
            $data = ['name' => $f['name'], 'email' => $f['email'], 'mobile' => $f['mobile'] ? substr($f['mobile'], -10) : null, 'password' => password_hash($pass, PASSWORD_DEFAULT), 'role_id' => $roleId, 'status' => 'active', 'bio' => 'Super Admin'];
            $existing = (int) $db->value('SELECT id FROM {p}users WHERE email = ?', [$f['email']]);
            if ($existing) {
                $db->update('users', $data, 'id = ?', [$existing]);
                $id = $existing;
            } else {
                $id = $db->insert('users', $data);
            }
            st_set('admin_id', $id);
            st_set('done', 4);
            go(5);
        } catch (Throwable $e) {
            $errors[] = 'खाता नहीं बन सका: ' . $e->getMessage();
        }
    }
}

/* ---------------- कदम 5: वेबसाइट ---------------- */
if ($step === 5 && $post) {
    $f = [
        'site_name' => trim((string) ($_POST['site_name'] ?? '')),
        'tagline' => trim((string) ($_POST['tagline'] ?? '')),
        'site_url' => rtrim(trim((string) ($_POST['site_url'] ?? '')), '/'),
        'contact_email' => strtolower(trim((string) ($_POST['contact_email'] ?? ''))),
        'primary_color' => (string) ($_POST['primary_color'] ?? '#d71920'),
        'secondary_color' => (string) ($_POST['secondary_color'] ?? '#15161a'),
        'timezone' => (string) ($_POST['timezone'] ?? 'Asia/Kolkata'),
        'language' => (string) ($_POST['language'] ?? 'hi'),
        'admin_path' => strtolower(trim((string) ($_POST['admin_path'] ?? 'admin'), '/ ')),
    ];
    st_set('site_form', $f);
    if ($f['site_name'] === '') {
        $errors[] = 'वेबसाइट का नाम लिखें।';
    }
    if (!filter_var($f['site_url'], FILTER_VALIDATE_URL) || !preg_match('~^https?://~', $f['site_url'])) {
        $errors[] = 'वेबसाइट का पूरा पता लिखें, जैसे https://example.com';
    }
    if ($f['contact_email'] !== '' && !filter_var($f['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'संपर्क ईमेल सही नहीं है।';
    }
    foreach (['primary_color', 'secondary_color'] as $k) {
        if (!preg_match('/^#[0-9a-f]{6}$/i', $f[$k])) {
            $errors[] = 'रंग सही नहीं है।';
        }
    }
    if (!in_array($f['timezone'], DateTimeZone::listIdentifiers(), true)) {
        $errors[] = 'टाइमज़ोन सही नहीं है।';
    }
    if (!in_array($f['language'], ['hi', 'en'], true)) {
        $errors[] = 'भाषा सही नहीं है।';
    }
    if (!preg_match('/^[a-z0-9][a-z0-9-]{2,29}$/', $f['admin_path']) || in_array($f['admin_path'], ['install', 'public', 'app', 'config', 'storage', 'api', 'news'], true)) {
        $errors[] = 'एडमिन पता 3-30 अंग्रेज़ी अक्षर/अंक का हो (जैसे admin या newsroom)।';
    }
    $logo = st('logo', '');
    $favicon = st('favicon', '');
    if (!$errors) {
        foreach (['logo' => 'image', 'favicon' => 'icon'] as $field => $kind) {
            if (!empty($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $r = UploadService::store($_FILES[$field], $kind, 'branding', BASE_PATH . '/public/uploads', 800);
                if ($r['ok']) {
                    $$field = $r['path'];
                } else {
                    $errors[] = ($field === 'logo' ? 'लोगो: ' : 'फ़ेविकॉन: ') . $r['error'];
                }
            }
        }
    }
    if (!$errors) {
        try {
            $db = connect(st('db'));
            $values = [
                'site_name' => $f['site_name'], 'tagline' => $f['tagline'], 'site_description' => $f['tagline'],
                'contact_email' => $f['contact_email'], 'admin_email' => $f['contact_email'], 'mail_from_email' => $f['contact_email'], 'mail_from_name' => $f['site_name'],
                'primary_color' => $f['primary_color'], 'secondary_color' => $f['secondary_color'],
                'timezone' => $f['timezone'], 'language' => $f['language'], 'logo' => $logo, 'favicon' => $favicon,
            ];
            foreach ($values as $k => $v) {
                $db->query('INSERT INTO {p}settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$k, (string) $v]);
            }
            $db->query('UPDATE {p}languages SET is_default = (code = ?)', [$f['language']]);
            st_set('logo', $logo);
            st_set('favicon', $favicon);
            st_set('done', 5);
            go(6);
        } catch (Throwable $e) {
            $errors[] = 'सेटिंग सेव नहीं हुई: ' . $e->getMessage();
        }
    }
}

/* ---------------- कदम 6: डेमो डेटा + कदम 7: पूरा ---------------- */
if ($step === 6 && $post) {
    try {
        $db = connect(st('db'));
        $demo = !empty($_POST['demo']);
        $demoPass = 'Demo@' . random_int(10000, 99999);
        if ($demo) {
            $seeder = require BASE_PATH . '/database/seeds/DemoSeeder.php';
            $seeder->run($db, ['demo_password' => $demoPass, 'admin_id' => st('admin_id')]);
        }
        $c = st('db');
        $site = st('site_form');
        $env = [
            'APP_URL' => $site['site_url'],
            'APP_KEY' => bin2hex(random_bytes(32)),
            'DEBUG' => false,
            'TIMEZONE' => $site['timezone'],
            'ADMIN_PATH' => $site['admin_path'],
            'CACHE' => true,
            'DB_HOST' => $c['host'],
            'DB_PORT' => $c['port'],
            'DB_NAME' => $c['name'],
            'DB_USER' => $c['user'],
            'DB_PASS' => $c['pass'],
            'DB_PREFIX' => $c['prefix'],
        ];
        $php = "<?php\n/** इंस्टॉलर ने " . date('d-m-Y H:i') . " पर बनाया। इस फ़ाइल को कभी सार्वजनिक न करें। */\nreturn " . var_export($env, true) . ";\n";
        if (@file_put_contents(BASE_PATH . '/config/env.php', $php, LOCK_EX) === false) {
            throw new RuntimeException('config/env.php नहीं लिखी जा सकी। config फ़ोल्डर की अनुमति 755 करें।');
        }
        @chmod(BASE_PATH . '/config/env.php', 0640);
        file_put_contents(BASE_PATH . '/storage/installed.lock', json_encode(['installed_at' => date('c'), 'version' => '1.0.0']));
        $db->insert('audit_logs', ['user_id' => st('admin_id'), 'user_name' => st('admin_form')['name'] ?? null, 'role' => 'Super Admin', 'action' => 'install', 'module' => 'system', 'description' => 'सॉफ़्टवेयर इंस्टॉल हुआ' . ($demo ? ' (डेमो डेटा के साथ)' : ''), 'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), 'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);

        $_SESSION['install_done'] = [
            'url' => $site['site_url'], 'admin' => $site['site_url'] . '/' . $site['admin_path'] . '/',
            'email' => st('admin_form')['email'] ?? '', 'demo' => $demo ? $demoPass : null, 'migrated' => st('migrated', []),
        ];
        unset($_SESSION['install']);
        go(7);
    } catch (Throwable $e) {
        $errors[] = 'इंस्टॉल पूरा नहीं हुआ: ' . $e->getMessage();
    }
}

$view = 'step' . $step;
require __DIR__ . '/views/layout.php';
