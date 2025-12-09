<?php
declare(strict_types=1);

namespace App\Repository\Contract;

interface PhoneCodeRepositoryInterface
{
    /**
     * Создать новый код для телефона.
     * Возвращает id вставленной записи.
     */
    public function createCode(string $phone, string $code, ?string $ip = null): int;

    /**
     * Найти действительный (неиспользованный и непросроченный) код.
     *
     * @return array|null
     */
    public function findValid(string $phone, string $code, int $ttlSeconds = 600): ?array;

    /**
     * Пометить код использованным.
     */
    public function markUsed(int $id): void;
}
