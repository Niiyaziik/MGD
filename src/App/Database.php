<?php
namespace App\App;

use PDO;
use App\App\LoggedPDO;

class Database {
    private static ?PDO $pdo = null;
    private static bool $enableLogging = true;

    public static function init(string $dsn, string $user, string $pass, bool $enableLogging = true): void {
        self::$enableLogging = $enableLogging;
        
        if ($enableLogging) {
            // Используем обертку с логированием
            self::$pdo = new LoggedPDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } else {
            // Обычный PDO без логирования
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
    }

    public static function pdo(): PDO {
        if (!self::$pdo) throw new \RuntimeException('DB not initialized');
        return self::$pdo;
    }

    public static function tx(callable $fn) {
        $pdo = self::pdo();
        try {
            $pdo->beginTransaction();
            $res = $fn($pdo);
            $pdo->commit();
            return $res;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
