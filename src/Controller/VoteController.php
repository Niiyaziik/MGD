<?php
namespace App\Controller;

use App\Service\VoteService;
use DomainException;
use Throwable;

class VoteController extends BaseController
{
    public function __construct(private VoteService $votes) {}

    // POST /api/votes
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
}
