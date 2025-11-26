<?php
namespace App\Repository\Pdo;

use App\Repository\Contract\CandidateRepositoryInterface;
use PDO;

class CandidateRepository implements CandidateRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function all(?int $districtId = null): array
    {
        if ($districtId) {
            $st = $this->pdo->prepare(
                "SELECT c.*, 
                        (SELECT COUNT(*) FROM votes v WHERE v.candidate_id=c.id) AS votes_count
                 FROM candidates c
                 WHERE c.deleted_at IS NULL AND c.district_id = ?
                 ORDER BY c.id ASC"
            );
            $st->execute([$districtId]);
        } else {
            $st = $this->pdo->query(
                "SELECT c.*,
                        (SELECT COUNT(*) FROM votes v WHERE v.candidate_id=c.id) AS votes_count
                 FROM candidates c
                 WHERE c.deleted_at IS NULL
                 ORDER BY c.id ASC"
            );
        }
        return $st->fetchAll();
    }

    public function first(int $limit = 5): array
    {
        $sql = "SELECT c.id, c.surname, c.name, c.patronymic, c.photo, c.email
                FROM candidates c
                -- JOIN districts d ON d.id = c.district_id
                ORDER BY c.id ASC
                LIMIT :limit";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public function find(int $id): array
    {
        $st = $this->pdo->prepare(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM votes v WHERE v.candidate_id=c.id) AS votes_count
             FROM candidates c
             WHERE c.id=? AND c.deleted_at IS NULL"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) { throw new \RuntimeException('Candidate not found'); }
        return $row;
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM candidates WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO candidates(surname, name, patronymic, phone, district, photo, email, description) VALUES(?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $data['surname'] ?? null,
            $data['name'] ?? null,
            $data['patronymic'] ?? null,
            $data['phone'] ?? null,
            $data['district'] ?? null,
            $data['photo'] ?? null,
            $data['email'] ?? null,
            $data['description'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            "UPDATE candidates SET surname=?, name=?, patronymic=?, phone=?, district=?, photo=?, email=?, description=?, updated_at=NOW()
             WHERE id=? AND deleted_at IS NULL"
        );
        $st->execute([
            $data['surname'] ?? null,
            $data['name'] ?? null,
            $data['patronymic'] ?? null,
            $data['phone'] ?? null,
            $data['district'] ?? null,
            $data['photo'] ?? null,
            $data['email'] ?? null,
            $data['description'] ?? null,
            $id
        ]);
    }

    public function delete(int $id): void
    {
        // мягкое удаление (как в твоих таблицах)
        $st = $this->pdo->prepare("UPDATE candidates SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL");
        $st->execute([$id]);
    }
}
