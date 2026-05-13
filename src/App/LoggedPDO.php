<?php
namespace App\App;

use PDO;
use PDOStatement;

/**
 * Обертка над PDO для логирования всех SQL-запросов
 */
class LoggedPDO extends PDO
{
    private string $logFile;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->logFile = __DIR__ . '/../../db_queries.log';
    }

    private function log(string $method, string $sql, ?array $params = null): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] %s: %s",
            $timestamp,
            $method,
            $sql
        );

        if ($params !== null && !empty($params)) {
            $logEntry .= "\n  Params: " . json_encode($params, JSON_UNESCAPED_UNICODE);
        }

        $logEntry .= "\n" . str_repeat('-', 80) . "\n";

        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->log('QUERY', $query);
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function prepare(string $query, array $options = []): mixed
{
    $stmt = parent::prepare($query, $options);
    if ($stmt) {
        return new LoggedPDOStatement($stmt, $query, $this->logFile);
    }
    return false;
}

    public function exec(string $statement): int|false
    {
        $this->log('EXEC', $statement);
        return parent::exec($statement);
    }
}

/**
 * Обертка над PDOStatement для логирования execute с параметрами
 */
class LoggedPDOStatement
{
    private PDOStatement $stmt;
    private string $sql;
    private string $logFile;

    public function __construct(PDOStatement $stmt, string $sql, string $logFile)
    {
        $this->stmt = $stmt;
        $this->sql = $sql;
        $this->logFile = $logFile;
    }

    public function execute(?array $params = null): bool
    {
        $this->log('EXECUTE', $this->sql, $params);
        $result = $this->stmt->execute($params);
        $rowCount = $this->stmt->rowCount();
        $this->logResult($rowCount);
        return $result;
    }

    private function log(string $method, string $sql, ?array $params = null): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] %s: %s",
            $timestamp,
            $method,
            $sql
        );

        if ($params !== null && !empty($params)) {
            $logEntry .= "\n  Params: " . json_encode($params, JSON_UNESCAPED_UNICODE);
        }

        $logEntry .= "\n" . str_repeat('-', 80) . "\n";

        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }

    // Проксируем все остальные методы к оригинальному PDOStatement
    public function __call(string $name, array $arguments)
    {
        return $this->stmt->$name(...$arguments);
    }

    // Проксируем доступ к свойствам
    public function __get(string $name)
    {
        return $this->stmt->$name;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->stmt->$name = $value;
    }

    public function fetchAll(?int $mode = null, ...$args): array
    {
        $result = $this->stmt->fetchAll($mode, ...$args);
        $this->logResult(count($result));
        return $result;
    }

    public function fetch(?int $mode = null, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $result = $this->stmt->fetch($mode, $cursorOrientation, $cursorOffset);
        $this->logResult($result !== false ? 1 : 0);
        return $result;
    }

    private function logResult(int $rowCount): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] RESULT: %d row(s) affected/returned\n%s\n",
            $timestamp,
            $rowCount,
            str_repeat('-', 80)
        );
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }

    // Проксируем основные методы PDOStatement
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return $this->stmt->bindValue($param, $value, $type);
    }

    public function bindParam(string|int $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        return $this->stmt->bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    public function columnCount(): int
    {
        return $this->stmt->columnCount();
    }

    public function errorCode(): ?string
    {
        return $this->stmt->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->stmt->errorInfo();
    }
}

