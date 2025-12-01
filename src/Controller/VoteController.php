<?php
namespace App\Controller;

use App\Repository\Contract\VoteRepositoryInterface;
use DomainException;
use Throwable;

class VoteController extends BaseController
{
    public function __construct(private VoteRepositoryInterface $votes) {}

    public function cast(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        $userId = (int)($data['user_id'] ?? 0);
        $candidateId = (int)($data['candidate_id'] ?? 0);

        if (!$userId || !$candidateId) {
            $this->json(['ok' => false, 'error' => 'user_id и candidate_id обязательны'], 422);
            return;
        }

        try {
            $vote = $this->votes->castVote($userId, $candidateId);
            $this->json(['ok' => true, 'data' => $vote], 201);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    public function index(): void
    {
        $format = $_GET['format'] ?? '';

        $rows = $this->votes->all();

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $title = 'Проголосовавшие';
        $votes = $rows;

        require __DIR__ . '/../../public/votes-admin.html';
    }
}
