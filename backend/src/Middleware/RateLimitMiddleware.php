<?php
/**
 * Rate Limiting Middleware
 */

class RateLimitMiddleware
{
    public static function handle(string $limiter = 'default', callable $next): mixed
    {
        $limits = Config::get('security.rate_limits', []);
        $config = $limits[$limiter] ?? $limits['api'] ?? ['attempts' => 60, 'window' => 60];
        
        $maxAttempts = $config['attempts'];
        $window = $config['window'];

        $key = self::getRateLimitKey($limiter);
        $attempts = Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            $retryAfter = Cache::get($key . ':time', time() + $window) - time();
            
            if (self::isAjaxRequest()) {
                http_response_code(429);
                header('Retry-After: ' . max(1, $retryAfter));
                echo json_encode([
                    'error' => 'Too Many Requests',
                    'message' => 'Rate limit exceeded. Please try again later.',
                    'retry_after' => max(1, $retryAfter),
                ]);
                exit;
            }
            
            http_response_code(429);
            header('Retry-After: ' . max(1, $retryAfter));
            echo "Rate limit exceeded. Please try again in " . max(1, $retryAfter) . " seconds.";
            exit;
        }

        // Increment counter
        Cache::set($key, $attempts + 1, $window);
        if (!$attempts) {
            Cache::set($key . ':time', time() + $window, $window);
        }

        // Add rate limit headers
        header('X-RateLimit-Limit: ' . $maxAttempts);
        header('X-RateLimit-Remaining: ' . max(0, $maxAttempts - $attempts - 1));
        header('X-RateLimit-Reset: ' . (Cache::get($key . ':time', time() + $window)));

        return $next();
    }

    public static function login(callable $next): mixed
    {
        return self::handle('login', $next);
    }

    public static function register(callable $next): mixed
    {
        return self::handle('register', $next);
    }

    public static function passwordReset(callable $next): mixed
    {
        return self::handle('password_reset', $next);
    }

    public static function api(callable $next): mixed
    {
        return self::handle('api', $next);
    }

    public static function custom(string $name, int $maxAttempts, int $window, callable $next): mixed
    {
        $key = "ratelimit:{$name}:" . self::getIdentifier();
        $attempts = Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            if (self::isAjaxRequest()) {
                http_response_code(429);
                echo json_encode(['error' => 'Too Many Requests', 'message' => 'Rate limit exceeded.']);
                exit;
            }
            
            http_response_code(429);
            echo "Rate limit exceeded.";
            exit;
        }

        Cache::set($key, $attempts + 1, $window);

        return $next();
    }

    private static function getRateLimitKey(string $limiter): string
    {
        return "ratelimit:{$limiter}:" . self::getIdentifier();
    }

    private static function getIdentifier(): string
    {
        // Use IP + User Agent for better identification
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // For authenticated users, use user ID
        if (isset($_SESSION['user_id'])) {
            return "user:{$_SESSION['user_id']}";
        }
        
        return "ip:{$ip}:ua:" . substr(hash('sha256', $ua), 0, 16);
    }

    private static function isAjaxRequest(): bool
    {
        return (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        );
    }
}