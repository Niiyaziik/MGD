<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Controller\VoteController;
use Tests\Functional\Support\FakeCandidateRepository;
use Tests\Functional\Support\FakeDistrictRepository;
use Tests\Functional\Support\FakeUserRepository;
use Tests\Functional\Support\FakeVoteRepository;
use Tests\Functional\Support\FunctionalTestCase;

final class VoteControllerFunctionalTest extends FunctionalTestCase
{
    private FakeVoteRepository $votes;
    private FakeCandidateRepository $candidates;
    private VoteController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->votes = new FakeVoteRepository();
        $this->candidates = new FakeCandidateRepository();
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

        $this->controller = new VoteController(
            $this->votes,
            $this->candidates,
            new FakeUserRepository(),
            new FakeDistrictRepository(),
        );

        $_SESSION['voter'] = [
            'user_id' => 100,
            'district_id' => 1,
            'district_num' => '1',
        ];
    }

    public function testVoteReturns422WhenCandidateIsMissing(): void
    {
        $_POST = [];
        $this->useRequest('POST', '/votes');

        $response = $this->capture(fn () => $this->controller->voted());

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['json']['ok']);
        $this->assertStringContainsString('кандидат', mb_strtolower($response['json']['error']));
    }

    public function testVoteReturns422WhenCandidateNotFound(): void
    {
        $this->setJsonBodyForVote(['candidate_id' => 999]);
        $this->useRequest('POST', '/votes');

        $response = $this->capture(fn () => $this->controller->voted());

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['json']['ok']);
        $this->assertSame('Кандидат не найден', $response['json']['error']);
    }

    public function testVoteReturns422WhenDistrictsDoNotMatch(): void
    {
        $this->setJsonBodyForVote(['candidate_id' => 20]);
        $this->useRequest('POST', '/votes');

        $response = $this->capture(fn () => $this->controller->voted());

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['json']['ok']);
        $this->assertSame(1, $response['json']['user_district_id']);
        $this->assertSame(2, $response['json']['candidate_district_id']);
    }

    public function testVoteCreatesVoteWhenDistrictsMatch(): void
    {
        $this->setJsonBodyForVote(['candidate_id' => 10]);
        $this->useRequest('POST', '/votes');

        $response = $this->capture(fn () => $this->controller->voted());

        $this->assertSame(201, $response['status']);
        $this->assertTrue($response['json']['ok']);
        $this->assertSame(100, $response['json']['data']['user_id']);
        $this->assertSame(10, $response['json']['data']['candidate_id']);
        $this->assertTrue($this->votes->userHasVote(100));
    }

    public function testVoteReturns422WhenUserAlreadyVoted(): void
    {
        $this->votes->votedUsers[100] = true;
        $this->setJsonBodyForVote(['candidate_id' => 10]);
        $this->useRequest('POST', '/votes');

        $response = $this->capture(fn () => $this->controller->voted());

        $this->assertSame(422, $response['status']);
        $this->assertFalse($response['json']['ok']);
        $this->assertStringContainsString('голосовали', $response['json']['error']);
    }

    /**
     * VoteController currently reads JSON from php://input. In functional tests
     * we cannot write to php://input, so we provide JSON through $_POST and
     * install a temporary stream wrapper for php://input-like behaviour would be
     * too invasive. These tests therefore use a small adapter object.
     */
    private function setJsonBodyForVote(array $body): void
    {
        $this->controller = new class(
            $this->votes,
            $this->candidates,
            new FakeUserRepository(),
            new FakeDistrictRepository(),
            $body,
        ) extends VoteController {
            public function __construct(
                FakeVoteRepository $votes,
                FakeCandidateRepository $candidates,
                FakeUserRepository $users,
                FakeDistrictRepository $districts,
                private array $body,
            ) {
                parent::__construct($votes, $candidates, $users, $districts);
            }

            protected function getJsonBody(): array
            {
                return $this->body;
            }
        };
    }
}
