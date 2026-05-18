<?php
namespace App\Controller;

use App\Repository\Contract\CandidateRepositoryInterface;
use DomainException;
use Throwable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class CandidateController extends BaseController
{
    public function __construct(private CandidateRepositoryInterface $candidates) {}

    public function index(): void
    {
        $json = (isset($_GET['format']) && $_GET['format'] === 'json')
              || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($json) {
            $all   = ($_GET['all'] ?? '') === '1';
            $limit = (int)($_GET['limit'] ?? 8);
            $data  = $all ? $this->candidates->all() : $this->candidates->first($limit);
            $this->json($data);
            return;
        }

        require __DIR__ . '/../../public/candidate/candidates.php';
    }

    public function adminIndex(): void
    {
        $json = (isset($_GET['format']) && $_GET['format'] === 'json')
              || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($json) {
            $data  = $this->candidates->all();
            $this->json($data);
            return;
        }

        require __DIR__ . '/../../public/candidate-admin/candidates-admin.php';
    }

    public function showAdd(): void
    {
        require __DIR__ . '/../../public/candidate-admin/candidate-add-admin.php';
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
        $districtNum = (int)($c['district'] ?? 0);
        if ($districtNum > 0) {
            $c['district_addresses'] = $this->loadDistrictAddresses($districtNum);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($c, JSON_UNESCAPED_UNICODE);
        return;
        }

        $candidateFio = '';
        if ($id > 0) {
            try {
                $c = $this->candidates->find($id);
                $candidateFio = trim(
                    ($c['surname'] ?? '') . ' ' .
                    ($c['name'] ?? '') . ' ' .
                    ($c['patronymic'] ?? '')
                );
            } catch (\Throwable) {
                $candidateFio = '';
            }
        }

        require __DIR__ . '/../../public/candidate/candidate.php';
    }

    /** @return list<string> */
    private function loadDistrictAddresses(int $districtNum): array
    {
        if ($districtNum < 1 || $districtNum > 40) {
            return [];
        }

        $path = __DIR__ . '/../../public/assets/district/' . $districtNum . '.txt';
        if (!is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $addresses = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line !== '') {
                $addresses[] = $line;
            }
        }

        return $addresses;
    }

    public function store(): void
    {
        $this->requireMethod('POST');

        error_log('STORE $_POST: ' . json_encode($_POST, JSON_UNESCAPED_UNICODE));

        $fullName = trim($_POST['full_name'] ?? '');

        $surname = $name = $patronymic = null;

        if ($fullName !== '') {
            $parts = preg_split('/\s+/', $fullName);

            $surname    = $parts[0] ?? null;
            $name       = $parts[1] ?? null;
            $patronymic = $parts[2] ?? null;
        }
        error_log('STORE parts: ' . json_encode($parts, JSON_UNESCAPED_UNICODE));
        error_log('STORE surname: ' . var_export($surname, true));
        error_log('STORE name: ' . var_export($name, true));
        error_log('STORE patronymic: ' . var_export($patronymic, true));
        if ($surname === null) {
            $this->json(['ok' => false, 'error' => 'Некорректное ФИО, нет фамилии'], 422);
            return;
        }

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

        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
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

            $data['photo'] = '/assets/img/candidates/' . $fileName;
        }

        try {
            $id = $this->candidates->create($data);

            header('Location: /candidates/admin');
            exit;
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            error_log("CANDIDATE_STORE_ERROR: " . $e->getMessage());
            error_log($e->getTraceAsString());

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
            require __DIR__ . '/../../public/candidate-admin/candidate-update-admin.php';

        } catch (Throwable $e) {
            error_log("EDIT ERROR: " . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Ошибка сервера'], 500);
        }
    }

    public function update(string $id): void
    {
        $id = (int)$id;

        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'Некорректный ID кандидата'], 400);
            return;
        }

        $this->requireMethod('POST');

        $existing = $this->candidates->findById($id);
        if (!$existing) {
            $this->json(['ok' => false, 'error' => 'Кандидат не найден'], 404);
            return;
        }

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

        $data = [
            'surname'     => $surname,
            'name'        => $name,
            'patronymic'  => $patronymic,
            'phone'       => $_POST['phone']       ?? null,
            'district'    => $_POST['address']     ?? null,
            'email'       => $_POST['email']       ?? null,
            'description' => $_POST['description'] ?? null,
            'photo'       => $existing['photo']    ?? null,
        ];

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

        try {
            $this->candidates->update($id, $data);

            header('Location: /candidates/admin');
            exit;

        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }


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

    public function indexAdmin(): void
    {
        $this->requireMethod('GET');

        $format  = $_GET['format']  ?? null;
        $deleted = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;

        if ($format === 'json') {
            try {
                $list = $this->candidates->getAdminCandidates($deleted);
                $this->json($list);
            } catch (Throwable $e) {
                $this->json(['ok' => false, 'error' => 'Ошибка загрузки кандидатов'], 500);
            }
            return;
        }

        require __DIR__ . '/../../public/candidate-db/candidates-db.php';
    }

    public function exportExcel(): void
    {
        $deleted = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;

        try {
            $rows = $this->candidates->getAdminCandidates($deleted);
        } catch (Throwable $e) {
            $rows = [];
        }

        if (!is_array($rows)) {
            $rows = [];
        }

        $spreadsheet = new Spreadsheet();

        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Кандидаты');

        $sheet1->fromArray(
            ['ID', 'Дата регистрации', 'Фамилия', 'Имя', 'Отчество', 'Телефон', 'Фото', 'ВК', 'Улица', 'Дом', 'Округ'],
            null,
            'A1'
        );

        $rowIndex = 2;
        $districtStats = [];

        foreach ($rows as $row) {
            $id         = $row['id'] ?? '';
            $createdAt = $row['registration_date'] ?? ($row['created_at'] ?? ($row['created'] ?? ($row['date'] ?? '')));
            $surname    = $row['surname'] ?? '';
            $name       = $row['name'] ?? '';
            $patronymic = $row['patronymic'] ?? '';
            $phone      = $row['phone'] ?? '';
            $photo      = $row['photo'] ?? '';
            $vk         = $row['vk'] ?? ($row['vk_link'] ?? '');
            $street     = $row['street'] ?? '';
            $house      = $row['house'] ?? '';
            $district   = $row['district'] ?? ($row['district_num'] ?? ($row['district_id'] ?? ''));

            $sheet1->setCellValue("A{$rowIndex}", $id);
            $sheet1->setCellValue("B{$rowIndex}", $createdAt);
            $sheet1->setCellValue("C{$rowIndex}", $surname);
            $sheet1->setCellValue("D{$rowIndex}", $name);
            $sheet1->setCellValue("E{$rowIndex}", $patronymic);
            $sheet1->setCellValue("F{$rowIndex}", $phone);
            $sheet1->setCellValue("G{$rowIndex}", $photo);
            $sheet1->setCellValue("H{$rowIndex}", $vk);
            $sheet1->setCellValue("I{$rowIndex}", $street);
            $sheet1->setCellValue("J{$rowIndex}", $house);
            $sheet1->setCellValue("K{$rowIndex}", $district);

            if ($district !== '') {
                $key = (string)$district;
                $districtStats[$key] = ($districtStats[$key] ?? 0) + 1;
            }

            $rowIndex++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = ($deleted ? 'Кандидаты-удалённые-' : 'Кандидаты-') . date('Y-m-d_H-i-s') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }


    public function adminDelete(): void
    {
        $this->requireMethod('DELETE');

        $body = [];
        try {
            $body = $this->getJsonBody();
        } catch (\Throwable $e) {
            // если нет json — попробуем взять id из query
        }

        $id = (int)($body['id'] ?? ($_GET['id'] ?? 0));

        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'Не передан id кандидата'], 422);
            return;
        }

        try {
            $this->candidates->delete($id);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Не удалось удалить кандидата'], 500);
        }
    }

    public function adminDeleted(): void
    {
        $this->requireMethod('GET');

        $format  = $_GET['format']  ?? null;

        if ($format === 'json') {
            try {
                $list = $this->candidates->getAdminCandidates(1);
                $this->json($list);
            } catch (Throwable $e) {
                $this->json(['ok' => false, 'error' => 'Ошибка загрузки удалённых пользователей'], 500);
            }
            return;
        }
        require __DIR__ . '/../../public/candidate-db/deleted-candidates-db.php';
    }

    public function adminEdit(int $id): void
    {
        $this->requireMethod('GET');

        try {
            $candidate = $this->candidates->find($id);
        } catch (Throwable $e) {
            http_response_code(404);
            echo "Кандидат не найден";
            return;
        }

        // сделаем массив доступным в шаблоне под переменной $candidate
        $candidateData = $candidate;

        // подключаем шаблон из public
        require __DIR__ . '/../../public/candidate-admin/candidate-edit.php';
    }
}
