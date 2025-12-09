<?php
declare(strict_types=1);

namespace App\Controller;

class CaptchaController extends BaseController
{
    public function verify(): void
    {
        $this->requireMethod('POST');

        $data  = $this->getJsonBody();
        $token = $data['token'] ?? '';

        if (!$token) {
            $this->json([
                'ok'    => false,
                'error' => 'Отсутствует токен reCAPTCHA'
            ], 400);
            return;
        }

        $secret = '6LdciSEsAAAAACObwW8hNVnSSeTx5GIyXUN-4EMQ';

        if (!$secret) {
            $this->json([
                'ok'    => false,
                'error' => 'Секретный ключ reCAPTCHA не задан'
            ], 500);
            return;
        }

        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? null;

        $postData = http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]);

        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  =>
                    "Content-type: application/x-www-form-urlencoded\r\n" .
                    "Content-Length: " . strlen($postData) . "\r\n",
                'content' => $postData,
                'timeout' => 5,
            ],
        ];

        $context = stream_context_create($opts);
        $resp    = @file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify',
            false,
            $context
        );

        if ($resp === false) {
            $this->json([
                'ok'    => false,
                'error' => 'Не удалось связаться с сервером Google reCAPTCHA'
            ], 502);
            return;
        }

        $result = json_decode($resp, true);

        error_log('reCAPTCHA response: ' . $resp);
        if (!is_array($result)) {
            $this->json([
                'ok'    => false,
                'error' => 'Некорректный ответ от Google reCAPTCHA'
            ], 502);
            return;
        }

        if (empty($result['success'])) {
            $this->json([
                'ok'    => false,
                'error' => 'Проверка reCAPTCHA не пройдена',
                'codes' => $result['error-codes'] ?? null,
            ], 400);
            return;
        }

        $this->json(['ok' => true]);
    }
}
