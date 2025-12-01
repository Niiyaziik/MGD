<?php
namespace App\Controller;

use App\Service\UserService;
use DomainException;
use Throwable;

class UserController extends BaseController
{
    public function __construct(private UserService $users) {}

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
}
