<?php
/**
 * Простой скрипт для просмотра логов SQL-запросов
 * Использование: php view_db_logs.php [количество строк]
 */

$logFile = __DIR__ . '/db_queries.log';
$lines = isset($argv[1]) ? (int)$argv[1] : 50;

if (!file_exists($logFile)) {
    echo "Файл логов не найден: $logFile\n";
    echo "Выполните несколько запросов к приложению, чтобы создать файл логов.\n";
    exit(1);
}

$content = file_get_contents($logFile);
$allLines = explode("\n", $content);
$recentLines = array_slice($allLines, -$lines * 2); // Берем больше строк, т.к. каждая запись может быть многострочной

echo "=== Последние SQL-запросы (последние $lines записей) ===\n\n";
echo implode("\n", $recentLines);
echo "\n\n=== Конец логов ===\n";
echo "Всего записей в файле: " . count(explode(str_repeat('-', 80), $content)) . "\n";
echo "Размер файла: " . number_format(filesize($logFile)) . " байт\n";


