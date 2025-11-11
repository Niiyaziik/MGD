<?php
namespace App\App;

use PDO;

class Database {
    private static ?PDO $pdo = null;

    public static function init(string $dsn, string $user, string $pass): void {
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
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
