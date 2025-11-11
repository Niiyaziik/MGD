<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$container = require __DIR__ . '/../bootstrap.php';

use App\App\Router;

$router = new Router($container);

// определяем маршруты
$router->get('/', function () {
    // можно отдать статический HTML
    readfile(__DIR__ . '/index.html');
});

// Округа
$router->get('/districts', [\App\Controllers\DistrictController::class, 'index']);   // список
$router->get('/districts/{id}', [\App\Controllers\DistrictController::class, 'show']); // просмотр
$router->post('/districts', [\App\Controllers\DistrictController::class, 'store']);  // создание
$router->post('/districts/{id}/update', [\App\Controllers\DistrictController::class, 'update']);
$router->post('/districts/{id}/delete', [\App\Controllers\DistrictController::class, 'destroy']);

// Кандидаты
$router->get('/candidates', [\App\Controllers\CandidateController::class, 'index']);
$router->get('/candidates/{id}', [\App\Controllers\CandidateController::class, 'show']);
$router->post('/candidates', [\App\Controllers\CandidateController::class, 'store']);
$router->post('/candidates/{id}/update', [\App\Controllers\CandidateController::class, 'update']);
$router->post('/candidates/{id}/delete', [\App\Controllers\CandidateController::class, 'destroy']);

// Пользователи
$router->get('/users', [\App\Controllers\UserController::class, 'index']);
$router->get('/users/{id}', [\App\Controllers\UserController::class, 'show']);
$router->post('/users', [\App\Controllers\UserController::class, 'store']);
$router->post('/users/{id}/update', [\App\Controllers\UserController::class, 'update']);
$router->post('/users/{id}/delete', [\App\Controllers\UserController::class, 'destroy']);

// Голоса
$router->post('/votes', [\App\Controllers\VoteController::class, 'store']); // проголосовать (1 раз)

// Админ
$router->get('/admin/login', [\App\Controllers\AdminController::class, 'loginForm']);
$router->post('/admin/login', [\App\Controllers\AdminController::class, 'login']);
$router->post('/admin/logout', [\App\Controllers\AdminController::class, 'logout']);

// ---------- Диспетчер ----------
$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$router->get('/about', function() {
    echo "<h1>О проекте</h1>";
});

$router->get('/api/hello', function() {
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Hello API']);
});