<?php
namespace App\Controller;

use App\Repository\Contract\VoteRepositoryInterface;
use App\Repository\Contract\CandidateRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Repository\Contract\DistrictRepositoryInterface;
use DomainException;
use Throwable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Mpdf\Mpdf;

class VoteController extends BaseController
{
    /**
     * Нормализация телефона в формат +7 (9**) ***-**-**
     */
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

        // Форматируем в формат +7 (9**) ***-**-**
        $formatted = sprintf(
            "+7 (%s) %s-%s-%s",
            substr($digits, 1, 3), // 9XX
            substr($digits, 4, 3), // XXX
            substr($digits, 7, 2), // XX
            substr($digits, 9, 2)  // XX
        );

        return $formatted;
    }
    public function __construct(
        private VoteRepositoryInterface $votes,
        private CandidateRepositoryInterface $candidates,
        private UserRepositoryInterface $users,
        private DistrictRepositoryInterface $districts
    ) {}

    public function voted(): void
    {
        $this->requireMethod('POST');

        // 🔐 МИДЛВАРЬ: проверяем, что голосующий есть в сессии
        $voter = $this->requireVoter();
        $userId        = (int)$voter['user_id'];
        $userDistrictId = (int)($voter['district_id'] ?? 0);

        $data        = $this->getJsonBody();
        $candidateId = (int)($data['candidate_id'] ?? 0);

        if (!$candidateId) {
            $this->json(['ok' => false, 'error' => 'Не передан кандидат'], 422);
            return;
        }

        if (!$userId || !$userDistrictId) {
            $this->json([
                'ok'    => false,
                'error' => 'Не удалось определить ваш округ. Попробуйте авторизоваться заново.',
            ], 422);
            return;
        }

        // 🔎 узнаём округ кандидата
        $candidateDistrict = $this->candidates->getCandidateDistrict($candidateId);
        if (!$candidateDistrict) {
            $this->json(['ok' => false, 'error' => 'Кандидат не найден'], 422);
            return;
        }

        // getCandidateDistrict возвращает что-то вроде: ['id' => <district_id>, 'district' => <номер>]
        $candidateDistrictId = (int)$candidateDistrict['id'];

        // 🚫 Если округа не совпадают — голос запрещён
        if ($candidateDistrictId !== $userDistrictId) {
            $this->json([
                'ok'    => false,
                'error' =>
                    'Вы не можете голосовать за кандидата из другого округа. ' .
                    'Ваш округ: ' . ($voter['district_num'] ?? 'не определён') . '. ' .
                    'Округ кандидата: ' . ($candidateDistrict['district'] ?? 'не указан') . '.',
                    'user_district_id'       => $userDistrictId,
                    'user_district_num'      => $voter['district_num'] ?? null,
                    'candidate_district_id'  => $candidateDistrictId,
                    'candidate_district_num' => $candidateDistrict['district'] ?? null,
            ], 422);
            return;
        }

        try {
            $vote = $this->votes->voted($userId, $candidateId);
            $this->json(['ok' => true, 'data' => $vote], 201);
        } catch (DomainException $e) {
            // сюда, например, прилетит "Вы уже голосовали"
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    public function index(): void
    {
        $format = $_GET['format'] ?? '';

        $rows = $this->votes->all();

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $title = 'Проголосовавшие';
        $votes = $rows;

        require __DIR__ . '/../../public/votes-db/votes-admin.php';
    }

    public function exportExcel(): void
    {
        $rows = $this->votes->getAdminList();
        if (!is_array($rows)) {
            $rows = [];
        }
        $spreadsheet = new Spreadsheet();
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Голосовавшие');
        $sheet1->fromArray(
            ['Номер округа', 'ФИО избирателя', 'Адрес', 'Телефон', 'Кандидат'],
            null,
            'A1'
        );
        $rowIndex = 2;
        $stats = [];

        foreach ($rows as $row) {
            $district = $row['district'] ?? $row['district_id'] ?? '';
            $fio      = $row['user_name'] ?? $row['fio'] ?? '';
            $address  = $row['address'] ?? '';
            $phone    = $row['phone'] ?? '';
            $candName = $row['candidate'] ?? $row['candidate_name'] ?? '';
            $candDistr = $row['candidate_district'] ?? $district;

            $sheet1->setCellValue("A{$rowIndex}", $district);
            $sheet1->setCellValue("B{$rowIndex}", $fio);
            $sheet1->setCellValue("C{$rowIndex}", $address);
            $sheet1->setCellValue("D{$rowIndex}", $phone);
            $sheet1->setCellValue("E{$rowIndex}", $candName);
            if ($candName !== '') {
                if (!isset($stats[$candName])) {
                    $stats[$candName] = [
                        'count'    => 0,
                        'district' => $candDistr,
                    ];
                }
                $stats[$candName]['count']++;
            }

            $rowIndex++;
        }

        foreach (['A','B','C','D','E'] as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Статистика');

        $sheet2->fromArray(
            ['Кандидат', 'Округ', 'Количество голосов', '% от общего числа'],
            null,
            'A1'
        );

        $totalVotes = array_sum(array_column($stats, 'count'));
        $rowIndex = 2;

        foreach ($stats as $candidateName => $info) {
            $count = $info['count'];
            $district = $info['district'];
            $percent = $totalVotes > 0
                ? round($count * 100 / $totalVotes, 2)
                : 0.0;

            $sheet2->setCellValue("A{$rowIndex}", $candidateName);
            $sheet2->setCellValue("B{$rowIndex}", $district);
            $sheet2->setCellValue("C{$rowIndex}", $count);
            $sheet2->setCellValue("D{$rowIndex}", $percent);

            $rowIndex++;
        }

        foreach (['A','B','C','D'] as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'Проголосовавшие-' . date('Y-m-d_H-i-s') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function deletedIndex(): void
    {
        $format = $_GET['format'] ?? null;

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->votes->getAdminList(true), JSON_UNESCAPED_UNICODE);
            return;
        }

        // можно отдельный шаблон, а можно тот же, но другая JS-обвязка
        require __DIR__ . '/../../public/votes-db/votes-deleted-admin.php';
    }

    public function delete(int $id): void
    {
        $ok = $this->votes->softDelete($id);

        header('Content-Type: application/json; charset=utf-8');
        if (!$ok) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Голос не найден или уже удалён'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    public function adminUpdate(): void
    {
        $this->requireMethod('PUT');
        $data = $this->getJsonBody();

        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            $this->json([
                'ok'    => false,
                'error' => 'Некорректный ID голоса',
            ], 422);
            return;
        }

        $payload = [
            'user_name' => trim((string)($data['user_name'] ?? '')),
            'address'   => trim((string)($data['address'] ?? '')), // пока не используем в репозитории
            'phone'     => trim((string)($data['phone'] ?? '')),
        ];

        try {
            $ok = $this->votes->adminUpdate($id, $payload);

            if (!$ok) {
                $this->json([
                    'ok'    => false,
                    'error' => 'Голос не найден или не изменён',
                ], 404);
                return;
            }

            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            error_log('votes.adminUpdate error: '.$e->getMessage());
            $this->json([
                'ok'    => false,
                'error' => 'Ошибка при сохранении изменений',
            ], 500);
        }
    }

    public function adminDelete(): void
    {
        $this->requireMethod('DELETE');
        $data = $this->getJsonBody();

        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            $this->json([
                'ok'    => false,
                'error' => 'Некорректный ID голоса',
            ], 422);
            return;
        }

        try {
            $ok = $this->votes->softDelete($id);

            if (!$ok) {
                $this->json([
                    'ok'    => false,
                    'error' => 'Голос не найден',
                ], 404);
                return;
            }

            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            error_log('votes.adminDelete error: '.$e->getMessage());
            $this->json([
                'ok'    => false,
                'error' => 'Ошибка при удалении голоса',
            ], 500);
        }
    }

    /**
     * Скачивание шаблона Excel только с шапками таблицы
     * Ширина колонок: x, 4x, 7x, 3x, 4x
     * Колонка "Кандидат" содержит выпадающий список всех доступных кандидатов
     */
    public function exportTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Голосовавшие');

        // Только шапки таблицы
        $sheet->fromArray(
            ['Номер округа', 'ФИО избирателя', 'Адрес', 'Телефон', 'Кандидат'],
            null,
            'A1'
        );

        // Устанавливаем ширину колонок: x, 4x, 7x, 3x, 4x (где x = 5)
        $baseWidth = 5;
        $sheet->getColumnDimension('A')->setWidth($baseWidth * 2);      // Номер округа: x
        $sheet->getColumnDimension('B')->setWidth($baseWidth * 7); // ФИО избирателя: 4x
        $sheet->getColumnDimension('C')->setWidth($baseWidth * 10); // Адрес: 7x
        $sheet->getColumnDimension('D')->setWidth($baseWidth * 3); // Телефон: 3x
        $sheet->getColumnDimension('E')->setWidth($baseWidth * 7); // Кандидат: 4x

        // Получаем список всех кандидатов
        $candidatesList = $this->candidates->all();
        $candidateNames = [];
        
        foreach ($candidatesList as $candidate) {
            $surname = trim($candidate['surname'] ?? '');
            $name = trim($candidate['name'] ?? '');
            $patronymic = trim($candidate['patronymic'] ?? '');
            
            $fullName = trim(implode(' ', array_filter([$surname, $name, $patronymic])));
            if (!empty($fullName)) {
                $candidateNames[] = $fullName;
            }
        }

        // Создаем выпадающий список для колонки "Кандидат"
        if (!empty($candidateNames)) {
            // Записываем список кандидатов в колонку F (видимая, но не используется в таблице)
            // Excel лучше работает с выпадающими списками, когда источник данных видим
            foreach ($candidateNames as $index => $name) {
                $sheet->setCellValue('AA' . ($index + 1), $name);
            }
            
            // Устанавливаем узкую ширину для колонки F, чтобы она не мешала
            $sheet->getColumnDimension('AA')->setWidth(1);
            
            // Создаем диапазон для списка кандидатов (абсолютные ссылки)
            $lastRow = count($candidateNames);
            $range = '$AA$1:$AA$' . $lastRow;
            
            // Применяем валидацию к колонке E (начиная со строки 2, до 1000 строк)
            $validation = $sheet->getCell('E2')->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(false); // Отключаем подсказку
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Ошибка ввода');
            $validation->setError('Выберите значение из выпадающего списка');
            $validation->setFormula1($range);
            // setShowDropDown по умолчанию true для TYPE_LIST, но явно установим
            $validation->setShowDropDown(true);

            // Копируем валидацию на все строки (до 1000 строк)
            for ($row = 2; $row <= 1000; $row++) {
                $newValidation = clone $validation;
                $sheet->getCell("E{$row}")->setDataValidation($newValidation);
            }
        }

        // Стилизация шапки
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E0E0']
            ]
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        $fileName = 'Образец-Проголосовавшие.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Импорт данных из Excel файла
     */
    public function importExcel(): void
    {
        $this->requireMethod('POST');

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'Файл не загружен'], 422);
            return;
        }

        $file = $_FILES['file'];
        $tmpPath = $file['tmp_name'];

        // Проверяем расширение
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            $this->json(['ok' => false, 'error' => 'Файл должен быть в формате .xlsx'], 422);
            return;
        }

        try {
            $spreadsheet = IOFactory::load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (empty($rows)) {
                $this->json(['ok' => false, 'error' => 'Файл пуст'], 422);
                return;
            }

            // Проверяем шапки
            $expectedHeaders = ['Номер округа', 'ФИО избирателя', 'Адрес', 'Телефон', 'Кандидат'];
            $actualHeaders = array_slice($rows[0], 0, 5);

            if ($actualHeaders !== $expectedHeaders) {
                $this->json([
                    'ok' => false,
                    'error' => 'Шапки таблицы не соответствуют ожидаемым. Ожидаются: ' . implode(', ', $expectedHeaders)
                ], 422);
                return;
            }

            // Пропускаем шапку и обрабатываем данные
            $imported = 0;
            $errors = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Пропускаем пустые строки
                if (empty(array_filter($row))) {
                    continue;
                }

                $district = trim((string)($row[0] ?? ''));
                $fio = trim((string)($row[1] ?? ''));
                $address = trim((string)($row[2] ?? ''));
                $phone = trim((string)($row[3] ?? ''));
                $candidate = trim((string)($row[4] ?? ''));

                // Минимальная валидация
                if (empty($fio)) {
                    $errors[] = "Строка " . ($i + 1) . ": не указано ФИО избирателя";
                    continue;
                }

                if (empty($district)) {
                    $errors[] = "Строка " . ($i + 1) . ": не указан номер округа";
                    continue;
                }

                if (empty($candidate)) {
                    $errors[] = "Строка " . ($i + 1) . ": не указан кандидат";
                    continue;
                }

                try {
                    // 1. Найти округ по номеру
                    $districtId = null;
                    $allDistricts = $this->districts->all();
                    foreach ($allDistricts as $dist) {
                        if ((string)($dist['district'] ?? '') === $district) {
                            $districtId = (int)($dist['id'] ?? 0);
                            break;
                        }
                    }
                    
                    if (!$districtId) {
                        $errors[] = "Строка " . ($i + 1) . ": округ '{$district}' не найден";
                        continue;
                    }

                    // 2. Найти кандидата по полному имени
                    $candidateId = null;
                    $allCandidates = $this->candidates->all();
                    foreach ($allCandidates as $cand) {
                        $candSurname = trim($cand['surname'] ?? '');
                        $candName = trim($cand['name'] ?? '');
                        $candPatronymic = trim($cand['patronymic'] ?? '');
                        $candFullName = trim(implode(' ', array_filter([$candSurname, $candName, $candPatronymic])));
                        
                        if ($candFullName === $candidate) {
                            $candidateId = (int)($cand['id'] ?? 0);
                            break;
                        }
                    }
                    
                    if (!$candidateId) {
                        $errors[] = "Строка " . ($i + 1) . ": кандидат '{$candidate}' не найден";
                        continue;
                    }

                    // 3. Найти или создать пользователя
                    $userId = null;
                    
                    // Нормализуем телефон в формат +7 (9**) ***-**-**
                    $normalizedPhone = null;
                    if (!empty($phone)) {
                        $normalizedPhone = $this->normalizePhone($phone);
                        if ($normalizedPhone) {
                            // Пытаемся найти по нормализованному телефону
                            $user = $this->users->findByPhone($normalizedPhone);
                            if ($user) {
                                $userId = (int)($user['id'] ?? 0);
                            }
                        }
                    }
                    
                    // Если не нашли по телефону, создаем нового пользователя
                    if (!$userId) {
                        // Парсим ФИО
                        $fioParts = preg_split('/\s+/', $fio);
                        $surname = $fioParts[0] ?? '';
                        $name = $fioParts[1] ?? '';
                        $patronymic = isset($fioParts[2]) ? implode(' ', array_slice($fioParts, 2)) : '';
                        
                        // Находим street_id и house_id по адресу (если указан)
                        $streetId = null;
                        $houseId = null;
                        if (!empty($address)) {
                            $addressInfo = $this->districts->findDistrictByStreetAndHouse($address, '');
                            if ($addressInfo) {
                                $streetId = $addressInfo['street_id'] ?? null;
                                $houseId = $addressInfo['house_id'] ?? null;
                            }
                        }
                        
                        $userId = $this->users->create([
                            'surname' => $surname,
                            'name' => $name,
                            'patronymic' => $patronymic,
                            'phone' => $normalizedPhone ?: null,
                            'district_id' => $districtId,
                            'street_id' => $streetId,
                            'house_id' => $houseId,
                            'auth_method' => 'Телефон'
                        ]);
                    }

                    // 4. Проверяем, не голосовал ли уже пользователь
                    if ($this->votes->userHasVote($userId)) {
                        $errors[] = "Строка " . ($i + 1) . ": пользователь уже голосовал";
                        continue;
                    }

                    // 5. Создаем голос
                    $this->votes->createVote($userId, $candidateId);
                    $imported++;

                } catch (\Throwable $e) {
                    error_log("Import error on row " . ($i + 1) . ": " . $e->getMessage());
                    $errors[] = "Строка " . ($i + 1) . ": ошибка импорта - " . $e->getMessage();
                    continue;
                }
            }

            if ($imported === 0 && !empty($errors)) {
                $this->json([
                    'ok' => false,
                    'error' => 'Не удалось импортировать данные: ' . implode('; ', array_slice($errors, 0, 5))
                ], 422);
                return;
            }

            $this->json([
                'ok' => true,
                'imported' => $imported,
                'errors' => array_slice($errors, 0, 10) // Первые 10 ошибок
            ]);

        } catch (\Throwable $e) {
            error_log('VoteController::importExcel error: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Ошибка при обработке файла: ' . $e->getMessage()], 500);
        } finally {
            // Удаляем временный файл
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * Экспорт данных в PDF
     * Использует библиотеку mPDF для генерации PDF файла
     */
    public function exportPdf(): void
    {
        $rows = $this->votes->getAdminList();
        if (!is_array($rows)) {
            $rows = [];
        }

        try {
            // Создаем экземпляр mPDF
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'L', // Landscape (альбомная ориентация) для лучшего отображения таблицы
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 15,
                'margin_bottom' => 15,
            ]);

            // Генерируем HTML для PDF
            $html = '
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; }
                h1 { text-align: center; font-size: 16px; margin-bottom: 15px; color: #247CCD; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; }
                th, td { border: 1px solid #333; padding: 5px; text-align: left; }
                th { background-color: #f0f0f0; font-weight: bold; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .col-district { width: 8%; }
                .col-name { width: 20%; }
                .col-address { width: 35%; }
                .col-phone { width: 15%; }
                .col-candidate { width: 22%; }
                .footer { margin-top: 20px; font-size: 8px; text-align: center; color: #666; }
            </style>
            <h1>Проголосовавшие</h1>
            <table>
                <thead>
                    <tr>
                        <th class="col-district">Номер округа</th>
                        <th class="col-name">ФИО избирателя</th>
                        <th class="col-address">Адрес</th>
                        <th class="col-phone">Телефон</th>
                        <th class="col-candidate">Кандидат</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($rows as $row) {
                $district = htmlspecialchars($row['district'] ?? $row['district_id'] ?? '');
                $fio = htmlspecialchars($row['user_name'] ?? $row['fio'] ?? '');
                $address = htmlspecialchars($row['address'] ?? '');
                $phone = htmlspecialchars($row['phone'] ?? '');
                $candidate = htmlspecialchars($row['candidate'] ?? $row['candidate_name'] ?? '');

                $html .= "<tr>
                    <td class=\"col-district\">{$district}</td>
                    <td class=\"col-name\">{$fio}</td>
                    <td class=\"col-address\">{$address}</td>
                    <td class=\"col-phone\">{$phone}</td>
                    <td class=\"col-candidate\">{$candidate}</td>
                </tr>";
            }

            $html .= '</tbody></table>
            <div class="footer">
                Сгенерировано: ' . date('d.m.Y H:i:s') . ' | Всего записей: ' . count($rows) . '
            </div>';

            // Записываем HTML в PDF
            $mpdf->WriteHTML($html);

            // Генерируем имя файла
            $fileName = 'Проголосовавшие-' . date('Y-m-d_H-i-s') . '.pdf';

            // Отправляем PDF в браузер
            $mpdf->Output($fileName, 'D'); // 'D' = Download
            exit;

        } catch (\Throwable $e) {
            error_log('VoteController::exportPdf error: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Ошибка при генерации PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Экспорт данных в CSV формат
     */
    public function exportCsv(): void
    {
        $rows = $this->votes->getAdminList();
        if (!is_array($rows)) {
            $rows = [];
        }

        $fileName = 'Проголосовавшие-' . date('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        // Открываем поток вывода
        $output = fopen('php://output', 'w');

        // Добавляем BOM для корректного отображения кириллицы в Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Записываем заголовки
        fputcsv($output, ['Номер округа', 'ФИО избирателя', 'Адрес', 'Телефон', 'Кандидат'], ';');

        // Записываем данные
        foreach ($rows as $row) {
            $district = $row['district'] ?? $row['district_id'] ?? '';
            $fio = $row['user_name'] ?? $row['fio'] ?? '';
            $address = $row['address'] ?? '';
            $phone = $row['phone'] ?? '';
            $candidate = $row['candidate'] ?? $row['candidate_name'] ?? '';

            fputcsv($output, [$district, $fio, $address, $phone, $candidate], ';');
        }

        fclose($output);
        exit;
    }
}
