<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests.
 *
 * Uses an in-memory SQLite database with a MySQL-compatible schema.
 * The NOW() function is registered as a SQLite UDF so that INSERT/UPDATE
 * queries that call NOW() work without modification.
 *
 * Each test method receives a clean database: all rows are deleted in setUp().
 * The PDO connection is shared within a test class (created once in setUpBeforeClass).
 *
 * NOTE: Queries that use MySQL-specific syntax which cannot be emulated in SQLite
 * (e.g. INTERVAL, CONCAT_WS, JSON_OBJECT) are individually skipped in the tests
 * that call those code paths.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static PDO $pdo;

    /** Tables in dependency order (children first for DELETE) */
    private const TABLES = [
        'votes',
        'phone_code',
        'users',
        'candidates',
        'admins',
        'house',
        'streets',
        'districts',
    ];

    public static function setUpBeforeClass(): void
    {
        static::$pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        static::$pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'));

        static::createSchema();
    }

    protected function setUp(): void
    {
        foreach (self::TABLES as $table) {
            static::$pdo->exec("DELETE FROM {$table}");
        }
    }

    // -------------------------------------------------------------------------
    // Seed helpers
    // -------------------------------------------------------------------------

    protected function insertAdmin(string $login, string $password, ?string $deletedAt = null): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        static::$pdo->prepare(
            "INSERT INTO admins (login, password_hash, deleted_at) VALUES (?, ?, ?)"
        )->execute([$login, $hash, $deletedAt]);
        return (int)static::$pdo->lastInsertId();
    }

    protected function insertDistrict(string $name = 'Тестовый округ'): int
    {
        static::$pdo->prepare(
            "INSERT INTO districts (district, street, house) VALUES (?, 'Тестовая ул.', '1')"
        )->execute([$name]);
        $districtId = (int)static::$pdo->lastInsertId();

        // Insert a street so DistrictRepository::find() returns non-empty results
        static::$pdo->prepare(
            "INSERT INTO streets (district_id, street) VALUES (?, 'Тестовая ул.')"
        )->execute([$districtId]);

        return $districtId;
    }

    protected function insertUser(array $data = []): int
    {
        $defaults = [
            'surname'    => 'Тестов',
            'name'       => 'Тест',
            'phone'      => '79990000001',
            'auth_method'=> 'Телефон',
        ];
        $d = array_merge($defaults, $data);
        static::$pdo->prepare(
            "INSERT INTO users (surname, name, patronymic, phone, link_vk, vk_id,
             district_id, auth_method, phone_verified, created_at)
             VALUES (:surname, :name, :patronymic, :phone, :link_vk, :vk_id,
             :district_id, :auth_method, 0, datetime('now'))"
        )->execute([
            ':surname'     => $d['surname'],
            ':name'        => $d['name'],
            ':patronymic'  => $d['patronymic']  ?? null,
            ':phone'       => $d['phone'],
            ':link_vk'     => $d['link_vk']     ?? null,
            ':vk_id'       => $d['vk_id']       ?? null,
            ':district_id' => $d['district_id'] ?? null,
            ':auth_method' => $d['auth_method'],
        ]);
        return (int)static::$pdo->lastInsertId();
    }

    protected function insertCandidate(array $data = []): int
    {
        $defaults = [
            'surname'     => 'Кандидатов',
            'name'        => 'Кандидат',
            'district_id' => 1,
        ];
        $d = array_merge($defaults, $data);
        static::$pdo->prepare(
            "INSERT INTO candidates
             (surname, name, patronymic, phone, vk_id, district_id, district, address, photo, email)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $d['surname'],
            $d['name'],
            $d['patronymic']  ?? null,
            $d['phone']       ?? null,
            $d['vk_id']       ?? null,
            $d['district_id'],
            $d['district']    ?? null,
            $d['address']     ?? null,
            $d['photo']       ?? null,
            $d['email']       ?? null,
        ]);
        return (int)static::$pdo->lastInsertId();
    }

    protected function insertPhoneCode(string $phone, string $code, ?string $usedAt = null): int
    {
        static::$pdo->prepare(
            "INSERT INTO phone_code (phone, code, created_at, used_at)
             VALUES (?, ?, datetime('now'), ?)"
        )->execute([$phone, $code, $usedAt]);
        return (int)static::$pdo->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // Schema
    // -------------------------------------------------------------------------

    private static function createSchema(): void
    {
        static::$pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                login         TEXT UNIQUE,
                password_hash TEXT NOT NULL,
                created_at    TEXT DEFAULT (datetime('now')),
                updated_at    TEXT,
                deleted_at    TEXT
            );

            CREATE TABLE IF NOT EXISTS districts (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                district   TEXT NOT NULL,
                street     TEXT NOT NULL DEFAULT '',
                house      TEXT NOT NULL DEFAULT '',
                number     INTEGER,
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT,
                deleted_at TEXT
            );

            CREATE TABLE IF NOT EXISTS streets (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                district_id INTEGER NOT NULL,
                street      TEXT NOT NULL,
                deleted_at  TEXT
            );

            CREATE TABLE IF NOT EXISTS house (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                street_id  INTEGER NOT NULL,
                house      TEXT NOT NULL,
                deleted_at TEXT
            );

            CREATE TABLE IF NOT EXISTS candidates (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                surname     TEXT,
                name        TEXT,
                patronymic  TEXT,
                phone       TEXT UNIQUE,
                vk_id       TEXT UNIQUE,
                district_id INTEGER,
                district    TEXT,
                address     TEXT,
                photo       TEXT,
                email       TEXT,
                description TEXT,
                created_at  TEXT DEFAULT (datetime('now')),
                updated_at  TEXT,
                deleted_at  TEXT
            );

            CREATE TABLE IF NOT EXISTS users (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                surname         TEXT,
                name            TEXT,
                patronymic      TEXT,
                phone           TEXT UNIQUE,
                link_vk         TEXT,
                district_id     INTEGER,
                auth_method     TEXT,
                phone_verified  INTEGER DEFAULT 0,
                vk_id           TEXT,
                vk_phone        TEXT,
                vk_email        TEXT,
                vk_avatar       TEXT,
                vk_profile_url  TEXT,
                street_id       INTEGER,
                house_id        INTEGER,
                created_at      TEXT DEFAULT (datetime('now')),
                updated_at      TEXT,
                deleted_at      TEXT
            );

            CREATE TABLE IF NOT EXISTS votes (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER NOT NULL,
                candidate_id INTEGER NOT NULL,
                created_at   TEXT DEFAULT (datetime('now')),
                deleted_at   TEXT,
                UNIQUE (user_id)
            );

            CREATE TABLE IF NOT EXISTS phone_code (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                phone      TEXT,
                code       TEXT,
                created_at TEXT DEFAULT (datetime('now')),
                used_at    TEXT
            );
        ");
    }
}
