<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repository\Pdo\CandidateRepository;
use Tests\Integration\Support\DatabaseTestCase;

final class CandidateRepositoryIntegrationTest extends DatabaseTestCase
{
    private CandidateRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new CandidateRepository(static::$pdo);
    }

    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    public function testFindByIdReturnsNullForNonexistentCandidate(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    public function testFindByIdReturnsRowWhenCandidateExists(): void
    {
        $id = $this->insertCandidate(['phone' => '79991000001']);

        $row = $this->repo->findById($id);

        $this->assertIsArray($row);
        $this->assertSame($id, (int)$row['id']);
    }

    public function testFindByIdReturnsSoftDeletedRows(): void
    {
        $id = $this->insertCandidate(['phone' => '79991000002']);
        static::$pdo->exec("UPDATE candidates SET deleted_at = datetime('now') WHERE id = {$id}");

        // findById has no deleted_at filter — it returns any row
        $row = $this->repo->findById($id);
        $this->assertIsArray($row);
        $this->assertNotNull($row['deleted_at']);
    }

    // -------------------------------------------------------------------------
    // find (throws on missing, filters deleted)
    // -------------------------------------------------------------------------

    public function testFindThrowsForNonexistentCandidate(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->repo->find(999);
    }

    public function testFindThrowsForDeletedCandidate(): void
    {
        $id = $this->insertCandidate(['phone' => '79991000003']);
        static::$pdo->exec("UPDATE candidates SET deleted_at = datetime('now') WHERE id = {$id}");

        $this->expectException(\RuntimeException::class);
        $this->repo->find($id);
    }

    public function testFindReturnsCandidateWhenExists(): void
    {
        $id = $this->insertCandidate(['surname' => 'Петров', 'phone' => '79991000004']);
        $row = $this->repo->find($id);
        $this->assertSame('Петров', $row['surname']);
    }

    // -------------------------------------------------------------------------
    // all
    // -------------------------------------------------------------------------

    public function testAllReturnsEmptyArrayWhenNoCandidates(): void
    {
        $this->assertSame([], $this->repo->all());
    }

    public function testAllReturnsCandidates(): void
    {
        $this->insertCandidate(['phone' => '79991000010']);
        $this->insertCandidate(['phone' => '79991000011']);

        $rows = $this->repo->all();

        $this->assertCount(2, $rows);
    }

    public function testAllFiltersDeletedCandidates(): void
    {
        $id = $this->insertCandidate(['phone' => '79991000012']);
        static::$pdo->exec("UPDATE candidates SET deleted_at = datetime('now') WHERE id = {$id}");

        $this->assertCount(0, $this->repo->all());
    }

    // -------------------------------------------------------------------------
    // delete (soft)
    // -------------------------------------------------------------------------

    public function testDeleteSoftDeletesCandidate(): void
    {
        $id = $this->insertCandidate(['phone' => '79991000020']);

        $this->repo->delete($id);

        $this->assertCount(0, $this->repo->all());
        $this->assertNotNull($this->repo->findById($id)['deleted_at']);
    }
}
