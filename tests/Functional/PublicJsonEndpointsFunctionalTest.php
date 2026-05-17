<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Controller\CandidateController;
use App\Controller\DistrictController;
use Tests\Functional\Support\FakeCandidateRepository;
use Tests\Functional\Support\FakeDistrictRepository;
use Tests\Functional\Support\FunctionalTestCase;

final class PublicJsonEndpointsFunctionalTest extends FunctionalTestCase
{
    public function testCandidatesIndexReturnsFirstCandidatesAsJson(): void
    {
        $repo = new FakeCandidateRepository();
        $repo->candidates = [
            1 => ['id' => 1, 'surname' => 'Иванов'],
            2 => ['id' => 2, 'surname' => 'Петров'],
            3 => ['id' => 3, 'surname' => 'Сидоров'],
        ];
        $controller = new CandidateController($repo);

        $this->useRequest('GET', '/candidates', ['format' => 'json', 'limit' => '2']);
        $response = $this->capture(fn () => $controller->index());

        $this->assertSame(200, $response['status']);
        $this->assertCount(2, $response['json']);
        $this->assertSame('Иванов', $response['json'][0]['surname']);
        $this->assertSame('Петров', $response['json'][1]['surname']);
    }

    public function testCandidatesIndexReturnsAllCandidatesWhenAllFlagIsSet(): void
    {
        $repo = new FakeCandidateRepository();
        $repo->candidates = [
            1 => ['id' => 1, 'surname' => 'Иванов'],
            2 => ['id' => 2, 'surname' => 'Петров'],
            3 => ['id' => 3, 'surname' => 'Сидоров'],
        ];
        $controller = new CandidateController($repo);

        $this->useRequest('GET', '/candidates', ['format' => 'json', 'all' => '1', 'limit' => '1']);
        $response = $this->capture(fn () => $controller->index());

        $this->assertSame(200, $response['status']);
        $this->assertCount(3, $response['json']);
    }

    public function testCandidateShowReturns400WhenIdMissing(): void
    {
        $controller = new CandidateController(new FakeCandidateRepository());

        $this->useRequest('GET', '/candidate', ['format' => 'json']);
        $response = $this->capture(fn () => $controller->show());

        $this->assertSame(400, $response['status']);
        $this->assertSame(['error' => 'bad id'], $response['json']);
    }

    public function testCandidateShowReturnsCandidateJson(): void
    {
        $repo = new FakeCandidateRepository();
        $repo->candidates = [
            5 => ['id' => 5, 'surname' => 'Кандидатов', 'name' => 'Кандидат'],
        ];
        $controller = new CandidateController($repo);

        $this->useRequest('GET', '/candidate', ['format' => 'json', 'id' => '5']);
        $response = $this->capture(fn () => $controller->show());

        $this->assertSame(200, $response['status']);
        $this->assertSame(5, $response['json']['id']);
        $this->assertSame('Кандидатов', $response['json']['surname']);
    }

    public function testAddressSuggestReturnsEmptyForShortQuery(): void
    {
        $controller = new DistrictController(new FakeDistrictRepository());

        $this->useRequest('GET', '/address/suggest', ['query' => 'а']);
        $response = $this->capture(fn () => $controller->suggest());

        $this->assertSame(200, $response['status']);
        $this->assertSame([], $response['json']);
    }

    public function testAddressSuggestReturnsLocalSuggestionsWithoutExternalFias(): void
    {
        $repo = new FakeDistrictRepository();
        $repo->suggestions = [
            ['street' => 'ул Ленина', 'house' => '10', 'label' => 'ул Ленина, 10', 'complete' => true],
        ];
        $controller = new DistrictController($repo);

        $this->useRequest('GET', '/address/suggest', ['query' => 'Ленина']);
        $response = $this->capture(fn () => $controller->suggest());

        $this->assertSame(200, $response['status']);
        $this->assertCount(1, $response['json']);
        $this->assertSame('ул Ленина', $response['json'][0]['street']);
        $this->assertTrue($response['json'][0]['complete']);
    }
}
