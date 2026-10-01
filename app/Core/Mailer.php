<?php
declare(strict_types=1);

namespace App\Core;

/**
 * ईमेल भेजना: PHP mail() या SMTP (सेटिंग → ईमेल)। SMTP शुद्ध PHP में (Composer नहीं):
 * SSL (465) / STARTTLS (587) / बिना एन्क्रिप्शन, AUTH LOGIN/PLAIN।
 * भेज न पाए तो storage/logs/mail-*.log में दर्ज होता है; आख़िरी गड़बड़ी lastError() में।
 */
final class Mailer
{
    private string $error = '';
    private array $transcript = [];

    public function __construct(private Logger $logger, private string $fromEmail, private string $fromName)
    {
    }

    public function lastError(): string
    {
        return $this->error;
    }

    /** SMTP बातचीत (पासवर्ड छिपा हुआ): टेस्ट मेल में दिखाने के लिए */
    public function transcript(): array
    {
        return $this->transcript;
    }

    public static function driver(): string
    {
        return setting('mail_driver', 'mail') === 'smtp' && trim((string) setting('smtp_host', '')) !== '' ? 'smtp' : 'mail';
    }

    /** $extra: अतिरिक्त हेडर (जैसे न्यूज़लेटर का List-Unsubscribe) */
    public function send(string $to, string $subject, string $html, array $extra = []): bool
    {
        $this->error = '';
        $this->transcript = [];
        $extra = array_values(array_filter($extra, static fn($h) => is_string($h) && !preg_match('/[\r\n]/', $h)));
        $ok = false;
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error = 'ईमेल पता सही नहीं';
        } elseif (self::driver() === 'smtp') {
            try {
                $this->smtp($to, $subject, $html, $extra);
                $ok = true;
            } catch (\Throwable $e) {
                $this->error = $e->getMessage();
            }
        } else {
            $headers = ['MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8', 'From: ' . $this->from(), 'X-Mailer: PHP', ...$extra];
            $ok = function_exists('mail') && @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $html, implode("\r\n", $headers));
            if (!$ok) {
                $this->error = function_exists('mail') ? 'सर्वर के mail() ने मेल स्वीकार नहीं किया (SMTP इस्तेमाल करें)' : 'सर्वर पर mail() बंद है (SMTP इस्तेमाल करें)';
            }
        }
        // OTP/पासवर्ड जैसे गोपनीय मेल का कोड और body लॉग में नहीं
        $secret = (bool) preg_match('/OTP|पासवर्ड|password/iu', $subject);
        $logSubject = $secret ? (string) preg_replace('/\d{4,}/', '******', $subject) : $subject;
        $this->logger->log($ok ? 'info' : 'warning', ($ok ? 'भेजा: ' : 'नहीं भेजा जा सका: ') . $logSubject . ($ok ? '' : ' (' . $this->error . ')'),
            ['to' => $to, 'driver' => self::driver(), 'body' => $ok || $secret ? null : mb_substr(strip_tags($html), 0, 2000)], 'mail');
        return $ok;
    }

    private function from(): string
    {
        return mb_encode_mimeheader($this->fromName, 'UTF-8') . ' <' . $this->fromEmail . '>';
    }

    // ---------- SMTP ----------
    private function smtp(string $to, string $subject, string $html, array $extra): void
    {
        $host = trim((string) setting('smtp_host'));
        $port = (int) setting('smtp_port', '587') ?: 587;
        $enc = (string) setting('smtp_encryption', 'tls');
        $user = (string) setting('smtp_username', '');
        $pass = (string) setting('smtp_password', '');
        $timeout = 20;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException("SMTP सर्वर ($host:$port) से कनेक्शन नहीं हुआ: " . ($errstr ?: 'पोर्ट बंद या होस्ट ग़लत'));
        }
        stream_set_timeout($fp, $timeout);
        try {
            $this->expect($fp, [220]);
            $ehlo = 'EHLO ' . (preg_replace('/[^a-z0-9.-]/i', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST)) ?: 'localhost');
            $caps = $this->cmd($fp, $ehlo, [250]);
            if ($enc === 'tls') {
                $this->cmd($fp, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new \RuntimeException('STARTTLS (TLS) शुरू नहीं हो सका: एन्क्रिप्शन "SSL" और पोर्ट 465 आज़माएँ');
                }
                $caps = $this->cmd($fp, $ehlo, [250]);
            }
            if ($user !== '') {
                if (stripos($caps, 'AUTH') !== false && preg_match('/AUTH[ =][^\n]*\bLOGIN\b/i', $caps)) {
                    $this->cmd($fp, 'AUTH LOGIN', [334]);
                    $this->cmd($fp, base64_encode($user), [334]);
                    $this->cmd($fp, base64_encode($pass), [235], true);
                } else {
                    $this->cmd($fp, 'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), [235], true);
                }
            }
            $this->cmd($fp, 'MAIL FROM:<' . $this->fromEmail . '>', [250]);
            $this->cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->cmd($fp, 'DATA', [354]);
            $msg = $this->message($to, $subject, $html, $extra);
            // डॉट-स्टफ़िंग: लाइन की शुरुआत का "." दोगुना
            $msg = preg_replace('/^\./m', '..', $msg);
            fwrite($fp, $msg . "\r\n.\r\n");
            $this->expect($fp, [250]);
            $this->cmd($fp, 'QUIT', [221, 250]);
        } finally {
            fclose($fp);
        }
    }

    private function message(string $to, string $subject, string $html, array $extra): string
    {
        $domain = substr((string) strrchr($this->fromEmail, '@'), 1) ?: 'localhost';
        $text = trim(html_entity_decode(strip_tags(preg_replace('~<(br|/p|/div|/h\d|/li|/tr)\b[^>]*>~i', "\n", $html) ?? ''), ENT_QUOTES, 'UTF-8'));
        $b = 'b' . bin2hex(random_bytes(8));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $this->from(),
            'To: <' . $to . '>',
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n"),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $b . '"',
            'X-Mailer: NewsCMS',
            ...$extra,
        ];
        return implode("\r\n", $headers) . "\r\n\r\n"
            . "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) . "\r\n"
            . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n"
            . "--$b--";
    }

    private function cmd($fp, string $line, array $ok, bool $secret = false): string
    {
        $this->transcript[] = '> ' . ($secret || str_starts_with($line, 'AUTH PLAIN') ? '******' : $line);
        fwrite($fp, $line . "\r\n");
        return $this->expect($fp, $ok);
    }

    private function expect($fp, array $ok): string
    {
        $resp = '';
        while (($l = fgets($fp, 1024)) !== false) {
            $resp .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        $this->transcript[] = '< ' . trim($resp);
        $code = (int) substr($resp, 0, 3);
        if (!in_array($code, $ok, true)) {
            $meta = stream_get_meta_data($fp);
            $why = $resp === '' ? ($meta['timed_out'] ? 'सर्वर ने जवाब नहीं दिया (timeout)' : 'कनेक्शन बंद हो गया') : trim($resp);
            if (in_array($code, [535, 534, 530], true)) {
                $why .= ' — यूज़रनेम/पासवर्ड ग़लत है (Gmail में App Password चाहिए)';
            }
            throw new \RuntimeException('SMTP: ' . mb_substr($why, 0, 300));
        }
        return $resp;
    }
}
