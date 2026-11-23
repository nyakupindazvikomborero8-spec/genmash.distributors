<?php
/**
 * Application Bootstrap
 */

// Define base paths
define('BASE_PATH', __DIR__ . '/../..');
define('BACKEND_PATH', __DIR__ . '/..');
define('CONFIG_PATH', BACKEND_PATH . '/config');
define('STORAGE_PATH', BACKEND_PATH . '/storage');
define('PUBLIC_PATH', BACKEND_PATH . '/public');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', Config::get('app.debug', false) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php_errors.log');

// Session configuration
$sessionConfig = Config::get('session', []);
ini_set('session.gc_maxlifetime', $sessionConfig['lifetime'] ?? 7200);
ini_set('session.cookie_lifetime', $sessionConfig['lifetime'] ?? 7200);
ini_set('session.cookie_secure', $sessionConfig['cookie']['secure'] ?? true);
ini_set('session.cookie_httponly', $sessionConfig['cookie']['httponly'] ?? true);
ini_set('session.cookie_samesite', $sessionConfig['cookie']['samesite'] ?? 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

if (isset($sessionConfig['cookie']['domain']) && $sessionConfig['cookie']['domain']) {
    ini_set('session.cookie_domain', $sessionConfig['cookie']['domain']);
}

session_name($sessionConfig['cookie']['name'] ?? 'genmashy_session');
session_start();

// Initialize cache
Cache::init();

// Set timezone
date_default_timezone_set(Config::get('app.timezone', 'Africa/Harare'));

// Load environment variables from .env if exists
$envPath = BACKEND_PATH . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Load config
Config::load(CONFIG_PATH . '/config.php');

// Auto-load models and services
spl_autoload_register(function ($class) {
    $paths = [
        BACKEND_PATH . '/src/Models/',
        BACKEND_PATH . '/src/Services/',
        BACKEND_PATH . '/src/Controllers/',
        BACKEND_PATH . '/src/Middleware/',
        BACKEND_PATH . '/src/Core/',
    ];

    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Global helper functions
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('auth')) {
    function auth(): ?User
    {
        return AuthService::user();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return CsrfMiddleware::getToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return CsrfMiddleware::csrfField();
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = Config::get('app.url', '');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('storage_url')) {
    function storage_url(string $path): string
    {
        return url('storage/' . ltrim($path, '/'));
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        // Simple route helper - would need a proper router implementation
        return url($name);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = null): mixed
    {
        return $_SESSION['old'][$key] ?? $default;
    }
}

if (!function_exists('session')) {
    function session(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_SESSION;
        }
        return $_SESSION[$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) {
            $_SESSION['flash'][$key] = $value;
            return;
        }
        
        $value = $_SESSION['flash'][$key] ?? $default;
        unset($_SESSION['flash'][$key]);
        return $value;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }
}

if (!function_exists('back')) {
    function back(): never
    {
        $url = $_SERVER['HTTP_REFERER'] ?? url('/');
        redirect($url);
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            echo json_encode(['error' => $message ?: 'Error', 'code' => $code]);
            exit;
        }
        
        // Load error view
        $viewPath = BACKEND_PATH . "/resources/views/errors/{$code}.php";
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "<h1>Error {$code}</h1><p>{$message}</p>";
        }
        exit;
    }
}

if (!function_exists('validate')) {
    function validate(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $rulesList = explode('|', $rule);
            $value = $data[$field] ?? null;
            
            foreach ($rulesList as $r) {
                $params = [];
                if (preg_match('/(\w+)\((.*)\)/', $r, $matches)) {
                    $r = $matches[1];
                    $params = explode(',', $matches[2]);
                }
                
                switch ($r) {
                    case 'required':
                        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                            $errors[$field][] = "The {$field} field is required.";
                        }
                        break;
                    case 'email':
                        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = "The {$field} must be a valid email address.";
                        }
                        break;
                    case 'min':
                        if ($value && strlen($value) < ($params[0] ?? 1)) {
                            $errors[$field][] = "The {$field} must be at least {$params[0]} characters.";
                        }
                        break;
                    case 'max':
                        if ($value && strlen($value) > ($params[0] ?? 255)) {
                            $errors[$field][] = "The {$field} must not exceed {$params[0]} characters.";
                        }
                        break;
                    case 'numeric':
                        if ($value && !is_numeric($value)) {
                            $errors[$field][] = "The {$field} must be a number.";
                        }
                        break;
                    case 'integer':
                        if ($value && !filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[$field][] = "The {$field} must be an integer.";
                        }
                        break;
                    case 'confirmed':
                        if ($value !== ($data[$field . '_confirmation'] ?? null)) {
                            $errors[$field][] = "The {$field} confirmation does not match.";
                        }
                        break;
                    case 'unique':
                        // Would need model/table checking
                        break;
                    case 'exists':
                        // Would need model/table checking
                        break;
                }
            }
        }
        
        return $errors;
    }
}

if (!function_exists('money')) {
    function money(float $amount, string $currency = 'USD', bool $symbol = true): string
    {
        $currencies = Config::get('currency.symbols', ['USD' => '$', 'ZWG' => 'ZWG$']);
        $decimals = Config::get('currency.decimals', 2);
        $thousands = Config::get('currency.thousands_separator', ',');
        $decimal = Config::get('currency.decimal_separator', '.');
        
        $formatted = number_format($amount, $decimals, $decimal, $thousands);
        
        if ($symbol) {
            return ($currencies[$currency] ?? '$') . $formatted;
        }
        
        return $formatted;
    }
}

if (!function_exists('str')) {
    function str(string $value): StringHelper
    {
        return new StringHelper($value);
    }
}

class StringHelper
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function slug(): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $this->value)));
        return trim($slug, '-');
    }

    public function ucfirst(): string
    {
        return ucfirst($this->value);
    }

    public function ucwords(): string
    {
        return ucwords($this->value);
    }

    public function limit(int $limit, string $end = '...'): string
    {
        if (strlen($this->value) <= $limit) {
            return $this->value;
        }
        return substr($this->value, 0, $limit - strlen($end)) . $end;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

if (!function_exists('collect')) {
    function collect(array $items = []): Collection
    {
        return new Collection($items);
    }
}

class Collection implements IteratorAggregate, Countable, ArrayAccess
{
    private array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    public function filter(callable $callback = null): self
    {
        return new self(array_filter($this->items, $callback ?? fn($v) => !empty($v)));
    }

    public function first(callable $callback = null): mixed
    {
        if ($callback) {
            foreach ($this->items as $item) {
                if ($callback($item)) return $item;
            }
            return null;
        }
        return $this->items[0] ?? null;
    }

    public function last(): mixed
    {
        return end($this->items);
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->items, $options);
    }
}

// Maintenance mode check
if (Config::get('app.maintenance_mode', false) && !AuthService::check()) {
    $allowedIps = ['127.0.0.1', '::1'];
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!in_array($clientIp, $allowedIps)) {
        http_response_code(503);
        echo "<!DOCTYPE html>
        <html>
        <head><title>Maintenance Mode</title>
        <style>body{font-family:Arial,sans-serif;text-align:center;padding:50px;background:#f5f5f5}
        .container{max-width:400px;margin:0 auto;background:#fff;padding:40px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
        h1{color:#e74c3c}</style></head>
        <body><div class='container'>
        <h1>Maintenance Mode</h1>
        <p>We're currently performing scheduled maintenance. Please check back soon.</p>
        </div></body></html>";
        exit;
    }
}