<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\Contract\AdminRepositoryInterface;

class AdminService
{
    public function __construct(
        private AdminRepositoryInterface $admins
    ) {}

    /**
     * Проверка: админ уже залогинен?
     */
    public function check(): bool
    {
        return isset($_SESSION['admin_id']) && is_numeric($_SESSION['admin_id']);
    }

    /**
     * Попытка логина администратора по логину и паролю.
     *
     * Возвращает массив:
     *  [
     *      'ok'    => bool,
     *      'error' => string|null,
     *      'admin' => array|null
     *  ]
     */
    public function attemptLogin(string $login, string $password): array
    {
        $login = trim($login);

        if ($login === '' || $password === '') {
            return [
                'ok'    => false,
                'error' => 'Логин и пароль обязательны',
                'admin' => null,
            ];
        }

        // Ищем админа по логину
        // Нужно, чтобы в AdminRepositoryInterface был метод findByLogin(string $login): ?array
        $admin = $this->admins->findByLogin($login);

        if (!$admin || !empty($admin['deleted_at'])) {
            // Специально не уточняем, что именно не так — логин или пароль
            return [
                'ok'    => false,
                'error' => 'Неверный логин или пароль',
                'admin' => null,
            ];
        }

        // Проверяем хеш пароля
        if (!password_verify($password, $admin['password_hash'])) {
            return [
                'ok'    => false,
                'error' => 'Неверный логин или пароль',
                'admin' => null,
            ];
        }

        // Всё ок — ставим админскую сессию
        $_SESSION['admin_id'] = (int)$admin['id'];
        session_regenerate_id(true);

        return [
            'ok'    => true,
            'error' => null,
            'admin' => $admin,
        ];
    }

    /**
     * Выход администратора.
     */
    public function logout(): void
    {
        unset($_SESSION['admin_id']);
        session_regenerate_id(true);
    }

    /**
     * Текущий админ (если нужен где-то ещё).
     */
    public function currentAdmin(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        $id = (int)$_SESSION['admin_id'];

        // Тут нужен метод find(int $id): ?array в AdminRepositoryInterface
        return $this->admins->find($id);
    }
}
