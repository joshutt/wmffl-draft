<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Hand-rolled route table (no framework — see docs/modernization-spec.md §3
 * for why: ~15 endpoints total, a framework wouldn't reduce the code needed).
 * Supports {param} path segments, e.g. '/roster/{teamId}'.
 */
final class Router
{
    /** @var array<string, array<array{pattern:string, regex:string, handler:callable}>> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([^/]+)', $pattern) . '$#';

        $this->routes[$method][] = [
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim($path, '/');

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                ($route['handler'])(...$matches);

                return;
            }
        }

        Response::error('Not found', 404);
    }
}
