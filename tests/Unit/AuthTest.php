<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\Auth;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    // ------------------------------------------------------------------ adminId

    public function testAdminIdReturnsNullWhenNotInSession(): void
    {
        $this->assertNull(Auth::adminId());
    }

    public function testAdminIdReturnsIntegerFromSession(): void
    {
        $_SESSION['admin_id'] = '5';

        $this->assertSame(5, Auth::adminId());
    }

    public function testAdminIdReturnsCastedZeroWhenSessionValueIsNotNumeric(): void
    {
        $_SESSION['admin_id'] = 'not-a-number';

        // Auth::adminId() приводит значение к int: (int)'not-a-number' === 0
        $this->assertSame(0, Auth::adminId());
    }

    // ------------------------------------------------------------------ isAdmin

    public function testIsAdminReturnsFalseWhenNoSession(): void
    {
        $this->assertFalse(Auth::isAdmin());
    }

    public function testIsAdminReturnsTrueWhenAdminIdInSession(): void
    {
        $_SESSION['admin_id'] = 3;

        $this->assertTrue(Auth::isAdmin());
    }

    // ------------------------------------------------------------------ userId

    public function testUserIdReturnsNullWhenNotInSession(): void
    {
        $this->assertNull(Auth::userId());
    }

    public function testUserIdReturnsIntegerFromSession(): void
    {
        $_SESSION['user_id'] = '42';

        $this->assertSame(42, Auth::userId());
    }

    // ------------------------------------------------------------------ isUser

    public function testIsUserReturnsFalseWhenNoSession(): void
    {
        $this->assertFalse(Auth::isUser());
    }

    public function testIsUserReturnsTrueWhenUserIdInSession(): void
    {
        $_SESSION['user_id'] = 10;

        $this->assertTrue(Auth::isUser());
    }

    // ------------------------------------------------------------------ session isolation

    public function testSessionIsClearedBetweenTests(): void
    {
        // $_SESSION сброшен в setUp, проверяем что не просочилось из предыдущего теста
        $this->assertArrayNotHasKey('admin_id', $_SESSION);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }
    public function testRequireAdminDoesNothingWhenAdminIsLoggedIn(): void
    {
        $_SESSION['admin_id'] = 1;

        ob_start();
        Auth::requireAdmin();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

}
