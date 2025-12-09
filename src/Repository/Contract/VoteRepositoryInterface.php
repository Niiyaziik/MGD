<?php
namespace App\Repository\Contract;

interface VoteRepositoryInterface
{
    public function all(): array;
    public function userHasVote(int $userId): bool;
    public function createVote(int $userId, int $candidateId): void;

    public function allWithRelations(): array;
    public function voted(int $userId, int $candidateId): array;
    public function getAdminList(bool $onlyDeleted = false): array;
    public function softDelete(int $voteId): bool;
    public function getDeletedAdminList(): array;
    public function adminUpdate(int $voteId, array $fields): bool;
}
