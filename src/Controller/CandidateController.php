<?php
namespace App\Controller;

use App\Repository\Contract\CandidateRepositoryInterface;
use DomainException;
use Throwable;

class CandidateController extends BaseController
{
    public function __construct(private CandidateRepositoryInterface $candidates) {}

    public function index(): void
    {
        $json = (isset($_GET['format']) && $_GET['format'] === 'json')
              || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($json) {
            $all   = ($_GET['all'] ?? '') === '1';
            $limit = (int)($_GET['limit'] ?? 5);
            $data  = $all ? $this->candidates->all() : $this->candidates->first($limit);
            $this->json($data);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/../../public/candidates.html');
    }

    public function indexAdmin(): void
    {
        $json = (isset($_GET['format']) && $_GET['format'] === 'json')
              || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($json) {
            $data  = $this->candidates->all();
            $this->json($data);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/../../public/candidates-admin.html');
    }

    public function showAdd(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/../../public/candidate-add-admin.html');
    }

    public function show(): void
    {
        $wantsJson = (($_GET['format'] ?? '') === 'json') ||
                 (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
                 
        $id = (int)($_GET['id'] ?? 0);

        if ($wantsJson) {
        if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'bad id']); return; }
        $c = $this->candidates->find($id);
        if (!$c) { http_response_code(404); echo json_encode(['error'=>'not found']); return; }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($c, JSON_UNESCAPED_UNICODE);
        return;
        }

        header_remove('Content-Type');
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/../../public/candidate.html');
    }

    // POST /api/candidates
    public function store(): void
    {
        $this->requireMethod('POST');

        error_log('STORE $_POST: ' . json_encode($_POST, JSON_UNESCAPED_UNICODE));

        $fullName = trim($_POST['full_name'] ?? '');

        $surname = $name = $patronymic = null;

        if ($fullName !== '') {
            // Разбиваем по одному или более пробелов
            $parts = preg_split('/\s+/', $fullName);

            $surname    = $parts[0] ?? null; // первая часть
            $name       = $parts[1] ?? null; // вторая часть
            $patronymic = $parts[2] ?? null; // третья часть (может отсутствовать)
        }
        error_log('STORE parts: ' . json_encode($parts, JSON_UNESCAPED_UNICODE));
        error_log('STORE surname: ' . var_export($surname, true));
        error_log('STORE name: ' . var_export($name, true));
        error_log('STORE patronymic: ' . var_export($patronymic, true));
        if ($surname === null) {
            $this->json(['ok' => false, 'error' => 'Некорректное ФИО, нет фамилии'], 422);
            return;
        }

        // Собираем данные из формы и мапим под репозиторий
        $data = [
            'surname'     => $surname,
            'name'        => $name,
            'patronymic'  => $patronymic,
            'phone'       => $_POST['phone']       ?? null,
            'district'    => $_POST['address']    ?? null,
            'email'       => $_POST['email']       ?? null,
            'description' => $_POST['description'] ?? null,
            'photo'       => null,
        ];

        error_log('STORE data before photo: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

        // Обработка фото
        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            // поправь путь под свою структуру (скорее всего /public/assets/...)
            $uploadDir = __DIR__ . '/../../public/assets/img/candidates/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $originalName = $_FILES['photo']['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed, true)) {
                $this->json(['ok' => false, 'error' => 'Недопустимый формат файла'], 422);
                return;
            }

            $fileName   = uniqid('candidate_', true) . '.' . $ext;
            $targetPath = $uploadDir . $fileName;

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                $this->json(['ok' => false, 'error' => 'Не удалось сохранить файл'], 500);
                return;
            }

            // путь, который будет храниться в БД и использоваться в <img src="...">
            $data['photo'] = '/assets/img/candidates/' . $fileName;
        }

        try {
            $id = $this->candidates->create($data);

            // если это форма, логичнее редирект, а не JSON
            header('Location: /candidates/admin');
            exit;
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            // Лог в файл / в error_log
            error_log("CANDIDATE_STORE_ERROR: " . $e->getMessage());
            error_log($e->getTraceAsString());

            // Временно отдаём реальный текст ошибки наружу
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function edit(string $id): void
    {
        $id = (int)$id;
        error_log("EDIT: called with id = " . $id);
        try {
            $candidate = $this->candidates->findById($id);

            if (!$candidate) {
                $this->json(['ok' => false, 'error' => 'Кандидат не найден'], 404);
                return;
            }
            // Подключаешь view и передаёшь $candidate
            include __DIR__ . '/../../public/candidate-update-admin.html';

        } catch (Throwable $e) {
            error_log("EDIT ERROR: " . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Ошибка сервера'], 500);
        }
    }

    // PATCH /api/candidates/{id}
    public function update(string $id): void
    {
        $id = (int)$id;

        // защита от кривого id
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'Некорректный ID кандидата'], 400);
            return;
        }

        // ожидаем обычный POST из формы
        $this->requireMethod('POST');

        // Пытаемся найти существующего кандидата (чтобы, например, не потерять старое фото)
        $existing = $this->candidates->findById($id);
        if (!$existing) {
            $this->json(['ok' => false, 'error' => 'Кандидат не найден'], 404);
            return;
        }

        // --------- ФИО из full_name ---------
        $fullName = trim($_POST['full_name'] ?? '');
        $surname = $name = $patronymic = null;

        if ($fullName !== '') {
            $parts = preg_split('/\s+/', $fullName);
            $surname    = $parts[0] ?? null;
            $name       = $parts[1] ?? null;
            $patronymic = $parts[2] ?? null;
        }

        if ($surname === null) {
            $this->json(['ok' => false, 'error' => 'Некорректное ФИО'], 422);
            return;
        }

        // --------- Остальные поля из формы ---------
        $data = [
            'surname'     => $surname,
            'name'        => $name,
            'patronymic'  => $patronymic,
            'phone'       => $_POST['phone']       ?? null,
            'district'    => $_POST['address']     ?? null, // address в форме = district в БД
            'email'       => $_POST['email']       ?? null,
            'description' => $_POST['description'] ?? null,
            'photo'       => $existing['photo']    ?? null, // по умолчанию оставляем старое фото
        ];

        // --------- Фото (если выбрали новое) ---------
        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {

            $uploadDir = __DIR__ . '/../../public/assets/img/candidates/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $originalName = $_FILES['photo']['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed, true)) {
                $this->json(['ok' => false, 'error' => 'Неверный формат фото'], 422);
                return;
            }

            $fileName   = uniqid('candidate_', true) . '.' . $ext;
            $targetPath = $uploadDir . $fileName;

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                $this->json(['ok' => false, 'error' => 'Ошибка сохранения фото'], 500);
                return;
            }

            $data['photo'] = '/assets/img/candidates/' . $fileName;
        }

        // --------- Сохранение ---------
        try {
            $this->candidates->update($id, $data);

            // Так как это форма — логичнее сделать редирект, а не JSON
            header('Location: /candidates/admin');
            exit;

            // Если нужен JSON-ответ:
            // $this->json(['ok' => true]);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }


    // DELETE /api/candidates/{id}
    public function destroy(int $id): void
    {
        $this->requireMethod('DELETE');

        try {
            $this->candidates->delete($id);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }
}
