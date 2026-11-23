<?php
/**
 * Cache Service
 * Simple file-based cache with optional database/Redis support
 */

class Cache
{
    private static string $driver = 'file';
    private static string $path = '';
    private static ?PDO $db = null;

    public static function init(): void
    {
        self::$driver = Config::get('cache.default', 'file');
        self::$path = Config::get('cache.stores.file.path', __DIR__ . '/../../storage/cache');
        
        if (!is_dir(self::$path)) {
            mkdir(self::$path, 0755, true);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::init();

        return match (self::$driver) {
            'file' => self::fileGet($key, $default),
            'database' => self::dbGet($key, $default),
            'redis' => self::redisGet($key, $default),
            default => $default,
        };
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        self::init();

        return match (self::$driver) {
            'file' => self::fileSet($key, $value, $ttl),
            'database' => self::dbSet($key, $value, $ttl),
            'redis' => self::redisSet($key, $value, $ttl),
            default => false,
        };
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    public static function delete(string $key): bool
    {
        self::init();

        return match (self::$driver) {
            'file' => self::fileDelete($key),
            'database' => self::dbDelete($key),
            'redis' => self::redisDelete($key),
            default => false,
        };
    }

    public static function clear(): bool
    {
        self::init();

        return match (self::$driver) {
            'file' => self::fileClear(),
            'database' => self::dbClear(),
            'redis' => self::redisClear(),
            default => false,
        };
    }

    public static function increment(string $key, int $value = 1): int
    {
        $current = (int)self::get($key, 0);
        $new = $current + $value;
        self::set($key, $new);
        return $new;
    }

    public static function decrement(string $key, int $value = 1): int
    {
        $current = (int)self::get($key, 0);
        $new = $current - $value;
        self::set($key, $new);
        return $new;
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (self::has($key)) {
            return self::get($key);
        }

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    // File driver
    private static function fileGet(string $key, mixed $default): mixed
    {
        $file = self::$path . '/' . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return $default;
        }

        $data = @unserialize(file_get_contents($file));
        
        if ($data === false || !is_array($data)) {
            @unlink($file);
            return $default;
        }

        if (isset($data['expires']) && $data['expires'] < time()) {
            @unlink($file);
            return $default;
        }

        return $data['value'] ?? $default;
    }

    private static function fileSet(string $key, mixed $value, int $ttl): bool
    {
        $file = self::$path . '/' . md5($key) . '.cache';
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time(),
        ];
        
        return @file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    private static function fileDelete(string $key): bool
    {
        $file = self::$path . '/' . md5($key) . '.cache';
        return @unlink($file);
    }

    private static function fileClear(): bool
    {
        $files = glob(self::$path . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    // Database driver
    private static function dbGet(string $key, mixed $default): mixed
    {
        if (!self::$db) {
            self::$db = Database::getConnection();
        }

        $stmt = self::$db->prepare('SELECT value, expires FROM cache WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            return $default;
        }

        if ($row['expires'] && $row['expires'] < time()) {
            self::dbDelete($key);
            return $default;
        }

        return @unserialize($row['value']) ?? $default;
    }

    private static function dbSet(string $key, mixed $value, int $ttl): bool
    {
        if (!self::$db) {
            self::$db = Database::getConnection();
        }

        $expires = time() + $ttl;
        $serialized = serialize($value);

        $stmt = self::$db->prepare('
            INSERT INTO cache (`key`, value, expires) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value), expires = VALUES(expires)
        ');
        
        return $stmt->execute([$key, $serialized, $expires]);
    }

    private static function dbDelete(string $key): bool
    {
        if (!self::$db) {
            self::$db = Database::getConnection();
        }

        $stmt = self::$db->prepare('DELETE FROM cache WHERE `key` = ?');
        return $stmt->execute([$key]);
    }

    private static function dbClear(): bool
    {
        if (!self::$db) {
            self::$db = Database::getConnection();
        }

        $stmt = self::$db->prepare('DELETE FROM cache');
        return $stmt->execute();
    }

    // Redis driver (placeholder - requires phpredis extension)
    private static function redisGet(string $key, mixed $default): mixed
    {
        if (!extension_loaded('redis')) {
            return $default;
        }

        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            $value = $redis->get('cache:' . $key);
            return $value ? @unserialize($value) : $default;
        } catch (Exception) {
            return $default;
        }
    }

    private static function redisSet(string $key, mixed $value, int $ttl): bool
    {
        if (!extension_loaded('redis')) {
            return false;
        }

        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            return $redis->setex('cache:' . $key, $ttl, serialize($value));
        } catch (Exception) {
            return false;
        }
    }

    private static function redisDelete(string $key): bool
    {
        if (!extension_loaded('redis')) {
            return false;
        }

        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            return $redis->del('cache:' . $key) > 0;
        } catch (Exception) {
            return false;
        }
    }

    private static function redisClear(): bool
    {
        if (!extension_loaded('redis')) {
            return false;
        }

        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            $keys = $redis->keys('cache:*');
            if ($keys) {
                $redis->del($keys);
            }
            return true;
        } catch (Exception) {
            return false;
        }
    }
}