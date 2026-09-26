<?php
declare(strict_types=1);

namespace App\Core;

/** आने वाला HTTP अनुरोध */
final class Request
{
    private array $routeParams = [];

    public function __construct(
        private array $get,
        private array $post,
        private array $files,
        private array $server,
        private string $basePath,
    ) {
    }

    public static function capture(string $basePath): self
    {
        return new self($_GET, $_POST, $_FILES, $_SERVER, $basePath);
    }

    /** फ़ॉर्म में _method से PUT/PATCH/DELETE */
    public function method(): string
    {
        $m = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($m === 'POST' && isset($this->post['_method'])) {
            $spoof = strtoupper((string) $this->post['_method']);
            if (in_array($spoof, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoof;
            }
        }
        return $m;
    }

    /** बेस पाथ हटाकर रास्ता: /news/admin/users → /admin/users */
    public function path(): string
    {
        $uri = rawurldecode(parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        if ($this->basePath !== '' && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }
        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    /** सिर्फ़ टेक्स्ट: ऐरे आए तो डिफ़ॉल्ट */
    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $key): bool
    {
        return in_array($this->input($key), ['1', 1, 'on', 'yes', 'true', true], true);
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    public function post(): array
    {
        return $this->post;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->get : ($this->get[$key] ?? $default);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        return is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $f : null;
    }

    public function isPost(): bool
    {
        return $this->method() !== 'GET' && $this->method() !== 'HEAD';
    }

    public function ip(): string
    {
        return substr((string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        return $this->isAjax() || str_contains((string) $this->header('Accept'), 'application/json');
    }

    public function fullUrl(): string
    {
        return (string) ($this->server['REQUEST_URI'] ?? '/');
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->server['SERVER_PORT'] ?? '') == 443
            || strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function setRouteParams(array $p): void
    {
        $this->routeParams = $p;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }
}
