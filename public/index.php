<?php
declare(strict_types=1);

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
);

// своё имя куки, чтобы не было стандартного PHPSESSID
session_name('MGDSESSID');

// Кука только для HTTP (JS не увидит)
ini_set('session.cookie_httponly', '1');

// Отдавать куку только по HTTPS (в проде будет работать, на локалхосте http – можно временно отключить)
// if ($isHttps) {
//     ini_set('session.cookie_secure', '1');
// }

// Защита от CSRF: кука не уходит при переходе с других сайтов
ini_set('session.cookie_samesite', 'Strict');

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isAdminRoute = str_starts_with($requestUri, '/admin');

// Если это пользователь — ставим короткую (напр. 3 мин)
// Если админ — вообще НЕ ограничиваем время
if ($isAdminRoute) {
    // Админская сессия — без ограничения cookie (0 = пока браузер открыт)
    ini_set('session.gc_maxlifetime', '0');
    ini_set('session.cookie_lifetime', '0');
} else {
    // Обычный пользователь — короткая сессия
    ini_set('session.gc_maxlifetime', '180');   // серверное время жизни
    ini_set('session.cookie_lifetime', '180');  // cookie в браузере
}
// стартуем сессию
session_start();

require __DIR__ . '/../vendor/autoload.php';

$container = require __DIR__ . '/../bootstrap.php';

use App\App\Router;
use App\Middleware\AdminMiddleware;
use App\Controller\AuthController;

$router = new Router($container);

// определяем маршруты
$router->get('/', function () {
    // Возвращаем содержимое index.html
    readfile(__DIR__ . '/index.html');
});

// Округа
$router->get('/districts', [\App\Controller\DistrictController::class, 'index']);
$router->get('/districts/admin', [\App\Controller\DistrictController::class, 'indexAdmin'])->middleware(AdminMiddleware::class);
$router->get('/district', [\App\Controller\DistrictController::class, 'show']);
$router->get('/district/admin/{id}', [\App\Controller\DistrictController::class, 'showAdmin'])->middleware(AdminMiddleware::class);
$router->post('/districts', [\App\Controller\DistrictController::class, 'store'])->middleware(AdminMiddleware::class);
$router->post('/districts/{id}/update', [\App\Controller\DistrictController::class, 'update'])->middleware(AdminMiddleware::class);
$router->post('/districts/{id}/delete', [\App\Controller\DistrictController::class, 'destroy'])->middleware(AdminMiddleware::class);
$router->post('/district/{district}/streets', [\App\Controller\DistrictController::class, 'storeStreet'])->middleware(AdminMiddleware::class);
$router->put('/district/{district}/streets', [\App\Controller\DistrictController::class, 'updateStreet'])->middleware(AdminMiddleware::class);
$router->post('/district/{district}/houses',  [\App\Controller\DistrictController::class, 'storeHouse'])->middleware(AdminMiddleware::class);
$router->put('/district/{district}/houses',  [\App\Controller\DistrictController::class, 'updateHouse'])->middleware(AdminMiddleware::class);
$router->delete('/district/{district}/houses',  [\App\Controller\DistrictController::class, 'deleteHouse'])->middleware(AdminMiddleware::class);
$router->get('/districts/admin/index', [\App\Controller\DistrictController::class, 'adminIndex'])->middleware(AdminMiddleware::class);
$router->get('/districts/admin/edit', [\App\Controller\DistrictController::class, 'adminUpdate'])->middleware(AdminMiddleware::class);
$router->put('/districts/admin/update', [\App\Controller\DistrictController::class, 'adminUpdate'])->middleware(AdminMiddleware::class);
$router->delete('/districts/admin/delete', [\App\Controller\DistrictController::class, 'adminDelete'])->middleware(AdminMiddleware::class);
$router->get('/districts/admin/deleted', [\App\Controller\DistrictController::class, 'adminDeleted'])->middleware(AdminMiddleware::class);
$router->get('/districts/admin/check-duplicates', [\App\Controller\DistrictController::class, 'checkDuplicates'])->middleware(AdminMiddleware::class);
$router->get('/address/suggest', [\App\Controller\DistrictController::class, 'suggest']);

