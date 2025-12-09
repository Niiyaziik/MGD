<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Service\AdminService;

class AdminMiddleware
{
    public function __construct(
        private AdminService $authService
    ) {
    }

    public function handle(): void
    {
        if (!$this->authService->check()) {
            header('Location: /');
            exit;
        }
    }
}
