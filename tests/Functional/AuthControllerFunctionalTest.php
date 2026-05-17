<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Functional\Support\FakeCandidateRepository;
use Tests\Functional\Support\FakeDistrictRepository;
use Tests\Functional\Support\FakeSmsService;
use Tests\Functional\Support\FakeUserRepository;
use Tests\Functional\Support\FakeVoteRepository;
use Tests\Functional\Support\FunctionalTestCase;
use Tests\Functional\Support\NullPhoneCodeRepository;
use Tests\Functional\Support\TestAuthController;

final class AuthControllerFunctionalTest extends FunctionalTestCase
{
    private FakeUserRepository $users;
    private FakeCandidateRepository $candidates;
    private FakeVoteRepository $votes;
    private FakeDistrictRepository $districts;
    private FakeSmsService $sms;
    private TestAuthController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = new FakeUserRepository();
        $this->candidates = new FakeCandidateRepository();
        $this->votes = new FakeVoteRepository();
        $this->districts = new FakeDistrictRepository();
        $this->sms = new FakeSmsService();

        $this->candidates->districtsByCandidate[10] = [
            'id' => 1,
            'district' => '1',
            'number' => '1',
        ];
        $this->candidates->districtsByCandidate[20] = [
            'id' => 2,
            'district' => '2',
            'number' => '2',
        ];
        $this->districts->addresses['ул Ленина|10'] = [
            'district_id' => 1,
            'district_number' => '1',
            'street_id' => 101,
            'house_id' => 201,
        ];

