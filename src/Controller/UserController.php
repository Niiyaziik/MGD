<?php
namespace App\Controller;

use App\Repository\Contract\UserRepositoryInterface;
use DomainException;
use Throwable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UserController extends BaseController
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function store(): void
    {
        $this->requireMethod('POST');
        $data = $this->getJsonBody();

        try {
            $user = $this->users->register($data);
            $this->json(['ok' => true, 'data' => $user], 201);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    public function update(int $id): void
    {
        $this->requireMethod('PATCH');
        $patch = $this->getJsonBody();

        try {
            $user = $this->users->update($id, $patch);
            $this->json(['ok' => true, 'data' => $user]);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Внутренняя ошибка'], 500);
        }
    }

    public function exportExcel(): void
    {
        $this->requireMethod('GET');

        $deleted = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;

        try {
            $rows = $this->users->getAdminUsers($deleted);
        } catch (Throwable $e) {
            $rows = [];
        }

        if (!is_array($rows)) {
            $rows = [];
        }

        $spreadsheet = new Spreadsheet();

        // Лист 1 — Пользователи
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Пользователи');

        $sheet1->fromArray(
            ['ID', 'Дата регистрации', 'Метод авторизации', 'Фамилия', 'Имя', 'Отчество', 'Телефон', 'ВК', 'Улица', 'Дом', 'Округ'],
            null,
            'A1'
        );

        $rowIndex = 2;
        $districtStats = [];

        foreach ($rows as $row) {
            $id = $row['id'] ?? '';
            $createdAt = $row['registration_date']
                ?? ($row['created_at'] ?? ($row['created'] ?? ($row['date'] ?? '')));
            $authMethod = $row['auth_method'] ?? ($row['auth'] ?? '');
            $surname = $row['surname'] ?? '';
            $name = $row['name'] ?? '';
            $patronymic = $row['patronymic'] ?? '';
            $phone = $row['phone'] ?? '';
            $vk = $row['link_vk'] ?? ($row['vk'] ?? ($row['vk_link'] ?? ''));
            $street = $row['street'] ?? '';
            $house = $row['house'] ?? '';
            $district = $row['district']
                ?? ($row['district_num'] ?? ($row['district_id'] ?? ''));

            $sheet1->setCellValue("A{$rowIndex}", $id);
            $sheet1->setCellValue("B{$rowIndex}", $createdAt);
            $sheet1->setCellValue("C{$rowIndex}", $authMethod);
            $sheet1->setCellValue("D{$rowIndex}", $surname);
            $sheet1->setCellValue("E{$rowIndex}", $name);
            $sheet1->setCellValue("F{$rowIndex}", $patronymic);
            $sheet1->setCellValue("G{$rowIndex}", $phone);
            $sheet1->setCellValue("H{$rowIndex}", $vk);
            $sheet1->setCellValue("I{$rowIndex}", $street);
            $sheet1->setCellValue("J{$rowIndex}", $house);
            $sheet1->setCellValue("K{$rowIndex}", $district);

            if ($district !== '' && $district !== null) {
                $key = (string)$district;
                $districtStats[$key] = ($districtStats[$key] ?? 0) + 1;
            }

            $rowIndex++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        // Лист 2 — Статистика по округам
        if (!empty($districtStats)) {
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Статистика');
            $sheet2->fromArray(['Округ', 'Кол-во пользователей'], null, 'A1');

            uksort($districtStats, static function ($a, $b) {
                $aIsNum = is_numeric($a);
                $bIsNum = is_numeric($b);
                if ($aIsNum && $bIsNum) return (int)$a <=> (int)$b;
                if ($aIsNum) return -1;
                if ($bIsNum) return 1;
                return strcmp((string)$a, (string)$b);
            });

            $i = 2;
            foreach ($districtStats as $districtKey => $count) {
                $sheet2->setCellValue("A{$i}", $districtKey);
                $sheet2->setCellValue("B{$i}", $count);
                $i++;
            }

            foreach (range('A', 'B') as $col) {
                $sheet2->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $fileName = ($deleted ? 'Пользователи-удалённые-' : 'Пользователи-') . date('Y-m-d_H-i-s') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function adminIndex(): void
    {
        $this->requireMethod('GET');

        $format  = $_GET['format']  ?? null;
        $deleted = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;

        if ($format === 'json') {
            try {
                $users = $this->users->getAdminUsers($deleted);
                $this->json($users);
            } catch (Throwable $e) {
                $this->json(['ok' => false, 'error' => 'Ошибка загрузки пользователей'], 500);
            }
            return;
        }

        require __DIR__ . '/../../public/users-db/users-db.php';
    }

    public function adminDeleted(): void
    {
        $this->requireMethod('GET');

        $format  = $_GET['format']  ?? null;
        $deleted = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;

        if ($format === 'json') {
            try {
                // 1 — только удалённые (deleted_at IS NOT NULL)
                $list = $this->users->getAdminUsers(1);
                $this->json($list);
            } catch (Throwable $e) {
                $this->json(['ok' => false, 'error' => 'Ошибка загрузки удалённых пользователей'], 500);
            }
        }

        require __DIR__ . '/../../public/users-db/deleted-users-db.php';

    }

    /**
     * PUT /users/admin/update
     * Обновление пользователя из админки.
     * Ожидает JSON вида:
     * {
     *   "id": 123,
     *   "surname": "...",
     *   "name": "...",
     *   "patronymic": "...",
     *   "phone": "...",
     *   "link_vk": "...",
     *   "district_id": 1,
     *   "street_id": 10,
     *   "house_id": 5,
     *   "auth_method": "phone/vk/..."
     * }
     */
    public function adminUpdate(): void
    {
        $this->requireMethod('PUT');
        $data = $this->getJsonBody();

        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'Не передан id пользователя'], 422);
            return;
        }

        try {
            $this->users->updateAdminUser($id, $data);
            $user = $this->users->find($id);

            $this->json(['ok' => true, 'data' => $user]);
        } catch (DomainException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Ошибка при обновлении пользователя'], 500);
        }
    }

    /**
     * DELETE /users/admin/delete
     * Мягкое удаление пользователя (запись deleted_at = NOW()).
     * Ожидает:
     *  - либо JSON: { "id": 123 }
     *  - либо query: /users/admin/delete?id=123
     */
    public function adminDelete(): void
    {
        $this->requireMethod('DELETE');

        $body = [];
        try {
            $body = $this->getJsonBody();
        } catch (\Throwable $e) {
            // если нет JSON — ничего страшного
        }

        $id = (int)($body['id'] ?? ($_GET['id'] ?? 0));

        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'Не передан id пользователя'], 422);
            return;
        }

        try {
            $this->users->delete($id);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Не удалось удалить пользователя'], 500);
        }
    }
}
