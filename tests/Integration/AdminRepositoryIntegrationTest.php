<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repository\Pdo\AdminRepository;
use Tests\Integration\Support\DatabaseTestCase;

final class AdminRepositoryIntegrationTest extends DatabaseTestCase
{
    private AdminRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AdminRepository(static::$pdo);
    }

    public function testFindByLoginReturnsNullWhenNoAdmins(): void
    {
        $this->assertNull($this->repo->findByLogin('admin'));
    }

    public function testFindByLoginReturnsAdminWhenExists(): void
    {
        $this->insertAdmin('superadmin', 'secret');

        $row = $this->repo->findByLogin('superadmin');

        $this->assertIsArray($row);
        $this->assertSame('superadmin', $row['login']);
        $this->assertArrayHasKey('password_hash', $row);
    }

    public function testFindByLoginReturnsNullForDeletedAdmin(): void
    {
        $this->insertAdmin('deleted_admin', 'pass', deletedAt: date('Y-m-d H:i:s'));

        $this->assertNull($this->repo->findByLogin('deleted_admin'));
    }

    public function testFindReturnsNullForNonexistentId(): void
    {
        $this->assertNull($this->repo->find(999));
    }

    public function testFindReturnsAdminById(): void
    {
        $id = $this->insertAdmin('findme', 'pass');

        $row = $this->repo->find($id);

        $this->assertIsArray($row);
        $this->assertSame($id, (int)$row['id']);
        $this->assertSame('findme', $row['login']);
    }

    public function testFindReturnsNullForDeletedAdmin(): void
    {
        $id = $this->insertAdmin('gone', 'pass', deletedAt: date('Y-m-d H:i:s'));

        $this->assertNull($this->repo->find($id));
    }

    public function testPasswordHashIsVerifiable(): void
    {
        $this->insertAdmin('secure', 'MyP@ssw0rd');
        $row = $this->repo->findByLogin('secure');

        $this->assertTrue(password_verify('MyP@ssw0rd', $row['password_hash']));
        $this->assertFalse(password_verify('wrong', $row['password_hash']));
    }
}
