<?php
declare(strict_types=1);

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
$router->get('/districts', [\App\Controller\DistrictController::class, 'index']);   // список
$router->get('/district', [\App\Controller\DistrictController::class, 'show']); // просмотр
$router->post('/districts', [\App\Controller\DistrictController::class, 'store']);  // создание
$router->post('/districts/{id}/update', [\App\Controller\DistrictController::class, 'update']);
$router->post('/districts/{id}/delete', [\App\Controller\DistrictController::class, 'destroy']);

// Кандидаты
$router->get('/candidates', [\App\Controller\CandidateController::class, 'index']);
$router->get('/candidate', [\App\Controller\CandidateController::class, 'show']);
$router->post('/candidates', [\App\Controller\CandidateController::class, 'store']);
$router->post('/candidates/{id}/update', [\App\Controller\CandidateController::class, 'update']);
$router->post('/candidates/{id}/delete', [\App\Controller\CandidateController::class, 'destroy']);

// Пользователи
$router->get('/users', [\App\Controller\UserController::class, 'index']);
$router->get('/users/{id}', [\App\Controller\UserController::class, 'show']);
$router->post('/users', [\App\Controller\UserController::class, 'store']);
$router->post('/users/{id}/update', [\App\Controller\UserController::class, 'update']);
$router->post('/users/{id}/delete', [\App\Controller\UserController::class, 'destroy']);

// Голоса
$router->post('/votes', [\App\Controller\VoteController::class, 'store']); // проголосовать (1 раз)

// Админ
$router->get('/admin/login', [\App\Controller\AdminController::class, 'loginForm']);
$router->post('/admin/login', [\App\Controller\AdminController::class, 'login']);
$router->post('/admin/logout', [\App\Controller\AdminController::class, 'logout']);

// ---------- Диспетчер ----------
$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$router->get('/about', function() {
    echo "<h1>О проекте</h1>";
});

$router->get('/api/hello', function() {
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Hello API']);
});