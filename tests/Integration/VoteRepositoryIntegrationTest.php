<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Model\Vote;
use App\Repository\Pdo\VoteRepository;
use Tests\Integration\Support\DatabaseTestCase;

final class VoteRepositoryIntegrationTest extends DatabaseTestCase
{
    private VoteRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new VoteRepository(static::$pdo);

        // Seed minimal users and candidates to satisfy DB constraints
        $this->insertUser(['phone' => '79990000001']);
        $this->insertUser(['phone' => '79990000002']);
        $this->insertCandidate(['phone' => '79991000001']);
        $this->insertCandidate(['phone' => '79991000002']);
    }

    private function userId(int $n = 1): int
    {
        $rows = static::$pdo->query("SELECT id FROM users ORDER BY id LIMIT {$n}")->fetchAll();
        return (int)$rows[$n - 1]['id'];
    }

    private function candidateId(int $n = 1): int
    {
        $rows = static::$pdo->query("SELECT id FROM candidates ORDER BY id LIMIT {$n}")->fetchAll();
        return (int)$rows[$n - 1]['id'];
    }

    // -------------------------------------------------------------------------
    // userHasVote / createVote
    // -------------------------------------------------------------------------

    public function testUserHasVoteReturnsFalseInitially(): void
    {
        $this->assertFalse($this->repo->userHasVote($this->userId()));
    }

    public function testCreateVoteAddsVoteRecord(): void
    {
        $uid = $this->userId();
        $cid = $this->candidateId();

        $this->repo->createVote($uid, $cid);

        $this->assertTrue($this->repo->userHasVote($uid));
    }

    public function testUserHasVoteReturnsFalseAfterSoftDelete(): void
    {
        $uid = $this->userId();
        $cid = $this->candidateId();
        $this->repo->createVote($uid, $cid);

        $voteId = (int)static::$pdo->query("SELECT id FROM votes LIMIT 1")->fetchColumn();
        $this->repo->softDelete($voteId);

        $this->assertFalse($this->repo->userHasVote($uid));
    }

    // -------------------------------------------------------------------------
    // findByUserId
    // -------------------------------------------------------------------------

    public function testFindByUserIdReturnsNullBeforeVoting(): void
    {
        $this->assertNull($this->repo->findByUserId($this->userId()));
    }

    public function testFindByUserIdReturnsVoteAfterVoting(): void
    {
        $uid = $this->userId();
        $cid = $this->candidateId();
        $this->repo->createVote($uid, $cid);

        $row = $this->repo->findByUserId($uid);

        $this->assertIsArray($row);
        $this->assertSame($uid, (int)$row['user_id']);
        $this->assertSame($cid, (int)$row['candidate_id']);
    }

    public function testFindByUserIdReturnsNullAfterSoftDelete(): void
    {
        $uid = $this->userId();
        $this->repo->createVote($uid, $this->candidateId());
        $voteId = (int)static::$pdo->query("SELECT id FROM votes LIMIT 1")->fetchColumn();
        $this->repo->softDelete($voteId);

        $this->assertNull($this->repo->findByUserId($uid));
    }

    // -------------------------------------------------------------------------
    // create(Vote)
    // -------------------------------------------------------------------------

    public function testCreateVoteObjectPersistsAndSetsId(): void
    {
        $vote              = new Vote();
        $vote->user_id     = $this->userId();
        $vote->candidate_id = $this->candidateId();

        $saved = $this->repo->create($vote);

        $this->assertInstanceOf(Vote::class, $saved);
        $this->assertNotNull($saved->id);
        $this->assertGreaterThan(0, $saved->id);
    }

    // -------------------------------------------------------------------------
    // softDelete
    // -------------------------------------------------------------------------

    public function testSoftDeleteReturnsTrueAndSetsDeletedAt(): void
    {
        $this->repo->createVote($this->userId(), $this->candidateId());
        $voteId = (int)static::$pdo->query("SELECT id FROM votes LIMIT 1")->fetchColumn();

        $result = $this->repo->softDelete($voteId);

        $this->assertTrue($result);
        $row = static::$pdo->query("SELECT * FROM votes WHERE id = {$voteId}")->fetch();
        $this->assertNotNull($row['deleted_at']);
    }

    public function testSoftDeleteReturnsFalseForNonexistentVote(): void
    {
        $this->assertFalse($this->repo->softDelete(9999));
    }
}
