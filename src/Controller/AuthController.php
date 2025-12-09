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

    public function vkRedirect(): void
    {
        echo 'Тут будет редирект на VK OAuth';
    }

    public function vkCallback(): void
    {
        echo 'VK callback – здесь логика получения профиля VK и создание/поиск user';
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

        error_log('[AuthController::sendCode] success, SMS sent');
        $this->json(['ok' => true]);
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

        $phone = trim((string)($data['phone'] ?? ''));
        $code  = trim((string)($data['code'] ?? ''));

        if ($code === '') {
            $this->json(['ok' => false, 'error' => 'Не указан код'], 422);
            return;
        }

        if ($phone !== '') {
            $result = $this->sms->checkCodeForPhone($phone, $code);
        } else {
            $result = $this->sms->checkCodeForCurrentVoter($code);
        }

        if (!$result['ok']) {
            $this->json($result, 422);
            return;
        }

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

        unset($_SESSION['voter']);
        session_regenerate_id(true);

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

    public function vkOneTap(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        // TODO: здесь через отдельный Auth-сервис (Auth.php) обрабатываешь $data,
        // пока просто заглушка:
        $this->json([
            'ok' => false,
            'error' => 'vkOneTap пока не реализован',
        ], 501);
    }
}
