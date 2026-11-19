<?php
declare(strict_types=1);

namespace App;

/**
 * A tiny router. Patterns use {name} for one path segment and {name*} for the
 * rest of the path: '/e/{slug}', '/theme/{name}/{file*}'.
 */
final class Router
{
    /** @var array<int, array{string, string, callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace_callback(
            '/\{([a-z_]+)(\*?)\}/',
            static fn (array $m) => '(?P<' . $m[1] . '>' . ($m[2] === '*' ? '.+' : '[^/]+') . ')',
            $pattern
        );
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler];
    }

    /** Runs the first matching route. Returns false when nothing matched. */
    public function dispatch(string $method, string $path): bool
    {
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if ($routeMethod === $method && preg_match($regex, $path, $m)) {
                $handler(array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
                return true;
            }
        }
        return false;
    }
}
