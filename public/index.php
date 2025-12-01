<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../vendor/autoload.php';

$container = require __DIR__ . '/../bootstrap.php';

use App\App\Router;

$router = new Router($container);

// определяем маршруты
$router->get('/', function () {
    // Возвращаем содержимое index.html
    readfile(__DIR__ . '/index.html');
});

// Округа
$router->get('/districts', [\App\Controller\DistrictController::class, 'index']);
$router->get('/districts/admin', [\App\Controller\DistrictController::class, 'indexAdmin']);
$router->get('/district', [\App\Controller\DistrictController::class, 'show']);
$router->get('/district/admin/{id}', [\App\Controller\DistrictController::class, 'showAdmin']);
$router->post('/districts', [\App\Controller\DistrictController::class, 'store']);
$router->post('/districts/{id}/update', [\App\Controller\DistrictController::class, 'update']);
$router->post('/districts/{id}/delete', [\App\Controller\DistrictController::class, 'destroy']);
$router->post('/district/{district}/streets', [\App\Controller\DistrictController::class, 'storeStreet']);
$router->put('/district/{district}/streets', [\App\Controller\DistrictController::class, 'updateStreet']);
$router->post('/district/{district}/houses',  [\App\Controller\DistrictController::class, 'storeHouse']);
$router->put('/district/{district}/houses',  [\App\Controller\DistrictController::class, 'updateHouse']);
$router->delete('/district/{district}/houses',  [\App\Controller\DistrictController::class, 'deleteHouse']);

// Кандидаты
$router->get('/candidates', [\App\Controller\CandidateController::class, 'index']);
$router->get('/candidates/admin', [\App\Controller\CandidateController::class, 'indexAdmin']);
$router->get('/candidate/add', [\App\Controller\CandidateController::class, 'showAdd']);
$router->get('/candidate', [\App\Controller\CandidateController::class, 'show']);
$router->post('/candidates', [\App\Controller\CandidateController::class, 'store']);
$router->get('/candidates/{id}/edit', [\App\Controller\CandidateController::class, 'edit']);
$router->post('/candidates/{id}/update', [\App\Controller\CandidateController::class, 'update']);
$router->post('/candidates/{id}/delete', [\App\Controller\CandidateController::class, 'destroy']);

// Пользователи
$router->get('/users', [\App\Controller\UserController::class, 'index']);
$router->get('/users/{id}', [\App\Controller\UserController::class, 'show']);
$router->post('/users', [\App\Controller\UserController::class, 'store']);
$router->post('/users/{id}/update', [\App\Controller\UserController::class, 'update']);
$router->post('/users/{id}/delete', [\App\Controller\UserController::class, 'destroy']);

// Голоса
$router->post('/votes', [\App\Controller\VoteController::class, 'store']);
$router->get('/votes/admin', [\App\Controller\VoteController::class, 'index']);

// Админ
$router->post('/admin/login', [\App\Controller\AdminController::class, 'login']);
$router->post('/admin/logout', [\App\Controller\AdminController::class, 'logout']);

$router->post('/auth/login-phone', [AuthController::class, 'loginByPhone']);

// VK OAuth (упрощённо, только структура)
$router->get('/auth/vk', [\App\Controller\AuthController::class, 'vkRedirect']);
$router->get('/auth/vk/callback', [\App\Controller\AuthController::class, 'vkCallback']);

// выход пользователя
$router->post('/auth/logout', [\App\Controller\AuthController::class, 'logoutUser']);

error_log('FRONT CONTROLLER: ' . $_SERVER['REQUEST_METHOD'] . ' ' . ($_SERVER['REQUEST_URI'] ?? ''));

// ---------- Диспетчер ----------
$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$router->get('/about', function() {
    echo "<h1>О проекте</h1>";
});

$router->get('/api/hello', function() {
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Hello API']);
});