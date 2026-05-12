<?php
declare(strict_types=1);

class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            $this->notFound();
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $action] = $handler;
            $controller = new $class();
            $controller->$action();
            return;
        }

        if (is_callable($handler)) {
            $handler();
            return;
        }

        $this->notFound();
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => '404 — Not Found']);
    }
}
