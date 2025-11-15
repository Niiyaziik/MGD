<?php
namespace App\Controller;

use App\Repository\Contract\CandidateRepositoryInterface;
use DomainException;
use Throwable;

class CandidateController extends BaseController
{
    public function __construct(private CandidateRepositoryInterface $candidates) {}

    public function index(): void
    {
        $json = (isset($_GET['format']) && $_GET['format'] === 'json')
              || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($json) {
            $all   = ($_GET['all'] ?? '') === '1';
            $limit = (int)($_GET['limit'] ?? 5);
            $data  = $all ? $this->candidates->all() : $this->candidates->first($limit);
            $this->json($data);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/../../public/candidates.html');
    }

    public function show(): void
    {
        $wantsJson = (($_GET['format'] ?? '') === 'json') ||
                 (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
                 
        $id = (int)($_GET['id'] ?? 0);

        if ($wantsJson) {
        if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'bad id']); return; }
        $c = $this->candidates->find($id);
        if (!$c) { http_response_code(404); echo json_encode(['error'=>'not found']); return; }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($c, JSON_UNESCAPED_UNICODE);
        return;
        }

        header_remove('Content-Type');
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/../../public/candidate.html');
    }

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
