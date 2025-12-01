<?php

namespace App\Service;

use App\Repository\Contract\UserRepositoryInterface;
use App\Repository\Contract\DistrictRepositoryInterface;
use App\Model\User;
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

    public function register(array $data): User
    {
        if (empty($data['surname']) || empty($data['name'])) {
            throw new DomainException('Имя и фамилия обязательны');
        }
        if (empty($data['phone']) && empty($data['vk_id'])) {
            throw new DomainException('Нужен хотя бы один идентификатор: телефон или VK');
        }
        if (!isset($data['district_id'])) {
            throw new DomainException('Не указан округ');
        }
        if (!$this->districts->findById((int)$data['district_id'])) {
            throw new DomainException('Округ не найден');
        }

        if (!empty($data['phone']) && $this->users->findByPhone($data['phone'])) {
            throw new DomainException('Телефон уже используется');
        }
        if (!empty($data['vk_id']) && $this->users->findByVkId($data['vk_id'])) {
            throw new DomainException('VK ID уже используется');
        }

        $user = new User();
        $user->surname = $data['surname'];
        $user->name = $data['name'];
        $user->patronymic = $data['patronymic'] ?? null;
        $user->phone = $data['phone'] ?? '';
        $user->vk_id = $data['vk_id'] ?? '';
        $user->district_id = (int)$data['district_id'];
        $user->auth_method = $data['auth_method'] ?? 'Телефон';

        try {
            $this->pdo->beginTransaction();
            $created = $this->users->create($user);
            $this->pdo->commit();
            return $created;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new RuntimeException('Не удалось создать пользователя', 0, $e);
        }
    }

    public function update(int $id, array $patch): User
    {
        $user = $this->users->findById($id);
        if (!$user) throw new DomainException('Пользователь не найден');

        if (array_key_exists('district_id', $patch)) {
            $districtId = (int)$patch['district_id'];
            if ($districtId && !$this->districts->findById($districtId)) {
                throw new DomainException('Округ не найден');
            }
            $user->district_id = $districtId;
        }

        if (array_key_exists('phone', $patch) && $patch['phone'] !== $user->phone) {
            if ($patch['phone'] && $this->users->findByPhone($patch['phone'])) {
                throw new DomainException('Телефон уже используется');
            }
            $user->phone = (string)$patch['phone'];
        }

        if (array_key_exists('vk_id', $patch) && $patch['vk_id'] !== $user->vk_id) {
            if ($patch['vk_id'] && $this->users->findByVkId($patch['vk_id'])) {
                throw new DomainException('VK ID уже используется');
            }
            $user->vk_id = (string)$patch['vk_id'];
        }

        $user->surname = $patch['surname'] ?? $user->surname;
        $user->name    = $patch['name'] ?? $user->name;
        $user->patronymic = $patch['patronymic'] ?? $user->patronymic;
        $user->auth_method = $patch['auth_method'] ?? $user->auth_method;

        return $this->users->update($user);
    }
}
