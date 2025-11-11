<?php
declare(strict_types=1);

namespace App\App;

/**
 * Минимальный DI-контейнер.
 * - set(id, factory|object): регистрирует зависимость
 * - get(id): возвращает (лениво создаёт) экземпляр
 * Factory-колбэку пробрасывается сам контейнер: fn(Container $c) => new ...
 */
final class Container
{
    /** @var array<string, callable|object> */
    private array $entries = [];

    /** @var array<string, object> */
    private array $resolved = [];

    /**
     * @param string $id
     * @param callable(self):object|object $concrete
     */
    public function set(string $id, callable|object $concrete): void
    {
        $this->entries[$id] = $concrete;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries) || array_key_exists($id, $this->resolved);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return T|mixed
     */
    public function get(string $id)
    {
        if (isset($this->resolved[$id])) {
            return $this->resolved[$id];
        }

        if (!isset($this->entries[$id])) {
            throw new \RuntimeException("Container: entry [$id] is not defined");
        }

        $entry = $this->entries[$id];

        // Если зарегистрирован объект — возвращаем как есть
        if (!is_callable($entry)) {
            $this->resolved[$id] = $entry;
            return $entry;
        }

        // Если зарегистрирована фабрика — создаём и кешируем
        $obj = $entry($this);
        $this->resolved[$id] = $obj;

        return $obj;
    }
}
