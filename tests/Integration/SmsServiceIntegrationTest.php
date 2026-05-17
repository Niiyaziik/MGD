<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/../Unit/Support/CurlMock.php';

use App\Repository\Contract\PhoneCodeRepositoryInterface;
use App\Service\SmsService;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\CurlMock;

/**
 * Integration tests for SmsService.
 *
 * HTTP calls are intercepted by CurlMock (same one used in unit tests).
 * The PhoneCode repository is an in-memory implementation so we avoid
 * the MySQL INTERVAL syntax issue while still testing the full service flow.
 *
 * What these tests cover:
 *  - SmsService normalises the phone number before calling the API
 *  - Code is stored in the repository ONLY after a successful API response
 *  - Code is NOT stored when the API returns an error or a cURL failure
 *  - checkCodeForPhone validates the code via the repository
 *  - sendCodeForCurrentVoter / checkCodeForCurrentVoter read from $_SESSION
 */
final class SmsServiceIntegrationTest extends TestCase
{
    private SmsService $service;
    private InMemoryPhoneCodeRepo $codeRepo;

    protected function setUp(): void
    {
        CurlMock::reset();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];

        $this->codeRepo = new InMemoryPhoneCodeRepo();
        $this->service  = new SmsService($this->codeRepo);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // -------------------------------------------------------------------------
    // sendCodeToPhone
    // -------------------------------------------------------------------------

    public function testSendCodeSavesCodeToRepoAfterSuccessfulApiResponse(): void
    {
        CurlMock::queueResponse(json_encode(['status' => 'success']), '', 200);

        $result = $this->service->sendCodeToPhone('+7 (999) 123-45-67');

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $this->codeRepo->codes);
        $this->assertArrayHasKey('79991234567', $this->codeRepo->codes);
    }

    public function testSendCodeDoesNotSaveCodeWhenApiReturnsFailed(): void
    {
        CurlMock::queueResponse(
            json_encode(['status' => 'error', 'message' => 'bad key']),
            '',
            200
        );

        $result = $this->service->sendCodeToPhone('79991234567');

        $this->assertFalse($result['ok']);
        $this->assertEmpty($this->codeRepo->codes);
    }

    public function testSendCodeDoesNotSaveCodeWhenCurlFails(): void
    {
        CurlMock::queueResponse(false, 'connection refused', 0);

        $result = $this->service->sendCodeToPhone('79991234567');

        $this->assertFalse($result['ok']);
        $this->assertEmpty($this->codeRepo->codes);
    }

    public function testSendCodeDoesNotSaveCodeWhenApiResponseIsInvalidJson(): void
    {
        CurlMock::queueResponse('not-json', '', 200);

        $result = $this->service->sendCodeToPhone('79991234567');

        $this->assertFalse($result['ok']);
        $this->assertEmpty($this->codeRepo->codes);
    }

    public function testSendCodeNormalisesPhoneBeforeStoring(): void
    {
        CurlMock::queueResponse(json_encode(['status' => 'success']), '', 200);

        // Different format — same number
        $this->service->sendCodeToPhone('8 (999) 000-00-01');

        $this->assertArrayHasKey('79990000001', $this->codeRepo->codes);
    }

    public function testSendCodeReturnsErrorForInvalidPhone(): void
    {
        $result = $this->service->sendCodeToPhone('not-a-phone');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('телефон', mb_strtolower($result['error']));
        $this->assertEmpty($this->codeRepo->codes);
    }

    // -------------------------------------------------------------------------
    // checkCodeForPhone
    // -------------------------------------------------------------------------

    public function testCheckCodeReturnsTrueForValidCode(): void
    {
        $this->codeRepo->store('79991234567', '654321');

        $result = $this->service->checkCodeForPhone('79991234567', '654321');

        $this->assertTrue($result['ok']);
        $this->assertTrue($this->codeRepo->markedUsed);
    }

    public function testCheckCodeReturnsFalseWhenCodeNotFound(): void
    {
        $result = $this->service->checkCodeForPhone('79991234567', '000000');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Неверный', $result['error']);
    }

    public function testCheckCodeReturnsFalseForInvalidPhone(): void
    {
        $result = $this->service->checkCodeForPhone('bad', '123456');

        $this->assertFalse($result['ok']);
    }

    public function testCheckCodeReturnsFalseForEmptyCode(): void
    {
        $result = $this->service->checkCodeForPhone('79991234567', '');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('код', mb_strtolower($result['error']));
    }

    // -------------------------------------------------------------------------
    // sendCodeForCurrentVoter / checkCodeForCurrentVoter
    // -------------------------------------------------------------------------

    public function testSendCodeForCurrentVoterUsesPhoneFromSession(): void
    {
        $_SESSION['voter'] = ['phone' => '79990000099'];
        CurlMock::queueResponse(json_encode(['status' => 'success']), '', 200);

        $result = $this->service->sendCodeForCurrentVoter();

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('79990000099', $this->codeRepo->codes);
    }

    public function testSendCodeForCurrentVoterReturnsErrorWhenNoSession(): void
    {
        $result = $this->service->sendCodeForCurrentVoter();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('телефон', mb_strtolower($result['error']));
    }

    public function testCheckCodeForCurrentVoterUsesPhoneFromSession(): void
    {
        $_SESSION['voter'] = ['phone' => '79990000088'];
        $this->codeRepo->store('79990000088', '112233');

        $result = $this->service->checkCodeForCurrentVoter('112233');

        $this->assertTrue($result['ok']);
    }

    public function testCheckCodeForCurrentVoterReturnsErrorWhenNoSession(): void
    {
        $result = $this->service->checkCodeForCurrentVoter('123456');

        $this->assertFalse($result['ok']);
    }
}

// ---------------------------------------------------------------------------
// Inline in-memory PhoneCode repository (avoids MySQL INTERVAL syntax)
// ---------------------------------------------------------------------------

final class InMemoryPhoneCodeRepo implements PhoneCodeRepositoryInterface
{
    /** @var array<string, string> phone → code */
    public array $codes = [];
    public bool $markedUsed = false;
    private int $nextId = 1;
    /** @var array<int, array{phone:string,code:string,used:bool}> */
    private array $rows = [];

    public function store(string $phone, string $code): void
    {
        $this->rows[$this->nextId] = ['phone' => $phone, 'code' => $code, 'used' => false];
        $this->codes[$phone] = $code;
        $this->nextId++;
    }

    public function createCode(string $phone, string $code, ?string $ip = null): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = ['phone' => $phone, 'code' => $code, 'used' => false];
        $this->codes[$phone] = $code;
        return $id;
    }

    public function findValid(string $phone, string $code, int $ttlSeconds = 600): ?array
    {
        foreach ($this->rows as $id => $row) {
            if ($row['phone'] === $phone && $row['code'] === $code && !$row['used']) {
                return ['id' => $id, 'phone' => $phone, 'code' => $code];
            }
        }
        return null;
    }

    public function markUsed(int $id): void
    {
        if (isset($this->rows[$id])) {
            $this->rows[$id]['used'] = true;
        }
        $this->markedUsed = true;
    }
}
