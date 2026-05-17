<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\SmsService;
use App\Repository\Contract\UserRepositoryInterface;
use App\Repository\Contract\PhoneCodeRepositoryInterface;
use App\Repository\Contract\CandidateRepositoryInterface;
use App\Repository\Contract\VoteRepositoryInterface;
use App\Repository\Contract\DistrictRepositoryInterface;

class AuthController extends BaseController
{
    public function __construct(
        private PhoneCodeRepositoryInterface $phoneCodes,
        private CandidateRepositoryInterface $candidates,
        private UserRepositoryInterface $users,
        private VoteRepositoryInterface $votes,
        private DistrictRepositoryInterface $districts,
        private SmsService $sms,
    ) {}

    /**
     * Старый вход по телефону (если ещё нужен)
     */
    public function loginByPhone(): void
    {
        $phone = $_POST['phone'] ?? '';
        $phone = trim($phone);

        if ($phone === '') {
            http_response_code(400);
            echo 'Телефон обязателен';
            return;
        }

        $normalized = preg_replace('/\D+/', '', $phone);

        $user = $this->users->findByPhone($normalized);

        if (!$user) {
            $userId = $this->users->create([
                'phone'       => $normalized,
                'auth_method' => 'Телефон',
            ]);
            $user = $this->users->find($userId);
        }

        $_SESSION['user_id'] = (int)$user['id'];

        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }

    public function vkConfig(): void
    {
        $this->requireMethod('GET');

        $enabledRaw = getenv('VK_ID_ENABLED');
        $enabled = $enabledRaw === false ? true : !in_array(strtolower((string)$enabledRaw), ['0', 'false', 'off', 'no'], true);

        $appId = (int)(getenv('VK_ID_APP_ID') ?: 54388523);
        $redirectUrl = getenv('VK_ID_REDIRECT_URL') ?: $this->buildAbsoluteUrl('/auth/vk/callback');
        $scope = getenv('VK_ID_SCOPE') ?: 'phone email';

        $this->json([
            'ok'          => true,
            'enabled'     => $enabled && $appId > 0,
            'app'         => $appId,
            'redirectUrl' => $redirectUrl,
            'scope'       => $scope,
        ]);
    }

    public function vkRedirect(): void
    {
        header('Location: /');
    }

    public function vkCallback(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>VK ID</title></head><body>';
        echo '<p>Вход через VK ID обработан. Вернитесь на страницу голосования.</p>';
        echo '<p><a href="/">На главную</a></p>';
        echo '</body></html>';
    }

    public function logoutUser(): void
    {
        unset($_SESSION['user_id']);
        header('Location: /');
    }

    private function normalizePhone(string $raw): ?string
    {
        // Оставляем только цифры
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '') {
            return null;
        }

        // Заменяем первый символ
        if ($digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        } elseif ($digits[0] !== '7') {
            $digits = '7' . $digits;
        }

        // Обрезаем до 11 цифр
        $digits = substr($digits, 0, 11);

        // Проверяем длину
        if (strlen($digits) !== 11) {
            return null;
        }

        // Проверка, что второй символ — 9 (мобильный номер РФ)
        if ($digits[1] !== '9') {
            return null;
        }

        // Форматируем
        $formatted = sprintf(
            "+7 (%s) %s-%s-%s",
            substr($digits, 1, 3), // XXX
            substr($digits, 4, 3), // XXX
            substr($digits, 7, 2), // XX
            substr($digits, 9, 2)  // XX
        );