        $this->controller = new TestAuthController(
            new NullPhoneCodeRepository(),
            $this->candidates,
            $this->users,
            $this->votes,
            $this->districts,
            $this->sms,
        );
    }

    public function testStatusReturnsUnauthorizedWhenNoVoterSession(): void
    {
        $this->useRequest('GET', '/auth/status');

        $response = $this->capture(fn () => $this->controller->status());

        $this->assertSame(200, $response['status']);
        $this->assertSame(['ok' => true, 'authorized' => false], $response['json']);
    }

    public function testPrecheckReturnsErrorWhenPhoneMissing(): void
    {
        $this->controller->jsonBody = [];
        $this->useRequest('POST', '/auth/precheck');

        $response = $this->capture(fn () => $this->controller->precheck());

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['json']['ok']);
    }

    public function testPrecheckBlocksPhoneThatAlreadyVoted(): void
    {
        $id = $this->users->create(['phone' => '+7 (999) 123-45-67']);
        $this->votes->votedUsers[$id] = true;

        $this->controller->jsonBody = ['phone' => '+7 (999) 123-45-67'];
        $this->useRequest('POST', '/auth/precheck');

        $response = $this->capture(fn () => $this->controller->precheck());

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['json']['ok']);
        $this->assertStringContainsString('уже проголосовал', $response['json']['error']);
    }

    public function testLoginCreatesVoterSessionWhenAddressAndCandidateDistrictMatch(): void
    {
        $this->controller->jsonBody = [
            'fio' => 'Иванов Иван Иванович',
            'phone' => '+7 (999) 123-45-67',
            'address' => 'г. Ульяновск, ул Ленина, д. 10',
            'candidate_id' => 10,
        ];
        $this->useRequest('POST', '/auth/login');

        $response = $this->capture(fn () => $this->controller->login());

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['json']['ok']);
        $this->assertTrue($response['json']['can_vote']);
        $this->assertSame(1, $response['json']['user_district_id']);
        $this->assertSame(1, $_SESSION['voter']['district_id']);
        $this->assertSame('+7 (999) 123-45-67', $_SESSION['voter']['phone']);
    }

    public function testLoginReturnsCanVoteFalseForDifferentDistrict(): void
    {
        $this->controller->jsonBody = [
            'fio' => 'Иванов Иван Иванович',
            'phone' => '+7 (999) 123-45-67',
            'address' => 'г. Ульяновск, ул Ленина, д. 10',
            'candidate_id' => 20,
        ];
        $this->useRequest('POST', '/auth/login');

        $response = $this->capture(fn () => $this->controller->login());

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['json']['ok']);
        $this->assertFalse($response['json']['can_vote']);
        $this->assertSame('wrong_district', $response['json']['reason']);
    }

    public function testLoginRejectsAddressThatCannotBeMappedToDistrict(): void
    {
        $this->controller->jsonBody = [
            'fio' => 'Иванов Иван Иванович',
            'phone' => '+7 (999) 123-45-67',
            'address' => 'г. Ульяновск, неизвестная улица, д. 1',
            'candidate_id' => 10,
        ];
        $this->useRequest('POST', '/auth/login');

        $response = $this->capture(fn () => $this->controller->login());

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['json']['ok']);
        $this->assertStringContainsString('адрес', mb_strtolower($response['json']['error']));
    }

    public function testSendCodeUsesSmsServiceWithoutRealSms(): void
    {
        $this->controller->jsonBody = ['phone' => '+7 (999) 123-45-67'];
        $this->useRequest('POST', '/auth/send-code');

        $response = $this->capture(fn () => $this->controller->sendCode());

        $this->assertSame(200, $response['status']);
        $this->assertSame(['ok' => true, 'sms_sent' => true], $response['json']);
        $this->assertSame('+7 (999) 123-45-67', $this->sms->lastPhone);
    }

    public function testSendCodeReturns422WhenSmsServiceFails(): void
    {
        $this->sms->sendOk = false;
        $this->controller->jsonBody = ['phone' => '+7 (999) 123-45-67'];
        $this->useRequest('POST', '/auth/send-code');

        $response = $this->capture(fn () => $this->controller->sendCode());

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['json']['ok']);
    }

    public function testCheckCodePassesPhoneAndCodeToSmsService(): void
    {
        $this->controller->jsonBody = ['phone' => '+7 (999) 123-45-67', 'code' => '123456'];
        $this->useRequest('POST', '/auth/check-code');

        $response = $this->capture(fn () => $this->controller->checkCode());

        $this->assertSame(200, $response['status']);
        $this->assertSame(['ok' => true], $response['json']);
        $this->assertSame('+7 (999) 123-45-67', $this->sms->lastPhone);
        $this->assertSame('123456', $this->sms->lastCode);
    }

    public function testCheckDistrictRequiresVoterSession(): void
    {
        $this->controller->jsonBody = ['candidate_id' => 10];
        $this->useRequest('POST', '/auth/check-district');

        $response = $this->capture(fn () => $this->controller->checkDistrict());

        $this->assertSame(401, $response['status']);
        $this->assertFalse($response['json']['ok']);
    }

    public function testCheckDistrictReturnsOkWhenDistrictMatches(): void
    {
        $_SESSION['voter'] = ['user_id' => 1, 'district_id' => 1, 'district_num' => '1'];
        $this->controller->jsonBody = ['candidate_id' => 10];
        $this->useRequest('POST', '/auth/check-district');

        $response = $this->capture(fn () => $this->controller->checkDistrict());

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['json']['ok']);
        $this->assertTrue($response['json']['can_vote']);
    }

    public function testVkConfigReturnsPublicConfigWithoutExternalCall(): void
    {
        $this->useRequest('GET', '/auth/vk/config');

        $response = $this->capture(fn () => $this->controller->vkConfig());

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['json']['ok']);
        $this->assertArrayHasKey('app', $response['json']);
        $this->assertArrayHasKey('redirectUrl', $response['json']);
    }

    public function testVkOneTapCreatesSessionFromPayloadWithoutExternalVkRequest(): void
    {
        $this->controller->jsonBody = [
            'user' => [
                'id' => '12345',
                'first_name' => 'иван',
                'last_name' => 'иванов',
                'phone' => '+7 (999) 123-45-67',
                'email' => 'ivan@example.test',
            ],
        ];
        $this->useRequest('POST', '/auth/vk/onetap');

        $response = $this->capture(fn () => $this->controller->vkOneTap());

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['json']['ok']);
        $this->assertSame('12345', $_SESSION['vk_login']['vk_id']);
        $this->assertSame('Иван', $_SESSION['vk_login']['first_name']);
        $this->assertSame('Иванов', $_SESSION['vk_login']['last_name']);
    }

    public function testVkMiddleNameRequiresVkSession(): void
    {
        $this->controller->jsonBody = ['middle_name' => 'Иванович'];
        $this->useRequest('POST', '/auth/vk/middle-name');

        $response = $this->capture(fn () => $this->controller->vkMiddleName());

        $this->assertSame(401, $response['status']);
        $this->assertFalse($response['json']['ok']);
    }

    public function testVkMiddleNameUpdatesSessionAndRepository(): void
    {
        $_SESSION['vk_login'] = [
            'user_id' => 7,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '+7 (999) 123-45-67',
            'vk_profile_url' => 'https://vk.com/id12345',
        ];
        $this->controller->jsonBody = ['middle_name' => 'иванович'];
        $this->useRequest('POST', '/auth/vk/middle-name');

        $response = $this->capture(fn () => $this->controller->vkMiddleName());

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['json']['ok']);
        $this->assertSame('Иванович', $_SESSION['vk_login']['middle_name']);
        $this->assertSame('Иванович', $this->users->lastMiddleName);
        $this->assertSame('Иванов Иван Иванович', $response['json']['prefill']['fio']);
    }
}
