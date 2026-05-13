<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

// отдать статические файлы напрямую (css/js/img)
if ($path !== '/' && is_file($file)) {
    return false;
}

// все остальное — через index.php
require __DIR__ . '/index.php';