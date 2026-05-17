<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\App\Router;
use App\Middleware\AdminMiddleware;
use Tests\Functional\Support\ArrayContainer;
use Tests\Functional\Support\FakeAdminService;
use Tests\Functional\Support\FunctionalTestCase;

final class RouterFunctionalTest extends FunctionalTestCase
{
    public function testDispatchCallsMatchingGetRoute(): void
    {
        $router = new Router();
        $router->get('/health', static function (): void {
            echo 'ok';
        });

        $this->useRequest('GET', '/health');
        $response = $this->capture(fn () => $router->dispatch('GET', '/health'));

        $this->assertSame(200, $response['status']);
        $this->assertSame('ok', $response['body']);
    }

    public function testDispatchPassesRouteParametersToHandler(): void
    {
        $router = new Router();
        $router->get('/candidates/{id}/edit', static function (string $id): void {
            echo 'candidate:' . $id;
        });

        $this->useRequest('GET', '/candidates/42/edit');
        $response = $this->capture(fn () => $router->dispatch('GET', '/candidates/42/edit'));

        $this->assertSame('candidate:42', $response['body']);
    }

    public function testDispatchReturns404ForUnknownRoute(): void
    {
        $router = new Router();
        $router->get('/known', static function (): void {
            echo 'known';
        });

        $this->useRequest('GET', '/missing');
        $response = $this->capture(fn () => $router->dispatch('GET', '/missing'));

        $this->assertSame(404, $response['status']);
        $this->assertSame('404 Not Found', $response['body']);
    }

    public function testMiddlewareAllowsRouteWhenAdminIsLoggedIn(): void
    {
        $container = new ArrayContainer([
            AdminMiddleware::class => new AdminMiddleware(new FakeAdminService(loggedIn: true)),
        ]);
        $router = new Router($container);
        $router->get('/admin-only', static function (): void {
            echo 'allowed';
        })->middleware(AdminMiddleware::class);

        $this->useRequest('GET', '/admin-only');
        $response = $this->capture(fn () => $router->dispatch('GET', '/admin-only'));

        $this->assertSame(200, $response['status']);
        $this->assertSame('allowed', $response['body']);
    }
}
