<?php
declare(strict_types=1);

namespace App\Core;

/**
 * एप्लिकेशन: सेवाएँ (services) तैयार करता है, रूट चलाता है, middleware की कतार बनाता है।
 */
final class App
{
    private static ?App $instance = null;
    private array $services = [];
    private array $factories = [];

    public function __construct(private string $basePath)
    {
        self::$instance = $this;
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new \RuntimeException('App शुरू नहीं हुआ');
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    /** सेवा रजिस्टर करें (पहली बार माँगने पर बनेगी) */
    public function bind(string $name, callable $factory): void
    {
        $this->factories[$name] = $factory;
        unset($this->services[$name]);
    }

    public function set(string $name, mixed $service): void
    {
        $this->services[$name] = $service;
    }

    public function get(string $name): mixed
    {
        if (!array_key_exists($name, $this->services)) {
            if (!isset($this->factories[$name])) {
                throw new \RuntimeException("सेवा नहीं मिली: $name");
            }
            $this->services[$name] = ($this->factories[$name])($this);
        }
        return $this->services[$name];
    }

    public static function isInstalled(string $basePath): bool
    {
        return is_file($basePath . '/config/env.php') && is_file($basePath . '/storage/installed.lock');
    }

    public function run(): void
    {
        // इंस्टॉल नहीं हुआ: इंस्टॉलर पर भेजें
        if (!self::isInstalled($this->basePath)) {
            $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
            header('Location: ' . $base . '/install/');
            return;
        }

        $this->boot();
        $request = $this->get('request');

        try {
            $response = $this->dispatch($request);
        } catch (ValidationException $e) {
            $response = $request->wantsJson()
                ? Response::json(['ok' => false, 'message' => 'कुछ जानकारी सही नहीं है।', 'errors' => $e->errors], 422)
                : Response::redirect(back_url())->withErrors($e->errors)->withInput($e->input);
        } catch (HttpException $e) {
            $response = $this->get('errors')->render($e->status, $e);
        }
        $this->securityHeaders($response);
        $response->send();
    }

    /** सभी सेवाएँ तैयार करें */
    public function boot(): void
    {
        $base = $this->basePath;
        $config = new Config($base . '/config');
        $this->set('config', $config);
        $debug = (bool) $config->get('app.debug', false);

        $logger = new Logger($base . '/storage/logs');
        $this->set('logger', $logger);
        $errors = new ErrorHandler($logger, $debug, $base . '/app/Views');
        $errors->register();
        $this->set('errors', $errors);

        date_default_timezone_set((string) $config->get('app.timezone', 'Asia/Kolkata'));
        mb_internal_encoding('UTF-8');

        $db = new Database($config->get('database'));
        $db->setTimezone(date('P'));
        $this->set('db', $db);

        $this->set('cache', new Cache($base . '/storage/cache', (bool) $config->get('app.cache', true)));

        // सेटिंग से टाइमज़ोन (एडमिन बदल सकता है)
        $tz = setting('timezone');
        if ($tz && in_array($tz, \DateTimeZone::listIdentifiers(), true) && $tz !== date_default_timezone_get()) {
            date_default_timezone_set($tz);
            $db->setTimezone(date('P'));
        }

        $request = Request::capture($this->detectBasePath());
        $this->set('request', $request);

        $session = new Session();
        $session->start($base . '/storage/sessions', $request->isSecure(), (string) $config->get('app.session_name', 'nsess'));
        $this->set('session', $session);

        $auth = new Auth(
            $db,
            $session,
            max(5, (int) setting('session_timeout', 120)),
            max(3, (int) setting('login_max_attempts', 5)),
            max(1, (int) setting('login_lockout_minutes', 15)),
        );
        $this->set('auth', $auth);
        $this->set('gate', new Gate($db, $auth));

        $view = new View($base . '/app/Views');
        $this->set('view', $view);

        $this->bind('mailer', fn() => new Mailer($logger, (string) setting('mail_from_email', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), (string) setting('site_name', 'News')));

        $router = new Router();
        $this->set('router', $router);
        foreach (['web', 'admin'] as $file) {
            (static function (Router $router, string $path): void {
                require $path;
            })($router, $base . '/routes/' . $file . '.php');
        }
    }

    /** सब-फ़ोल्डर इंस्टॉल: /news/index.php → /news */
    private function detectBasePath(): string
    {
        $path = parse_url((string) config('app.url'), PHP_URL_PATH) ?: '';
        return rtrim($path, '/');
    }

    private function dispatch(Request $request): Response
    {
        [$route, $params] = $this->get('router')->match($request->method(), $request->path());
        $request->setRouteParams($params);

        $aliases = (array) config('app.middleware', []);
        $stack = array_merge((array) config('app.global_middleware', []), $route['middleware']);

        $core = function (Request $req) use ($route, $params): Response {
            $handler = $route['handler'];
            if ($handler instanceof \Closure) {
                $result = $handler($req, ...array_values($params));
            } else {
                [$class, $method] = $handler;
                $controller = new $class();
                $result = $controller->$method($req, ...array_values($this->castParams($params)));
            }
            return $result instanceof Response ? $result : new Response((string) $result);
        };

        // middleware को उल्टे क्रम में लपेटें: पहला middleware सबसे पहले चले
        $next = $core;
        foreach (array_reverse($stack) as $entry) {
            [$alias, $args] = array_pad(explode(':', $entry, 2), 2, '');
            $class = $aliases[$alias] ?? throw new \RuntimeException("Middleware नहीं मिला: $alias");
            $params2 = $args === '' ? [] : explode(',', $args);
            $next = static fn(Request $req) => (new $class())->handle($req, $next, ...$params2);
        }
        return $next($request);
    }

    private function castParams(array $params): array
    {
        return array_map(static fn($v) => ctype_digit((string) $v) ? (int) $v : $v, $params);
    }

    private function securityHeaders(Response $r): void
    {
        $r->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}
