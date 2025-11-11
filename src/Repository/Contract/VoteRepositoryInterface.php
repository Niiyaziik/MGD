<?php
namespace App\Repository\Contract;

interface VoteRepositoryInterface
{
    public function userHasVote(int $userId): bool;
    public function createVote(int $userId, int $candidateId): void;

    /** Все голоса с подгруженными user и candidate (простая сборка) */
    public function allWithRelations(): array;
}
