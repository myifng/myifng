<?php
declare(strict_types=1);

namespace App\Core;

/**
 * राउटर: नामित रूट, ग्रुप (प्रीफ़िक्स + middleware), पैरामीटर {id:\d+}
 *   $r->get('/users/{id:\d+}', [UserController::class, 'show'])->name('admin.users.show')->middleware('can:users.view');
 */
final class Router
{
    private array $routes = [];
    private array $named = [];
    private array $groupStack = [];
    private ?int $last = null;

    public function get(string $path, array|\Closure $handler): self { return $this->add(['GET', 'HEAD'], $path, $handler); }
    public function post(string $path, array|\Closure $handler): self { return $this->add(['POST'], $path, $handler); }
    public function put(string $path, array|\Closure $handler): self { return $this->add(['PUT', 'PATCH'], $path, $handler); }
    public function delete(string $path, array|\Closure $handler): self { return $this->add(['DELETE'], $path, $handler); }
    public function any(string $path, array|\Closure $handler): self { return $this->add(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $handler); }

    public function add(array $methods, string $path, array|\Closure $handler): self
    {
        $prefix = '';
        $mw = [];
        $namePrefix = '';
        foreach ($this->groupStack as $g) {
            $prefix .= rtrim($g['prefix'] ?? '', '/');
            $mw = array_merge($mw, (array) ($g['middleware'] ?? []));
            $namePrefix .= $g['as'] ?? '';
        }
        $full = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        $this->routes[] = ['methods' => $methods, 'path' => $full, 'handler' => $handler, 'middleware' => $mw, 'name' => null, 'namePrefix' => $namePrefix];
        $this->last = array_key_last($this->routes);
        return $this;
    }

    public function name(string $name): self
    {
        $r = &$this->routes[$this->last];
        $r['name'] = $r['namePrefix'] . $name;
        $this->named[$r['name']] = $r['path'];
        return $this;
    }

    public function middleware(string ...$mw): self
    {
        $this->routes[$this->last]['middleware'] = array_merge($this->routes[$this->last]['middleware'], $mw);
        return $this;
    }

    public function group(array $attrs, callable $fn): void
    {
        $this->groupStack[] = $attrs;
        $fn($this);
        array_pop($this->groupStack);
    }

    /** अनुरोध से मिलता रूट: [route, params] या 404/405 */
    public function match(string $method, string $path): array
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            $regex = $this->compile($route['path']);
            if (!preg_match($regex, $path, $m)) {
                continue;
            }
            if (!in_array($method, $route['methods'], true)) {
                $allowed = array_merge($allowed, $route['methods']);
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return [$route, $params];
        }
        throw new HttpException($allowed ? 405 : 404);
    }

    /** {name} या {name:regex}; regex में {4} / {2,8} जैसे quantifier भी चलते हैं */
    private const PARAM = '/\{(\w+)(?::((?:[^{}]|\{[0-9,]+\})+))?\}/';

    private function compile(string $path): string
    {
        $regex = preg_replace_callback(self::PARAM, static fn($m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')', $path);
        return '#^' . $regex . '$#u';
    }

    /** नामित रूट का URL: url('admin.users.edit', ['id' => 5]) */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new \InvalidArgumentException("रूट नहीं मिला: $name");
        }
        $path = preg_replace_callback(self::PARAM, static function ($m) use (&$params, $name) {
            if (!array_key_exists($m[1], $params)) {
                throw new \InvalidArgumentException("रूट $name के लिए {$m[1]} चाहिए");
            }
            $v = $params[$m[1]];
            unset($params[$m[1]]);
            return rawurlencode((string) $v);
        }, $this->named[$name]);
        return $path . ($params ? '?' . http_build_query($params) : '');
    }

    public function has(string $name): bool
    {
        return isset($this->named[$name]);
    }
}
