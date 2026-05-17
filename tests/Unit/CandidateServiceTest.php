<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repository\Contract\CandidateRepositoryInterface;
use App\Service\CandidateService;
use DomainException;
use PHPUnit\Framework\TestCase;

final class CandidateServiceTest extends TestCase
{
    public function testCreateCandidateWithoutSurnameThrowsException(): void
    {
        $service = new CandidateService($this->createMock(CandidateRepositoryInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Фамилия обязательна');

        $service->create([
            'name' => 'Иван',
            'district_id' => 1,
        ]);
    }

    public function testCreateCandidateWithoutNameThrowsException(): void
    {
        $service = new CandidateService($this->createMock(CandidateRepositoryInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Имя обязательно');

        $service->create([
            'surname' => 'Иванов',
            'district_id' => 1,
        ]);
    }

    public function testCreateCandidateWithoutDistrictThrowsException(): void
    {
        $service = new CandidateService($this->createMock(CandidateRepositoryInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Округ обязателен');

        $service->create([
            'surname' => 'Иванов',
            'name' => 'Иван',
        ]);
    }

    public function testCreateCandidateWithInvalidEmailThrowsException(): void
    {
        $service = new CandidateService($this->createMock(CandidateRepositoryInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Некорректный email');

        $service->create([
            'surname' => 'Иванов',
            'name' => 'Иван',
            'district_id' => 1,
            'email' => 'bad-email',
        ]);
    }

    public function testCreateCandidateWithInvalidPhoneThrowsException(): void
    {
        $service = new CandidateService($this->createMock(CandidateRepositoryInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Некорректный телефон');

        $service->create([
            'surname' => 'Иванов',
            'name' => 'Иван',
            'district_id' => 1,
            'phone' => '123',
        ]);
    }

    public function testCreateCandidateWithZeroDistrictThrowsException(): void
    {
        $service = new CandidateService($this->createMock(CandidateRepositoryInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Некорректный округ');

        $service->create([
            'surname'     => 'Иванов',
            'name'        => 'Иван',
            'district_id' => 0,
        ]);
    }

    public function testCreateCandidateWithVkIdWithSpacesThrowsException(): void
    {
        $service = new CandidateService($this->createMock(CandidateRepositoryInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('vk_id не должен содержать пробелы');

        $service->create([
            'surname'     => 'Иванов',
            'name'        => 'Иван',
            'district_id' => 1,
            'vk_id'       => 'vk id with spaces',
        ]);
    }

    public function testCreateCandidateDelegatesCleanDataToRepository(): void
    {
        $repo = $this->createMock(CandidateRepositoryInterface::class);

        $repo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data['surname'] === 'Петров'
                    && $data['name'] === 'Пётр'
                    && $data['patronymic'] === null
                    && $data['district_id'] === 2
                    && $data['email'] === 'petr@example.ru'
                    && $data['phone'] === '+79991234567'
                    && $data['vk_id'] === 'id123'
                    && $data['photo'] === '/uploads/photo.jpg'
                    && !array_key_exists('unknown_field', $data);
            }))
            ->willReturn(15);

        $service = new CandidateService($repo);

        $id = $service->create([
            'surname'       => '  Петров  ',
            'name'          => '  Пётр ',
            'patronymic'    => '   ',
            'district_id'   => '2',
            'email'         => ' petr@example.ru ',
            'phone'         => '+7 (999) 123-45-67',
            'vk_id'         => 'id123',
            'photo'         => '/uploads/photo.jpg',
            'unknown_field' => 'ignored',
        ]);

        $this->assertSame(15, $id);
    }

    public function testCreateCandidateAcceptsAbsolutePhotoUrl(): void
    {
        $repo = $this->createMock(CandidateRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('create')
            ->with($this->arrayHasKey('photo'))
            ->willReturn(9);

        $service = new CandidateService($repo);

        $this->assertSame(9, $service->create([
            'surname'     => 'Сидоров',
            'name'        => 'Сидор',
            'district_id' => 1,
            'photo'       => 'https://example.com/photo.jpg',
        ]));
    }

    public function testCreateCandidateWithInvalidPhotoThrowsException(): void
    {
        $service = new CandidateService($this->createMock(CandidateRepositoryInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Некорректный путь к фото');

        $service->create([
            'surname'     => 'Иванов',
            'name'        => 'Иван',
            'district_id' => 1,
            'photo'       => 'http://',
        ]);
    }

    public function testUpdateThrowsWhenCandidateNotFound(): void
    {
        $repo = $this->createMock(CandidateRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn([]);

        $service = new CandidateService($repo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Кандидат не найден');

        $service->update(999, ['name' => 'Новое имя']);
    }

    public function testUpdateDelegatesCleanPatchToRepositoryWhenCandidateExists(): void
    {
        $repo = $this->createMock(CandidateRepositoryInterface::class);
        $repo->method('find')->with(1)->willReturn(['id' => 1, 'name' => 'Иван']);

        $repo->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function (array $data): bool {
                return $data['name'] === 'Пётр'
                    && $data['phone'] === '89991234567'
                    && $data['patronymic'] === null;
            }));

        $service = new CandidateService($repo);
        $service->update(1, [
            'name'       => ' Пётр ',
            'phone'      => '8 (999) 123-45-67',
            'patronymic' => '   ',
        ]);
    }

    public function testUpdateWithInvalidDistrictThrowsException(): void
    {
        $repo = $this->createMock(CandidateRepositoryInterface::class);
        $repo->method('find')->with(1)->willReturn(['id' => 1]);

        $service = new CandidateService($repo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Некорректный округ');

        $service->update(1, ['district_id' => -1]);
    }

    public function testDeleteThrowsWhenCandidateNotFound(): void
    {
        $repo = $this->createMock(CandidateRepositoryInterface::class);
        $repo->method('find')->with(777)->willReturn([]);

        $service = new CandidateService($repo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Кандидат не найден');

        $service->delete(777);
    }

    public function testDeleteDelegatesToRepositoryWhenCandidateExists(): void
    {
        $repo = $this->createMock(CandidateRepositoryInterface::class);
        $repo->method('find')->with(5)->willReturn(['id' => 5]);
        $repo->expects($this->once())->method('delete')->with(5);

        $service = new CandidateService($repo);
        $service->delete(5);
    }
}
