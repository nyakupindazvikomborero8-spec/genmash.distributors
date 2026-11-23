<?php
/**
 * Authentication Middleware
 */

class AuthMiddleware
{
    public static function handle(callable $next): mixed
    {
        $user = AuthService::user();
        
        if (!$user) {
            // Store intended URL for redirect after login
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            
            if (self::isAjaxRequest()) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized', 'message' => 'Please log in to continue.']);
                exit;
            }
            
            header('Location: /login');
            exit;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();
        
        // Check session timeout
        $lifetime = Config::get('session.lifetime', 7200);
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $lifetime) {
            AuthService::logout();
            
            if (self::isAjaxRequest()) {
                http_response_code(401);
                echo json_encode(['error' => 'Session expired', 'message' => 'Your session has expired. Please log in again.']);
                exit;
            }
            
            header('Location: /login?expired=1');
            exit;
        }

        return $next();
    }

    public static function guest(callable $next): mixed
    {
        if (AuthService::check()) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden', 'message' => 'Already logged in.']);
                exit;
            }
            
            header('Location: /account');
            exit;
        }

        return $next();
    }

    public static function role(array $allowedRoles, callable $next): mixed
    {
        $user = AuthService::user();
        
        if (!$user) {
            return self::handle($next);
        }

        $roleNames = [
            1 => 'super_admin',
            2 => 'admin',
            3 => 'sales',
            4 => 'customer',
        ];

        $userRole = $roleNames[$user->role_id] ?? 'customer';
        
        if (!in_array($userRole, $allowedRoles)) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden', 'message' => 'You do not have permission to access this resource.']);
                exit;
            }
            
            header('Location: /unauthorized');
            exit;
        }

        return $next();
    }

    public static function permission(string|array $permissions, callable $next): mixed
    {
        $user = AuthService::user();
        
        if (!$user) {
            return self::handle($next);
        }

        $perms = is_array($permissions) ? $permissions : [$permissions];
        
        if (!$user->hasAnyPermission($perms)) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden', 'message' => 'You do not have permission to perform this action.']);
                exit;
            }
            
            header('Location: /unauthorized');
            exit;
        }

        return $next();
    }

    private static function isAjaxRequest(): bool
    {
        return (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        );
    }
}