<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Model\Vote;
use App\Repository\Pdo\CandidateRepository;
use App\Repository\Pdo\UserRepository;
use App\Repository\Pdo\VoteRepository;
use App\Service\VoteService;
use DomainException;
use RuntimeException;
use Tests\Integration\Support\DatabaseTestCase;

final class VoteServiceIntegrationTest extends DatabaseTestCase
{
    private VoteService $service;
    private int $userId;
    private int $candidateId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId      = $this->insertUser(['phone' => '79990000001']);
        $this->candidateId = $this->insertCandidate(['phone' => '79991000001']);

        $this->service = new VoteService(
            static::$pdo,
            new UserRepository(static::$pdo),
            new CandidateRepository(static::$pdo),
            new VoteRepository(static::$pdo),
        );
    }

    public function testCastVotePersistsVoteToDatabase(): void
    {
        $vote = $this->service->castVote($this->userId, $this->candidateId);

        $this->assertInstanceOf(Vote::class, $vote);
        $this->assertSame($this->userId, $vote->user_id);
        $this->assertSame($this->candidateId, $vote->candidate_id);
        $this->assertNotNull($vote->id);

        // Verify directly in DB
        $row = static::$pdo->query("SELECT * FROM votes WHERE id = {$vote->id}")->fetch();
        $this->assertNotFalse($row);
        $this->assertSame($this->userId, (int)$row['user_id']);
        $this->assertSame($this->candidateId, (int)$row['candidate_id']);
    }

    public function testCastVoteThrowsWhenUserNotFound(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Пользователь не найден');

        $this->service->castVote(9999, $this->candidateId);
    }

    public function testCastVoteThrowsWhenCandidateNotFound(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Кандидат не найден');

        $this->service->castVote($this->userId, 9999);
    }

    public function testCastVoteThrowsWhenUserAlreadyVoted(): void
    {
        // First vote succeeds
        $this->service->castVote($this->userId, $this->candidateId);

        // Second vote by the same user must fail (UNIQUE constraint on user_id)
        $this->expectException(\Throwable::class);

        $secondCandidate = $this->insertCandidate(['phone' => '79991000002']);
        $this->service->castVote($this->userId, $secondCandidate);
    }

    public function testCastVoteRollsBackTransactionOnError(): void
    {
        // First vote
        $this->service->castVote($this->userId, $this->candidateId);

        // Attempt a second vote (will fail)
        $secondCandidate = $this->insertCandidate(['phone' => '79991000003']);
        try {
            $this->service->castVote($this->userId, $secondCandidate);
        } catch (\Throwable) {
            // expected
        }

        // Only one vote in the DB (rollback worked)
        $count = (int)static::$pdo->query("SELECT COUNT(*) FROM votes WHERE deleted_at IS NULL")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testTwoDistinctUsersCanVoteIndependently(): void
    {
        $secondUser = $this->insertUser(['phone' => '79990000002']);

        $v1 = $this->service->castVote($this->userId, $this->candidateId);
        $v2 = $this->service->castVote($secondUser, $this->candidateId);

        $this->assertNotSame($v1->id, $v2->id);

        $count = (int)static::$pdo->query("SELECT COUNT(*) FROM votes WHERE deleted_at IS NULL")->fetchColumn();
        $this->assertSame(2, $count);
    }
}
