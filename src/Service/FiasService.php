<?php
declare(strict_types=1);

namespace App\Service;

final class FiasService
{
    private const TOKEN_URL  = 'https://fias.nalog.ru/Home/GetSpasSettings';
    private const HINT_URL   = 'https://fias-public-service.nalog.ru/api/spas/v2.0/GetAddressHint';
    private const DADATA_URL = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address';

    private string $tokenCacheFile;
    private string $dadataApiKey;

    public function __construct(?string $tokenCacheFile = null, string $dadataApiKey = '')
    {
        $this->tokenCacheFile = $tokenCacheFile ?: (sys_get_temp_dir() . '/fias_token_cache.json');
        $this->dadataApiKey   = $dadataApiKey;
    }

    /**
     * Один HTTP-запрос к DaData Suggestions API.
     * Возвращает массив `suggestions` или [] при ошибке.
     *
     * @param array<string,mixed> $body
     * @param string[]            $headers
     * @return array<int,mixed>
     */
    private function dadataRequest(array $body, array $headers): array
    {
        $ch = curl_init(self::DADATA_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resp     = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $curlErr !== '') {
            error_log('[FiasService::dadataRequest] cURL error: ' . $curlErr);
            return [];
        }
        if ($httpCode !== 200) {
            error_log("[FiasService::dadataRequest] HTTP {$httpCode}: " . mb_substr((string)$resp, 0, 200));
            return [];
        }

        $out = json_decode($resp, true);
        return $out['suggestions'] ?? [];
    }

    /**
     * Подсказки адресов через DaData (при наличии ключа) или локальную БД.
     * Возвращает массив [{street, house, label, complete}].
     *
     * @return array<int, array{street:string, house:string, label:string, complete:bool}>
     */
    public function suggestAddress(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        if ($this->dadataApiKey === '') {
            error_log('[FiasService::suggestAddress] DaData key not set');
            return [];
        }

        $locations = [['region' => 'Ульяновская', 'city' => 'Ульяновск']];
        $headers   = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Token ' . $this->dadataApiKey,
        ];

        // Запрос 1: улицы → дома (обычные адреса)
        $r1 = $this->dadataRequest([
            'query'      => $query,
            'count'      => 7,
            'locations'  => $locations,
            'from_bound' => ['value' => 'street'],
            'to_bound'   => ['value' => 'house'],
        ], $headers);

        // Запрос 2: населённые пункты (СНТ и т.п.) → участки
        $r2 = $this->dadataRequest([
            'query'      => $query,
            'count'      => 7,
            'locations'  => $locations,
            'from_bound' => ['value' => 'settlement'],
            'to_bound'   => ['value' => 'stead'],
        ], $headers);

        $suggestions = array_merge($r1, $r2);

        $result = [];
        foreach ($suggestions as $s) {
            $d = $s['data'] ?? [];

            // Улица, либо СНТ/садовое общество (settlement)
            $street = trim(
                $d['street_with_type']
                ?? $d['street']
                ?? $d['settlement_with_type']
                ?? $d['settlement']
                ?? ''
            );

            // Дом или земельный участок
            $house = trim($d['house'] ?? '');
            if ($house === '') {
                $steadType = trim($d['stead_type'] ?? 'уч');
                $stead     = trim($d['stead'] ?? '');
                if ($stead !== '') {
                    $house = $steadType . ' ' . $stead;
                }
            }

            if ($street === '') {
                continue;
            }

            $label = $s['value'] ?? ($house ? "{$street}, {$house}" : $street);

            $result[] = [
                'street'   => $street,
                'house'    => $house,
                'label'    => $label,
                'complete' => $house !== '',
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{street:string, house:string}>
     */
    public function searchAddresses(string $query): array
    {
        $query = trim($query);
        if ($query === '') return [];

        // Если хочешь — можешь НЕ добавлять город автоматически
        if (mb_stripos($query, 'ульяновск') === false) {
            $query = 'Ульяновск, ' . $query;
        }

        $token = $this->getToken();
        if ($token === '') {
            error_log('[FIAS] token is empty');
            return [];
        }

        // 1) пробуем GET (часто так работает)
        $data = $this->callHintGet($query, $token);

        // 2) если не вышло — пробуем POST (иногда у них так)
        if ($data === null) {
            $data = $this->callHintPost($query, $token);
        }

        if ($data === null) {
            return [];
        }

        // Ответ может быть:
        // - массивом объектов
        // - объектом вида { "addresses": [...] } или { "result": [...] }
        $items = [];
        if (is_array($data['addresses'] ?? null)) {
            $items = $data['addresses'];
        } elseif (is_array($data['result'] ?? null)) {
            $items = $data['result'];
        } elseif (is_array($data['data'] ?? null)) {
            $items = $data['data'];
        } elseif (is_array($data) && isset($data[0])) {
            $items = $data;
        }

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $parsed = $this->parseFiasAddress($item);
            if ($parsed) $out[] = $parsed;
        }

        return $out;
    }

    private function callHintGet(string $q, string $token): ?array
    {
        $url = self::HINT_URL . '?' . http_build_query([
            'search_string' => $q,
            'address_type'  => 2,   // MUNICIPALITY (как дефолт в клиенте)
        ]);

        return $this->curlJson($url, $token, 'GET');
    }

    private function callHintPost(string $q, string $token): ?array
    {
        $payload = [
            'searchString' => $q, // если вдруг их бекенд это примет
            'addressType'  => 2,
            'searchNonActive' => false,
        ];

        return $this->curlJson(self::HINT_URL, $token, 'POST', $payload);
    }

    /**
     * Достаем токен с fias.nalog.ru и кешируем.
     * Токен обычно живет недолго — кешируем на 30 минут.
     */
    private function getToken(): string
    {
        // кеш
        if (is_file($this->tokenCacheFile)) {
            $cached = json_decode((string)file_get_contents($this->tokenCacheFile), true);
            if (is_array($cached) && !empty($cached['token']) && !empty($cached['exp']) && time() < (int)$cached['exp']) {
                return (string)$cached['token'];
            }
        }

        $url = self::TOKEN_URL . '?' . http_build_query([
            'url' => 'https://fias.nalog.ru/',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: MGD/1.0',
            ],
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            error_log('[FIAS] token curl error: ' . curl_error($ch));
            curl_close($ch);
            return '';
        }

        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            error_log('[FIAS] token http error: ' . $code . ' body_prefix=' . mb_substr($resp, 0, 200));
            return '';
        }

        $data = json_decode($resp, true);
        if (!is_array($data) || empty($data['Token'])) {
            error_log('[FIAS] token json unexpected: ' . mb_substr($resp, 0, 200));
            return '';
        }

        $token = (string)$data['Token'];

        @file_put_contents($this->tokenCacheFile, json_encode([
            'token' => $token,
            'exp'   => time() + 30 * 60,
        ], JSON_UNESCAPED_UNICODE));

        return $token;
    }

