<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repository\Contract\AdminRepositoryInterface;
use App\Service\AdminService;
use PHPUnit\Framework\TestCase;

final class AdminServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testLoginFailsWhenLoginAndPasswordAreEmpty(): void
    {
        $repo = $this->createMock(AdminRepositoryInterface::class);

        $service = new AdminService($repo);

        $result = $service->attemptLogin('', '');

        $this->assertFalse($result['ok']);
        $this->assertSame('Логин и пароль обязательны', $result['error']);
        $this->assertNull($result['admin']);
    }

    public function testLoginFailsWhenAdminNotFound(): void
    {
        $repo = $this->createMock(AdminRepositoryInterface::class);

        $repo->expects($this->once())
            ->method('findByLogin')
            ->with('admin')
            ->willReturn(null);

        $service = new AdminService($repo);

        $result = $service->attemptLogin('admin', '123456');

        $this->assertFalse($result['ok']);
        $this->assertSame('Неверный логин или пароль', $result['error']);
    }

    public function testLoginFailsWhenPasswordIsWrong(): void
    {
        $repo = $this->createMock(AdminRepositoryInterface::class);

        $repo->expects($this->once())
            ->method('findByLogin')
            ->with('admin')
            ->willReturn([
                'id' => 1,
                'login' => 'admin',
                'password_hash' => password_hash('correct-password', PASSWORD_DEFAULT),
                'deleted_at' => null,
            ]);

        $service = new AdminService($repo);

        $result = $service->attemptLogin('admin', 'wrong-password');

        $this->assertFalse($result['ok']);
        $this->assertSame('Неверный логин или пароль', $result['error']);
    }

    public function testLoginFailsWhenAdminIsDeleted(): void
    {
        $repo = $this->createMock(AdminRepositoryInterface::class);

        $repo->method('findByLogin')->willReturn([
            'id'            => 2,
            'login'         => 'admin',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'deleted_at'    => '2024-01-01 00:00:00',
        ]);

        $service = new AdminService($repo);

        $result = $service->attemptLogin('admin', 'secret');

        $this->assertFalse($result['ok']);
        $this->assertSame('Неверный логин или пароль', $result['error']);
        $this->assertNull($result['admin']);
    }

    public function testLoginSuccessfullyAndSetsSession(): void
    {
        @session_start();

        $hash = password_hash('qwerty', PASSWORD_DEFAULT);

        $repo = $this->createMock(AdminRepositoryInterface::class);
        $repo->method('findByLogin')->willReturn([
            'id'            => 7,
            'login'         => 'superadmin',
            'password_hash' => $hash,
            'deleted_at'    => null,
        ]);

        $service = new AdminService($repo);

        $result = $service->attemptLogin('superadmin', 'qwerty');

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
        $this->assertNotNull($result['admin']);
        $this->assertSame(7, $_SESSION['admin_id']);
    }

    public function testCheckReturnsFalseWhenNotLoggedIn(): void
    {
        $repo    = $this->createMock(AdminRepositoryInterface::class);
        $service = new AdminService($repo);

        $this->assertFalse($service->check());
    }

    public function testCheckReturnsTrueWhenAdminIdInSession(): void
    {
        $_SESSION['admin_id'] = 3;

        $repo    = $this->createMock(AdminRepositoryInterface::class);
        $service = new AdminService($repo);

        $this->assertTrue($service->check());
    }

    public function testLogoutRemovesAdminIdFromSession(): void
    {
        @session_start();
        $_SESSION['admin_id'] = 5;

        $repo    = $this->createMock(AdminRepositoryInterface::class);
        $service = new AdminService($repo);

        $service->logout();

        $this->assertArrayNotHasKey('admin_id', $_SESSION);
    }

    public function testCurrentAdminReturnsNullWhenNotLoggedIn(): void
    {
        $repo    = $this->createMock(AdminRepositoryInterface::class);
        $service = new AdminService($repo);

        $this->assertNull($service->currentAdmin());
    }

    public function testCurrentAdminCallsFindWhenLoggedIn(): void
    {
        $_SESSION['admin_id'] = 10;

        $adminData = ['id' => 10, 'login' => 'root', 'deleted_at' => null];

        $repo = $this->createMock(AdminRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('find')
            ->with(10)
            ->willReturn($adminData);

        $service = new AdminService($repo);

        $this->assertSame($adminData, $service->currentAdmin());
    }
}