<?php
namespace App\Controller;

use App\Model\District;
use App\Repository\Contract\DistrictRepositoryInterface;
use DomainException;
use Throwable;

class DistrictController extends BaseController
{
    public function __construct(private DistrictRepositoryInterface $districts) {}

    public function index(): void
    {
        $this->requireMethod('GET');

        $json = (isset($_GET['format']) && $_GET['format'] === 'json')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($json) {
            try {
                $items = $this->districts->all();
                $this->json($items);
                return;
            } catch (Throwable $e) {
                $this->json(['error' => 'Внутренняя ошибка'], 500);
                return;
            }
    }

    header_remove('Content-Type');
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/../../public/districts.html');
    }

    public function indexAdmin(): void
    {
        $this->requireMethod('GET');

        $json = (isset($_GET['format']) && $_GET['format'] === 'json')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($json) {
            try {
                $items = $this->districts->all();
                $this->json($items);
                return;
            } catch (Throwable $e) {
                $this->json(['error' => 'Внутренняя ошибка'], 500);
                return;
            }
    }

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

        $wantsJson = 
            (isset($_GET['format']) && $_GET['format'] === 'json')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($wantsJson) {
            try {
                $rows = $this->districts->find($district);

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

        header_remove('Content-Type');
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/../../public/district.html');
        }

    public function showAdmin(string $id): void
    {
        $this->requireMethod('GET');

        $district = (int)$id;
            error_log("SHOW_ADMIN: district int={$district}");

        if ($district <= 0) {
            error_log("SHOW_ADMIN: invalid district");
            $this->json(['error' => 'Некорректный номер округа'], 400);
            return;
        }

        $wantsJson =
            (isset($_GET['format']) && $_GET['format'] === 'json')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        error_log("SHOW_ADMIN: wantsJson=" . ($wantsJson ? 'yes' : 'no'));

        if ($wantsJson) {
            try {
                error_log("SHOW_ADMIN: find({$district})");

                $rows = $this->districts->findAdmin($district);
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

        header_remove('Content-Type');
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/../../public/district-admin.html');
    }


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

    public function adminIndex(): void
    {
        $this->requireMethod('GET');

        $format  = $_GET['format']  ?? null;
        $deleted = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;

        if ($format === 'json') {
            try {
                $rows = $this->districts->getAdminAddresses($deleted);
                $this->json($rows);
            } catch (Throwable $e) {
                $this->json(['ok' => false, 'error' => 'Ошибка загрузки адресов'], 500);
            }
            return;
        }

        header_remove('Content-Type');
        header('Content-Type: text/html; charset=utf-8');
        include __DIR__ . '/../../public/districts-db.php';
    }

    public function adminDelete(): void
    {
        $this->requireMethod('DELETE');

        $body = [];
        try {
            $body = $this->getJsonBody();
        } catch (Throwable $e) {}

        $id = (int)($body['id'] ?? ($_GET['id'] ?? 0));

        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'Не передан id строки'], 422);
            return;
        }

        try {
            $this->districts->deleteAddress($id);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Не удалось удалить строку'], 500);
        }
    }

    public function checkDuplicates(): void
    {
        $this->requireMethod('GET');

        try {
            $duplicates = $this->districts->findAddressDuplicates();
            $this->json(['ok' => true, 'data' => $duplicates]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Ошибка проверки базы'], 500);
        }
    }

    public function suggest(): void
    {
        $this->requireMethod('GET');

        $query = (string)($_GET['query'] ?? '');
        $query = trim($query);

        if ($query === '') {
            $this->json([]);
            return;
        }

        $list = $this->districts->suggest($query);

        $this->json($list);
    }
}