        return $formatted;
    }

    /**
     * POST /auth/send-code
     *
     * Варианты:
     *  - если в body есть "phone" — отправляем код на этот номер (универсальный сценарий);
     *  - если "phone" нет — берём номер из $_SESSION['voter']['phone']
     *    и шлём код текущему голосующему.
     */
    public function sendCode(): void
    {
        $this->requireMethod('POST');

        // --- ЛОГИ НА ВХОДЕ ---
        $data = $this->getJsonBody();
        error_log('[AuthController::sendCode] raw body: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

        $phone = trim((string)($data['phone'] ?? ''));
        error_log('[AuthController::sendCode] phone from body: "' . $phone . '"');

        if ($phone !== '') {
            // универсальный сценарий: телефон передали явно
            error_log('[AuthController::sendCode] using explicit phone from body');
            $result = $this->sms->sendCodeToPhone($phone);
        } else {
            // сценарий голосования: телефон берём из $_SESSION['voter']['phone']
            $sessionVoter = $_SESSION['voter'] ?? null;
            error_log(
                '[AuthController::sendCode] no phone in body, $_SESSION[voter] = ' .
                json_encode($sessionVoter, JSON_UNESCAPED_UNICODE)
            );

            $result = $this->sms->sendCodeForCurrentVoter();
        }

        // --- ЛОГ РЕЗУЛЬТАТА ОТ SmsService ---
        error_log('[AuthController::sendCode] result from SmsService: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

        if (empty($result['ok'])) {
            $errorMsg = $result['error'] ?? 'unknown error';
            error_log('[AuthController::sendCode] ERROR: ' . $errorMsg);

            // здесь можно навесить разные статусы, но для простоты отдаём 422
            $this->json($result, 422);
            return;
        }

        // Код сохранен в БД (SMS может быть не отправлено, но это не критично)
        $smsSent = $result['sms_sent'] ?? true;
        if ($smsSent) {
            error_log('[AuthController::sendCode] success, SMS sent and code saved in DB');
        } else {
            error_log('[AuthController::sendCode] success, code saved in DB but SMS not sent');
        }
        $this->json(['ok' => true, 'sms_sent' => $smsSent]);
    }
    /**
     * POST /auth/check-code
     *
     * Варианты:
     *  - если в body есть "phone" и "code" — проверяем конкретный номер;
     *  - если "phone" нет — проверяем код для текущего голосующего
     *    (телефон из $_SESSION['voter']['phone']).
     */
    public function checkCode(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        error_log('[AuthController::checkCode] raw body: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

        $phone = trim((string)($data['phone'] ?? ''));
        $code  = trim((string)($data['code'] ?? ''));

        error_log('[AuthController::checkCode] phone = "' . $phone . '", code = "' . $code . '"');

        if ($code === '') {
            error_log('[AuthController::checkCode] ERROR: code is empty');
            $this->json(['ok' => false, 'error' => 'Не указан код'], 422);
            return;
        }

        if ($phone !== '') {
            error_log('[AuthController::checkCode] using explicit phone from body');
            $result = $this->sms->checkCodeForPhone($phone, $code);
        } else {
            error_log('[AuthController::checkCode] using phone from session');
            $result = $this->sms->checkCodeForCurrentVoter($code);
        }

        error_log('[AuthController::checkCode] result: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

        if (!$result['ok']) {
            error_log('[AuthController::checkCode] ERROR: ' . ($result['error'] ?? 'unknown'));
            $this->json($result, 422);
            return;
        }

        error_log('[AuthController::checkCode] success, code verified');
        $this->json(['ok' => true]);
    }

    /* ============================================================
     *  НОВЫЙ СЦЕНАРИЙ ДЛЯ ГОЛОСУЮЩЕГО
     *  POST /auth/login   (вызывается после капчи)
     *  POST /auth/logout  (после голосования или по кнопке "Выход")
     * ============================================================ */
    public function login(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        $fio         = trim($data['fio'] ?? '');
        $phoneRaw    = trim($data['phone'] ?? '');
        $addressRaw  = trim($data['address'] ?? '');
        $candidateId = (int)($data['candidate_id'] ?? 0);

        if (!$fio || !$phoneRaw || !$addressRaw || !$candidateId) {
            $this->json(['ok' => false, 'error' => 'Заполните все поля'], 422);
            return;
        }

        // 1) Нормализуем телефон
        $phone = $this->normalizePhone($phoneRaw);
        if (!$phone) {
            $this->json(['ok' => false, 'error' => 'Некорректный номер телефона'], 422);
            return;
        }

        // 2) Ищем пользователя по телефону
        $existingUser   = $this->users->findByPhone($phone);
        $existingUserId = null;
        $userHasVote    = false;

        if ($existingUser) {
            $existingUserId = (int)$existingUser['id'];
            $userHasVote    = $this->votes->userHasVote($existingUserId);
        }

        // 3) Если по этому номеру уже есть голос – вообще НЕ пускаем
        if ($userHasVote) {
            $this->json([
                'ok'    => false,
                'error' => 'Пользователь с этим номером телефона уже проголосовал. Повторная авторизация невозможна.',
            ], 422);
            return;
        }

        // 4) Проверяем кандидата и его округ
        $candidateDistrict = $this->candidates->getCandidateDistrict($candidateId);
        if (!$candidateDistrict) {
            $this->json(['ok' => false, 'error' => 'Кандидат не найден'], 422);
            return;
        }

        // 5) Разбираем адрес пользователя на улицу и дом
        try {
            [$streetName, $houseValue] = $this->parseAddress($addressRaw);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        }

        // 6) Через DistrictRepository ищем округ + street_id + house_id
        $addressInfo = $this->districts->findDistrictByStreetAndHouse($streetName, $houseValue);

        if (!$addressInfo) {
            $this->json([
                'ok'    => false,
                'error' => 'По указанному адресу не найден дом или округ. Проверьте корректность улицы и номера дома.',
            ], 422);
            return;
        }

        $userDistrictId  = (int)$addressInfo['district_id'];
        $userDistrictNum = (string)$addressInfo['district_number'];
        $streetId        = (int)$addressInfo['street_id'];
        $houseId         = (int)$addressInfo['house_id'];

        // 7) Разбираем ФИО на части
        $surname = null;
        $name = null;
        $patronymic = null;

        $parts = preg_split('/\s+/', trim($fio));
        if ($parts) {
            $surname    = $parts[0] ?? null;
            $name       = $parts[1] ?? null;
            $patronymic = $parts[2] ?? null;
        }

        // 8) Создаём или обновляем пользователя (телефон НЕ меняем)
        if ($existingUser && !$userHasVote) {
            $userId = $existingUserId;

            $this->users->updateProfile($userId, [
                'surname'     => $surname,
                'name'        => $name,
                'patronymic'  => $patronymic,
                'district_id' => $userDistrictId,
                'street_id'   => $streetId,
                'house_id'    => $houseId,
            ]);
        } else {
            // Пользователь с таким телефоном ещё не существует — создаём
            $userId = $this->users->create([
                'surname'     => $surname,
                'name'        => $name,
                'patronymic'  => $patronymic,
                'phone'       => $phone,
                'link_vk'     => null,
                'district_id' => $userDistrictId,
                'street_id'   => $streetId,
                'house_id'    => $houseId,
                'auth_method' => 'Телефон',
            ]);
        }

        // 9) Проверяем: может ли он голосовать за этого кандидата?
        $canVote = ($userDistrictId === (int)$candidateDistrict['id']);

        session_regenerate_id(true);

        $_SESSION['voter'] = [
            'user_id'      => $userId,
            'fio'          => $fio,
            'phone'        => $phone,
            'address'      => $addressRaw,
            'district_id'  => $userDistrictId,
            'district_num' => $userDistrictNum,
            'street_id'    => $streetId,
            'house_id'     => $houseId,
        ];

        unset($_SESSION['admin_id']);

        // 10) Если округа не совпадают — фронт сам редиректит по can_vote=false
        if (!$canVote) {
            $this->json([
                'ok'                     => true,
                'can_vote'               => false,
                'user_id'                => $userId,
                'reason'                 => 'wrong_district',
                'user_district_id'       => $userDistrictId,
                'user_district_num'      => $userDistrictNum,
                'candidate_district_id'  => (int)$candidateDistrict['id'],
                'candidate_district_num' => (string)$candidateDistrict['number'],
                'message'                =>
                    'Вы не можете проголосовать за этого кандидата, так как он относится к другому округу. ' .
                    'Ваш округ: ' . $userDistrictNum,
            ]);
            return;
        }

        // 11) Всё хорошо: можно голосовать
        $this->json([
            'ok'                     => true,
            'can_vote'               => true,
            'user_id'                => $userId,
            'user_district_id'       => $userDistrictId,
            'user_district_num'      => $userDistrictNum,
            'candidate_district_id'  => (int)$candidateDistrict['id'],
            'candidate_district_num' => (string)$candidateDistrict['number'],
        ]);
    }

    private function parseAddress(string $address): array
    {
        $parts = array_map('trim', explode(',', $address));
        $parts = array_filter($parts, fn($v) => $v !== '');

        if (count($parts) < 2) {
            throw new \RuntimeException('Адрес должен содержать улицу и дом через запятую');
        }

        $housePart  = array_pop($parts);
        $streetPart = array_pop($parts);

        if (!preg_match('/([0-9]+[0-9А-Яа-яA-Za-z\/\-]*)/u', $housePart, $m)) {
            throw new \RuntimeException('Не удалось определить номер дома из адреса');
        }

        $houseValue = $m[1];
        $streetName = trim($streetPart);

        if ($streetName === '' || $houseValue === '') {
            throw new \RuntimeException('Улица или дом не распознаны в адресе');
        }

        return [$streetName, $houseValue];
    }

    public function logout(): void
    {
        $this->requireMethod('POST');

        // Проверяем, активна ли сессия
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Очищаем все данные сессии
            $_SESSION = [];
            
            // Удаляем cookie сессии
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
            // Полностью уничтожаем сессию
            session_destroy();
        }

        $this->json(['ok' => true]);
    }

    public function status(): void
    {
        $this->requireMethod('GET');

        $voter = $_SESSION['voter'] ?? null;

        if (!$voter) {
            $this->json([
                'ok'         => true,
                'authorized' => false,
            ]);
            return;
        }

        $this->json([
            'ok'         => true,
            'authorized' => true,
            'voter'      => [
                'user_id'      => $voter['user_id']      ?? null,
                'fio'          => $voter['fio']          ?? null,
                'phone'        => $voter['phone']        ?? null,
                'district_id'  => $voter['district_id']  ?? null,
                'district_num' => $voter['district_num'] ?? null,
            ],
        ]);
    }

    public function precheck(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        $phoneRaw = trim($data['phone'] ?? '');
        if (!$phoneRaw) {
            $this->json([
                'ok'    => false,
                'error' => 'Укажите номер телефона',
            ], 422);
            return;
        }

        // Нормализуем телефон так же, как в login()
        $phone = $this->normalizePhone($phoneRaw);
        if (!$phone) {
            $this->json([
                'ok'    => false,
                'error' => 'Некорректный номер телефона',
            ], 422);
            return;
        }

        // Ищем пользователя по телефону
        $existingUser   = $this->users->findByPhone($phone);
        $existingUserId = $existingUser ? (int)$existingUser['id'] : 0;

        $userHasVote = $existingUserId
            ? $this->votes->userHasVote($existingUserId)
            : false;

        // Если по этому номеру уже есть голос — сразу отвечаем ошибкой
        if ($userHasVote) {
            $this->json([
                'ok'    => false,
                'error' => 'Пользователь с этим номером телефона уже проголосовал. Повторная авторизация невозможна.',
            ], 422);
            return;
        }

        // Всё хорошо — по этому номеру ещё не голосовали
        $this->json(['ok' => true]);
    }

    public function checkDistrict(): void
    {
        $this->requireMethod('POST');
        
        // Проверяем, что пользователь авторизован
        $voter = $_SESSION['voter'] ?? null;
        if (!$voter) {
            $this->json([
                'ok'    => false,
                'error' => 'Вы не авторизованы',
            ], 401);
            return;
        }

        $data = $this->getJsonBody();
        $candidateId = (int)($data['candidate_id'] ?? 0);

        if (!$candidateId) {
            $this->json(['ok' => false, 'error' => 'Не указан кандидат'], 422);
            return;
        }

        // Получаем округ кандидата
        $candidateDistrict = $this->candidates->getCandidateDistrict($candidateId);
        if (!$candidateDistrict) {
            $this->json(['ok' => false, 'error' => 'Кандидат не найден'], 422);
            return;
        }

        $userDistrictId = (int)($voter['district_id'] ?? 0);
        $userDistrictNum = (string)($voter['district_num'] ?? '');
        $candidateDistrictId = (int)$candidateDistrict['id'];
        $candidateDistrictNum = (string)($candidateDistrict['number'] ?? '');

        // Проверяем совпадение округов
        $canVote = ($userDistrictId === $candidateDistrictId);

        if (!$canVote) {
            $this->json([
                'ok'                     => false,
                'can_vote'               => false,
                'user_district_id'       => $userDistrictId,
                'user_district_num'      => $userDistrictNum,
                'candidate_district_id'  => $candidateDistrictId,
                'candidate_district_num' => $candidateDistrictNum,
                'error'                  => 'Вы не можете проголосовать за этого кандидата, так как он относится к другому округу. Ваш округ: ' . $userDistrictNum,
            ], 422);
            return;
        }

        $this->json([
            'ok'                     => true,
            'can_vote'               => true,
            'user_district_id'       => $userDistrictId,
            'user_district_num'      => $userDistrictNum,
            'candidate_district_id'  => $candidateDistrictId,
            'candidate_district_num' => $candidateDistrictNum,
        ]);
    }

    public function vkOneTap(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        if (!$data) {
            $this->json([
                'ok' => false,
                'error' => 'VK не передал данные авторизации',
            ], 422);
            return;
        }

        try {
            $vkUser = $this->normalizeVkPayload($data);
        } catch (\Throwable $e) {
            error_log('VK normalize error: ' . $e->getMessage());
            $this->json([
                'ok' => false,
                'error' => 'Не удалось обработать данные VK',
            ], 422);
            return;
        }

        if (empty($vkUser['vk_id'])) {
            $this->json([
                'ok' => false,
                'error' => 'VK не передал идентификатор пользователя',
            ], 422);
            return;
        }

        $existingUser = $this->users->findByVkId((string)$vkUser['vk_id']);

        if (!$existingUser && !empty($vkUser['phone'])) {
            $existingUser = $this->users->findByPhone((string)$vkUser['phone']);
        }

        $saveData = [
            'surname'        => $vkUser['last_name'] ?: null,
            'name'           => $vkUser['first_name'] ?: null,
            'patronymic'     => null,
            'phone'          => $vkUser['phone'] ?: null,
            'link_vk'        => $vkUser['vk_profile_url'],
            'vk_id'          => $vkUser['vk_id'],
            'vk_phone'       => $vkUser['phone'] ?: null,
            'vk_email'       => $vkUser['email'] ?: null,
            'vk_avatar'      => $vkUser['avatar'] ?: null,
            'vk_profile_url' => $vkUser['vk_profile_url'],
            'phone_verified' => 0,
            'auth_method'    => 'ВК',
        ];

        if ($existingUser) {
            $userId = (int)$existingUser['id'];
            $this->users->updateVkData($userId, $saveData);
        } else {
            $userId = $this->users->createFromVk($saveData);
        }

        $_SESSION['vk_login'] = [
            'user_id'        => $userId,
            'vk_id'          => $vkUser['vk_id'],
            'first_name'     => $vkUser['first_name'],
            'last_name'      => $vkUser['last_name'],
            'middle_name'    => null,
            'phone'          => $vkUser['phone'],
            'vk_phone'       => $vkUser['phone'],
            'vk_email'       => $vkUser['email'],
            'vk_avatar'      => $vkUser['avatar'],
            'vk_profile_url' => $vkUser['vk_profile_url'],
        ];

        $this->json([
            'ok' => true,
            'message' => 'VK ID успешно подключён. Введите отчество и продолжите авторизацию.',
            'prefill' => [
                'user_id'        => $userId,
                'vk_id'          => $vkUser['vk_id'],
                'first_name'     => $vkUser['first_name'],
                'last_name'      => $vkUser['last_name'],
                'phone'          => $vkUser['phone'],
                'vk_profile_url' => $vkUser['vk_profile_url'],
            ],
        ]);
    }

    public function vkMiddleName(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        $vkLogin = $_SESSION['vk_login'] ?? null;
        if (!$vkLogin || empty($vkLogin['user_id'])) {
            $this->json([
                'ok' => false,
                'error' => 'Сначала выполните вход через VK',
            ], 401);
            return;
        }

        $middleName = trim((string)($data['middle_name'] ?? ''));
        $middleName = preg_replace('/\s+/u', ' ', $middleName) ?? '';

        if (!preg_match('/^[А-Яа-яЁё][А-Яа-яЁё\-\s]{1,99}$/u', $middleName)) {
            $this->json([
                'ok' => false,
                'error' => 'Введите корректное отчество русскими буквами',
            ], 422);
            return;
        }

        $middleName = $this->capitalizeRussianName($middleName);

        $this->users->updateVkMiddleName((int)$vkLogin['user_id'], $middleName);

        $_SESSION['vk_login']['middle_name'] = $middleName;

        $fullName = trim(($vkLogin['last_name'] ?? '') . ' ' . ($vkLogin['first_name'] ?? '') . ' ' . $middleName);

        $this->json([
            'ok' => true,
            'prefill' => [
                'fio' => $fullName,
                'phone' => $vkLogin['phone'] ?? '',
                'vk_profile_url' => $vkLogin['vk_profile_url'] ?? null,
            ],
        ]);
    }

    private function normalizeVkPayload(array $data): array
    {
        $claims = [];
        if (!empty($data['id_token']) && is_string($data['id_token'])) {
            $claims = $this->decodeJwtPayload($data['id_token']);
        }

        $sources = [];
        foreach (['user', 'userinfo', 'user_info', 'userInfo', 'profile', 'payload'] as $key) {
            if (!empty($data[$key]) && is_array($data[$key])) {
                $sources[] = $data[$key];
            }
        }
        $sources[] = $data;
        if ($claims) {
            $sources[] = $claims;
        }

        $vkId = $this->firstScalar($sources, ['vk_id', 'user_id', 'id', 'sub']);
        $firstName = $this->firstScalar($sources, ['first_name', 'firstName', 'given_name', 'givenName']);
        $lastName = $this->firstScalar($sources, ['last_name', 'lastName', 'family_name', 'familyName']);
        $fullName = $this->firstScalar($sources, ['name', 'full_name', 'fullName']);

        if ((!$firstName || !$lastName) && $fullName) {
            $parts = preg_split('/\s+/u', trim($fullName));
            if (!$firstName && isset($parts[1])) {
                $firstName = $parts[1];
            } elseif (!$firstName && isset($parts[0])) {
                $firstName = $parts[0];
            }
            if (!$lastName && isset($parts[0])) {
                $lastName = $parts[0];
            }
        }

        $phoneRaw = $this->firstScalar($sources, ['phone', 'phone_number', 'phoneNumber', 'mobile_phone']);
        $phone = $phoneRaw ? $this->normalizePhone((string)$phoneRaw) : null;

        $email = $this->firstScalar($sources, ['email']);
        $avatar = $this->firstScalar($sources, ['avatar', 'photo', 'picture', 'photo_200', 'photoMaxOrig']);

        $vkId = $vkId ? preg_replace('/\D+/', '', (string)$vkId) : null;
        $vkProfileUrl = $vkId ? 'https://vk.com/id' . $vkId : null;

        return [
            'vk_id'          => $vkId,
            'first_name'     => $firstName ? $this->capitalizeRussianName((string)$firstName) : null,
            'last_name'      => $lastName ? $this->capitalizeRussianName((string)$lastName) : null,
            'phone'          => $phone,
            'email'          => $email,
            'avatar'         => $avatar,
            'vk_profile_url' => $vkProfileUrl,
        ];
    }

    private function firstScalar(array $sources, array $keys): ?string
    {
        foreach ($sources as $source) {
            foreach ($keys as $key) {
                if (isset($source[$key]) && is_scalar($source[$key]) && trim((string)$source[$key]) !== '') {
                    return trim((string)$source[$key]);
                }
            }
        }

        foreach ($sources as $source) {
            $found = $this->firstScalarRecursive($source, $keys);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function firstScalarRecursive(array $source, array $keys): ?string
    {
        foreach ($source as $key => $value) {
            if (in_array((string)$key, $keys, true) && is_scalar($value) && trim((string)$value) !== '') {
                return trim((string)$value);
            }

            if (is_array($value)) {
                $found = $this->firstScalarRecursive($value, $keys);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }

        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $json = base64_decode($payload, true);
        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function capitalizeRussianName(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $parts = preg_split('/([\s\-]+)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!$parts) {
            return $value;
        }

        foreach ($parts as $i => $part) {
            if (preg_match('/^[А-Яа-яЁё]+$/u', $part)) {
                $parts[$i] = mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8') .
                    mb_strtolower(mb_substr($part, 1, null, 'UTF-8'), 'UTF-8');
            }
        }

        return implode('', $parts);
    }

    private function buildAbsoluteUrl(string $path): string
    {
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO']
            ?? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443) ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $proto . '://' . $host . $path;
    }
}
