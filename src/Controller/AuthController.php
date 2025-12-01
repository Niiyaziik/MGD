<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\Contract\UserRepositoryInterface;
use App\Service\Auth;

class AuthController
{
    public function __construct(
        private UserRepositoryInterface $users
    ) {}

    public function loginByPhone(): void
    {
        $phone = $_POST['phone'] ?? '';
        $phone = trim($phone);

        if ($phone === '') {
            http_response_code(400);
            echo 'Телефон обязателен';
            return;
        }

        $normalized = preg_replace('/\D+/', '', $phone);

        $user = $this->users->findByPhone($normalized);

        if (!$user) {
            $userId = $this->users->create([
                'phone'       => $normalized,
                'auth_method' => 'Телефон',
            ]);
            $user = $this->users->find($userId);
        }

        $_SESSION['user_id'] = (int)$user['id'];

        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }

    public function vkRedirect(): void
    {
        echo 'Тут будет редирект на VK OAuth';
    }

    public function vkCallback(): void
    {

        echo 'VK callback – здесь логика получения профиля VK и создание/поиск user';
    }

    public function logoutUser(): void
    {
        unset($_SESSION['user_id']);
        header('Location: /');
    }
}
