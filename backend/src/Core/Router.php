<?php
/**
 * Simple Router
 */

class Router
{
    private static array $routes = [];
    private static array $middleware = [];
    private static array $groups = [];
    private static ?string $currentGroup = null;

    public static function get(string $uri, callable|string $action, array $middleware = []): void
    {
        self::addRoute('GET', $uri, $action, $middleware);
    }

    public static function post(string $uri, callable|string $action, array $middleware = []): void
    {
        self::addRoute('POST', $uri, $action, $middleware);
    }

    public static function put(string $uri, callable|string $action, array $middleware = []): void
    {
        self::addRoute('PUT', $uri, $action, $middleware);
    }

    public static function patch(string $uri, callable|string $action, array $middleware = []): void
    {
        self::addRoute('PATCH', $uri, $action, $middleware);
    }

    public static function delete(string $uri, callable|string $action, array $middleware = []): void
    {
        self::addRoute('DELETE', $uri, $action, $middleware);
    }

    public static function match(array $methods, string $uri, callable|string $action, array $middleware = []): void
    {
        foreach ($methods as $method) {
            self::addRoute($method, $uri, $action, $middleware);
        }
    }

    public static function any(string $uri, callable|string $action, array $middleware = []): void
    {
        self::addRoute('*', $uri, $action, $middleware);
    }

    private static function addRoute(string $method, string $uri, callable|string $action, array $middleware): void
    {
        $prefix = self::$currentGroup ? self::$groups[self::$currentGroup]['prefix'] ?? '' : '';
        $middleware = array_merge(self::$groups[self::$currentGroup]['middleware'] ?? [], $middleware);

        self::$routes[] = [
            'method' => $method,
            'uri' => $prefix . $uri,
            'action' => $action,
            'middleware' => $middleware,
            'name' => null,
        ];
    }

    public static function group(array $attributes, callable $callback): void
    {
        $name = $attributes['prefix'] ?? uniqid('group_');
        self::$groups[$name] = [
            'prefix' => $attributes['prefix'] ?? '',
            'middleware' => $attributes['middleware'] ?? [],
            'namespace' => $attributes['namespace'] ?? '',
        ];
        
        $previousGroup = self::$currentGroup;
        self::$currentGroup = $name;
        
        $callback();
        
        self::$currentGroup = $previousGroup;
    }

    public static function dispatch(string $method, string $uri): mixed
    {
        // Normalize URI
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';
        $uri = rtrim($uri, '/') ?: '/';

        foreach (self::$routes as $route) {
            if ($route['method'] !== '*' && $route['method'] !== $method) {
                continue;
            }

            $pattern = self::uriToPattern($route['uri']);
            
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remove full match
                
                // Run middleware
                foreach ($route['middleware'] as $middleware) {
                    if (is_string($middleware)) {
                        $middleware = [new $middleware(), 'handle'];
                    }
                    if (is_callable($middleware)) {
                        $result = $middleware(function() {});
                        if ($result !== null) return $result;
                    }
                }

                // Execute action
                return self::executeAction($route['action'], $matches);
            }
        }

        // Route not found
        abort(404, 'Page not found');
    }

    private static function uriToPattern(string $uri): string
    {
        // Convert {param} to regex
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?<$1>[^/]+)', $uri);
        $pattern = str_replace('/', '\/', $pattern);
        return '#^' . $pattern . '$#';
    }

    private static function executeAction(callable|string $action, array $params): mixed
    {
        if (is_callable($action)) {
            return $action(...array_values($params));
        }

        // Controller@method format
        if (is_string($action) && strpos($action, '@') !== false) {
            [$controller, $method] = explode('@', $action);
            
            $controllerClass = "App\\Controllers\\{$controller}";
            if (!class_exists($controllerClass)) {
                $controllerClass = "Controllers\\{$controller}";
            }
            
            if (!class_exists($controllerClass)) {
                abort(500, "Controller {$controllerClass} not found");
            }

            $instance = new $controllerClass();
            
            if (!method_exists($instance, $method)) {
                abort(500, "Method {$method} not found in {$controllerClass}");
            }

            return $instance->$method(...array_values($params));
        }

        abort(500, 'Invalid route action');
    }

    public static function getRoutes(): array
    {
        return self::$routes;
    }
}