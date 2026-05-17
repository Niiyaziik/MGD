<?php
namespace App\Model;

use App\App\Database;
use PDO;

abstract class BaseModel {
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];

    public ?int $id = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;

    public function __construct(array $attrs = []) {
        foreach ($attrs as $k=>$v) { $this->{$k} = $v; }
    }

    protected static function pdo(): PDO { return Database::pdo(); }

    public static function find(int $id): ?static {
        $sql = "SELECT * FROM ".static::$table." WHERE ".static::$primaryKey."=? AND deleted_at IS NULL";
        $st = static::pdo()->prepare($sql); $st->execute([$id]);
        $row = $st->fetch();
        return $row ? new static($row) : null;
    }

    /** @return static[] */
    public static function all(array $where = [], ?string $orderBy = null): array {
        $sql = "SELECT * FROM ".static::$table." WHERE deleted_at IS NULL";
        $params = [];
        foreach ($where as $k=>$v) { $sql .= " AND {$k}=?"; $params[] = $v; }
        if ($orderBy) $sql .= " ORDER BY ".$orderBy;
        $st = static::pdo()->prepare($sql); $st->execute($params);
        return array_map(fn($r)=>new static($r), $st->fetchAll());
    }

    public static function create(array $data): static {
        $data = array_intersect_key($data, array_flip(static::$fillable));
        if (!$data) throw new \InvalidArgumentException('No data to insert');
        $cols = array_keys($data);
        $place = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO ".static::$table." (".implode(',', $cols).") VALUES ($place)";
        $st = static::pdo()->prepare($sql); $st->execute(array_values($data));
        $id = (int)static::pdo()->lastInsertId();
        return static::find($id);
    }

    public function update(array $data): void {
        $data = array_intersect_key($data, array_flip(static::$fillable));
        if (!$data) return;
        $sets = []; $params = [];
        foreach ($data as $k=>$v){ $sets[]="$k=?"; $params[]=$v; $this->{$k}=$v; }
        $params[] = $this->{static::$primaryKey};
        $sql = "UPDATE ".static::$table." SET ".implode(',', $sets).", updated_at = NOW() WHERE ".static::$primaryKey."=? AND deleted_at IS NULL";
        static::pdo()->prepare($sql)->execute($params);
    }

    public function delete(): void {
        $id = $this->{static::$primaryKey};
        static::pdo()->prepare("UPDATE ".static::$table." SET deleted_at=NOW() WHERE ".static::$primaryKey."=? AND deleted_at IS NULL")
            ->execute([$id]);
        $this->deleted_at = date('Y-m-d H:i:s');
    }
}
