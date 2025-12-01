<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\Contract\CandidateRepositoryInterface;
use DomainException;

final class CandidateService
{
    public function __construct(
        private CandidateRepositoryInterface $repo
    ) {}


    public function create(array $data): array
    {
        $clean = $this->validate($data, isUpdate: false);
        return $this->repo->create($clean);
    }


    public function update(int $id, array $patch): array
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new DomainException('Кандидат не найден');
        }

        $clean = $this->validate($patch, isUpdate: true);
        return $this->repo->update($id, $clean);
    }

    public function delete(int $id): void
    {
        $ok = $this->repo->delete($id);
        if (!$ok) {
            throw new DomainException('Кандидат не найден');
        }
    }


    private function validate(array $in, bool $isUpdate): array
    {
        $allowed = [
            'surname','name','patronymic','district_id',
            'email','phone','vk_id','photo',
        ];
        $data = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $in)) {
                $data[$k] = $in[$k];
            }
        }

        foreach (['surname','name','patronymic','email','phone','vk_id','photo'] as $k) {
            if (array_key_exists($k, $data) && is_string($data[$k])) {
                $data[$k] = trim($data[$k]);
                if ($data[$k] === '') {
                    $data[$k] = null;
                }
            }
        }

        if (!$isUpdate) {
            if (empty($data['surname'])) {
                throw new DomainException('Фамилия обязательна');
            }
            if (empty($data['name'])) {
                throw new DomainException('Имя обязательно');
            }
            if (!isset($data['district_id'])) {
                throw new DomainException('Округ обязателен');
            }
        }

        if (array_key_exists('district_id', $data)) {
            $data['district_id'] = (int)$data['district_id'];
            if ($data['district_id'] <= 0) {
                throw new DomainException('Некорректный округ');
            }
        }

        if (array_key_exists('email', $data) && $data['email'] !== null) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new DomainException('Некорректный email');
            }
        }

        if (array_key_exists('phone', $data) && $data['phone'] !== null) {
            $norm = preg_replace('~[^0-9+]~', '', $data['phone']);
            if ($norm !== null) {
                $norm = preg_replace('~(?!^)\+~', '', $norm);
            }
            $digits = preg_replace('~\D~', '', (string)$norm);
            if (strlen((string)$digits) < 7 || strlen((string)$digits) > 20) {
                throw new DomainException('Некорректный телефон');
            }
            $data['phone'] = $norm;
        }

        if (array_key_exists('vk_id', $data) && $data['vk_id'] !== null) {
            $data['vk_id'] = (string)$data['vk_id'];
            if (preg_match('~\s~', $data['vk_id'])) {
                throw new DomainException('vk_id не должен содержать пробелы');
            }
        }

        if (array_key_exists('photo', $data) && $data['photo'] !== null) {
            $p = $data['photo'];
            $isUrl = filter_var($p, FILTER_VALIDATE_URL) !== false;
            $isRel = str_st_starts_with($p, '/') || !preg_match('~^https?://~i', $p);
            if (!$isUrl && !$isRel) {
                throw new DomainException('Некорректный путь к фото');
            }
        }

        return $data;
    }
}


if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
