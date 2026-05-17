<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use App\Controller\AdminController;
use App\Controller\AuthController;
use App\Repository\Contract\AdminRepositoryInterface;
use App\Repository\Contract\CandidateRepositoryInterface;
use App\Repository\Contract\DistrictRepositoryInterface;
use App\Repository\Contract\PhoneCodeRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Repository\Contract\VoteRepositoryInterface;
use App\Service\AdminService;
use App\Service\SmsService;
use RuntimeException;

final class ArrayContainer
{
    /** @param array<class-string, object> $items */
    public function __construct(private array $items) {}

    public function get(string $id): object
    {
        return $this->items[$id] ?? throw new RuntimeException("Container entry {$id} not found");
    }
}

final class TestAdminController extends AdminController
{
    public array $jsonBody = [];

    protected function getJsonBody(): array
    {
        return $this->jsonBody;
    }
}

final class TestAuthController extends AuthController
{
    public array $jsonBody = [];

    protected function getJsonBody(): array
    {
        return $this->jsonBody;
    }
}

final class FakeAdminService extends AdminService
{
    public function __construct(
        private bool $loggedIn = false,
        private bool $loginOk = true,
    ) {
        parent::__construct(new NullAdminRepository());
    }

    public function check(): bool
    {
        return $this->loggedIn;
    }

    public function attemptLogin(string $login, string $password): array
    {
        if (!$this->loginOk || $login === '' || $password === '') {
            return ['ok' => false, 'error' => 'Неверный логин или пароль', 'admin' => null];
        }

        $_SESSION['admin_id'] = 1;
        return ['ok' => true, 'error' => null, 'admin' => ['id' => 1, 'login' => $login]];
    }

    public function logout(): void
    {
        unset($_SESSION['admin_id']);
    }
}

final class FakeSmsService extends SmsService
{
    public bool $sendOk = true;
    public bool $checkOk = true;
    public ?string $lastPhone = null;
    public ?string $lastCode = null;

    public function __construct()
    {
        parent::__construct(new NullPhoneCodeRepository());
    }

    public function sendCodeToPhone(string $rawPhone): array
    {
        $this->lastPhone = $rawPhone;
        return $this->sendOk
            ? ['ok' => true, 'sms_sent' => true]
            : ['ok' => false, 'error' => 'SMS disabled in test'];
    }

    public function sendCodeForCurrentVoter(): array
    {
        $this->lastPhone = (string)($_SESSION['voter']['phone'] ?? '');
        return $this->sendOk
            ? ['ok' => true, 'sms_sent' => true]
            : ['ok' => false, 'error' => 'No voter phone'];
    }

    public function checkCodeForPhone(string $rawPhone, string $code): array
    {
        $this->lastPhone = $rawPhone;
        $this->lastCode = $code;
        return $this->checkOk
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Bad code'];
    }

    public function checkCodeForCurrentVoter(string $code): array
    {
        $this->lastPhone = (string)($_SESSION['voter']['phone'] ?? '');
        $this->lastCode = $code;
        return $this->checkOk
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Bad code'];
    }
}

final class FakeUserRepository implements UserRepositoryInterface
{
    /** @var array<int, array<string,mixed>> */
    public array $users = [];
    public int $nextId = 1;
    public ?array $lastCreated = null;
    public ?array $lastProfileUpdate = null;
    public ?array $lastVkUpdate = null;
    public ?string $lastMiddleName = null;

    public function firstOrCreateByPhone(string $phone): array
    {
        $existing = $this->findByPhone($phone);
        if ($existing) {
            return $existing;
        }
        $id = $this->create(['phone' => $phone]);
        return $this->find($id);
    }

    public function find(int $id): array
    {
        return $this->users[$id] ?? throw new RuntimeException('User not found');
    }

    public function all(): array
    {
        return array_values($this->users);
    }

    public function findByPhone(string $phone): ?array
    {
        foreach ($this->users as $user) {
            if (($user['phone'] ?? null) === $phone && empty($user['deleted_at'])) {
                return $user;
            }
        }
        return null;
    }

    public function create(array $data): int
    {
        $id = $this->nextId++;
        $this->lastCreated = $data;
        $this->users[$id] = ['id' => $id] + $data;
        return $id;
    }

    public function update(int $id, array $data): void
    {
        $this->users[$id] = array_merge($this->users[$id] ?? ['id' => $id], $data);
    }

    public function updateProfile(int $id, array $data): void
    {
        $this->lastProfileUpdate = ['id' => $id, 'data' => $data];
        $this->update($id, $data);
    }

    public function delete(int $id): void
    {
        $this->users[$id]['deleted_at'] = date('Y-m-d H:i:s');
    }

    public function getAdminUsers(int $deleted = 0): array
    {
        return array_values($this->users);
    }

    public function updateAdminUser(int $id, array $data): void
    {
        $this->update($id, $data);
    }

    public function markPhoneVerified(int $id): void
    {
        $this->update($id, ['phone_verified' => 1]);
    }

