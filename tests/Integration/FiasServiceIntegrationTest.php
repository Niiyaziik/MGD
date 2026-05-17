<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/../Unit/Support/CurlMock.php';

use App\Service\FiasService;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\CurlMock;

/**
 * Integration tests for FiasService.
 *
 * All HTTP calls are intercepted by CurlMock — no real network requests are made.
 * Tests cover DaData suggest flow and the FIAS address-search flow.
 */
final class FiasServiceIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        CurlMock::reset();
    }

    // -------------------------------------------------------------------------
    // suggestAddress — DaData flow
    // -------------------------------------------------------------------------

    public function testSuggestAddressReturnsEmptyForShortQuery(): void
    {
        $service = new FiasService(null, 'test-key');

        $this->assertSame([], $service->suggestAddress('а'));
        $this->assertSame([], $service->suggestAddress(''));
    }

    public function testSuggestAddressReturnsEmptyWhenApiKeyNotSet(): void
    {
        $service = new FiasService(null, '');

        $this->assertSame([], $service->suggestAddress('Ленина'));
    }

    public function testSuggestAddressReturnsParsedSuggestionsForStreetAndHouse(): void
    {
        CurlMock::queueResponse(json_encode([
            'suggestions' => [
                [
                    'value' => 'г Ульяновск, ул Ленина, д 5',
                    'data'  => ['street_with_type' => 'ул Ленина', 'house' => '5'],
                ],
                [
                    'value' => 'г Ульяновск, ул Ленина',
                    'data'  => ['street_with_type' => 'ул Ленина'],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));
        // Second DaData request (settlement → stead) returns nothing
        CurlMock::queueResponse(json_encode(['suggestions' => []], JSON_UNESCAPED_UNICODE));

        $service = new FiasService(null, 'real-key');
        $result  = $service->suggestAddress('Ленина');

        $this->assertCount(2, $result);
        $this->assertSame('ул Ленина', $result[0]['street']);
        $this->assertSame('5', $result[0]['house']);
        $this->assertTrue($result[0]['complete']);
        $this->assertFalse($result[1]['complete']);
    }

    public function testSuggestAddressIncludesSntSuggestions(): void
    {
        // First request (street → house) returns nothing
        CurlMock::queueResponse(json_encode(['suggestions' => []], JSON_UNESCAPED_UNICODE));
        // Second request (settlement → stead) returns SNT row
        CurlMock::queueResponse(json_encode([
            'suggestions' => [
                [
                    'value' => 'г Ульяновск, СНТ Весна, уч 3',
                    'data'  => [
                        'settlement_with_type' => 'СНТ Весна',
                        'stead_type'           => 'уч',
                        'stead'                => '3',
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $service = new FiasService(null, 'real-key');
        $result  = $service->suggestAddress('Весна');

        $this->assertCount(1, $result);
        $this->assertSame('СНТ Весна', $result[0]['street']);
        $this->assertSame('уч 3', $result[0]['house']);
        $this->assertTrue($result[0]['complete']);
    }

    public function testSuggestAddressReturnsEmptyOnApiError(): void
    {
        CurlMock::queueResponse('Internal Server Error', '', 500);
        CurlMock::queueResponse('Internal Server Error', '', 500);

        $service = new FiasService(null, 'real-key');

        $this->assertSame([], $service->suggestAddress('Гончарова'));
    }

    public function testSuggestAddressReturnsEmptyOnCurlFailure(): void
    {
        CurlMock::queueResponse(false, 'timeout', 0);
        CurlMock::queueResponse(false, 'timeout', 0);

        $service = new FiasService(null, 'real-key');

        $this->assertSame([], $service->suggestAddress('Минаева'));
    }

    // -------------------------------------------------------------------------
    // searchAddresses — FIAS flow (uses token cache)
    // -------------------------------------------------------------------------

    public function testSearchAddressesReturnsEmptyForEmptyQuery(): void
    {
        $service = new FiasService(null, '');

        $this->assertSame([], $service->searchAddresses(''));
    }

    public function testSearchAddressesReturnsParsedRows(): void
    {
        $cache = tempnam(sys_get_temp_dir(), 'fias-');
        file_put_contents($cache, json_encode(['token' => 'tok', 'exp' => time() + 3600]));

        CurlMock::queueResponse(json_encode([
            ['address' => 'г. Ульяновск, ул. Гончарова, д. 7'],
            ['address' => ''],
        ], JSON_UNESCAPED_UNICODE));

        $service = new FiasService($cache, '');
        $result  = $service->searchAddresses('Гончарова');
        @unlink($cache);

        $this->assertCount(1, $result);
        $this->assertSame('Гончарова', $result[0]['street']);
        $this->assertSame('7', $result[0]['house']);
    }

    public function testSearchAddressesReturnsFallbackPostResult(): void
    {
        $cache = tempnam(sys_get_temp_dir(), 'fias-');
        file_put_contents($cache, json_encode(['token' => 'tok', 'exp' => time() + 3600]));

        // GET fails → fallback to POST
        CurlMock::queueResponse('bad', '', 400);
        CurlMock::queueResponse(json_encode([
            'result' => [['address' => 'г. Ульяновск, ул. Минаева, д. 3']],
        ], JSON_UNESCAPED_UNICODE));

        $service = new FiasService($cache, '');
        $result  = $service->searchAddresses('Минаева');
        @unlink($cache);

        $this->assertCount(1, $result);
        $this->assertSame('Минаева', $result[0]['street']);
    }

    public function testSearchAddressesReturnsEmptyWhenTokenExpiredAndRefreshFails(): void
    {
        $cache = tempnam(sys_get_temp_dir(), 'fias-');
        // Expired token
        file_put_contents($cache, json_encode(['token' => 'old', 'exp' => time() - 1]));

        // Token refresh fails
        CurlMock::queueResponse('error', '', 500);

        $service = new FiasService($cache, '');
        $result  = $service->searchAddresses('Ленина');
        @unlink($cache);

        $this->assertSame([], $result);
    }

    public function testSearchAddressesPrependsUlyanovskWhenMissing(): void
    {
        $cache = tempnam(sys_get_temp_dir(), 'fias-');
        file_put_contents($cache, json_encode(['token' => 'tok', 'exp' => time() + 3600]));

        CurlMock::queueResponse(json_encode([
            ['address' => 'г. Ульяновск, ул. Кирова, д. 10'],
        ], JSON_UNESCAPED_UNICODE));

        $service = new FiasService($cache, '');
        // "Ульяновск" is not in the query — service should prepend it
        $result = $service->searchAddresses('Кирова, 10');
        @unlink($cache);

        $this->assertCount(1, $result);
    }
}
