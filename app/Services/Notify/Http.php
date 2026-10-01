<?php
declare(strict_types=1);

namespace App\Services\Notify;

/** छोटा HTTP क्लाइंट (curl, न हो तो stream): [status, error] */
final class Http
{
    public static function post(string $url, string $body, array $headers, int $timeout = 10): array
    {
        return self::request('POST', $url, $body, $headers, $timeout);
    }

    public static function request(string $method, string $url, ?string $body, array $headers, int $timeout = 10): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false] + ($body !== null ? [CURLOPT_POSTFIELDS => $body] : []));
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = $resp === false ? curl_error($ch) : ($code >= 400 ? mb_substr((string) $resp, 0, 150) : '');
            return [$code, $err];
        }
        $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => (string) $body, 'timeout' => $timeout, 'ignore_errors' => true]]);
        $resp = @file_get_contents($url, false, $ctx);
        $code = isset($http_response_header[0]) && preg_match('~\s(\d{3})\s~', $http_response_header[0] . ' ', $m) ? (int) $m[1] : 0;
        return [$code, $resp === false ? 'कनेक्शन नहीं हुआ' : ($code >= 400 ? mb_substr((string) $resp, 0, 150) : '')];
    }
}
