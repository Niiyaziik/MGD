<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\Contract\PhoneCodeRepositoryInterface;

class SmsService
{
    public function __construct(
        private PhoneCodeRepositoryInterface $phoneCodes,
    ) {}

    /**
     * Отправка кода на произвольный телефон (например, переданный из фронта)
     */
    public function sendCodeToPhone(string $rawPhone): array
    {
        error_log("SmsService::sendCodeToPhone: raw phone = '{$rawPhone}'");

        $phone = $this->normalizeSmsPhone($rawPhone);
        error_log("SmsService::sendCodeToPhone: normalized phone = " . var_export($phone, true));

        if ($phone === null) {
            error_log("SmsService::sendCodeToPhone: phone normalization failed");
            return [
                'ok'    => false,
                'error' => 'Неверный формат телефона',
            ];
        }

        $code = (string)random_int(100000, 999999);
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;

        error_log("SmsService::sendCodeToPhone: creating code for phone {$phone}, ip={$ip}");

        // Сохраняем код в БД (это главное - код должен быть доступен для проверки)
        $this->phoneCodes->createCode($phone, $code, $ip);

        $text = "Код подтверждения: {$code}";

        $apiId = '8390F4D8-4279-5F2E-6B35-607ADADD110D';
        $from  = '';

        $params = [
            'api_id' => $apiId,
            'to'     => $phone,
            'msg'    => $text,
            'json'   => 1,
            'from'   => $from,
        ];

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $url   = 'https://sms.ru/sms/send?' . $query;

        error_log("SmsService::sendCodeToPhone: request URL = {$url}");

        // Пытаемся отправить SMS, но не блокируем процесс, если не получилось
        $resp = @file_get_contents($url);
        if ($resp === false) {
            error_log("SmsService::sendCodeToPhone: sms.ru send error: no response for phone {$phone}, code {$code} (code saved in DB)");
            // Код сохранен в БД, возвращаем успех, даже если SMS не отправилось
            return ['ok' => true, 'sms_sent' => false];
        }

        error_log("SmsService::sendCodeToPhone: raw response = {$resp}");

        $out = json_decode($resp, true);

        if (!is_array($out) || ($out['status'] ?? '') !== 'OK') {
            error_log("SmsService::sendCodeToPhone: sms.ru response error: {$resp} (code saved in DB)");
            // Код сохранен в БД, возвращаем успех, даже если SMS не отправилось
            return ['ok' => true, 'sms_sent' => false];
        }

        $smsInfo = $out['sms'][$phone] ?? null;
        if (!is_array($smsInfo) || ($smsInfo['status'] ?? '') !== 'OK') {
            error_log(
                "SmsService::sendCodeToPhone: sms.ru number error for {$phone}: "
                . json_encode($smsInfo, JSON_UNESCAPED_UNICODE)
                . " (code saved in DB)"
            );
            // Код сохранен в БД, возвращаем успех, даже если SMS не отправилось
            return ['ok' => true, 'sms_sent' => false];
        }

        error_log("SmsService::sendCodeToPhone: OK for {$phone}, SMS sent successfully");

        return ['ok' => true, 'sms_sent' => true];
    }

    /**
     * Отправка кода на телефон из сессии голосующего
     */
    public function sendCodeForCurrentVoter(): array
    {
        $voter = $_SESSION['voter'] ?? null;
        error_log('SmsService::sendCodeForCurrentVoter: session voter = ' . json_encode($voter, JSON_UNESCAPED_UNICODE));

        if (!$voter || empty($voter['phone'])) {
            error_log('SmsService::sendCodeForCurrentVoter: no voter or phone in session');
            return [
                'ok'    => false,
                'error' => 'Не удалось определить номер телефона для отправки SMS.',
            ];
        }

        return $this->sendCodeToPhone((string)$voter['phone']);
    }

    /**
     * Проверка кода для произвольного телефона
     */
    public function checkCodeForPhone(string $rawPhone, string $code): array
    {
        error_log("SmsService::checkCodeForPhone: raw phone = '{$rawPhone}', code = '{$code}'");

        $phone = $this->normalizeSmsPhone($rawPhone);
        error_log("SmsService::checkCodeForPhone: normalized phone = " . var_export($phone, true));

        if ($phone === null) {
            error_log("SmsService::checkCodeForPhone: phone normalization failed");
            return [
                'ok'    => false,
                'error' => 'Неверный формат телефона',
            ];
        }

        $code = trim($code);
        if ($code === '') {
            return [
                'ok'    => false,
                'error' => 'Не указан код',
            ];
        }

        error_log("SmsService::checkCodeForPhone: checking code for phone {$phone}");

        $row = $this->phoneCodes->findValid($phone, $code, 600);
        
        if (!$row) {
            error_log("SmsService::checkCodeForPhone: code not found or expired for phone {$phone}");
            return [
                'ok'    => false,
                'error' => 'Неверный или просроченный код',
            ];
        }

        error_log("SmsService::checkCodeForPhone: code found, marking as used, id = {$row['id']}");
        $this->phoneCodes->markUsed((int)$row['id']);

        error_log("SmsService::checkCodeForPhone: OK for phone {$phone}");
        return ['ok' => true];
    }

    /**
     * Проверка кода для текущего голосующего (телефон из сессии)
     */
    public function checkCodeForCurrentVoter(string $code): array
    {
        $voter = $_SESSION['voter'] ?? null;
        error_log('SmsService::checkCodeForCurrentVoter: session voter = ' . json_encode($voter, JSON_UNESCAPED_UNICODE));

        if (!$voter || empty($voter['phone'])) {
            error_log('SmsService::checkCodeForCurrentVoter: no voter or phone in session');
            return [
                'ok'    => false,
                'error' => 'Не удалось определить номер телефона для проверки кода.',
            ];
        }

        return $this->checkCodeForPhone((string)$voter['phone'], $code);
    }

    // нормализация телефона — твой рабочий метод normalizeSmsPhone(...)
    private function normalizeSmsPhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        if ($digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        } elseif ($digits[0] !== '7') {
            $digits = '7' . $digits;
        }

        $digits = substr($digits, 0, 11);
        if (strlen($digits) !== 11) {
            return null;
        }

        return $digits;
    }
}