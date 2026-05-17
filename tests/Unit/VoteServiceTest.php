<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Model\Vote;
use App\Repository\Contract\CandidateRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Repository\Contract\VoteRepositoryInterface;
use App\Service\VoteService;
use DomainException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VoteServiceTest extends TestCase
{
    private function makeUserRepo(?array $returnUser): UserRepositoryInterface
    {
        return new class($returnUser) implements UserRepositoryInterface {
            public function __construct(private readonly ?array $user) {}
            public function findById(int $id): ?array { return $this->user; }
            public function firstOrCreateByPhone(string $phone): array { return []; }
            public function find(int $id): array { return $this->user ?? []; }
            public function all(): array { return []; }
            public function findByPhone(string $phone): ?array { return null; }
            public function create(array $data): int { return 0; }
            public function update(int $id, array $data): void {}
            public function delete(int $id): void {}
            public function getAdminUsers(int $deleted = 0): array { return []; }
            public function updateAdminUser(int $id, array $data): void {}
            public function markPhoneVerified(int $id): void {}
            public function getUserDistrictByAddress(string $street, string $house): ?array { return null; }
            public function updateVkData(int $id, array $data): void {}
            public function updateVkMiddleName(int $id, string $middleName): void {}
            public function createFromVk(array $data): int { return 0; }
            public function findByVkId(string $vkId): ?array { return null; }
        };
    }

    private function makeCandidateRepo(?array $returnCandidate): CandidateRepositoryInterface
    {
        return new class($returnCandidate) implements CandidateRepositoryInterface {
            public function __construct(private readonly ?array $candidate) {}
            public function findById(int $id): ?array { return $this->candidate; }
            public function all(?int $districtId = null): array { return []; }
            public function find(int $id): array { return $this->candidate ?? []; }
            public function first(int $limit = 5): array { return []; }
            public function create(array $data): int { return 0; }
            public function update(int $id, array $data): void {}
            public function delete(int $id): void {}
            public function getAdminCandidates(int $deleted = 0): array { return []; }
            public function getCandidateDistrict(int $candidateId): ?array { return null; }
        };
    }

    private function makeVoteRepo(?array $existingVote, bool $throwUniqueViolation = false, bool $throwGenericError = false): VoteRepositoryInterface
    {
        return new class($existingVote, $throwUniqueViolation, $throwGenericError) implements VoteRepositoryInterface {
            public ?Vote $createdVote = null;

            public function __construct(
                private readonly ?array $existing,
                private readonly bool $throwUniqueViolation,
                private readonly bool $throwGenericError,
            ) {}

            public function findByUserId(int $userId): ?array { return $this->existing; }
            public function create(object $vote): object
            {
                if ($this->throwUniqueViolation) {
                    throw new \RuntimeException('SQLSTATE[23000]: Integrity constraint violation');
                }
                if ($this->throwGenericError) {
                    throw new \RuntimeException('database is unavailable');
                }
                $this->createdVote = $vote;
                return $vote;
            }
            public function all(): array { return []; }
            public function userHasVote(int $userId): bool { return $this->existing !== null; }
            public function createVote(int $userId, int $candidateId): void {}
            public function allWithRelations(): array { return []; }
            public function voted(int $userId, int $candidateId): array { return []; }
            public function getAdminList(bool $onlyDeleted = false): array { return []; }
            public function softDelete(int $voteId): bool { return true; }
            public function getDeletedAdminList(): array { return []; }
            public function adminUpdate(int $voteId, array $fields): bool { return true; }
        };
    }

    private function makePdo(): PDO
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        return $pdo;
    }

    public function testCastVoteThrowsWhenUserNotFound(): void
    {
        $service = new VoteService(
            $this->makePdo(),
            $this->makeUserRepo(null),
            $this->makeCandidateRepo(['id' => 1]),
            $this->makeVoteRepo(null),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Пользователь не найден');

        $service->castVote(999, 1);
    }

    public function testCastVoteThrowsWhenCandidateNotFound(): void
    {
        $service = new VoteService(
            $this->makePdo(),
            $this->makeUserRepo(['id' => 1, 'phone' => '79991234567']),
            $this->makeCandidateRepo(null),
            $this->makeVoteRepo(null),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Кандидат не найден');

        $service->castVote(1, 999);
    }

    public function testCastVoteThrowsRuntimeExceptionWhenUserAlreadyVotedInsideTransaction(): void
    {
        $service = new VoteService(
            $this->makePdo(),
            $this->makeUserRepo(['id' => 1, 'phone' => '79991234567']),
            $this->makeCandidateRepo(['id' => 1]),
            $this->makeVoteRepo(['id' => 5, 'user_id' => 1, 'candidate_id' => 1]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Не удалось сохранить голос');

        $service->castVote(1, 1);
    }

    public function testCastVoteCreatesVoteAndCommitsTransaction(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('commit')->willReturn(true);
        $pdo->expects($this->never())->method('rollBack');

        $service = new VoteService(
            $pdo,
            $this->makeUserRepo(['id' => 1, 'phone' => '79991234567']),
            $this->makeCandidateRepo(['id' => 2]),
            $this->makeVoteRepo(null),
        );

        $vote = $service->castVote(1, 2);

        $this->assertInstanceOf(Vote::class, $vote);
        $this->assertSame(1, $vote->user_id);
        $this->assertSame(2, $vote->candidate_id);
    }

    public function testCastVoteConvertsUniqueViolationToDomainException(): void
    {
        $service = new VoteService(
            $this->makePdo(),
            $this->makeUserRepo(['id' => 1]),
            $this->makeCandidateRepo(['id' => 2]),
            $this->makeVoteRepo(null, throwUniqueViolation: true),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Пользователь уже голосовал');

        $service->castVote(1, 2);
    }

    public function testCastVoteRollsBackAndThrowsRuntimeExceptionForGenericRepositoryError(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $service = new VoteService(
            $pdo,
            $this->makeUserRepo(['id' => 1]),
            $this->makeCandidateRepo(['id' => 2]),
            $this->makeVoteRepo(null, throwGenericError: true),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Не удалось сохранить голос');

        $service->castVote(1, 2);
    }
}
