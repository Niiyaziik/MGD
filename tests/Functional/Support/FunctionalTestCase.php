<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fakes.php';

abstract class FunctionalTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SESSION = [];
        http_response_code(200);

        parent::tearDown();
    }

    /**
     * @return array{status:int, body:string, json:mixed}
     */
    protected function capture(callable $action): array
    {
        ob_start();
        $action();
        $body = (string)ob_get_clean();

        return [
            'status' => http_response_code(),
            'body' => $body,
            'json' => $body === '' ? null : json_decode($body, true),
        ];
    }

    protected function useRequest(string $method, string $uri = '/', array $query = []): void
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $uri;
        $_GET = $query;
        http_response_code(200);
    }
}
