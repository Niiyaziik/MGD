<?php
namespace App\Repository\Pdo;

use App\Repository\Contract\VoteRepositoryInterface;
use PDO;

class VoteRepository implements VoteRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function userHasVote(int $userId): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM votes WHERE user_id=? AND deleted_at IS NULL LIMIT 1");
        $st->execute([$userId]);
        return (bool)$st->fetchColumn();
    }

    public function createVote(int $userId, int $candidateId): void
    {
        $st = $this->pdo->prepare("INSERT INTO votes(user_id,candidate_id) VALUES(?,?)");
        $st->execute([$userId, $candidateId]);
    }

    public function allWithRelations(): array
    {
        // простая сборка с подзапросами (или можно JOIN’ы)
        $st = $this->pdo->query(
            "SELECT v.*,
                (SELECT JSON_OBJECT('id',u.id,'phone',u.phone,'district_id',u.district_id)
                 FROM users u WHERE u.id=v.user_id) AS user_json,
                (SELECT JSON_OBJECT('id',c.id,'name',c.name,'district_id',c.district_id)
                 FROM candidates c WHERE c.id=v.candidate_id) AS candidate_json
             FROM votes v
             WHERE v.deleted_at IS NULL
             ORDER BY v.id DESC"
        );
        $rows = $st->fetchAll();
        // раскодируем JSON-поля в массивы
        foreach ($rows as &$r) {
            $r['user'] = $r['user_json'] ? json_decode($r['user_json'], true) : null;
            $r['candidate'] = $r['candidate_json'] ? json_decode($r['candidate_json'], true) : null;
            unset($r['user_json'], $r['candidate_json']);
        }
        return $rows;
    }

    public function all(): array
    {
        $sql = "
        SELECT
            v.id                                              AS id, 
            COALESCE(u.district_id)                          AS district,
            CONCAT_WS(' ', u.surname, u.name, u.patronymic)  AS user_name,
            CONCAT(s.street, ', ', h.house)                  AS address,
            u.phone                                          AS phone,
            CONCAT_WS(' ', c.surname, c.name, c.patronymic)  AS candidate
        FROM votes v
        JOIN users u
          ON u.id = v.user_id
         AND u.deleted_at IS NULL
        JOIN candidates c
          ON c.id = v.candidate_id
         AND c.deleted_at IS NULL
        LEFT JOIN streets s
          ON s.id = u.street_id
         AND s.deleted_at IS NULL
        LEFT JOIN house h
          ON h.id = u.house_id
         AND h.deleted_at IS NULL
        WHERE v.deleted_at IS NULL
        ORDER BY
            COALESCE(u.district_id),
            u.surname,
            u.name,
            u.patronymic,
            v.id
    ";

        $st = $this->pdo->query($sql);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    }

    public function voted(int $userId, int $candidateId): array
    {
        // защита от повторного голосования
        if ($this->userHasVote($userId)) {
            throw new DomainException('Вы уже голосовали');
        }

        // создаём голос
        $st = $this->pdo->prepare(
            "INSERT INTO votes (user_id, candidate_id, created_at)
             VALUES (?, ?, NOW())"
        );
        $st->execute([$userId, $candidateId]);

        $id = (int)$this->pdo->lastInsertId();

        // возвращаем свежевставленный голос (можно без этого, если тебе не надо)
        $st = $this->pdo->prepare("SELECT * FROM votes WHERE id = ?");
        $st->execute([$id]);
        $vote = $st->fetch(PDO::FETCH_ASSOC);

        return $vote ?: [];
    }

    // public function getAdminList(): array
    // {
    //     // Возвращает то же, что и отображается во фронтенде в votes-admin.php
    //     return $this->all();
    // }

    public function getAdminList(bool $onlyDeleted = false): array
    {
        $where = $onlyDeleted
            ? 'v.deleted_at IS NOT NULL'
            : 'v.deleted_at IS NULL';

        $sql = "
            SELECT
                v.id                  AS id,
                u.id                  AS user_id,
                COALESCE(u.district_id)                         AS district,
                CONCAT_WS(' ', u.surname, u.name, u.patronymic) AS user_name,
                CONCAT(s.street, ', ', h.house)                 AS address,
                u.phone                                         AS phone,
                CONCAT_WS(' ', c.surname, c.name, c.patronymic) AS candidate
            FROM votes v
            JOIN users u
            ON u.id = v.user_id
            AND u.deleted_at IS NULL
            JOIN candidates c
            ON c.id = v.candidate_id
            AND c.deleted_at IS NULL
            LEFT JOIN streets s
            ON s.id = u.street_id
            AND s.deleted_at IS NULL
            LEFT JOIN house h
            ON h.id = u.house_id
            AND h.deleted_at IS NULL
            WHERE $where
            ORDER BY
                COALESCE(u.district_id),
                u.surname,
                u.name,
                u.patronymic,
                v.id
        ";

        $st = $this->pdo->query($sql);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    }

    public function softDelete(int $voteId): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE votes
            SET deleted_at = NOW()
            WHERE id = ? AND deleted_at IS NULL"
        );
        $st->execute([$voteId]);
        return $st->rowCount() > 0;
    }

    public function getDeletedAdminList(): array
    {
        return $this->getAdminList(true);
    }

    public function adminUpdate(int $voteId, array $fields): bool
    {
        // находим user_id по голосу
        $st = $this->pdo->prepare(
            'SELECT user_id FROM votes WHERE id = ? AND deleted_at IS NULL'
        );
        $st->execute([$voteId]);
        $userId = $st->fetchColumn();

        if (!$userId) {
            return false;
        }

        $userId = (int)$userId;

        $fio   = trim((string)($fields['user_name'] ?? ''));
        $phone = trim((string)($fields['phone'] ?? ''));
        // address пока только для отображения, в БД как отдельная строка не хранится
        // $address = trim((string)($fields['address'] ?? ''));

        $sets   = [];
        $params = [];

        if ($fio !== '') {
            $parts = preg_split('/\s+/', $fio);
            $surname    = $parts[0] ?? null;
            $name       = $parts[1] ?? null;
            $patronymic = $parts[2] ?? null;

            if ($surname !== null) {
                $sets[]   = 'surname = ?';
                $params[] = $surname;
            }
            if ($name !== null) {
                $sets[]   = 'name = ?';
                $params[] = $name;
            }
            if ($patronymic !== null) {
                $sets[]   = 'patronymic = ?';
                $params[] = $patronymic;
            }
        }

        if ($phone !== '') {
            $sets[]   = 'phone = ?';
            $params[] = $phone;
        }

        if (!$sets) {
            // менять нечего — считаем, что всё ОК
            return true;
        }

        $params[] = $userId;

        $sql = 'UPDATE users SET '.implode(', ', $sets).', updated_at = NOW() WHERE id = ?';
        $st  = $this->pdo->prepare($sql);

        return $st->execute($params);
    }
}