    public function getUserDistrictByAddress(string $street, string $house): ?array
    {
        return null;
    }

    public function updateVkData(int $id, array $data): void
    {
        $this->lastVkUpdate = ['id' => $id, 'data' => $data];
        $this->update($id, $data);
    }

    public function updateVkMiddleName(int $id, string $middleName): void
    {
        $this->lastMiddleName = $middleName;
        $this->update($id, ['patronymic' => $middleName]);
    }

    public function createFromVk(array $data): int
    {
        return $this->create($data);
    }

    public function findByVkId(string $vkId): ?array
    {
        foreach ($this->users as $user) {
            if (($user['vk_id'] ?? null) === $vkId && empty($user['deleted_at'])) {
                return $user;
            }
        }
        return null;
    }
}

final class FakeCandidateRepository implements CandidateRepositoryInterface
{
    /** @var array<int, array<string,mixed>> */
    public array $candidates = [];
    /** @var array<int, array<string,mixed>> */
    public array $districtsByCandidate = [];

    public function all(?int $districtId = null): array
    {
        return array_values($this->candidates);
    }

    public function find(int $id): array
    {
        return $this->candidates[$id] ?? throw new RuntimeException('Candidate not found');
    }

    public function first(int $limit = 5): array
    {
        return array_slice(array_values($this->candidates), 0, $limit);
    }

    public function create(array $data): int
    {
        $id = count($this->candidates) + 1;
        $this->candidates[$id] = ['id' => $id] + $data;
        return $id;
    }

    public function update(int $id, array $data): void
    {
        $this->candidates[$id] = array_merge($this->candidates[$id] ?? ['id' => $id], $data);
    }

    public function delete(int $id): void
    {
        unset($this->candidates[$id]);
    }

    public function getAdminCandidates(int $deleted = 0): array
    {
        return array_values($this->candidates);
    }

    public function getCandidateDistrict(int $candidateId): ?array
    {
        return $this->districtsByCandidate[$candidateId] ?? null;
    }
}

final class FakeVoteRepository implements VoteRepositoryInterface
{
    /** @var array<int,bool> */
    public array $votedUsers = [];
    public bool $throwOnVote = false;

    public function all(): array
    {
        return [];
    }

    public function userHasVote(int $userId): bool
    {
        return $this->votedUsers[$userId] ?? false;
    }

    public function createVote(int $userId, int $candidateId): void
    {
        $this->votedUsers[$userId] = true;
    }

    public function allWithRelations(): array
    {
        return [];
    }

    public function voted(int $userId, int $candidateId): array
    {
        if ($this->throwOnVote || $this->userHasVote($userId)) {
            throw new \DomainException('Вы уже голосовали');
        }
        $this->votedUsers[$userId] = true;
        return ['id' => 1, 'user_id' => $userId, 'candidate_id' => $candidateId];
    }

    public function getAdminList(bool $onlyDeleted = false): array
    {
        return [];
    }

    public function softDelete(int $voteId): bool
    {
        return true;
    }

    public function getDeletedAdminList(): array
    {
        return [];
    }

    public function adminUpdate(int $voteId, array $fields): bool
    {
        return true;
    }
}

final class FakeDistrictRepository implements DistrictRepositoryInterface
{
    /** @var array<int, array<int, array<string,mixed>>> */
    public array $districtRows = [];
    /** @var array<string, array<string,mixed>> */
    public array $addresses = [];
    /** @var array<int, array<string,mixed>> */
    public array $suggestions = [];

    public function all(): array
    {
        return [['id' => 1, 'district' => '1']];
    }

    public function find(int $id): array
    {
        return $this->districtRows[$id] ?? [];
    }

    public function createStreet(int $districtId, string $street): void {}
    public function renameStreet(int $districtId, string $oldStreet, string $newStreet): int { return 1; }
    public function createHouse(int $districtId, string $street, string $house): void {}
    public function updateHouse(int $districtId, string $street, string $oldHouse, string $newHouse): int { return 1; }
    public function deleteHouse(int $districtId, string $street, string $house): int { return 1; }
    public function getAdminAddresses(int $deleted = 0): array { return []; }
    public function deleteAddress(int $id): void {}
    public function findAddressDuplicates(): array { return []; }

    public function suggest(string $query): array
    {
        return $this->suggestions;
    }

    public function findDistrictByStreetAndHouse(string $streetName, string $houseValue): ?array
    {
        return $this->addresses[$streetName . '|' . $houseValue] ?? null;
    }
}

final class NullPhoneCodeRepository implements PhoneCodeRepositoryInterface
{
    public function createCode(string $phone, string $code, ?string $ip = null): int { return 1; }
    public function findValid(string $phone, string $code, int $ttlSeconds = 600): ?array { return null; }
    public function markUsed(int $id): void {}
}

final class NullAdminRepository implements AdminRepositoryInterface
{
    public function findByLogin(string $login): ?array { return null; }
    public function find(int $id): ?array { return null; }
}
