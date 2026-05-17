<?php

declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/Support/CurlMock.php';

use App\Service\FiasService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Unit\Support\CurlMock;

final class FiasServiceTest extends TestCase
{
    protected function setUp(): void
    {
        CurlMock::reset();
    }

    public function testSuggestAddressReturnsEmptyWhenQueryTooShort(): void
    {
        $service = new FiasService(null, 'some-api-key');

        $this->assertSame([], $service->suggestAddress('к'));
        $this->assertSame([], $service->suggestAddress(''));
    }

    public function testSuggestAddressReturnsEmptyWhenApiKeyNotSet(): void
    {
        $service = new FiasService(null, '');

        $result = $service->suggestAddress('Кирова');

        $this->assertSame([], $result);
    }

    public function testSuggestAddressReturnsArrayStructureWhenApiReturnsSuggestions(): void
    {
        CurlMock::queueResponse(json_encode([
            'suggestions' => [
                [
                    'value' => 'г Ульяновск, ул Ленина, д 10',
                    'data'  => [
                        'street_with_type' => 'ул Ленина',
                        'house'            => '10',
                    ],
                ],
                [
                    'value' => 'г Ульяновск, СНТ Весна, уч 5',
                    'data'  => [
                        'settlement_with_type' => 'СНТ Весна',
                        'stead_type'           => 'уч',
                        'stead'                => '5',
                    ],
                ],
                [
                    'value' => 'Адрес без улицы',
                    'data'  => [],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));
        CurlMock::queueResponse(json_encode(['suggestions' => []], JSON_UNESCAPED_UNICODE));

        $service = new FiasService(null, 'test-key');
        $result = $service->suggestAddress('Ленина');

        $this->assertCount(2, $result);
        $this->assertSame('ул Ленина', $result[0]['street']);
        $this->assertSame('10', $result[0]['house']);
        $this->assertTrue($result[0]['complete']);
        $this->assertSame('СНТ Весна', $result[1]['street']);
        $this->assertSame('уч 5', $result[1]['house']);
    }

    public function testSuggestAddressReturnsEmptyWhenDaDataReturnsHttpError(): void
    {
        CurlMock::queueResponse('server error', '', 500);
        CurlMock::queueResponse('server error', '', 500);

        $service = new FiasService(null, 'test-key');

        $this->assertSame([], $service->suggestAddress('Кирова'));
    }

    public function testSearchAddressesReturnsEmptyForEmptyQuery(): void
    {
        $service = new FiasService();
        $result  = $service->searchAddresses('');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSearchAddressesUsesCachedTokenAndParsesGetResponse(): void
    {
        $cacheFile = tempnam(sys_get_temp_dir(), 'fias-token-');
        file_put_contents($cacheFile, json_encode([
            'token' => 'cached-token',
            'exp'   => time() + 3600,
        ], JSON_UNESCAPED_UNICODE));

        CurlMock::queueResponse(json_encode([
            ['address' => 'г. Ульяновск, ул. Ленина, д. 10'],
            ['name'    => 'г. Ульяновск, проспект Нариманова, дом 20'],
            ['value'   => ''],
        ], JSON_UNESCAPED_UNICODE));

        $service = new FiasService($cacheFile, '');
        $result = $service->searchAddresses('Ленина');

        @unlink($cacheFile);

        $this->assertCount(2, $result);
        $this->assertSame('Ленина', $result[0]['street']);
        $this->assertSame('10', $result[0]['house']);
        $this->assertSame('Нариманова', $result[1]['street']);
        $this->assertSame('20', $result[1]['house']);
    }

    public function testSearchAddressesFallsBackToPostWhenGetReturnsNull(): void
    {
        $cacheFile = tempnam(sys_get_temp_dir(), 'fias-token-');
        file_put_contents($cacheFile, json_encode([
            'token' => 'cached-token',
            'exp'   => time() + 3600,
        ], JSON_UNESCAPED_UNICODE));

        CurlMock::queueResponse('bad request', '', 400);
        CurlMock::queueResponse(json_encode([
            'result' => [
                ['address' => 'г. Ульяновск, улица Гончарова, д. 5'],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $service = new FiasService($cacheFile, '');
        $result = $service->searchAddresses('Ульяновск, Гончарова');

        @unlink($cacheFile);

        $this->assertCount(1, $result);
        $this->assertSame('Гончарова', $result[0]['street']);
        $this->assertSame('5', $result[0]['house']);
    }

    public function testSearchAddressesReturnsEmptyWhenTokenCacheIsExpiredAndTokenRequestFails(): void
    {
        $cacheFile = tempnam(sys_get_temp_dir(), 'fias-token-');
        file_put_contents($cacheFile, json_encode([
            'token' => 'old-token',
            'exp'   => time() - 3600,
        ], JSON_UNESCAPED_UNICODE));

        CurlMock::queueResponse('token error', '', 500);

        $service = new FiasService($cacheFile, '');
        $result = $service->searchAddresses('Ленина');

        @unlink($cacheFile);

        $this->assertSame([], $result);
    }

    public function testExtractCookieReturnsCookieValue(): void
    {
        $service = new FiasService(null, '');
        $method = (new ReflectionClass($service))->getMethod('extractCookie');
        $method->setAccessible(true);

        $headers = "HTTP/1.1 200 OK\r\nSet-Cookie: sessionId=abc123; Path=/\r\n";

        $this->assertSame('abc123', $method->invoke($service, $headers, 'sessionId'));
        $this->assertSame('', $method->invoke($service, $headers, 'missing'));
    }

    public function testConstructorAcceptsNullTokenCacheFile(): void
    {
        $service = new FiasService(null, 'test-key');
        $this->assertInstanceOf(FiasService::class, $service);
    }
}
