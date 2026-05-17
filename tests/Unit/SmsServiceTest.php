<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/Support/CurlMock.php';

use App\Repository\Contract\PhoneCodeRepositoryInterface;
use App\Service\SmsService;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\CurlMock;

final class SmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        CurlMock::reset();
    }

    public function testSendCodeToPhoneReturnsErrorWhenPhoneIsInvalid(): void
    {
        $repo = $this->createMock(PhoneCodeRepositoryInterface::class);
        $repo->expects($this->never())->method('createCode');

        $service = new SmsService($repo);
        $result = $service->sendCodeToPhone('123');

        $this->assertFalse($result['ok']);
        $this->assertSame('Неверный формат телефона', $result['error']);
    }

    public function testSendCodeToPhoneCreatesCodeWhenSmsApiReturnsSuccess(): void
    {
        CurlMock::queueResponse(json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE));

        $repo = $this->createMock(PhoneCodeRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('createCode')
            ->with(
                '79991234567',
                $this->matchesRegularExpression('/^\d{6}$/'),
                '127.0.0.1'
            )
            ->willReturn(1);

        $service = new SmsService($repo);
        $result = $service->sendCodeToPhone('+7 (999) 123-45-67');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['sms_sent']);
    }

    public function testSendCodeToPhoneReturnsErrorWhenCurlFails(): void
    {
        CurlMock::queueResponse(false, 'timeout', 0);

        $repo = $this->createMock(PhoneCodeRepositoryInterface::class);
        $repo->expects($this->never())->method('createCode');

        $service = new SmsService($repo);
        $result = $service->sendCodeToPhone('89991234567');

        $this->assertFalse($result['ok']);
        $this->assertSame('Не удалось отправить SMS. Попробуйте позже.', $result['error']);
    }

    public function testSendCodeToPhoneReturnsErrorWhenApiResponseIsInvalidJson(): void
    {
        CurlMock::queueResponse('not-json', '', 200);

        $service = new SmsService($this->createMock(PhoneCodeRepositoryInterface::class));
        $result = $service->sendCodeToPhone('9991234567');

        $this->assertFalse($result['ok']);
        $this->assertSame('SMS-сервис вернул некорректный ответ.', $result['error']);
    }

    public function testSendCodeToPhoneReturnsApiErrorMessage(): void
    {
        CurlMock::queueResponse(json_encode([
            'status' => 'error',
            'data'   => ['message' => 'Недостаточно средств'],
        ], JSON_UNESCAPED_UNICODE));

        $service = new SmsService($this->createMock(PhoneCodeRepositoryInterface::class));
        $result = $service->sendCodeToPhone('79991234567');

        $this->assertFalse($result['ok']);
        $this->assertSame('Ошибка отправки SMS: Недостаточно средств', $result['error']);
    }

    public function testSendCodeForCurrentVoterReturnsErrorWhenVoterMissing(): void
    {
        $service = new SmsService($this->createMock(PhoneCodeRepositoryInterface::class));
        $result = $service->sendCodeForCurrentVoter();

        $this->assertFalse($result['ok']);
        $this->assertSame('Не удалось определить номер телефона для отправки SMS.', $result['error']);
    }

    public function testSendCodeForCurrentVoterUsesPhoneFromSession(): void
    {
        $_SESSION['voter'] = ['phone' => '89991234567'];
        CurlMock::queueResponse(json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE));

        $repo = $this->createMock(PhoneCodeRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('createCode')
            ->with('79991234567', $this->matchesRegularExpression('/^\d{6}$/'), '127.0.0.1')
            ->willReturn(1);

        $service = new SmsService($repo);
        $result = $service->sendCodeForCurrentVoter();

        $this->assertTrue($result['ok']);
    }

    public function testCheckCodeReturnsErrorWhenPhoneIsInvalid(): void
    {
        $repo = $this->createMock(PhoneCodeRepositoryInterface::class);
        $service = new SmsService($repo);

        $result = $service->checkCodeForPhone('123', '123456');

        $this->assertFalse($result['ok']);
        $this->assertSame('Неверный формат телефона', $result['error']);
    }

    public function testCheckCodeReturnsErrorWhenCodeIsEmpty(): void
    {
        $repo = $this->createMock(PhoneCodeRepositoryInterface::class);
        $service = new SmsService($repo);

        $result = $service->checkCodeForPhone('+7 (999) 123-45-67', '');

        $this->assertFalse($result['ok']);
        $this->assertSame('Не указан код', $result['error']);
    }

    public function testCheckCodeReturnsErrorWhenCodeIsWrong(): void
    {
        $repo = $this->createMock(PhoneCodeRepositoryInterface::class);

        $repo->expects($this->once())
            ->method('findValid')
            ->with('79991234567', '000000', 600)
            ->willReturn(null);

        $service = new SmsService($repo);
        $result = $service->checkCodeForPhone('+7 (999) 123-45-67', '000000');

        $this->assertFalse($result['ok']);
        $this->assertSame('Неверный или просроченный код', $result['error']);
    }

    public function testCheckCodeReturnsOkWhenCodeIsValid(): void
    {
        $repo = $this->createMock(PhoneCodeRepositoryInterface::class);

        $repo->expects($this->once())
            ->method('findValid')
            ->with('79991234567', '123456', 600)
            ->willReturn([
                'id' => 1,
                'phone' => '79991234567',
                'code' => '123456',
            ]);

        $repo->expects($this->once())
            ->method('markUsed')
            ->with(1);

        $service = new SmsService($repo);
        $result = $service->checkCodeForPhone('+7 (999) 123-45-67', '123456');

        $this->assertTrue($result['ok']);
    }

    public function testCheckCodeForCurrentVoterReturnsErrorWhenVoterMissing(): void
    {
        $service = new SmsService($this->createMock(PhoneCodeRepositoryInterface::class));
        $result = $service->checkCodeForCurrentVoter('123456');

        $this->assertFalse($result['ok']);
        $this->assertSame('Не удалось определить номер телефона для проверки кода.', $result['error']);
    }

    public function testCheckCodeForCurrentVoterUsesPhoneFromSession(): void
    {
        $_SESSION['voter'] = ['phone' => '89991234567'];

        $repo = $this->createMock(PhoneCodeRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findValid')
            ->with('79991234567', '123456', 600)
            ->willReturn(['id' => 7]);
        $repo->expects($this->once())->method('markUsed')->with(7);

        $service = new SmsService($repo);
        $result = $service->checkCodeForCurrentVoter('123456');

        $this->assertTrue($result['ok']);
    }
}
