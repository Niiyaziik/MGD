<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\Contract\UserRepositoryInterface;
use App\Repository\Contract\DistrictRepositoryInterface;
use DomainException;
use PDO;
use RuntimeException;

class UserService
{
    public function __construct(
        private PDO $pdo,
        private UserRepositoryInterface $users,
        private DistrictRepositoryInterface $districts,
    ) {}

    /**
     * Регистрирует пользователя и возвращает данные созданной записи.
     * Метод работает через интерфейс UserRepositoryInterface: create(array): int.
     */
    public function register(array $data): array
    {
        $surname = trim((string)($data['surname'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $vkId = trim((string)($data['vk_id'] ?? ''));

        if ($surname === '' || $name === '') {
            throw new DomainException('Имя и фамилия обязательны');
        }
        if ($phone === '' && $vkId === '') {
            throw new DomainException('Нужен хотя бы один идентификатор: телефон или VK');
        }
        if (!isset($data['district_id'])) {
            throw new DomainException('Не указан округ');
        }

        $districtId = (int)$data['district_id'];
        if (!$this->districtExists($districtId)) {
            throw new DomainException('Округ не найден');
        }

        if ($phone !== '' && $this->users->findByPhone($phone)) {
            throw new DomainException('Телефон уже используется');
        }
        if ($vkId !== '' && $this->users->findByVkId($vkId)) {
            throw new DomainException('VK ID уже используется');
        }

        $clean = [
            'surname'     => $surname,
            'name'        => $name,
            'patronymic'  => trim((string)($data['patronymic'] ?? '')) ?: null,
            'phone'       => $phone,
            'vk_id'       => $vkId,
            'district_id' => $districtId,
            'auth_method' => $data['auth_method'] ?? 'Телефон',
        ];

        try {
            $this->pdo->beginTransaction();
            $id = $this->users->create($clean);
            $this->pdo->commit();

            $created = $this->safeFindUser($id);
            return $created ?: (['id' => $id] + $clean);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new RuntimeException('Не удалось создать пользователя', 0, $e);
        }
    }

    /**
     * Обновляет пользователя и возвращает актуальные данные.
     */
    public function update(int $id, array $patch): array
    {
        $user = $this->safeFindUser($id);
        if (!$user) {
            throw new DomainException('Пользователь не найден');
        }

        $clean = [];

        if (array_key_exists('district_id', $patch)) {
            $districtId = (int)$patch['district_id'];
            if ($districtId && !$this->districtExists($districtId)) {
                throw new DomainException('Округ не найден');
            }
            $clean['district_id'] = $districtId;
        }

        if (array_key_exists('phone', $patch)) {
            $phone = trim((string)$patch['phone']);
            if ($phone !== (string)($user['phone'] ?? '')) {
                $existing = $phone !== '' ? $this->users->findByPhone($phone) : null;
                if ($existing && (int)($existing['id'] ?? 0) !== $id) {
                    throw new DomainException('Телефон уже используется');
                }
            }
            $clean['phone'] = $phone;
        }

        if (array_key_exists('vk_id', $patch)) {
            $vkId = trim((string)$patch['vk_id']);
            if ($vkId !== (string)($user['vk_id'] ?? '')) {
                $existing = $vkId !== '' ? $this->users->findByVkId($vkId) : null;
                if ($existing && (int)($existing['id'] ?? 0) !== $id) {
                    throw new DomainException('VK ID уже используется');
                }
            }
            $clean['vk_id'] = $vkId;
        }

        foreach (['surname', 'name', 'patronymic', 'auth_method'] as $field) {
            if (array_key_exists($field, $patch)) {
                $value = is_string($patch[$field]) ? trim($patch[$field]) : $patch[$field];
                $clean[$field] = $value === '' ? null : $value;
            }
        }

        if ($clean !== []) {
            $this->users->update($id, $clean);
        }

        $fresh = $this->safeFindUser($id);
        return $fresh ?: array_merge($user, $clean);
    }

    private function safeFindUser(int $id): array
    {
        try {
            return $this->users->find($id);
        } catch (\Throwable) {
            return [];
        }
    }

    private function districtExists(int $districtId): bool
    {
        try {
            return (bool)$this->districts->find($districtId);
        } catch (\Throwable) {
            return false;
        }
    }

}
