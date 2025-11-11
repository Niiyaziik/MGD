<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\App\Container;
use App\App\Database;
use App\Repository\Contract\{
    CandidateRepositoryInterface,
    UserRepositoryInterface,
    VoteRepositoryInterface,
    DistrictRepositoryInterface
};
use App\Repository\Pdo\{
    CandidateRepository,
    UserRepository,
    VoteRepository,
    DistrictRepository
};
use App\Service\{
    VoteService, UserService, CandidateService, AdminAuthService
};

$_ENV['DB_DSN']  = 'mysql:host=localhost;port=3306;dbname=mgd;charset=utf8mb4';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASS'] = 'pass';

Database::init($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASS']);

function runMigrations(PDO $pdo): void
{
    // Создать таблицу версий, если её нет
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(191) PRIMARY KEY,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Текущая версия миграции (меняй при изменениях схемы)
    $version = '2025_11_10_000001_initial_schema';

    // Проверим, применялась ли уже
    $stmt = $pdo->prepare("SELECT 1 FROM schema_migrations WHERE version = :v LIMIT 1");
    $stmt->execute([':v' => $version]);
    if ($stmt->fetchColumn()) {
        // echo "Миграция уже применена.\n";
        return;
    }
    $steps = [
        // districts
        "CREATE TABLE IF NOT EXISTS districts (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          district VARCHAR(255) NOT NULL,
          street VARCHAR(255) NOT NULL,
          house VARCHAR(255) NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL,
          deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // candidates
        "CREATE TABLE IF NOT EXISTS candidates (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          surname VARCHAR(255),
          name VARCHAR(255),
          patronymic VARCHAR(255) NULL,
          phone VARCHAR(32) UNIQUE,
          vk_id VARCHAR(100) UNIQUE,
          district_id INT UNSIGNED NOT NULL,
          photo VARCHAR(255) NULL,
          email VARCHAR(255) NULL,
          description VARCHAR(255) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL,
          deleted_at TIMESTAMP NULL,
          CONSTRAINT fk_candidates_district FOREIGN KEY (district_id) REFERENCES districts(id)
            ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // users
        "CREATE TABLE IF NOT EXISTS users (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          surname VARCHAR(255),
          name VARCHAR(255),
          patronymic VARCHAR(255) NULL,
          phone VARCHAR(32) UNIQUE,
          link_vk VARCHAR(255) UNIQUE,
          district_id INT UNSIGNED NULL,
          auth_method ENUM('ВК', 'МАКС', 'Телефон'),
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL,
          deleted_at TIMESTAMP NULL,
          CONSTRAINT fk_users_district FOREIGN KEY (district_id) REFERENCES districts(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // admins
        "CREATE TABLE IF NOT EXISTS admins (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          login VARCHAR(255) UNIQUE,
          password_hash VARCHAR(255) NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL,
          deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // votes
        "CREATE TABLE IF NOT EXISTS votes (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id INT UNSIGNED NOT NULL,
          candidate_id INT UNSIGNED NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          deleted_at TIMESTAMP NULL,
          UNIQUE KEY uniq_user (user_id),
          KEY idx_candidate (candidate_id),
          CONSTRAINT fk_votes_user FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT fk_votes_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($steps as $i => $sql) {
        try {
            $pdo->exec($sql);
            // echo "Шаг " . ($i + 1) . "/" . count($steps) . " выполнен\n";
        } catch (Throwable $e) {
            // echo "Ошибка на шаге " . ($i + 1) . ": " . $e->getMessage() . "\n";
            return;
        }
    }
    $pdo->prepare("INSERT INTO schema_migrations(version) VALUES(:v)")
        ->execute([':v' => $version]);

    // echo "Миграция {$version} применена\n";

}

runMigrations(Database::pdo());

$container = new Container();

$container->set(PDO::class, fn() => Database::pdo());

$container->set(CandidateRepositoryInterface::class,
    fn($c) => new CandidateRepository($c->get(PDO::class))
);
$container->set(UserRepositoryInterface::class,
    fn($c) => new UserRepository($c->get(PDO::class))
);
$container->set(VoteRepositoryInterface::class,
    fn($c) => new VoteRepository($c->get(PDO::class))
);
$container->set(DistrictRepositoryInterface::class,
    fn($c) => new DistrictRepository($c->get(PDO::class))
);
$container->set(AdminRepositoryInterface::class,
    fn($c) => new AdminRepository($c->get(PDO::class))
);

$container->set(VoteService::class, fn($c)=> new VoteService(
    $c->get(PDO::class),
    $c->get(\App\Repositories\Contracts\UserRepositoryInterface::class),
    $c->get(\App\Repositories\Contracts\CandidateRepositoryInterface::class),
    $c->get(\App\Repositories\Contracts\VoteRepositoryInterface::class),
));

$container->set(UserService::class, fn($c)=> new UserService(
    $c->get(PDO::class),
    $c->get(\App\Repositories\Contracts\UserRepositoryInterface::class),
    $c->get(\App\Repositories\Contracts\DistrictRepositoryInterface::class),
));

$container->set(CandidateService::class, fn($c)=> new CandidateService(
    $c->get(\App\Repositories\Contracts\CandidateRepositoryInterface::class),
    $c->get(\App\Repositories\Contracts\DistrictRepositoryInterface::class),
));

$container->set(AdminAuthService::class, fn($c)=> new AdminAuthService(
    $c->get(\App\Repositories\Contracts\AdminRepositoryInterface::class),
));

return $container;
