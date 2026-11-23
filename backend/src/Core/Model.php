<?php
/**
 * Base Model Class
 * Provides common database operations for all models
 */

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];
    protected static array $hidden = [];
    protected static array $casts = [];
    protected static array $dates = ['created_at', 'updated_at', 'deleted_at'];
    
    protected array $attributes = [];
    protected array $original = [];
    protected array $changes = [];
    protected bool $exists = false;
    protected bool $wasRecentlyCreated = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function query(): Builder
    {
        return new Builder(static::class);
    }

    public static function find(mixed $id): ?static
    {
        return static::query()->where(static::$primaryKey, $id)->first();
    }

    public static function findOrFail(mixed $id): static
    {
        $model = static::find($id);
        if (!$model) {
            throw new ModelNotFoundException("No query results for model [" . static::class . "] with ID {$id}");
        }
        return $model;
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $model = static::query()->where($attributes)->first();
        if ($model) {
            return $model;
        }
        return static::create(array_merge($attributes, $values));
    }

    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $model = static::query()->where($attributes)->first();
        if ($model) {
            $model->fill($values)->save();
            return $model;
        }
        return static::create(array_merge($attributes, $values));
    }

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $this->castAttribute($key, $value);
        if ($this->exists && (!isset($this->original[$key]) || $this->original[$key] !== $value)) {
            $this->changes[$key] = $value;
        }
        return $this;
    }

    public function getAttribute(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        return null;
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->original[$key], $this->changes[$key]);
    }

    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        }
        return $this->performInsert();
    }

    protected function performInsert(): bool
    {
        $attributes = $this->getDirtyAttributes();
        
        // Set timestamps
        if (in_array('created_at', static::$dates)) {
            $attributes['created_at'] = date('Y-m-d H:i:s');
        }
        if (in_array('updated_at', static::$dates)) {
            $attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $columns = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($attributes)));
        $placeholders = implode(', ', array_map(fn($c) => ":{$c}", array_keys($attributes)));
        
        $sql = "INSERT INTO `{$this->getTable()}` ({$columns}) VALUES ({$placeholders})";
        
        $stmt = Database::getConnection()->prepare($sql);
        $result = $stmt->execute($attributes);
        
        if ($result) {
            $this->attributes[static::$primaryKey] = Database::getConnection()->lastInsertId();
            $this->exists = true;
            $this->wasRecentlyCreated = true;
            $this->syncOriginal();
        }
        
        return $result;
    }

    protected function performUpdate(): bool
    {
        $dirty = $this->getDirtyAttributes();
        
        if (empty($dirty)) {
            return true;
        }

        // Update timestamp
        if (in_array('updated_at', static::$dates)) {
            $dirty['updated_at'] = date('Y-m-d H:i:s');
        }

        $sets = implode(', ', array_map(fn($c) => "`{$c}` = :{$c}", array_keys($dirty)));
        $sql = "UPDATE `{$this->getTable()}` SET {$sets} WHERE `{$this->getPrimaryKey()}` = :id";
        
        $dirty['id'] = $this->getKey();
        
        $stmt = Database::getConnection()->prepare($sql);
        $result = $stmt->execute($dirty);
        
        if ($result) {
            $this->syncOriginal();
        }
        
        return $result;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // Soft delete if deleted_at column exists
        if (in_array('deleted_at', static::$dates) && $this->hasColumn('deleted_at')) {
            $this->setAttribute('deleted_at', date('Y-m-d H:i:s'));
            return $this->save();
        }

        $sql = "DELETE FROM `{$this->getTable()}` WHERE `{$this->getPrimaryKey()}` = :id";
        $stmt = Database::getConnection()->prepare($sql);
        $result = $stmt->execute(['id' => $this->getKey()]);
        
        if ($result) {
            $this->exists = false;
        }
        
        return $result;
    }

    public function forceDelete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $sql = "DELETE FROM `{$this->getTable()}` WHERE `{$this->getPrimaryKey()}` = :id";
        $stmt = Database::getConnection()->prepare($sql);
        $result = $stmt->execute(['id' => $this->getKey()]);
        
        if ($result) {
            $this->exists = false;
        }
        
        return $result;
    }

    public function restore(): bool
    {
        if (!in_array('deleted_at', static::$dates) || !$this->hasColumn('deleted_at')) {
            return false;
        }

        $this->setAttribute('deleted_at', null);
        return $this->save();
    }

    public function getKey(): mixed
    {
        return $this->attributes[static::$primaryKey] ?? null;
    }

    public function getKeyName(): string
    {
        return static::$primaryKey;
    }

    public function getTable(): string
    {
        return static::$table ?: strtolower((new ReflectionClass($this))->getShortName()) . 's';
    }

    protected function getDirtyAttributes(): array
    {
        $dirty = [];
        foreach ($this->changes as $key => $value) {
            if ($this->isFillable($key)) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
        $this->changes = [];
    }

    protected function isFillable(string $key): bool
    {
        return in_array($key, static::$fillable) || static::$fillable === ['*'];
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if (!isset(static::$casts[$key]) || $value === null) {
            return $value;
        }

        return match (static::$casts[$key]) {
            'int', 'integer' => (int)$value,
            'float', 'double' => (float)$value,
            'bool', 'boolean' => (bool)$value,
            'array' => is_array($value) ? $value : json_decode($value, true) ?? [],
            'json' => is_string($value) ? json_decode($value, true) ?? [] : json_encode($value),
            'date' => $value instanceof DateTime ? $value->format('Y-m-d') : $value,
            'datetime' => $value instanceof DateTime ? $value->format('Y-m-d H:i:s') : $value,
            default => $value,
        };
    }

    public function toArray(): array
    {
        $attributes = $this->attributes;
        
        foreach (static::$hidden as $key) {
            unset($attributes[$key]);
        }
        
        return $attributes;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function hasColumn(string $column): bool
    {
        try {
            $sql = "SHOW COLUMNS FROM `{$this->getTable()}` LIKE ?";
            $stmt = Database::getConnection()->prepare($sql);
            $stmt->execute([$column]);
            return $stmt->fetch() !== false;
        } catch (Exception) {
            return false;
        }
    }

    public function wasRecentlyCreated(): bool
    {
        return $this->wasRecentlyCreated;
    }

    public function isDirty(string $key = null): bool
    {
        if ($key === null) {
            return !empty($this->changes);
        }
        return isset($this->changes[$key]);
    }

    public function getOriginal(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->original;
        }
        return $this->original[$key] ?? $default;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public static function all(array $columns = ['*']): array
    {
        return static::query()->get($columns);
    }

    public static function count(): int
    {
        return static::query()->count();
    }

    public static function where(string $column, string $operator = '=', mixed $value = null): Builder
    {
        return static::query()->where($column, $operator, $value);
    }

    public static function whereIn(string $column, array $values): Builder
    {
        return static::query()->whereIn($column, $values);
    }

    public static function whereNotIn(string $column, array $values): Builder
    {
        return static::query()->whereNotIn($column, $values);
    }

    public static function whereNull(string $column): Builder
    {
        return static::query()->whereNull($column);
    }

    public static function whereNotNull(string $column): Builder
    {
        return static::query()->whereNotNull($column);
    }

    public static function orderBy(string $column, string $direction = 'asc'): Builder
    {
        return static::query()->orderBy($column, $direction);
    }

    public static function limit(int $limit): Builder
    {
        return static::query()->limit($limit);
    }

    public static function paginate(int $perPage = 20, int $page = 1): array
    {
        return static::query()->paginate($perPage, $page);
    }

    public function refresh(): static
    {
        if (!$this->exists) {
            return $this;
        }
        
        $fresh = static::find($this->getKey());
        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->original = $fresh->original;
            $this->changes = [];
        }
        return $this;
    }
}

