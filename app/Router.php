<?php
declare(strict_types=1);

class Router
{
    /** Exact-match routes: ['GET']['/path'] = handler */
    private array $routes = [];

    /**
     * Parameterised routes: ['GET'][] = [regex, handler, paramNames[]]
     * Registered in order; first match wins.
     */
    private array $patternRoutes = [];

    // ── Route registration ────────────────────────────────────────────────────

    public function get(string $path, array|callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, array|callable $handler): void
    {
        if (!str_contains($path, '{')) {
            $this->routes[$method][$path] = $handler;
            return;
        }

        // Extract parameter names and build a regex
        preg_match_all('/\{(\w+)\}/', $path, $m);
        $paramNames = $m[1];

        // Build pattern segment by segment so literal parts are properly escaped
        $segments = explode('/', $path);
        $regexParts = array_map(static function (string $seg): string {
            if (preg_match('/^\{(\w+)\}$/', $seg)) {
                return '([^/]+)';
            }
            return preg_quote($seg, '#');
        }, $segments);

        $pattern = '#^' . implode('/', $regexParts) . '$#';

        $this->patternRoutes[$method][] = [$pattern, $handler, $paramNames];
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        // 1. Exact match (O(1) hash lookup)
        if (isset($this->routes[$method][$path])) {
            $this->call($this->routes[$method][$path], []);
            return;
        }

        // 2. Pattern match (checked in registration order)
        foreach ($this->patternRoutes[$method] ?? [] as [$pattern, $handler, $paramNames]) {
            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches); // drop the full-match capture
                $params = array_combine($paramNames, $matches);
                $this->call($handler, $params);
                return;
            }
        }

        $this->notFound();
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Invoke a handler, always passing the params array.
     * All controller action methods must declare `array $params = []`.
     */
    private function call(array|callable $handler, array $params): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $action] = $handler;
            $controller = new $class();
            $controller->$action($params);
            return;
        }

        if (is_callable($handler)) {
            $handler($params);
        }
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => '404 — Not Found']);
    }
}
