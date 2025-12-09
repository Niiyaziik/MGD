<?php
declare(strict_types=1);

namespace App\Repository\Contract;

interface AdminRepositoryInterface
{
    /**
     * Найти администратора по логину.
     * Возвращает ассоциативный массив (строка из БД) или null.
     */
    public function findByLogin(string $login): ?array;

    /**
     * Найти администратора по id.
     */
    public function find(int $id): ?array;
}
