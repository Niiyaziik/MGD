<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\App\Database;

$_ENV['DB_DSN']  = 'mysql:host=localhost;port=3306;dbname=mgd;charset=utf8mb4';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASS'] = 'pass';

Database::init($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
$pdo = Database::pdo();

// Определим текущую БД, чтобы корректно собрать список таблиц
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!$dbName) {
    throw new RuntimeException('Не выбрана база данных в DSN (dbname=...)');
}

// 1) Снять FK-проверки
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

// 2) Собрать и дропнуть все таблицы текущей схемы
$tables = $pdo->query("
    SELECT TABLE_NAME
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = " . $pdo->quote($dbName)
)->fetchAll(PDO::FETCH_COLUMN);

if ($tables) {
    $list = implode(', ', array_map(
        fn($t) => '`' . str_replace('`', '``', $t) . '`',
        $tables
    ));
    $pdo->exec("DROP TABLE IF EXISTS $list");
}

// 3) Вернуть FK-проверки
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

echo "Все таблицы в БД `$dbName` удалены.\n";

// 4) Прогнать миграции из bootstrap.php
define('BOOTSTRAP_NO_CONTAINER', true);
require __DIR__ . '/../bootstrap.php'; // внутри вызывается runMigrations(...)
echo "fresh: миграции применены заново.\n";
