<?php
/**
 * Base Controller
 */

abstract class Controller
{
    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    protected function success(mixed $data = null, string $message = 'Success', int $status = 200): never
    {
        $response = ['success' => true, 'message' => $message];
        if ($data !== null) {
            $response['data'] = $data;
        }
        $this->json($response, $status);
    }

    protected function error(string $message = 'Error', int $status = 400, mixed $errors = null): never
    {
        $response = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        $this->json($response, $status);
    }

    protected function notFound(string $message = 'Resource not found'): never
    {
        $this->error($message, 404);
    }

    protected function unauthorized(string $message = 'Unauthorized'): never
    {
        $this->error($message, 401);
    }

    protected function forbidden(string $message = 'Forbidden'): never
    {
        $this->error($message, 403);
    }

    protected function validationError(array $errors): never
    {
        $this->json(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
    }

    protected function validate(array $data, array $rules): array
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
                    case 'in':
                        if ($value && !in_array($value, $params)) {
                            $errors[$field][] = "The {$field} must be one of: " . implode(', ', $params);
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }

    protected function getInput(): array
    {
        $input = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $input = $_GET;
        } elseif (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            if (strpos($contentType, 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
            } else {
                $input = $_POST;
            }
        }
        
        return $input;
    }

    protected function getQuery(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    protected function paginate(Builder $query, int $perPage = null, int $page = null): array
    {
        $perPage = $perPage ?? (int)$this->getQuery('per_page', Config::get('pagination.default_per_page', 20));
        $page = $page ?? (int)$this->getQuery('page', 1);
        
        return $query->paginate($perPage, $page);
    }
}