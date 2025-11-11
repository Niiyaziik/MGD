<?php
namespace App\Controller;

use App\Model\District;
use App\Repository\Contract\DistrictRepositoryInterface;
use DomainException;
use Throwable;

class DistrictController extends BaseController
{
    public function __construct(private DistrictRepositoryInterface $districts) {}

    // GET /api/districts
    public function index(): void
    {
        $this->requireMethod('GET');

        try {
            // Если у репозитория есть пагинация — подставь свои методы/параметры
            $items = $this->districts->all(); // верни только не удалённые (deleted_at IS NULL)
            $this->json(['ok' => true, 'data' => $items]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    // GET /api/districts/{id}
    public function show(int $id): void
    {
        $this->requireMethod('GET');

        try {
            $district = $this->districts->findById($id);
            if (!$district || $district->deleted_at !== null) {
                $this->json(['ok' => false, 'error' => 'Округ не найден'], 404);
                return;
            }
            $this->json(['ok' => true, 'data' => $district]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    // POST /api/districts
    public function store(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        // Валидация (минимальная доменная)
        $district = trim((string)($data['district'] ?? ''));
        $street   = trim((string)($data['street'] ?? ''));
        $house    = trim((string)($data['house'] ?? ''));

        if ($district === '' || $street === '' || $house === '') {
            $this->json(['ok' => false, 'error' => 'Поля district, street, house обязательны'], 422);
            return;
        }

        try {
            $entity = new District();
            $entity->district = $district;
            $entity->street   = $street;
            $entity->house    = $house;

            $created = $this->districts->create($entity);
            $this->json(['ok' => true, 'data' => $created], 201);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    // PATCH /api/districts/{id}
    public function update(int $id): void
    {
        $this->requireMethod('PATCH');
        $patch = $this->getJsonBody();

        try {
            $entity = $this->districts->findById($id);
            if (!$entity || $entity->deleted_at !== null) {
                $this->json(['ok' => false, 'error' => 'Округ не найден'], 404);
                return;
            }

            if (array_key_exists('district', $patch)) {
                $val = trim((string)$patch['district']);
                if ($val === '') { $this->json(['ok'=>false,'error'=>'district не может быть пустым'],422); return; }
                $entity->district = $val;
            }
            if (array_key_exists('street', $patch)) {
                $val = trim((string)$patch['street']);
                if ($val === '') { $this->json(['ok'=>false,'error'=>'street не может быть пустым'],422); return; }
                $entity->street = $val;
            }
            if (array_key_exists('house', $patch)) {
                $val = trim((string)$patch['house']);
                if ($val === '') { $this->json(['ok'=>false,'error'=>'house не может быть пустым'],422); return; }
                $entity->house = $val;
            }

            $updated = $this->districts->update($entity);
            $this->json(['ok' => true, 'data' => $updated]);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    // DELETE /api/districts/{id}
    public function destroy(int $id): void
    {
        $this->requireMethod('DELETE');

        try {
            $existing = $this->districts->findById($id);
            if (!$existing || $existing->deleted_at !== null) {
                // Идемпотентность: удалённый/не существующий считаем успехом
                $this->json(['ok' => true]);
                return;
            }

            $this->districts->softDelete($id); // устанавливает deleted_at = NOW()
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }
}
