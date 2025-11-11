<?php
namespace App\Controller;

use App\Service\CandidateService;
use DomainException;
use Throwable;

class CandidateController extends BaseController
{
    public function __construct(private CandidateService $candidates) {}

    // POST /api/candidates
    public function store(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        try {
            $candidate = $this->candidates->create($data);
            $this->json(['ok' => true, 'data' => $candidate], 201);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    // PATCH /api/candidates/{id}
    public function update(int $id): void
    {
        $this->requireMethod('PATCH');
        $patch = $this->getJsonBody();

        try {
            $candidate = $this->candidates->update($id, $patch);
            $this->json(['ok' => true, 'data' => $candidate]);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    // DELETE /api/candidates/{id}
    public function destroy(int $id): void
    {
        $this->requireMethod('DELETE');

        try {
            $this->candidates->delete($id);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }
}
