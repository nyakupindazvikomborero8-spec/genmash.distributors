<?php
/**
 * Setting Model
 */

class Setting extends Model
{
    protected static string $table = 'settings';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'key', 'value', 'type', 'group', 'label', 'description', 'is_public', 'validation_rules',
    ];
    protected static array $casts = [
        'is_public' => 'boolean',
        'validation_rules' => 'json',
    ];

    public function getValue(): mixed
    {
        $value = $this->value;
        $type = $this->type;

        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'integer' => (int)$value,
            'float' => (float)$value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true) ?? [],
            'array' => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    public function setValue(mixed $value): void
    {
        $type = $this->type;
        $this->value = match ($type) {
            'json', 'array' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string)$value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();
        return $setting ? $setting->getValue() : $default;
    }

    public static function set(string $key, mixed $value): bool
    {
        $setting = static::query()->where('key', $key)->first();
        if ($setting) {
            $setting->setValue($value);
            return $setting->save();
        }
        return false;
    }

    public static function getGroup(string $group, bool $publicOnly = false): array
    {
        $query = static::query()->where('group', $group);
        if ($publicOnly) {
            $query->where('is_public', true);
        }
        $settings = $query->get();
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->getValue();
        }
        return $result;
    }

    public static function getAllPublic(): array
    {
        $settings = static::query()->where('is_public', true)->get();
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->getValue();
        }
        return $result;
    }
}