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

        $resp = @file_get_contents($url);
        if ($resp === false) {
            error_log("SmsService::sendCodeToPhone: sms.ru send error: no response for phone {$phone}, code {$code}");
            return [
                'ok'    => false,
                'error' => 'Не удалось отправить SMS. Попробуйте позже.',
            ];
        }

        error_log("SmsService::sendCodeToPhone: raw response = {$resp}");

        $out = json_decode($resp, true);

        if (!is_array($out) || ($out['status'] ?? '') !== 'OK') {
            error_log("SmsService::sendCodeToPhone: sms.ru response error: {$resp}");
            return [
                'ok'    => false,
                'error' => 'Ошибка сервиса SMS. Попробуйте позже.',
            ];
        }

        $smsInfo = $out['sms'][$phone] ?? null;
        if (!is_array($smsInfo) || ($smsInfo['status'] ?? '') !== 'OK') {
            error_log(
                "SmsService::sendCodeToPhone: sms.ru number error for {$phone}: "
                . json_encode($smsInfo, JSON_UNESCAPED_UNICODE)
            );
            return [
                'ok'    => false,
                'error' => 'Не удалось отправить SMS на указанный номер.',
            ];
        }

        error_log("SmsService::sendCodeToPhone: OK for {$phone}");

        return ['ok' => true];
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