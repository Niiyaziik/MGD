<?php

namespace App\Service;

use App\Repository\Contract\AdminRepositoryInterface;
use DomainException;

class AdminService
{
    public function __construct(
        private AdminRepositoryInterface $admins
    ) {}

    public function check(string $login, string $password): ?int
    {
        $admin = $this->admins->findByLogin($login);
        if (!$admin) {
            return null;
        }

        if (!password_verify($password, $admin['password_hash'])) {
            return null;
        }

        return (int)$admin['id'];
    }
}
