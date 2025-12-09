<?php
declare(strict_types=1);

namespace App\App;

final class RouteDefinition
{
    private array $route;

    public function __construct(array &$route)
    {
        $this->route =& $route;
    }

    public function middleware(string ...$middlewares): self
    {
        if (!isset($this->route['middleware']) || !is_array($this->route['middleware'])) {
            $this->route['middleware'] = [];
        }

        foreach ($middlewares as $mw) {
            $this->route['middleware'][] = $mw;
        }

        return $this;
    }
}

final class Router
{
    private array $routes = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'DELETE' => [],
    ];

    public function __construct(
        private readonly ?object $container = null
    ) {}

    public function get(string $pattern, callable|array $handler): RouteDefinition
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): RouteDefinition
    {
        return $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable|array $handler): RouteDefinition
    {
        return $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable|array $handler): RouteDefinition
    {
        return $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable|array $handler): RouteDefinition
    {
        $regex = '#^' . preg_replace(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            '(?P<$1>[^/]+)',
            $pattern
        ) . '$#';

        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        $route = [
            'regex'      => $regex,
            'handler'    => $handler,
            'middleware' => [],
        ];

        $this->routes[$method][] = $route;
        $index = \count($this->routes[$method]) - 1;

        return new RouteDefinition($this->routes[$method][$index]);
    }

    public function dispatch(string $method, string $path): void
    {
        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $route) {
            $regex   = $route['regex'];
            $handler = $route['handler'];
            $mwList  = $route['middleware'] ?? [];

            if (\preg_match($regex, $path, $m)) {
                $params = [];
                foreach ($m as $k => $v) {
                    if (\is_string($k)) {
                        $params[$k] = $v;
                    }
                }

                $this->runMiddlewares($mwList, $params);
                $this->invoke($handler, $params);
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    private function runMiddlewares(array $middlewares, array $params): void
    {
        foreach ($middlewares as $mwClass) {
            $mw = $this->container?->get($mwClass) ?? new $mwClass();

            if (!method_exists($mw, 'handle')) {
                continue;
            }

            $mw->handle($params);
        }
    }

    private function invoke(callable|array $handler, array $params): void
    {
        if (\is_array($handler)) {
            [$class, $method] = $handler;
            $controller = $this->container?->get($class) ?? new $class();
            $controller->$method(...$params);
        } else {
            $handler(...$params);
        }
    }
}
