<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminService;

class AdminController extends BaseController
{
    public function __construct(
        private AdminService $adminService
    ) {
    }

    /**
     * Стартовая страница админки.
     * Можно вызывать, например, по /candidates/admin или /admin
     */
    public function index(): void
    {
        $this->requireMethod('GET');

        if (!$this->adminService->check()) {
            header('Location: /');
            return;
        }

        // Подставь своe представление
        $this->render('admin/dashboard', []);
    }

    /**
     * POST /admin/login
     * Логин админа по логину и паролю.
     *
     * ОЖИДАЕМ JSON:
     * {
     *   "login": "admin",
     *   "password": "******"
     * }
     */
    public function loginByPassword(): void
    {
        $this->requireMethod('POST');

        $data = $this->getJsonBody();

        $login    = (string)($data['login']    ?? '');
        $password = (string)($data['password'] ?? '');

        $result = $this->adminService->attemptLogin($login, $password);

        if (!$result['ok']) {
            $this->json([
                'ok'    => false,
                'error' => $result['error'] ?? 'Ошибка авторизации',
            ], 422);
            return;
        }

        // Можно вернуть какую-то информацию об админе, если нужно
        $this->json(['ok' => true]);
    }

    /**
     * POST /admin/logout
     */
public function logout(): void
{
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}
}
