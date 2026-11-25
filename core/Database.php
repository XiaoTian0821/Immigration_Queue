<?php
/**
 * PDO connection manager (singleton).
 */
class Database
{
    private static ?PDO $instance = null;

    public static function conn(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }

    /** Shortcut for select. */
    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Shortcut for a single value. */
    public static function value(string $sql, array $params = [])
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /** Shortcut for one row. */
    public static function row(string $sql, array $params = []): ?array
    {
        $r = self::select($sql, $params);
        return $r[0] ?? null;
    }

    /** Run insert/update/delete. */
    public static function run(string $sql, array $params = []): int
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function lastInsertId(): string
    {
        return self::conn()->lastInsertId();
    }

    public static function begin(): void   { self::conn()->beginTransaction(); }
    public static function commit(): void  { self::conn()->commit(); }
    public static function rollback(): void{ if (self::conn()->inTransaction()) self::conn()->rollBack(); }
}