    private function extractCookie(string $headers, string $name): string
    {
        if (preg_match('/^Set-Cookie:\s*' . preg_quote($name, '/') . '=([^;]+)/mi', $headers, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * Универсальный JSON вызов.
     * Важно: передаем токен и как Cookie, и как заголовок Authorization (на всякий).
     */
    private function curlJson(string $url, string $token, string $method, ?array $jsonBody = null): ?array
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'master-token: ' . $token,
            'User-Agent: MGD/1.0',
        ];

        $baseOpts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,

            // ВАЖНО: увеличиваем таймауты и форсим IPv4
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,

            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        $maxAttempts = 3;
        $retryHttpCodes = [500, 502, 503, 504];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = curl_init($url);
            $opts = $baseOpts;

            if ($method === 'POST') {
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json; charset=utf-8';
                $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody ?? [], JSON_UNESCAPED_UNICODE);
            }

            curl_setopt_array($ch, $opts);

            $resp = curl_exec($ch);
            $curlErr = curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            // Успех
            if ($resp !== false && $code === 200) {
                $data = json_decode($resp, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('[FIAS] json error: ' . json_last_error_msg() . ' body: ' . mb_substr($resp, 0, 300));
                    return null;
                }
                return $data;
            }

            // Логируем неуспех
            error_log(sprintf(
                '[FIAS] attempt %d/%d failed: http=%d curl="%s" body_prefix="%s"',
                $attempt, $maxAttempts, $code, $curlErr, mb_substr((string)$resp, 0, 120)
            ));

            // Решаем, надо ли ретраить
            $shouldRetry =
                ($resp === false && $curlErr !== '') // сетевые ошибки (timeout/reset)
                || in_array($code, $retryHttpCodes, true);

            if (!$shouldRetry || $attempt === $maxAttempts) {
                return null;
            }

            // backoff: 0.2s, 0.6s, 1.4s ...
            usleep((int)(200000 * (2 ** ($attempt - 1))));
        }

        return null;
    }


    /**
     * Приводим разные форматы ответа к {street, house}
     */
    private function parseFiasAddress(array $item): ?array
    {
        // В ответе обычно есть address / name / value
        $address = (string)($item['address'] ?? $item['name'] ?? $item['value'] ?? '');
        if ($address === '') return null;

        // вырезаем "г. Ульяновск" если есть
        $address = preg_replace('/^г\.?\s*ульяновск,?\s*/iu', '', $address);

        $street = $address;
        $house  = '';

        if (preg_match('/,?\s*(?:дом|строение|корпус|корп\.?|д\.?)\s*([^,]+)/iu', $address, $m)) {
            $house  = trim($m[1]);
            $street = preg_replace('/,?\s*(?:дом|строение|корпус|корп\.?|д\.?)\s*[^,]+/iu', '', $address);
        }

        $street = trim($street);
        $street = preg_replace('/^(ул\.?|улица|проспект|пр\.?|переулок|пер\.?|бульвар|б-р|площадь|пл\.?)\s+/iu', '', $street);

        if ($street === '') return null;

        return [
            'street' => $street,
            'house'  => $house,
        ];
    }
}