// Кандидаты
$router->get('/candidates', [\App\Controller\CandidateController::class, 'index']);
$router->get('/candidates/admin', [\App\Controller\CandidateController::class, 'adminIndex'])->middleware(AdminMiddleware::class);
$router->get('/candidate/add', [\App\Controller\CandidateController::class, 'showAdd'])->middleware(AdminMiddleware::class);
$router->get('/candidate', [\App\Controller\CandidateController::class, 'show']);
$router->post('/candidates', [\App\Controller\CandidateController::class, 'store'])->middleware(AdminMiddleware::class);
$router->get('/candidates/{id}/edit', [\App\Controller\CandidateController::class, 'edit'])->middleware(AdminMiddleware::class);
$router->post('/candidates/{id}/update', [\App\Controller\CandidateController::class, 'update'])->middleware(AdminMiddleware::class);
$router->post('/candidates/{id}/delete', [\App\Controller\CandidateController::class, 'destroy'])->middleware(AdminMiddleware::class);
$router->get('/candidates/admin/index', [\App\Controller\CandidateController::class, 'indexAdmin'])->middleware(AdminMiddleware::class);
$router->get('/candidates/admin/edit', [\App\Controller\CandidateController::class, 'adminUpdate'])->middleware(AdminMiddleware::class);
$router->put('/candidates/admin/update', [\App\Controller\CandidateController::class, 'adminUpdate'])->middleware(AdminMiddleware::class);
$router->delete('/candidates/admin/delete', [\App\Controller\CandidateController::class, 'adminDelete'])->middleware(AdminMiddleware::class);
$router->get('/candidates/admin/deleted', [\App\Controller\CandidateController::class, 'adminDeleted'])->middleware(AdminMiddleware::class);

// Пользователи
// $router->get('/users', [\App\Controller\UserController::class, 'index']);
// $router->get('/users/{id}', [\App\Controller\UserController::class, 'show']);
// $router->post('/users', [\App\Controller\UserController::class, 'store']);
$router->post('/users/{id}/update', [\App\Controller\UserController::class, 'update'])->middleware(AdminMiddleware::class);
// $router->post('/users/{id}/delete', [\App\Controller\UserController::class, 'destroy']);
$router->get('/users/admin', [\App\Controller\UserController::class, 'adminIndex'])->middleware(AdminMiddleware::class);
$router->get('/users/admin/edit', [\App\Controller\UserController::class, 'adminUpdate'])->middleware(AdminMiddleware::class);
$router->put('/users/admin/update', [\App\Controller\UserController::class, 'adminUpdate'])->middleware(AdminMiddleware::class);
$router->delete('/users/admin/delete', [\App\Controller\UserController::class, 'adminDelete'])->middleware(AdminMiddleware::class);
$router->get('/users/admin/deleted', [\App\Controller\UserController::class, 'adminDeleted'])->middleware(AdminMiddleware::class);
// // Страница активных пользователей
// $router->get('/users-page', function () {
//     include __DIR__ . '/users-db.php';
// });

// // Страница удалённых пользователей
// $router->get('/deleted-users', function () {
//     include __DIR__ . '/deleted-users-db.php';
// });
$router->post('/captcha/verify', [\App\Controller\CaptchaController::class, 'verify']);
// Голоса
$router->post('/votes', [\App\Controller\VoteController::class, 'voted']);
$router->get('/votes/admin', [\App\Controller\VoteController::class, 'index'])->middleware(AdminMiddleware::class);
$router->get('/votes/admin/export', [\App\Controller\VoteController::class, 'exportExcel'])->middleware(AdminMiddleware::class);
$router->get('/votes/admin', [\App\Controller\VoteController::class, 'index'])->middleware(\App\Middleware\AdminMiddleware::class);
$router->get('/votes/admin/deleted', [\App\Controller\VoteController::class, 'deletedIndex'])->middleware(\App\Middleware\AdminMiddleware::class);
$router->put('/votes/admin/update', [\App\Controller\VoteController::class, 'adminUpdate'])->middleware(\App\Middleware\AdminMiddleware::class);

$router->delete('/votes/admin/delete', [\App\Controller\VoteController::class, 'adminDelete'])->middleware(\App\Middleware\AdminMiddleware::class);

// Админ
$router->post('/admin/login', [\App\Controller\AdminController::class, 'loginByPassword']);
$router->post('/admin/logout', [\App\Controller\AdminController::class, 'logout']);

$router->post('/auth/login-phone', [AuthController::class, 'loginByPhone']);

$router->post('/auth/login',  [\App\Controller\AuthController::class, 'login']);
$router->post('/auth/logout', [\App\Controller\AuthController::class, 'logout']);
$router->get('/auth/status', [\App\Controller\AuthController::class, 'status']);
$router->post('/auth/precheck', [\App\Controller\AuthController::class, 'precheck']);

// VK OAuth (упрощённо, только структура)
$router->post('/auth/vk/onetap', [AuthController::class, 'vkOneTap']);
$router->get('/auth/vk', [\App\Controller\AuthController::class, 'vkRedirect']);
$router->get('/auth/vk/callback', [\App\Controller\AuthController::class, 'vkCallback']);
$router->post('/auth/send-code',  [\App\Controller\AuthController::class, 'sendCode']);
$router->post('/auth/check-code', [\App\Controller\AuthController::class, 'checkCode']);
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