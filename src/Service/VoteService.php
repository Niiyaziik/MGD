<?php

namespace App\Service;

use App\Repository\Contract\UserRepositoryInterface;
use App\Repository\Contract\CandidateRepositoryInterface;
use App\Repository\Contract\VoteRepositoryInterface;
use App\Model\Vote;
use PDO;
use RuntimeException;
use DomainException;

class VoteService
{
    public function __construct(
        private PDO $pdo,
        private UserRepositoryInterface $users,
        private CandidateRepositoryInterface $candidates,
        private VoteRepositoryInterface $votes,
    ) {}

    /**
     * Голосование пользователя за кандидата
     */
    public function castVote(int $userId, int $candidateId): Vote
    {
        // 1) Проверки предметной области
        $user = $this->users->findById($userId);
        if (!$user) {
            throw new DomainException('Пользователь не найден');
        }

        $candidate = $this->candidates->findById($candidateId);
        if (!$candidate) {
            throw new DomainException('Кандидат не найден');
        }

        // (опционально) правило: голосовать можно только в своём округе
        // if ($user->district_id !== $candidate->district_id) {
        //     throw new DomainException('Нельзя голосовать за кандидата из другого округа');
        // }

        // 2) Транзакция, чтобы исключить гонки
        try {
            $this->pdo->beginTransaction();

            // Защита на уровне приложения (вдобавок к UNIQUE в БД)
            $existing = $this->votes->findByUserId($userId);
            if ($existing) {
                throw new DomainException('Пользователь уже голосовал');
            }

            $vote = new Vote();
            $vote->user_id = $userId;
            $vote->candidate_id = $candidateId;

            $vote = $this->votes->create($vote);

            $this->pdo->commit();
            return $vote;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            // Если нарвались на дубликат UNIQUE (например, параллельный запрос)
            if ($this->isUniqueViolation($e)) {
                throw new DomainException('Пользователь уже голосовал');
            }
            throw new RuntimeException('Не удалось сохранить голос', 0, $e);
        }
    }

    private function isUniqueViolation(\Throwable $e): bool
    {
        // MySQL: SQLSTATE[23000]: Integrity constraint violation
        return str_contains($e->getMessage(), '23000') || str_contains($e->getMessage(), 'Integrity constraint');
    }
}
