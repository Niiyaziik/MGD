<?php
namespace App\Controller;

use App\Service\AdminAuthService;
use DomainException;

class AdminController extends BaseController
{
    public function __construct(private AdminService $auth) {}

    public function login(): void
    {
        $login    = $_POST['login']    ?? '';
        $password = $_POST['password'] ?? '';

        $login    = trim($login);
        $password = trim($password);

        if ($login === '' || $password === '') {
            http_response_code(400);
            echo 'Логин и пароль обязательны';
            return;
        }

        $adminId = $this->auth->checkCredentials($login, $password);

        if ($adminId === null) {
            http_response_code(401);
            echo 'Неверный логин или пароль';
            return;
        }

        $_SESSION['admin_id'] = $adminId;

        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }

    public function logout(): void
    {
        unset($_SESSION['admin_id']);
        header('Location: /');
    }
}
