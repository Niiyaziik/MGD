<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repository\Pdo\PhoneCodeRepository;
use Tests\Integration\Support\DatabaseTestCase;

/**
 * Integration tests for PhoneCodeRepository.
 *
 * PhoneCodeRepository::createCode and findValid use MySQL-specific INTERVAL syntax
 * which is not supported by SQLite. Those tests are marked as skipped.
 * markUsed() uses only NOW() (registered as a SQLite UDF) and is fully testable.
 */
final class PhoneCodeRepositoryIntegrationTest extends DatabaseTestCase
{
    private PhoneCodeRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PhoneCodeRepository(static::$pdo);
    }

    public function testCreateCodeRequiresMysql(): void
    {
        $this->markTestSkipped(
            'PhoneCodeRepository::createCode uses MySQL INTERVAL syntax — run against MySQL to test'
        );
    }

    public function testFindValidRequiresMysql(): void
    {
        $this->markTestSkipped(
            'PhoneCodeRepository::findValid uses MySQL INTERVAL syntax — run against MySQL to test'
        );
    }

    public function testMarkUsedSetsUsedAt(): void
    {
        $id = $this->insertPhoneCode('79990000001', '123456');

        $before = static::$pdo->query("SELECT used_at FROM phone_code WHERE id = {$id}")->fetchColumn();
        $this->assertNull($before);

        $this->repo->markUsed($id);

        $after = static::$pdo->query("SELECT used_at FROM phone_code WHERE id = {$id}")->fetchColumn();
        $this->assertNotNull($after);
    }

    public function testMarkUsedIsIdempotentForAlreadyUsedCode(): void
    {
        $id = $this->insertPhoneCode('79990000002', '654321', usedAt: date('Y-m-d H:i:s'));

        // Should not throw
        $this->repo->markUsed($id);

        $row = static::$pdo->query("SELECT used_at FROM phone_code WHERE id = {$id}")->fetch();
        $this->assertNotNull($row['used_at']);
    }

    public function testMarkUsedDoesNotAffectOtherCodes(): void
    {
        $id1 = $this->insertPhoneCode('79990000003', '111111');
        $id2 = $this->insertPhoneCode('79990000004', '222222');

        $this->repo->markUsed($id1);

        $row2 = static::$pdo->query("SELECT used_at FROM phone_code WHERE id = {$id2}")->fetch();
        $this->assertNull($row2['used_at']);
    }
}
