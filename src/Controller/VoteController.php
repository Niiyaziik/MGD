<?php
namespace App\Controller;

use App\Repository\Contract\VoteRepositoryInterface;
use App\Repository\Contract\CandidateRepositoryInterface;
use DomainException;
use Throwable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class VoteController extends BaseController
{
    public function __construct(private VoteRepositoryInterface $votes, private CandidateRepositoryInterface $candidates) {}

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

        require __DIR__ . '/../../public/votes-admin.php';
    }

    public function exportExcel(): void
    {
        // ⚠️ ВАЖНО:
        // Используем тот же источник данных, что и для /votes/admin?format=json
        // Если у тебя уже есть метод в репозитории/контроллере, который делает все JOIN'ы,
        // лучше вынести его в приватный метод и использовать и здесь, и в index().
        $rows = $this->votes->getAdminList(); // ИМЯ метода подстрой под свой VoteRepository

        if (!is_array($rows)) {
            $rows = [];
        }

        $spreadsheet = new Spreadsheet();

        // ---------------- ЛИСТ 1: "Голосовавшие" ----------------
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Голосовавшие');

        // Шапка
        $sheet1->fromArray(
            ['Номер округа', 'ФИО избирателя', 'Адрес', 'Телефон', 'Кандидат'],
            null,
            'A1'
        );

        $rowIndex = 2;

        // Для подсчёта статистики по кандидатам
        $stats = []; // [candidateName => ['count' => n, 'district' => '...']]

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

            // собираем статистику
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

        // Немного авто-ширины
        foreach (['A','B','C','D','E'] as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        // ---------------- ЛИСТ 2: "Статистика" ----------------
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

        // ---------------- ОТДАЁМ ФАЙЛ ----------------
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
        require __DIR__ . '/../../public/votes-deleted-admin.php';
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
}
