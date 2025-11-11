<?php

namespace App\Service;

use App\Repository\Contract\AdminRepositoryInterface;
use DomainException;

class AdminAuthService
{
    public function __construct(
        private AdminRepositoryInterface $admins
    ) {}

    /**
     * Проверка логина/пароля администратора.
     * Возвращает admin-id или бросает исключение.
     */
    public function login(string $login, string $password): int
    {
        $admin = $this->admins->findByLogin($login);
        if (!$admin) {
            throw new DomainException('Неверные логин или пароль');
        }

        if (!password_verify($password, $admin->password_hash)) {
            throw new DomainException('Неверные логин или пароль');
        }

        return (int)$admin->id;
    }

    public function makePasswordHash(string $raw): string
    {
        return password_hash($raw, PASSWORD_DEFAULT);
    }
}
