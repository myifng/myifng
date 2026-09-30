<?php
declare(strict_types=1);

namespace App\Services\Notify;

/**
 * SMS / WhatsApp: कोई एक कंपनी हार्डकोड नहीं।
 * ड्राइवर (सेटिंग): off | log (सिर्फ़ लॉग में, टेस्ट के लिए) | http (अपने प्रोवाइडर का API: URL/बॉडी में {to}, {message}, {url})
 */
final class GatewayChannel implements Channel
{
    public function __construct(private string $name)
    {
    }

    public function send(string $recipient, array $message): bool|string
    {
        $driver = (string) setting($this->name . '_driver', 'off');
        $text = trim($message['title'] . ($message['body'] !== '' ? "\n" . $message['body'] : '') . ($message['url'] ? "\n" . $message['url'] : ''));
        if ($driver === 'log') {
            logger()->info(strtoupper($this->name) . ' → ' . $recipient . ': ' . str_replace("\n", ' | ', $text));
            return true;
        }
        if ($driver !== 'http') {
            return 'gone'; // बंद: कतार में न अटके
        }
        $vars = ['{to}' => $recipient, '{message}' => $text, '{url}' => (string) $message['url']];
        $url = strtr((string) setting($this->name . '_http_url'), array_map('rawurlencode', $vars));
        if (!preg_match('~^https://~', $url)) {
            return 'API URL https:// से शुरू हो';
        }
        $body = (string) setting($this->name . '_http_body');
        $json = static fn($s) => substr(json_encode($s, JSON_UNESCAPED_UNICODE), 1, -1);
        $headers = array_values(array_filter(array_map('trim', explode("\n", (string) setting($this->name . '_http_headers')))));
        if ($body !== '') {
            $body = strtr($body, array_map($json, $vars));
            $headers[] = 'Content-Type: application/json';
        }
        [$code, $err] = Http::request($body !== '' ? 'POST' : 'GET', $url, $body !== '' ? $body : null, $headers);
        return $code >= 200 && $code < 300 ? true : 'HTTP ' . $code . ($err ? ': ' . $err : '');
    }
}
