<?php
declare(strict_types=1);

namespace App\Core;

/** जवाब: HTML, JSON या रीडायरेक्ट */
class Response
{
    private array $headers = [];

    public function __construct(private string $body = '', private int $status = 200)
    {
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return (new self('', $status))->header('Location', $url);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return (new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $status))
            ->header('Content-Type', 'application/json; charset=utf-8');
    }

    /** CSV डाउनलोड (Excel में हिंदी सही दिखे, इसलिए BOM के साथ) */
    public static function csv(string $filename, array $header, iterable $rows): self
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $header, ',', '"', '\\');
        foreach ($rows as $row) {
            // फ़ॉर्मूला इंजेक्शन से बचाव: = + - @ से शुरू होने वाले मान के आगे '
            fputcsv($fh, array_map(static fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $row), ',', '"', '\\');
        }
        rewind($fh);
        $body = (string) stream_get_contents($fh);
        fclose($fh);
        return (new self($body))
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . preg_replace('/[^a-z0-9_.-]/i', '', $filename) . '"')
            ->header('Cache-Control', 'no-store');
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function status(): int
    {
        return $this->status;
    }

    /** रीडायरेक्ट के साथ एक बार दिखने वाला संदेश */
    public function with(string $type, string $message): self
    {
        app('session')->flash('flash', ['type' => $type, 'message' => $message]);
        return $this;
    }

    public function withErrors(array $errors): self
    {
        app('session')->flash('errors', $errors);
        return $this;
    }

    public function withInput(array $input): self
    {
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_csrf']);
        app('session')->flash('old', $input);
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        echo $this->body;
    }
}
