<?php
declare(strict_types=1);

namespace Tests\Unit\Support;

if (!defined('CURLOPT_RETURNTRANSFER')) {
    define('CURLOPT_RETURNTRANSFER', 19913);
}
if (!defined('CURLOPT_POST')) {
    define('CURLOPT_POST', 47);
}
if (!defined('CURLOPT_POSTFIELDS')) {
    define('CURLOPT_POSTFIELDS', 10015);
}
if (!defined('CURLOPT_HTTPHEADER')) {
    define('CURLOPT_HTTPHEADER', 10023);
}
if (!defined('CURLOPT_TIMEOUT')) {
    define('CURLOPT_TIMEOUT', 13);
}
if (!defined('CURLOPT_CONNECTTIMEOUT')) {
    define('CURLOPT_CONNECTTIMEOUT', 78);
}
if (!defined('CURLOPT_SSL_VERIFYPEER')) {
    define('CURLOPT_SSL_VERIFYPEER', 64);
}
if (!defined('CURLOPT_FOLLOWLOCATION')) {
    define('CURLOPT_FOLLOWLOCATION', 52);
}
if (!defined('CURLOPT_IPRESOLVE')) {
    define('CURLOPT_IPRESOLVE', 113);
}
if (!defined('CURL_IPRESOLVE_V4')) {
    define('CURL_IPRESOLVE_V4', 1);
}
if (!defined('CURLINFO_HTTP_CODE')) {
    define('CURLINFO_HTTP_CODE', 2097154);
}

final class CurlMock
{
    /** @var list<array{body:string|false,error:string,http:int}> */
    public static array $queue = [];

    /** @var array<int,array<string,mixed>> */
    public static array $handles = [];

    public static ?string $lastUrl = null;

    /** @var array<mixed> */
    public static array $lastOptions = [];

    public static function reset(): void
    {
        self::$queue = [];
        self::$handles = [];
        self::$lastUrl = null;
        self::$lastOptions = [];
    }

    public static function queueResponse(string|false $body, string $error = '', int $httpCode = 200): void
    {
        self::$queue[] = [
            'body'  => $body,
            'error' => $error,
            'http'  => $httpCode,
        ];
    }

    /** @return array{body:string|false,error:string,http:int} */
    public static function popResponse(): array
    {
        return array_shift(self::$queue) ?? [
            'body'  => false,
            'error' => 'No mocked cURL response',
            'http'  => 0,
        ];
    }
}

namespace App\Service;

use Tests\Unit\Support\CurlMock;

if (!function_exists(__NAMESPACE__ . '\\curl_init')) {
    function curl_init(?string $url = null): object
    {
        $handle = new \stdClass();
        CurlMock::$lastUrl = $url;
        CurlMock::$handles[spl_object_id($handle)] = [
            'url'     => $url,
            'options' => [],
            'body'    => false,
            'error'   => '',
            'http'    => 0,
        ];
        return $handle;
    }
}

if (!function_exists(__NAMESPACE__ . '\\curl_setopt_array')) {
    function curl_setopt_array(object $handle, array $options): bool
    {
        $id = spl_object_id($handle);
        CurlMock::$handles[$id]['options'] = $options;
        CurlMock::$lastOptions = $options;
        return true;
    }
}

if (!function_exists(__NAMESPACE__ . '\\curl_exec')) {
    function curl_exec(object $handle): string|false
    {
        $id = spl_object_id($handle);
        $response = CurlMock::popResponse();
        CurlMock::$handles[$id]['body'] = $response['body'];
        CurlMock::$handles[$id]['error'] = $response['error'];
        CurlMock::$handles[$id]['http'] = $response['http'];
        return $response['body'];
    }
}

if (!function_exists(__NAMESPACE__ . '\\curl_error')) {
    function curl_error(object $handle): string
    {
        return (string)(CurlMock::$handles[spl_object_id($handle)]['error'] ?? '');
    }
}

if (!function_exists(__NAMESPACE__ . '\\curl_getinfo')) {
    function curl_getinfo(object $handle, int $option): mixed
    {
        if ($option === CURLINFO_HTTP_CODE) {
            return (int)(CurlMock::$handles[spl_object_id($handle)]['http'] ?? 0);
        }

        return null;
    }
}

if (!function_exists(__NAMESPACE__ . '\\curl_close')) {
    function curl_close(object $handle): void
    {
        unset(CurlMock::$handles[spl_object_id($handle)]);
    }
}
