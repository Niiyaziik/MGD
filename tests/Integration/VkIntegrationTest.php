<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repository\Pdo\UserRepository;
use App\Service\UserService;
use DomainException;
use Tests\Integration\Support\DatabaseTestCase;

/**
 * Integration tests for VK-related functionality.
 *
 * No real VK API calls are made. Tests cover:
 *  - UserRepository: createFromVk, findByVkId, updateVkData, updateVkMiddleName
 *  - UserService: register with vk_id, uniqueness constraint for vk_id
 */
final class VkIntegrationTest extends DatabaseTestCase
{
    private UserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new UserRepository(static::$pdo);
    }

    // -------------------------------------------------------------------------
    // UserRepository — VK CRUD
    // -------------------------------------------------------------------------

    public function testCreateFromVkStoresAllVkFields(): void
    {
        $id = $this->repo->createFromVk([
            'surname'        => 'Смирнов',
            'name'           => 'Алексей',
            'patronymic'     => 'Иванович',
            'vk_id'          => 'vk_abc123',
            'link_vk'        => 'https://vk.com/vk_abc123',
            'vk_phone'       => '79990001234',
            'vk_email'       => 'alex@example.com',
            'vk_avatar'      => 'https://cdn.vk.com/avatar.jpg',
            'vk_profile_url' => 'https://vk.com/id123',
            'phone_verified' => 0,
            'auth_method'    => 'ВК',
        ]);

        $this->assertGreaterThan(0, $id);

        $row = $this->repo->findById($id);
        $this->assertSame('Смирнов', $row['surname']);
        $this->assertSame('Алексей', $row['name']);
        $this->assertSame('vk_abc123', $row['vk_id']);
        $this->assertSame('https://vk.com/vk_abc123', $row['link_vk']);
        $this->assertSame('alex@example.com', $row['vk_email']);
        $this->assertSame('ВК', $row['auth_method']);
    }

    public function testFindByVkIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findByVkId('nonexistent_vk_id'));
    }

    public function testFindByVkIdReturnsUserWhenFound(): void
    {
        $id = $this->repo->createFromVk([
            'vk_id'       => 'unique_vk_42',
            'auth_method' => 'ВК',
        ]);

        $row = $this->repo->findByVkId('unique_vk_42');

        $this->assertIsArray($row);
        $this->assertSame($id, (int)$row['id']);
        $this->assertSame('unique_vk_42', $row['vk_id']);
    }

    public function testFindByVkIdReturnsNullForSoftDeletedUser(): void
    {
        $id = $this->repo->createFromVk(['vk_id' => 'deleted_vk', 'auth_method' => 'ВК']);
        static::$pdo->exec("UPDATE users SET deleted_at = datetime('now') WHERE id = {$id}");

        $this->assertNull($this->repo->findByVkId('deleted_vk'));
    }

    public function testUpdateVkDataChangesFields(): void
    {
        $id = $this->repo->createFromVk([
            'vk_id'       => 'vk_upd1',
            'vk_email'    => 'old@example.com',
            'auth_method' => 'ВК',
        ]);

        $this->repo->updateVkData($id, [
            'vk_email'  => 'new@example.com',
            'vk_avatar' => 'https://new-avatar.example.com/img.jpg',
        ]);

        $row = $this->repo->findById($id);
        $this->assertSame('new@example.com', $row['vk_email']);
        $this->assertSame('https://new-avatar.example.com/img.jpg', $row['vk_avatar']);
    }

    public function testUpdateVkDataDoesNotOverwriteWithNull(): void
    {
        $id = $this->repo->createFromVk([
            'vk_id'    => 'vk_upd2',
            'vk_email' => 'keep@example.com',
            'auth_method' => 'ВК',
        ]);

        // Pass null for vk_email — COALESCE should keep the old value
        $this->repo->updateVkData($id, ['vk_email' => null]);

        $row = $this->repo->findById($id);
        $this->assertSame('keep@example.com', $row['vk_email']);
    }

    public function testUpdateVkMiddleNameSetsPatronymic(): void
    {
        $id = $this->repo->createFromVk(['vk_id' => 'vk_mid1', 'auth_method' => 'ВК']);

        $this->repo->updateVkMiddleName($id, 'Петрович');

        $row = $this->repo->findById($id);
        $this->assertSame('Петрович', $row['patronymic']);
    }

    // -------------------------------------------------------------------------
    // UserService — VK registration flow
    // -------------------------------------------------------------------------

    public function testRegisterWithVkIdCreatesUser(): void
    {
        $districtId    = $this->insertDistrict();
        $districtRepo  = $this->makeSimpleDistrictRepo($districtId);
        $service       = new UserService(static::$pdo, $this->repo, $districtRepo);

        $result = $service->register([
            'surname'     => 'Кузнецов',
            'name'        => 'Кузьма',
            'vk_id'       => 'vk_service_test',
            'district_id' => $districtId,
            'auth_method' => 'ВК',
        ]);

        $this->assertArrayHasKey('id', $result);
        $row = $this->repo->findByVkId('vk_service_test');
        $this->assertNotNull($row);
        $this->assertSame('Кузнецов', $row['surname']);
    }

    public function testRegisterThrowsWhenVkIdAlreadyTaken(): void
    {
        $districtId   = $this->insertDistrict();
        $districtRepo = $this->makeSimpleDistrictRepo($districtId);
        $service      = new UserService(static::$pdo, $this->repo, $districtRepo);

        $this->repo->createFromVk(['vk_id' => 'vk_taken', 'auth_method' => 'ВК']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('VK ID уже используется');

        $service->register([
            'surname'     => 'Другой',
            'name'        => 'Пользователь',
            'vk_id'       => 'vk_taken',
            'district_id' => $districtId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper — minimal DistrictRepository stub
    // -------------------------------------------------------------------------

    private function makeSimpleDistrictRepo(int $existingId): \App\Repository\Contract\DistrictRepositoryInterface
    {
        return new class($existingId) implements \App\Repository\Contract\DistrictRepositoryInterface {
            public function __construct(private int $existingId) {}

            public function find(int $id): array
            {
                if ($id === $this->existingId) {
                    return [['id' => $id]];
                }
                throw new \RuntimeException('District not found');
            }

            public function all(): array { return []; }
            public function createStreet(int $d, string $s): void {}
            public function renameStreet(int $d, string $o, string $n): int { return 0; }
            public function createHouse(int $d, string $s, string $h): void {}
            public function updateHouse(int $d, string $s, string $o, string $n): int { return 0; }
            public function deleteHouse(int $d, string $s, string $h): int { return 0; }
            public function getAdminAddresses(int $deleted = 0): array { return []; }
            public function deleteAddress(int $id): void {}
            public function findAddressDuplicates(): array { return []; }
            public function suggest(string $query): array { return []; }
            public function findDistrictByStreetAndHouse(string $s, string $h): ?array { return null; }
        };
    }
}
