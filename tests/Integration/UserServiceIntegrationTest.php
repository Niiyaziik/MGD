<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repository\Contract\DistrictRepositoryInterface;
use App\Repository\Pdo\UserRepository;
use App\Service\UserService;
use DomainException;
use RuntimeException;
use Tests\Integration\Support\DatabaseTestCase;

final class UserServiceIntegrationTest extends DatabaseTestCase
{
    private UserService $service;
    private UserRepository $userRepo;
    private int $districtId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepo   = new UserRepository(static::$pdo);
        $this->districtId = $this->insertDistrict();

        $districtRepo  = $this->makeDistrictRepo();
        $this->service = new UserService(static::$pdo, $this->userRepo, $districtRepo);
    }

    // -------------------------------------------------------------------------
    // register
    // -------------------------------------------------------------------------

    public function testRegisterCreatesUserAndReturnsData(): void
    {
        $result = $this->service->register([
            'surname'     => 'Иванов',
            'name'        => 'Иван',
            'phone'       => '79991234567',
            'district_id' => $this->districtId,
            'auth_method' => 'Телефон',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);

        // Verify the record is actually in the DB
        $stored = $this->userRepo->findById((int)$result['id']);
        $this->assertSame('Иванов', $stored['surname']);
        $this->assertSame('79991234567', $stored['phone']);
    }

    public function testRegisterThrowsWhenDistrictDoesNotExist(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Округ не найден');

        $this->service->register([
            'surname'     => 'Петров',
            'name'        => 'Петр',
            'phone'       => '79990000001',
            'district_id' => 9999,
        ]);
    }

    public function testRegisterThrowsWhenPhoneAlreadyUsed(): void
    {
        $this->insertUser(['phone' => '79991111111']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Телефон уже используется');

        $this->service->register([
            'surname'     => 'Сидоров',
            'name'        => 'Сидор',
            'phone'       => '79991111111',
            'district_id' => $this->districtId,
        ]);
    }

    public function testRegisterThrowsWhenVkIdAlreadyUsed(): void
    {
        $this->insertUser(['phone' => '79991111112', 'vk_id' => 'vk123']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('VK ID уже используется');

        $this->service->register([
            'surname'     => 'Новый',
            'name'        => 'Пользователь',
            'vk_id'       => 'vk123',
            'district_id' => $this->districtId,
        ]);
    }

    public function testRegisterThrowsWhenNameMissing(): void
    {
        $this->expectException(DomainException::class);

        $this->service->register([
            'surname'     => '',
            'name'        => '',
            'phone'       => '79990000002',
            'district_id' => $this->districtId,
        ]);
    }

    public function testRegisterThrowsWhenNoIdentifier(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Нужен хотя бы один идентификатор');

        $this->service->register([
            'surname'     => 'Тестов',
            'name'        => 'Тест',
            'district_id' => $this->districtId,
        ]);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function testUpdateThrowsWhenUserNotFound(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Пользователь не найден');

        $this->service->update(9999, ['surname' => 'Новый']);
    }

    public function testUpdateChangesAllowedFields(): void
    {
        $id = $this->insertUser(['phone' => '79993210001']);

        $result = $this->service->update($id, ['surname' => 'Обновлённый', 'name' => 'Имя']);

        $this->assertSame('Обновлённый', $result['surname'] ?? $result['surname']);
        $stored = $this->userRepo->findById($id);
        $this->assertSame('Обновлённый', $stored['surname']);
    }

    public function testUpdateThrowsWhenPhoneAlreadyUsedByAnotherUser(): void
    {
        $id1 = $this->insertUser(['phone' => '79993210002']);
        $this->insertUser(['phone' => '79993210003']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Телефон уже используется');

        $this->service->update($id1, ['phone' => '79993210003']);
    }

    // -------------------------------------------------------------------------
    // Helper: mock DistrictRepository that checks our SQLite districts table
    // -------------------------------------------------------------------------

    private function makeDistrictRepo(): DistrictRepositoryInterface
    {
        $pdo = static::$pdo;

        return new class($pdo) implements DistrictRepositoryInterface {
            public function __construct(private \PDO $pdo) {}

            public function find(int $id): array
            {
                $st = $this->pdo->prepare(
                    "SELECT id FROM districts WHERE id = ? AND deleted_at IS NULL"
                );
                $st->execute([$id]);
                $row = $st->fetch();
                if (!$row) {
                    throw new \RuntimeException('District not found');
                }
                return [$row];
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
        };
    }
}
