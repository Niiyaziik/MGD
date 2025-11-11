<?php
namespace App\Controller;

abstract class BaseController
{
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function param(array $source, string $key, mixed $default = null): mixed
    {
        return $source[$key] ?? $default;
    }

    protected function requireMethod(string $expected): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($expected)) {
            $this->json(['ok' => false, 'error' => 'Method Not Allowed'], 405);
            exit;
        }
    }
}
