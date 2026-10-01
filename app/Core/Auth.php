<?php
declare(strict_types=1);

namespace App\Core;

/**
 * लॉगिन सिस्टम: पासवर्ड जाँच, रेट लिमिट, सेशन रोटेशन, निष्क्रियता टाइमआउट, लॉगिन हिस्ट्री।
 */
final class Auth
{
    private ?array $user = null;
    private bool $resolved = false;

    public function __construct(
        private Database $db,
        private Session $session,
        private int $timeoutMinutes = 120,
        private int $maxAttempts = 5,
        private int $lockMinutes = 15,
    ) {
    }

    /**
     * $login = false: सिर्फ़ जाँच (दो-चरण लॉगिन में OTP के बाद लॉगिन होता है)
     * @return array{ok: bool, message: string, user?: array}
     */
    public function attempt(string $email, string $password, Request $req, bool $login = true): array
    {
        $email = strtolower(trim($email));
        $ip = $req->ip();

        $recent = (int) $this->db->value(
            'SELECT COUNT(*) FROM {p}login_attempts WHERE (ip = ? OR email = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$ip, $email, $this->lockMinutes]
        );
        if ($recent >= $this->maxAttempts) {
            return ['ok' => false, 'message' => "बहुत ज़्यादा ग़लत प्रयास हुए। {$this->lockMinutes} मिनट बाद दोबारा कोशिश करें।"];
        }

        $user = $this->db->first('SELECT * FROM {p}users WHERE email = ? AND deleted_at IS NULL LIMIT 1', [$email]);
        // यूज़र न मिले तब भी hash जाँचें, ताकि समय से पता न चले कि ईमेल मौजूद है या नहीं
        $hash = $user['password'] ?? '$2y$12$ikfQjMVFw4el3JYK.g/xyODbGhKw8jVwzJj5l5Sp.kl7jHfoLjvSy';
        $valid = password_verify($password, $hash) && $user !== null;

        if (!$valid) {
            $this->db->insert('login_attempts', ['ip' => $ip, 'email' => mb_substr($email, 0, 190)]);
            if ($user) {
                $this->history((int) $user['id'], $req, 'failed');
            }
            $left = $this->maxAttempts - $recent - 1;
            return ['ok' => false, 'message' => 'ईमेल या पासवर्ड ग़लत है।' . ($left > 0 && $left <= 2 ? " ($left प्रयास बाकी)" : '')];
        }
        if ($user['status'] !== 'active') {
            $this->history((int) $user['id'], $req, 'blocked');
            return ['ok' => false, 'message' => $user['status'] === 'suspended' ? 'आपका खाता निलंबित है। एडमिन से संपर्क करें।' : 'आपका खाता अभी चालू नहीं है।'];
        }

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->db->update('users', ['password' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
        }
        $this->db->delete('login_attempts', 'ip = ? OR email = ?', [$ip, $email]);
        if ($login) {
            $this->login($user, $req);
        }
        return ['ok' => true, 'message' => '', 'user' => $user];
    }

    public function login(array $user, Request $req): void
    {
        $this->session->regenerate();
        $this->session->set('auth', [
            'id' => (int) $user['id'],
            'fp' => hash('sha256', $req->userAgent()),
            'last' => time(),
            'at' => time(),
        ]);
        $this->db->update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $req->ip()], 'id = ?', [$user['id']]);
        $this->history((int) $user['id'], $req, 'success');
        $this->resolved = false;
    }

    private function history(int $userId, Request $req, string $status): void
    {
        $this->db->insert('login_history', ['user_id' => $userId, 'ip' => $req->ip(), 'user_agent' => $req->userAgent(), 'status' => $status]);
    }

    /** लॉगिन यूज़र (रोल के साथ) या null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;
        $auth = $this->session->get('auth');
        if (!is_array($auth) || empty($auth['id'])) {
            return $this->user = null;
        }
        // दूसरे ब्राउज़र से चुराया सेशन, या बहुत देर से निष्क्रिय सेशन: लॉगआउट
        $fp = hash('sha256', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255));
        if (!hash_equals((string) $auth['fp'], $fp) || (time() - (int) $auth['last']) > $this->timeoutMinutes * 60) {
            if (!hash_equals((string) $auth['fp'], $fp)) {
                // Phase 15: ब्राउज़र बदला = संभव सेशन चोरी; ऑडिट में दर्ज (user() को दोबारा न बुलाए, इसलिए सीधे insert)
                try {
                    $this->db->insert('audit_logs', ['user_id' => (int) $auth['id'], 'action' => 'session_mismatch', 'module' => 'auth',
                        'description' => 'दूसरे ब्राउज़र से सेशन इस्तेमाल: लॉगआउट किया', 'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
                } catch (\Throwable) {
                }
            }
            $this->logout();
            $this->session->flash('flash', ['type' => 'warning', 'message' => 'सुरक्षा के लिए आपका सेशन बंद कर दिया गया। दोबारा लॉगिन करें।']);
            return $this->user = null;
        }
        $user = $this->db->first(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM {p}users u JOIN {p}roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = ? LIMIT 1',
            [(int) $auth['id'], 'active']
        );
        if (!$user) {
            $this->logout();
            return $this->user = null;
        }
        $auth['last'] = time();
        $this->session->set('auth', $auth);
        unset($user['password']);
        return $this->user = $user;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function logout(): void
    {
        $this->session->forget('auth');
        $this->session->regenerate();
        $this->user = null;
        $this->resolved = true;
    }

    public function refresh(): void
    {
        $this->resolved = false;
    }
}
