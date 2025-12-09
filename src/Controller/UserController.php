<?php
namespace App\Controller;

use App\Repository\Contract\UserRepositoryInterface;
use DomainException;
use Throwable;

class UserController extends BaseController
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function store(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        try {
            $user = $this->users->register($data);
            $this->json(['ok' => true, 'data' => $user], 201);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    public function update(int $id): void
    {
        $this->requireMethod('PATCH');
        $patch = $this->getJsonBody();

        try {
            $user = $this->users->update($id, $patch);
            $this->json(['ok' => true, 'data' => $user]);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    public function adminIndex(): void
    {
        $this->requireMethod('GET');

        $format  = $_GET['format']  ?? null;
        $deleted = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;

        if ($format === 'json') {
            try {
                $users = $this->users->getAdminUsers($deleted);
                $this->json($users);
            } catch (Throwable $e) {
                $this->json(['ok' => false, 'error' => 'Ошибка загрузки пользователей'], 500);
            }
            return;
        }

        header_remove('Content-Type');
        header('Content-Type: text/html; charset=utf-8');
        include __DIR__ . '/../../public/users-db.php';
    }

    public function adminDeleted(): void
    {
        $this->requireMethod('GET');

        try {
            // 1 — только удалённые (deleted_at IS NOT NULL)
            $list = $this->users->getAdminUsers(1);
            $this->json($list);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Ошибка загрузки удалённых пользователей'], 500);
        }
    }

    /**
     * PUT /users/admin/update
     * Обновление пользователя из админки.
     * Ожидает JSON вида:
     * {
     *   "id": 123,
     *   "surname": "...",
     *   "name": "...",
     *   "patronymic": "...",
     *   "phone": "...",
     *   "link_vk": "...",
     *   "district_id": 1,
     *   "street_id": 10,
     *   "house_id": 5,
     *   "auth_method": "phone/vk/..."
     * }
     */
    public function adminUpdate(): void
    {
        $this->requireMethod('PUT');
        $data = $this->getJsonBody();

        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'Не передан id пользователя'], 422);
            return;
        }

        try {
            $this->users->updateAdminUser($id, $data);
            $user = $this->users->find($id);

            $this->json(['ok' => true, 'data' => $user]);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Ошибка при обновлении пользователя'], 500);
        }
    }

    /**
     * DELETE /users/admin/delete
     * Мягкое удаление пользователя (запись deleted_at = NOW()).
     * Ожидает:
     *  - либо JSON: { "id": 123 }
     *  - либо query: /users/admin/delete?id=123
     */
    public function adminDelete(): void
    {
        $this->requireMethod('DELETE');

        $body = [];
        try {
            $body = $this->getJsonBody();
        } catch (\Throwable $e) {
            // если нет JSON — ничего страшного
        }

        $id = (int)($body['id'] ?? ($_GET['id'] ?? 0));

        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'Не передан id пользователя'], 422);
            return;
        }

        try {
            $this->users->delete($id);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Не удалось удалить пользователя'], 500);
        }
    }
}
