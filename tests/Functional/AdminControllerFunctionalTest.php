<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Functional\Support\FakeAdminService;
use Tests\Functional\Support\FunctionalTestCase;
use Tests\Functional\Support\TestAdminController;

final class AdminControllerFunctionalTest extends FunctionalTestCase
{
    public function testLoginReturnsOkForValidCredentials(): void
    {
        $controller = new TestAdminController(new FakeAdminService(loginOk: true));
        $controller->jsonBody = ['login' => 'admin', 'password' => 'secret'];

        $this->useRequest('POST', '/admin/login');
        $response = $this->capture(fn () => $controller->loginByPassword());

        $this->assertSame(200, $response['status']);
        $this->assertSame(['ok' => true], $response['json']);
        $this->assertSame(1, $_SESSION['admin_id']);
    }

    public function testLoginReturns422ForInvalidCredentials(): void
    {
        $controller = new TestAdminController(new FakeAdminService(loginOk: false));
        $controller->jsonBody = ['login' => 'admin', 'password' => 'wrong'];

        $this->useRequest('POST', '/admin/login');
        $response = $this->capture(fn () => $controller->loginByPassword());

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['json']['ok']);
        $this->assertArrayHasKey('error', $response['json']);
        $this->assertArrayNotHasKey('admin_id', $_SESSION);
    }

}
