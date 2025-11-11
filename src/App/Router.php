<?php
declare(strict_types=1);

namespace App\App;

final class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function __construct(
        private readonly ?object $container = null
    ) {}

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable|array $handler): void
    {
        // переводим /foo/{id} -> regex
        $regex = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[$method][] = [$regex, $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $routes = $this->routes[$method] ?? [];
        foreach ($routes as [$regex, $handler]) {
            if (preg_match($regex, $path, $m)) {
                $params = [];
                foreach ($m as $k => $v) {
                    if (is_string($k)) $params[$k] = $v;
                }
                $this->invoke($handler, $params);
                return;
            }
        }
        http_response_code(404);
        echo '404 Not Found';
    }

    private function invoke(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            // если есть контейнер — попросим его создать контроллер (иначе new)
            $controller = $this->container?->get($class) ?? new $class();
            $controller->$method(...$params);
        } else {
            $handler(...$params);
        }
    }
}
