<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Service\AdminAuthService;

class AdminMiddleware
{
    private AdminAuthService $auth;

    public function __construct(AdminAuthService $auth)
    {
        $this->auth = $auth;
    }

    public function __invoke(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!$this->auth->check()) {
            http_response_code(403);

            echo 'Доступ запрещен. Требуется вход администратора.';
            return false;
        }

        return true;
    }
}
