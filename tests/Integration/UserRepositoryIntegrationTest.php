<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repository\Pdo\UserRepository;
use Tests\Integration\Support\DatabaseTestCase;

final class UserRepositoryIntegrationTest extends DatabaseTestCase
{
    private UserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new UserRepository(static::$pdo);
    }

    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    public function testFindByIdReturnsNullForNonexistentUser(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    public function testFindByIdReturnsUserWhenExists(): void
    {
        $id = $this->insertUser(['phone' => '79991110001']);

        $row = $this->repo->findById($id);

        $this->assertIsArray($row);
        $this->assertSame($id, (int)$row['id']);
        $this->assertSame('79991110001', $row['phone']);
    }

    public function testFindByIdReturnsNullForSoftDeletedUser(): void
    {
        $id = $this->insertUser(['phone' => '79991110002']);
        static::$pdo->exec("UPDATE users SET deleted_at = datetime('now') WHERE id = {$id}");

        $this->assertNull($this->repo->findById($id));
    }

    // -------------------------------------------------------------------------
    // findByPhone
    // -------------------------------------------------------------------------

    public function testFindByPhoneReturnsNullForUnknownPhone(): void
    {
        $this->assertNull($this->repo->findByPhone('70000000000'));
    }

    public function testFindByPhoneReturnsUserWhenExists(): void
    {
        $this->insertUser(['phone' => '79991234567']);

        $row = $this->repo->findByPhone('79991234567');

        $this->assertIsArray($row);
        $this->assertSame('79991234567', $row['phone']);
    }

    // -------------------------------------------------------------------------
    // find (throws on missing)
    // -------------------------------------------------------------------------

    public function testFindThrowsForNonexistentUser(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->repo->find(999);
    }

    public function testFindReturnsUserWhenExists(): void
    {
        $id = $this->insertUser(['phone' => '79995550001']);
        $row = $this->repo->find($id);
        $this->assertSame($id, (int)$row['id']);
    }

    // -------------------------------------------------------------------------
    // firstOrCreateByPhone
    // -------------------------------------------------------------------------

    public function testFirstOrCreateByPhoneCreatesNewUser(): void
    {
        $row = $this->repo->firstOrCreateByPhone('79990000099');

        $this->assertIsArray($row);
        $this->assertSame('79990000099', $row['phone']);
        $this->assertNotEmpty($row['id']);
    }

    public function testFirstOrCreateByPhoneReturnsExistingUser(): void
    {
        $id = $this->insertUser(['phone' => '79990000088']);

        $row = $this->repo->firstOrCreateByPhone('79990000088');

        $this->assertSame($id, (int)$row['id']);
    }

    // -------------------------------------------------------------------------
    // delete (soft)
    // -------------------------------------------------------------------------

    public function testDeleteSoftDeletesUser(): void
    {
        $id = $this->insertUser(['phone' => '79990000077']);

        $this->repo->delete($id);

        $this->assertNull($this->repo->findById($id));
    }
}
