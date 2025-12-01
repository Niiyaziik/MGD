<?php
namespace App\Repository\Contract;

interface VoteRepositoryInterface
{
    public function all(): array;
    public function userHasVote(int $userId): bool;
    public function createVote(int $userId, int $candidateId): void;

    public function allWithRelations(): array;
}
