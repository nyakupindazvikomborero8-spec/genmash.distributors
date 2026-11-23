<?php
/**
 * CSRF Protection Middleware
 */

class CsrfMiddleware
{
    public static function handle(callable $next): mixed
    {
        // Generate token for GET requests
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            self::generateToken();
            return $next();
        }

        // Skip CSRF for API routes with token in header
        if (self::hasValidApiToken()) {
            return $next();
        }

        // Validate CSRF token for state-changing requests
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if (!self::validateToken()) {
                if (self::isAjaxRequest()) {
                    http_response_code(419);
                    echo json_encode(['error' => 'CSRF token mismatch', 'message' => 'Invalid or missing CSRF token. Please refresh the page and try again.']);
                    exit;
                }
                
                http_response_code(419);
                echo "CSRF token mismatch. Please refresh the page and try again.";
                exit;
            }
        }

        return $next();
    }

    public static function generateToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function getToken(): string
    {
        return $_SESSION['csrf_token'] ?? self::generateToken();
    }

    public static function validateToken(): bool
    {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (!$token || !$sessionToken) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    public static function hasValidApiToken(): bool
    {
        $apiToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$apiToken) return false;

        // Check for Bearer token
        if (str_starts_with($apiToken, 'Bearer ')) {
            $token = substr($apiToken, 7);
            // Validate against API tokens in database (implementation depends on your API auth strategy)
            return self::validateApiToken($token);
        }

        return false;
    }

    private static function validateApiToken(string $token): bool
    {
        // Simple implementation - in production, use proper API token validation
        // This could check against a personal_access_tokens table
        return !empty($token) && strlen($token) >= 32;
    }

    private static function isAjaxRequest(): bool
    {
        return (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        );
    }

    // Helper for Blade-like templates
    public static function csrfField(): string
    {
        $token = self::getToken();
        return "<input type='hidden' name='_token' value='" . htmlspecialchars($token) . "'>";
    }

    public static function csrfMeta(): string
    {
        $token = self::getToken();
        return "<meta name='csrf-token' content='" . htmlspecialchars($token) . "'>";
    }
}