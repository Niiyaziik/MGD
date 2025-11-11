<?php
namespace App\Controller;

use App\Service\AdminAuthService;
use DomainException;

class AdminController extends BaseController
{
    public function __construct(private AdminAuthService $auth) {}

    // POST /api/admin/login
    public function login(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        $login = trim((string)($data['login'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($login === '' || $password === '') {
            $this->json(['ok' => false, 'error' => 'Логин и пароль обязательны'], 422);
            return;
        }

        try {
            $adminId = $this->auth->login($login, $password);
            // тут можно выдать токен/сессию
            $this->json(['ok' => true, 'admin_id' => $adminId]);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 401);
        }
    }
}
