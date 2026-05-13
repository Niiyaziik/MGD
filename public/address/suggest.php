<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use App\Service\FiasService;

header('Content-Type: application/json; charset=utf-8');

$query = trim((string)($_GET['query'] ?? ''));
if ($query === '') {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new FiasService();
    $items = $service->searchAddresses($query);

    // JS у тебя сейчас ждёт массив объектов (street/house)
    echo json_encode($items, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'FIAS error'], JSON_UNESCAPED_UNICODE);
}
