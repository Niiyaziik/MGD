<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repository\Pdo\AdminRepository;
use App\Service\AdminService;
use Tests\Integration\Support\DatabaseTestCase;

final class AdminServiceIntegrationTest extends DatabaseTestCase
{
    private AdminService $service;

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $this->service = new AdminService(new AdminRepository(static::$pdo));
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testLoginSucceedsWithCorrectCredentials(): void
    {
        $this->insertAdmin('admin', 'correct_pass');

        $result = $this->service->attemptLogin('admin', 'correct_pass');

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
        $this->assertIsArray($result['admin']);
        $this->assertSame('admin', $result['admin']['login']);
        $this->assertSame($result['admin']['id'], $_SESSION['admin_id']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $this->insertAdmin('admin2', 'real_pass');

        $result = $this->service->attemptLogin('admin2', 'wrong_pass');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Неверный', $result['error']);
        $this->assertArrayNotHasKey('admin_id', $_SESSION);
    }

    public function testLoginFailsWhenAdminNotFound(): void
    {
        $result = $this->service->attemptLogin('nobody', 'pass');

        $this->assertFalse($result['ok']);
        $this->assertFalse(isset($_SESSION['admin_id']));
    }

    public function testLoginFailsForDeletedAdmin(): void
    {
        $this->insertAdmin('deleted', 'pass', deletedAt: date('Y-m-d H:i:s'));

        $result = $this->service->attemptLogin('deleted', 'pass');

        $this->assertFalse($result['ok']);
    }

    public function testLoginFailsWithEmptyCredentials(): void
    {
        $result = $this->service->attemptLogin('', '');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('обязательны', $result['error']);
    }

    public function testCheckReturnsFalseWithNoSession(): void
    {
        $this->assertFalse($this->service->check());
    }

    public function testCheckReturnsTrueAfterLogin(): void
    {
        $this->insertAdmin('chkadmin', 'pass');
        $this->service->attemptLogin('chkadmin', 'pass');

        $this->assertTrue($this->service->check());
    }

    public function testLogoutClearsSession(): void
    {
        $this->insertAdmin('logoutadmin', 'pass');
        $this->service->attemptLogin('logoutadmin', 'pass');

        $this->service->logout();

        $this->assertFalse($this->service->check());
        $this->assertFalse(isset($_SESSION['admin_id']));
    }

    public function testCurrentAdminReturnsNullWhenNotLoggedIn(): void
    {
        $this->assertNull($this->service->currentAdmin());
    }

    public function testCurrentAdminReturnsAdminAfterLogin(): void
    {
        $this->insertAdmin('curadmin', 'pass');
        $this->service->attemptLogin('curadmin', 'pass');

        $admin = $this->service->currentAdmin();

        $this->assertIsArray($admin);
        $this->assertSame('curadmin', $admin['login']);
    }
}
