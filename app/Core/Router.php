<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Small regex router. Supports {param} placeholders and per-route middleware
 * metadata. Middleware is collected and executed by the dispatcher.
 */
final class Router
{
    /** @var array<string, array<int, array{pattern:string, handler:mixed, middleware:array, name:?string}>> */
    private array $routes = [];

    public function get(string $path, mixed $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('GET', $path, $handler, $middleware, $name);
    }

    public function post(string $path, mixed $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('POST', $path, $handler, $middleware, $name);
    }

    public function put(string $path, mixed $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('PUT', $path, $handler, $middleware, $name);
    }

    public function delete(string $path, mixed $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('DELETE', $path, $handler, $middleware, $name);
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $uri = $this->normalize($uri);

        $allowedForPath = [];

        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            $params = array_filter(
                $matches,
                static fn ($key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            $this->runMiddleware($route['middleware']);
            $this->call($route['handler'], $params);

            return;
        }

        foreach (array_keys($this->routes) as $verb) {
            foreach ($this->routes[$verb] as $route) {
                if (preg_match($route['pattern'], $uri)) {
                    $allowedForPath[] = $verb;
                }
            }
        }

        if ($allowedForPath !== []) {
            header('Allow: ' . implode(', ', array_unique($allowedForPath)));
            Response::json(['error' => 'Method Not Allowed'], 405);
            exit;
        }

        Response::view('errors/404', ['title' => 'Page Not Found'], 404);
    }

    private function add(string $method, string $path, mixed $handler, array $middleware, ?string $name): void
    {
        $this->routes[$method][] = [
            'pattern'    => $this->compile($path),
            'handler'    => $handler,
            'middleware' => $middleware,
            'name'       => $name,
        ];
    }

    /**
     * Convert /users/{id} into an anchored regex with a named capture group.
     */
    private function compile(string $path): string
    {
        $normalized = $this->normalize($path);

        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $normalized
        );

        return '#^' . $pattern . '$#';
    }

    private function normalize(string $path): string
    {
        $path = trim($path);
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $entry) {
            if (is_string($entry)) {
                $entry = [$entry];
            }

            if (!is_array($entry) || !isset($entry[0]) || !is_string($entry[0]) || !class_exists($entry[0])) {
                continue;
            }

            $class = array_shift($entry);

            if ($entry !== [] && $entry !== [null]) {
                $instance = new $class(...array_values($entry));
            } else {
                $instance = new $class();
            }

            if (method_exists($instance, 'handle')) {
                $instance->handle();
            }
        }
    }

    private function call(mixed $handler, array $params): void
    {
        if ($handler instanceof Closure) {
            $handler(...array_values($params));
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (!class_exists($class)) {
                throw new RuntimeException("Controller {$class} not found.");
            }

            $controller = new $class();

            if (!method_exists($controller, $method)) {
                throw new RuntimeException("Method {$class}::{$method} not found.");
            }

            $controller->{$method}(...array_values($params));

            return;
        }

        if (is_callable($handler)) {
            $handler(...array_values($params));
            return;
        }

        throw new RuntimeException('Invalid route handler.');
    }
}
