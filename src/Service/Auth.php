<?php
declare(strict_types=1);

namespace App\Service;

class Auth
{
    public static function adminId(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
    }

    public static function isAdmin(): bool
    {
        return self::adminId() !== null;
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function isUser(): bool
    {
        return self::userId() !== null;
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            http_response_code(403);
            echo 'Доступ запрещён. Требуется вход администратора.';
            exit;
        }
    }
}
