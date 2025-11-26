<?php
namespace App\Controller;

use App\Model\District;
use App\Repository\Contract\DistrictRepositoryInterface;
use DomainException;
use Throwable;

class DistrictController extends BaseController
{
    public function __construct(private DistrictRepositoryInterface $districts) {}

    // GET /api/districts
    public function index(): void
    {
    $this->requireMethod('GET');

    // Определяем нужен ли JSON
    $json = (isset($_GET['format']) && $_GET['format'] === 'json')
         || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

    // Если JSON — вернуть данные округов
    if ($json) {
        try {
            $items = $this->districts->all(); // только не удалённые (deleted_at IS NULL)
            $this->json($items);
            return;
        } catch (Throwable $e) {
            $this->json(['error' => 'Внутренняя ошибка'], 500);
            return;
        }
    }

    // Если HTML — отдаём страницу округов
    header_remove('Content-Type');
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/../../public/districts.html');
    }

    public function indexAdmin(): void
    {
    $this->requireMethod('GET');

    // Определяем нужен ли JSON
    $json = (isset($_GET['format']) && $_GET['format'] === 'json')
         || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

    // Если JSON — вернуть данные округов
    if ($json) {
        try {
            $items = $this->districts->all(); // только не удалённые (deleted_at IS NULL)
            $this->json($items);
            return;
        } catch (Throwable $e) {
            $this->json(['error' => 'Внутренняя ошибка'], 500);
            return;
        }
    }

    // Если HTML — отдаём страницу округов
    header_remove('Content-Type');
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/../../public/districts-admin.html');
    }


    public function show(): void
    {
        $this->requireMethod('GET');

        $district = (int)($_GET['district'] ?? 0);
        if ($district <= 0) {
            $this->json(['error' => 'Некорректный номер округа'], 400);
            return;
        }

        // Определяем формат: JSON или HTML
        $wantsJson = 
            (isset($_GET['format']) && $_GET['format'] === 'json')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($wantsJson) {
            // ---- JSON ----
            try {
                $rows = $this->districts->find($district); // массив строк

                if (!$rows) {
                    $this->json(['error' => 'Округ не найден'], 404);
                    return;
                }

                $this->json($rows);
                return;
            } catch (\Throwable $e) {
                $this->json(['error' => 'Внутренняя ошибка'], 500);
                return;
            }
        }

        // ---- HTML ----
        header_remove('Content-Type');
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/../../public/district.html');
        }

    public function showAdmin(string $id): void
    {
        $this->requireMethod('GET');

        // id приходит из роутера как строка, приводим к числу
        $district = (int)$id;
            error_log("SHOW_ADMIN: district int={$district}");

        if ($district <= 0) {
            error_log("SHOW_ADMIN: invalid district");
            $this->json(['error' => 'Некорректный номер округа'], 400);
            return;
        }

        // Определяем формат: JSON или HTML
        $wantsJson =
            (isset($_GET['format']) && $_GET['format'] === 'json')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        error_log("SHOW_ADMIN: wantsJson=" . ($wantsJson ? 'yes' : 'no'));

        if ($wantsJson) {
            // ---- JSON ----
            try {
                error_log("SHOW_ADMIN: find({$district})");

                // ищем данные по номеру округа
                $rows = $this->districts->findAdmin($district); // массив строк
                error_log("SHOW_ADMIN: find({$district}) rows=" . json_encode($rows, JSON_UNESCAPED_UNICODE));
                if (!$rows) {
                    $this->json(['error' => 'Округ не найден'], 404);
                    return;
                }

                $this->json($rows);
                return;
            } catch (\Throwable $e) {
                $this->json(['error' => 'Внутренняя ошибка'], 500);
                return;
            }
        }

        // ---- HTML ----
        header_remove('Content-Type');
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/../../public/district-admin.html');
    }


    // POST /district/{district}/streets
    public function storeStreet(string $district): void
    {
        $this->requireMethod('POST');

        $district = (int) $district;

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];

        $street = isset($data['street']) ? trim($data['street']) : '';

        if ($district <= 0 || $street === '') {
            $this->json(['ok' => false, 'error' => 'district or street is empty'], 400);
            return;
        }

        try {
            $this->districts->createStreet($district, $street);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'internal error'], 500);
        }
    }

    // PUT /district/{district}/streets
    public function updateStreet(string $district): void
    {
        $this->requireMethod('PUT');

        $district = (int) $district;

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];

        $oldStreet = isset($data['old_street']) ? trim($data['old_street']) : '';
        $newStreet = isset($data['new_street']) ? trim($data['new_street']) : '';

        if ($district <= 0 || $oldStreet === '' || $newStreet === '') {
            $this->json(['ok' => false, 'error' => 'bad data'], 400);
            return;
        }

        try {
            $updated = $this->districts->renameStreet($district, $oldStreet, $newStreet);
            $this->json(['ok' => true, 'updated' => $updated]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'internal error'], 500);
        }
    }

    // POST /district/{district}/houses
    public function storeHouse(string $district): void
    {
        $this->requireMethod('POST');

        $district = (int) $district;

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];

        $street = isset($data['street']) ? trim($data['street']) : '';
        $house  = isset($data['house'])  ? trim($data['house'])  : '';

        if ($district <= 0 || $street === '' || $house === '') {
            $this->json(['ok' => false, 'error' => 'bad data'], 400);
            return;
        }

        try {
            $this->districts->createHouse($district, $street, $house);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'internal error'], 500);
        }
    }

    // PUT /district/{district}/houses
    public function updateHouse(string $district): void
    {
        $this->requireMethod('PUT');

        $district = (int) $district;

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];

        $street   = isset($data['street'])    ? trim($data['street'])    : '';
        $oldHouse = isset($data['old_house']) ? trim($data['old_house']) : '';
        $newHouse = isset($data['new_house']) ? trim($data['new_house']) : '';

        if ($district <= 0 || $street === '' || $oldHouse === '' || $newHouse === '') {
            $this->json(['ok' => false, 'error' => 'bad data'], 400);
            return;
        }

        try {
            $updated = $this->districts->updateHouse($district, $street, $oldHouse, $newHouse);
            $this->json(['ok' => true, 'updated' => $updated]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'internal error'], 500);
        }
    }

    // DELETE /district/{district}/houses
    public function deleteHouse(string $district): void
    {
        $this->requireMethod('DELETE');

        $district = (int) $district;

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];

        $street = isset($data['street']) ? trim($data['street']) : '';
        $house  = isset($data['house'])  ? trim($data['house'])  : '';

        if ($district <= 0 || $street === '' || $house === '') {
            $this->json(['ok' => false, 'error' => 'bad data'], 400);
            return;
        }

        try {
            $deleted = $this->districts->deleteHouse($district, $street, $house);
            $this->json(['ok' => true, 'deleted' => $deleted]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'internal error'], 500);
        }
    }
}
