<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Repository\Contract\DistrictRepositoryInterface;
use App\Repository\Contract\PhoneCodeRepositoryInterface;
use App\Repository\Pdo\AdminRepository;
use App\Repository\Pdo\CandidateRepository;
use App\Repository\Pdo\UserRepository;
use App\Repository\Pdo\VoteRepository;
use App\Service\AdminService;
use App\Service\SmsService;
use App\Service\UserService;
use App\Service\VoteService;
use DomainException;
use Tests\Integration\Support\DatabaseTestCase;

final class SecuritySemiAutomatedTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testAdminPanelIsNotAvailableWithoutSession(): void
    {
        $service = new AdminService(new AdminRepository(static::$pdo));

        $this->assertFalse($service->check());
        $this->assertArrayNotHasKey('admin_id', $_SESSION);
    }

    public function testAdminLoginWithWrongPasswordDoesNotCreateSession(): void
    {
        $this->insertAdmin('admin', 'correct-password');
        $service = new AdminService(new AdminRepository(static::$pdo));

        $result = $service->attemptLogin('admin', 'wrong-password');

        $this->assertFalse($result['ok']);
        $this->assertArrayNotHasKey('admin_id', $_SESSION);
    }

    public function testDeletedAdminCannotLogin(): void
    {
        $this->insertAdmin('deleted-admin', 'password', deletedAt: date('Y-m-d H:i:s'));
        $service = new AdminService(new AdminRepository(static::$pdo));

        $result = $service->attemptLogin('deleted-admin', 'password');

        $this->assertFalse($result['ok']);
        $this->assertArrayNotHasKey('admin_id', $_SESSION);
    }

    public function testAdminPasswordIsStoredAsHash(): void
    {
        $this->insertAdmin('hash-check', 'plain-password');

        $hash = static::$pdo
            ->query("SELECT password_hash FROM admins WHERE login = 'hash-check'")
            ->fetchColumn();

        $this->assertIsString($hash);
        $this->assertNotSame('plain-password', $hash);
        $this->assertTrue(password_verify('plain-password', $hash));
    }

    public function testSqlInjectionLikeAdminLoginDoesNotAuthenticate(): void
    {
        $this->insertAdmin('safe-admin', 'safe-password');
        $service = new AdminService(new AdminRepository(static::$pdo));

        $result = $service->attemptLogin("' OR '1'='1", "' OR '1'='1");

        $this->assertFalse($result['ok']);
        $this->assertArrayNotHasKey('admin_id', $_SESSION);
    }

    public function testLogoutClearsAdminSession(): void
    {
        $this->insertAdmin('logout-admin', 'password');
        $service = new AdminService(new AdminRepository(static::$pdo));

        $login = $service->attemptLogin('logout-admin', 'password');
        $this->assertTrue($login['ok']);
        $this->assertArrayHasKey('admin_id', $_SESSION);

        $service->logout();

        $this->assertFalse($service->check());
        $this->assertArrayNotHasKey('admin_id', $_SESSION);
    }

    public function testInvalidPhoneDoesNotCreateSmsCode(): void
    {
        $repo = new SecurityPhoneCodeRepositoryStub();
        $service = new SmsService($repo);

        $result = $service->sendCodeToPhone('not-a-phone');

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $repo->createdCount);
    }

    public function testWrongSmsCodeIsRejected(): void
    {
        $repo = new SecurityPhoneCodeRepositoryStub();
        $repo->createCode('79990000001', '123456', '127.0.0.1');
        $service = new SmsService($repo);

        $result = $service->checkCodeForPhone('79990000001', '000000');

        $this->assertFalse($result['ok']);
        $this->assertFalse($repo->wasMarkedUsed);
    }

    public function testUsedSmsCodeCannotBeReused(): void
    {
        $repo = new SecurityPhoneCodeRepositoryStub();
        $codeId = $repo->createCode('79990000001', '123456', '127.0.0.1');
        $repo->markUsed($codeId);
        $service = new SmsService($repo);

        $result = $service->checkCodeForPhone('79990000001', '123456');

        $this->assertFalse($result['ok']);
    }

    public function testVoteForNonExistingCandidateIsRejected(): void
    {
        $userId = $this->insertUser(['phone' => '79990000001']);
        $service = $this->makeVoteService();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Кандидат не найден');

        $service->castVote($userId, 999999);
    }

    public function testVoteFromNonExistingUserIsRejected(): void
    {
        $candidateId = $this->insertCandidate(['phone' => '79991000001']);
        $service = $this->makeVoteService();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Пользователь не найден');

        $service->castVote(999999, $candidateId);
    }

    public function testRepeatedVotingIsRejected(): void
    {
        $userId = $this->insertUser(['phone' => '79990000001']);
        $candidateId = $this->insertCandidate(['phone' => '79991000001']);
        $secondCandidateId = $this->insertCandidate(['phone' => '79991000002']);
        $service = $this->makeVoteService();

        $service->castVote($userId, $candidateId);

        $this->expectException(\Throwable::class);
        $service->castVote($userId, $secondCandidateId);
    }

    public function testDuplicateVkIdIsRejected(): void
    {
        $districtId = $this->insertDistrict();
        $userRepo = new UserRepository(static::$pdo);
        $service = new UserService(
            static::$pdo,
            $userRepo,
            new SecurityDistrictRepositoryStub($districtId),
        );

        $userRepo->createFromVk([
            'surname' => 'Иванов',
            'name' => 'Иван',
            'vk_id' => 'vk-duplicate',
            'auth_method' => 'ВК',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('VK ID уже используется');

        $service->register([
            'surname' => 'Петров',
            'name' => 'Петр',
            'vk_id' => 'vk-duplicate',
            'district_id' => $districtId,
            'auth_method' => 'ВК',
        ]);
    }

    public function testDuplicatePhoneIsRejected(): void
    {
        $districtId = $this->insertDistrict();
        $this->insertUser(['phone' => '79990000001']);

        $service = new UserService(
            static::$pdo,
            new UserRepository(static::$pdo),
            new SecurityDistrictRepositoryStub($districtId),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Телефон уже используется');

        $service->register([
            'surname' => 'Сидоров',
            'name' => 'Сидор',
            'phone' => '79990000001',
            'district_id' => $districtId,
            'auth_method' => 'Телефон',
        ]);
    }

    private function makeVoteService(): VoteService
    {
        return new VoteService(
            static::$pdo,
            new UserRepository(static::$pdo),
            new CandidateRepository(static::$pdo),
            new VoteRepository(static::$pdo),
        );
    }
}

final class SecurityPhoneCodeRepositoryStub implements PhoneCodeRepositoryInterface
{
    public int $createdCount = 0;
    public bool $wasMarkedUsed = false;

    /** @var array<int, array{phone:string, code:string, used:bool}> */
    private array $codes = [];
    private int $nextId = 1;

    public function createCode(string $phone, string $code, ?string $ip = null): int
    {
        $id = $this->nextId++;
        $this->codes[$id] = [
            'phone' => $phone,
            'code' => $code,
            'used' => false,
        ];
        $this->createdCount++;
        return $id;
    }

    public function findValid(string $phone, string $code, int $ttlSeconds = 600): ?array
    {
        foreach ($this->codes as $id => $row) {
            if ($row['phone'] === $phone && $row['code'] === $code && !$row['used']) {
                return [
                    'id' => $id,
                    'phone' => $phone,
                    'code' => $code,
                ];
            }
        }

        return null;
    }

    public function markUsed(int $id): void
    {
        if (isset($this->codes[$id])) {
            $this->codes[$id]['used'] = true;
        }

        $this->wasMarkedUsed = true;
    }
}

final class SecurityDistrictRepositoryStub implements DistrictRepositoryInterface
{
    public function __construct(private int $existingDistrictId) {}

    public function find(int $id): array
    {
        if ($id === $this->existingDistrictId) {
            return [['id' => $id]];
        }

        throw new \RuntimeException('District not found');
    }

    public function all(): array { return []; }
    public function createStreet(int $districtId, string $street): void {}
    public function renameStreet(int $districtId, string $oldStreet, string $newStreet): int { return 0; }
    public function createHouse(int $districtId, string $street, string $house): void {}
    public function updateHouse(int $districtId, string $street, string $oldHouse, string $newHouse): int { return 0; }
    public function deleteHouse(int $districtId, string $street, string $house): int { return 0; }
    public function getAdminAddresses(int $deleted = 0): array { return []; }
    public function deleteAddress(int $id): void {}
    public function findAddressDuplicates(): array { return []; }
    public function suggest(string $query): array { return []; }
    public function findDistrictByStreetAndHouse(string $streetName, string $houseValue): ?array { return null; }
}
