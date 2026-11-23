<?php
/**
 * Database Connection Manager
 * Provides a PDO connection with proper configuration
 */

class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        if (empty(self::$config)) {
            self::$config = Config::get('database', []);
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            self::$config['host'] ?? 'localhost',
            self::$config['port'] ?? 3306,
            self::$config['database'] ?? 'genmashy',
            self::$config['charset'] ?? 'utf8mb4'
        );

        $options = self::$config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$connection = new PDO(
                $dsn,
                self::$config['username'] ?? '',
                self::$config['password'] ?? '',
                $options
            );

            // Set connection attributes
            self::$connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            self::$connection->exec("SET time_zone = '+00:00'");

            return self::$connection;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new RuntimeException('Database connection failed');
        }
    }

    public static function close(): void
    {
        self::$connection = null;
    }

    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    public static function rollBack(): bool
    {
        return self::getConnection()->rollBack();
    }

    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }

    /**
     * Execute a query within a transaction
     */
    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();
        try {
            $result = $callback(self::getConnection());
            self::commit();
            return $result;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }
}