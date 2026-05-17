<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repository\Contract\DistrictRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Service\UserService;
use DomainException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UserServiceTest extends TestCase
{
    private function makeDistrictRepo(array $findReturn): DistrictRepositoryInterface
    {
        return new class($findReturn) implements DistrictRepositoryInterface {
            public function __construct(private readonly array $district) {}
            public function all(): array { return []; }
            public function find(int $id): array { return $this->district; }
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

    private function makeUserRepo(
        array $findReturn = [],
        ?array $findByPhoneReturn = null,
        ?array $findByVkReturn = null,
        int $createReturn = 1,
        bool $throwOnCreate = false,
    ): UserRepositoryInterface {
        return new class(
            $findReturn,
            $findByPhoneReturn,
            $findByVkReturn,
            $createReturn,
            $throwOnCreate,
        ) implements UserRepositoryInterface {
            /** @var array<int,array> */
            public array $updated = [];

            public function __construct(
                private array $byId,
                private readonly ?array $byPhone,
                private readonly ?array $byVk,
                private readonly int $newId,
                private readonly bool $throwOnCreate,
            ) {}

            public function firstOrCreateByPhone(string $phone): array { return []; }
            public function find(int $id): array { return $this->byId; }
            public function all(): array { return []; }
            public function findByPhone(string $phone): ?array { return $this->byPhone; }
            public function create(array $data): int
            {
                if ($this->throwOnCreate) {
                    throw new \RuntimeException('db error');
                }
                $this->byId = ['id' => $this->newId] + $data;
                return $this->newId;
            }
            public function update(int $id, array $data): void
            {
                $this->updated[$id] = $data;
                $this->byId = array_merge($this->byId, $data);
            }
            public function delete(int $id): void {}
            public function getAdminUsers(int $deleted = 0): array { return []; }
            public function updateAdminUser(int $id, array $data): void {}
            public function markPhoneVerified(int $id): void {}
            public function getUserDistrictByAddress(string $street, string $house): ?array { return null; }
            public function updateVkData(int $id, array $data): void {}
            public function updateVkMiddleName(int $id, string $middleName): void {}
            public function createFromVk(array $data): int { return 0; }
            public function findByVkId(string $vkId): ?array { return $this->byVk; }
        };
    }

    private function makePdo(): PDO
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        return $pdo;
    }

    public function testRegisterThrowsWhenSurnameOrNameMissing(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(),
            $this->makeDistrictRepo(['id' => 1]),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Имя и фамилия обязательны');

        $service->register(['phone' => '79991234567', 'district_id' => 1]);
    }

    public function testRegisterThrowsWhenNoIdentifier(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(),
            $this->makeDistrictRepo(['id' => 1]),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Нужен хотя бы один идентификатор');

        $service->register(['surname' => 'Иванов', 'name' => 'Иван', 'district_id' => 1]);
    }

    public function testRegisterThrowsWhenDistrictIdMissing(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(),
            $this->makeDistrictRepo(['id' => 1]),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Не указан округ');

        $service->register([
            'surname' => 'Иванов',
            'name'    => 'Иван',
            'phone'   => '79991234567',
        ]);
    }

    public function testRegisterThrowsWhenDistrictNotFound(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(),
            $this->makeDistrictRepo([]),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Округ не найден');

        $service->register([
            'surname'     => 'Иванов',
            'name'        => 'Иван',
            'phone'       => '79991234567',
            'district_id' => 99,
        ]);
    }

    public function testRegisterThrowsWhenPhoneAlreadyUsed(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(findByPhoneReturn: ['id' => 42, 'phone' => '79991234567']),
            $this->makeDistrictRepo(['id' => 1]),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Телефон уже используется');

        $service->register([
            'surname'     => 'Иванов',
            'name'        => 'Иван',
            'phone'       => '79991234567',
            'district_id' => 1,
        ]);
    }

    public function testRegisterThrowsWhenVkIdAlreadyUsed(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(findByVkReturn: ['id' => 7, 'vk_id' => 'vk123']),
            $this->makeDistrictRepo(['id' => 1]),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('VK ID уже используется');

        $service->register([
            'surname'     => 'Иванов',
            'name'        => 'Иван',
            'vk_id'       => 'vk123',
            'district_id' => 1,
        ]);
    }

    public function testRegisterCreatesUserAndReturnsCreatedData(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(createReturn: 10),
            $this->makeDistrictRepo(['id' => 1]),
        );

        $user = $service->register([
            'surname'     => '  Иванов ',
            'name'        => ' Иван ',
            'patronymic'  => ' Иванович ',
            'phone'       => '79991234567',
            'vk_id'       => 'vk123',
            'district_id' => '1',
        ]);

        $this->assertSame(10, $user['id']);
        $this->assertSame('Иванов', $user['surname']);
        $this->assertSame('Иван', $user['name']);
        $this->assertSame(1, $user['district_id']);
        $this->assertSame('Телефон', $user['auth_method']);
    }

    public function testRegisterRollsBackWhenRepositoryThrows(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $service = new UserService(
            $pdo,
            $this->makeUserRepo(throwOnCreate: true),
            $this->makeDistrictRepo(['id' => 1]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Не удалось создать пользователя');

        $service->register([
            'surname'     => 'Иванов',
            'name'        => 'Иван',
            'phone'       => '79991234567',
            'district_id' => 1,
        ]);
    }

    public function testUpdateThrowsWhenUserNotFound(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(findReturn: []),
            $this->makeDistrictRepo(['id' => 1]),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Пользователь не найден');

        $service->update(999, ['surname' => 'Петров']);
    }

    public function testUpdateThrowsWhenNewDistrictNotFound(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(findReturn: ['id' => 1, 'phone' => '79991234567', 'vk_id' => 'vk1']),
            $this->makeDistrictRepo([]),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Округ не найден');

        $service->update(1, ['district_id' => 99]);
    }

    public function testUpdateThrowsWhenPhoneAlreadyUsedByAnotherUser(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(
                findReturn: ['id' => 1, 'phone' => '79991234567', 'vk_id' => 'vk1'],
                findByPhoneReturn: ['id' => 99, 'phone' => '79997654321']
            ),
            $this->makeDistrictRepo(['id' => 1]),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Телефон уже используется');

        $service->update(1, ['phone' => '79997654321']);
    }

    public function testUpdateThrowsWhenVkIdAlreadyUsedByAnotherUser(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(
                findReturn: ['id' => 1, 'phone' => '79991234567', 'vk_id' => 'vk1'],
                findByVkReturn: ['id' => 99, 'vk_id' => 'vk2']
            ),
            $this->makeDistrictRepo(['id' => 1]),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('VK ID уже используется');

        $service->update(1, ['vk_id' => 'vk2']);
    }

    public function testUpdateChangesAllowedFieldsAndReturnsFreshData(): void
    {
        $service = new UserService(
            $this->makePdo(),
            $this->makeUserRepo(findReturn: [
                'id' => 1,
                'surname' => 'Иванов',
                'name' => 'Иван',
                'patronymic' => null,
                'phone' => '79991234567',
                'vk_id' => 'vk1',
                'district_id' => 1,
                'auth_method' => 'Телефон',
            ]),
            $this->makeDistrictRepo(['id' => 2]),
        );

        $user = $service->update(1, [
            'surname'     => ' Петров ',
            'name'        => ' Пётр ',
            'patronymic'  => ' Петрович ',
            'phone'       => '79997654321',
            'vk_id'       => 'vk2',
            'district_id' => 2,
            'auth_method' => 'ВК',
        ]);

        $this->assertSame('Петров', $user['surname']);
        $this->assertSame('Пётр', $user['name']);
        $this->assertSame('Петрович', $user['patronymic']);
        $this->assertSame('79997654321', $user['phone']);
        $this->assertSame('vk2', $user['vk_id']);
        $this->assertSame(2, $user['district_id']);
        $this->assertSame('ВК', $user['auth_method']);
    }
}