/**
 * Query Builder
 */
class Builder
{
    protected string $modelClass;
    protected string $table;
    protected array $wheres = [];
    protected array $joins = [];
    protected array $selects = ['*'];
    protected array $orders = [];
    protected array $groups = [];
    protected array $havings = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected ?string $lock = null;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
        $this->table = (new $modelClass())->getTable();
    }

    protected function getModel(): Model
    {
        return new $this->modelClass();
    }

    public function select(array|string $columns = ['*']): self
    {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function where(string $column, string $operator = '=', mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ['type' => 'basic', 'column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'and'];
        return $this;
    }

    public function orWhere(string $column, string $operator = '=', mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ['type' => 'basic', 'column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'or'];
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = ['type' => 'in', 'column' => $column, 'values' => $values, 'boolean' => 'and', 'not' => false];
        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[] = ['type' => 'in', 'column' => $column, 'values' => $values, 'boolean' => 'and', 'not' => true];
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['type' => 'null', 'column' => $column, 'boolean' => 'and', 'not' => false];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['type' => 'null', 'column' => $column, 'boolean' => 'and', 'not' => true];
        return $this;
    }

    public function join(string $table, string $first, string $operator = null, string $second = null, string $type = 'inner'): self
    {
        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator = null, string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string $table, string $first, string $operator = null, string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orders[] = ['column' => $column, 'direction' => $direction];
        return $this;
    }

    public function groupBy(string|array $columns): self
    {
        $this->groups = array_merge($this->groups, is_array($columns) ? $columns : func_get_args());
        return $this;
    }

    public function having(string $column, string $operator = '=', mixed $value = null): self
    {
        $this->havings[] = ['column' => $column, 'operator' => $operator, 'value' => $value];
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    public function lock(bool $value = true): self
    {
        $this->lock = $value ? 'FOR UPDATE' : null;
        return $this;
    }

    public function sharedLock(): self
    {
        $this->lock = 'LOCK IN SHARE MODE';
        return $this;
    }

    public function get(array $columns = ['*']): array
    {
        $this->selects = $columns;
        $results = $this->runSelect();
        
        return array_map(function ($row) {
            $model = new $this->modelClass($row);
            $model->exists = true;
            $model->syncOriginal();
            return $model;
        }, $results);
    }

    public function first(array $columns = ['*']): ?Model
    {
        $this->limit = 1;
        $results = $this->get($columns);
        return $results[0] ?? null;
    }

    public function firstOrFail(array $columns = ['*']): Model
    {
        $model = $this->first($columns);
        if (!$model) {
            throw new ModelNotFoundException("No query results for model [" . $this->modelClass . "]");
        }
        return $model;
    }

    public function count(): int
    {
        $sql = $this->toCountSql();
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($this->getBindings());
        return (int)$stmt->fetchColumn();
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function paginate(int $perPage = 20, int $page = 1): array
    {
        $perPage = max(1, min($perPage, Config::get('pagination.max_per_page', 100)));
        $page = max(1, $page);
        
        $total = $this->count();
        $lastPage = (int)ceil($total / $perPage);
        
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;
        
        $data = $this->get();
        
        return [
            'data' => $data,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $total > 0 ? ($page - 1) * $perPage + 1 : null,
            'to' => $total > 0 ? min($page * $perPage, $total) : null,
            'has_more_pages' => $page < $lastPage,
        ];
    }

    public function simplePaginate(int $perPage = 20, int $page = 1): array
    {
        $perPage = max(1, min($perPage, Config::get('pagination.max_per_page', 100)));
        $page = max(1, $page);
        
        $this->limit = $perPage + 1;
        $this->offset = ($page - 1) * $perPage;
        
        $data = $this->get();
        $hasMore = count($data) > $perPage;
        
        if ($hasMore) {
            array_pop($data);
        }
        
        return [
            'data' => $data,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more_pages' => $hasMore,
        ];
    }

    protected function runSelect(): array
    {
        $sql = $this->toSql();
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($this->getBindings());
        return $stmt->fetchAll();
    }

    protected function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->selects) . ' FROM `' . $this->table . '`';
        
        // Joins
        foreach ($this->joins as $join) {
            $sql .= ' ' . strtoupper($join['type']) . " JOIN `{$join['table']}` ON ";
            
            if ($join['operator'] && $join['second']) {
                $sql .= "`{$join['first']}` {$join['operator']} `{$join['second']}`";
            } else {
                $sql .= "`{$join['first']}`";
            }
        }
        
        // Where clauses
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWheres();
        }
        
        // Group by
        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', array_map(fn($c) => "`{$c}`", $this->groups));
        }
        
        // Having
        if (!empty($this->havings)) {
            $sql .= ' HAVING ' . implode(' AND ', array_map(
                fn($h) => "`{$h['column']}` {$h['operator']} " . ($h['value'] === null ? 'NULL' : '?'),
                $this->havings
            ));
        }
        
        // Order by
        if (!empty($this->orders)) {
            $sql .= ' ORDER BY ' . implode(', ', array_map(
                fn($o) => "`{$o['column']}` {$o['direction']}",
                $this->orders
            ));
        }
        
        // Limit & Offset
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        
        // Lock
        if ($this->lock) {
            $sql .= ' ' . $this->lock;
        }
        
        return $sql;
    }

    protected function toCountSql(): string
    {
        $sql = 'SELECT COUNT(*) AS aggregate FROM `' . $this->table . '`';
        
        foreach ($this->joins as $join) {
            $sql .= ' ' . strtoupper($join['type']) . " JOIN `{$join['table']}` ON ";
            if ($join['operator'] && $join['second']) {
                $sql .= "`{$join['first']}` {$join['operator']} `{$join['second']}`";
            } else {
                $sql .= "`{$join['first']}`";
            }
        }
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWheres();
        }
        
        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', array_map(fn($c) => "`{$c}`", $this->groups));
        }
        
        if (!empty($this->havings)) {
            $sql .= ' HAVING ' . implode(' AND ', array_map(
                fn($h) => "`{$h['column']}` {$h['operator']} " . ($h['value'] === null ? 'NULL' : '?'),
                $this->havings
            ));
        }
        
        return $sql;
    }

    protected function buildWheres(): string
    {
        $wheres = [];
        
        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : strtoupper($where['boolean']) . ' ';
            
            switch ($where['type']) {
                case 'basic':
                    $operator = $where['operator'];
                    $value = $where['value'];
                    if ($value === null && $operator === '=') {
                        $operator = 'IS';
                        $value = 'NULL';
                    } elseif ($value === null && $operator === '!=') {
                        $operator = 'IS NOT';
                        $value = 'NULL';
                    }
                    $placeholder = $value === 'NULL' ? 'NULL' : '?';
                    $wheres[] = "{$boolean}`{$where['column']}` {$operator} {$placeholder}";
                    break;
                    
                case 'in':
                    $not = $where['not'] ? 'NOT ' : '';
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $wheres[] = "{$boolean}`{$where['column']}` {$not}IN ({$placeholders})";
                    break;
                    
                case 'null':
                    $not = $where['not'] ? 'NOT ' : '';
                    $wheres[] = "{$boolean}`{$where['column']}` {$not}IS NULL";
                    break;
            }
        }
        
        return implode(' ', $wheres);
    }

    protected function getBindings(): array
    {
        $bindings = [];
        
        foreach ($this->wheres as $where) {
            switch ($where['type']) {
                case 'basic':
                    if ($where['value'] !== null && $where['value'] !== 'NULL') {
                        $bindings[] = $where['value'];
                    }
                    break;
                case 'in':
                    $bindings = array_merge($bindings, $where['values']);
                    break;
            }
        }
        
        foreach ($this->havings as $having) {
            if ($having['value'] !== null) {
                $bindings[] = $having['value'];
            }
        }
        
        return $bindings;
    }
}

class ModelNotFoundException extends Exception {}