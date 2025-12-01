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
            COALESCE(u.district_id, c.district)              AS district,
            CONCAT_WS(' ', u.surname, u.name, u.patronymic)  AS voter_name,
            CONCAT('ул. ', s.street, ', ', h.house)          AS address,
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
            COALESCE(u.district_id, c.district),
            u.surname,
            u.name,
            u.patronymic,
            v.id
    ";

        $st = $this->pdo->query($sql);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    }
}
