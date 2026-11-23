<?php
/**
 * Role Model
 */

class Role extends Model
{
    protected static string $table = 'roles';
    protected static string $primaryKey = 'id';
    protected static array $fillable = ['name', 'display_name', 'description', 'permissions', 'is_system'];
    protected static array $casts = [
        'permissions' => 'json',
        'is_system' => 'boolean',
    ];

    public function getPermissions(): array
    {
        $perms = $this->permissions ?? [];
        return is_array($perms) ? $perms : [];
    }

    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        return in_array('*', $permissions) || in_array($permission, $permissions);
    }

    public static function getDefaultRoles(): array
    {
        return [
            'super_admin' => 'Super Administrator',
            'admin' => 'Administrator',
            'sales' => 'Sales Staff',
            'customer' => 'Customer',
        ];
    }
}