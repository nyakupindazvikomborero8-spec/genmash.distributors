<?php
/**
 * User Model
 */

class User extends Model
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'uuid', 'role_id', 'email', 'password_hash', 'first_name', 'last_name',
        'phone', 'avatar', 'is_active', 'last_login_at', 'last_login_ip',
        'failed_login_attempts', 'locked_until', 'password_reset_token',
        'password_reset_expires', 'email_verification_token', 'email_verification_expires',
        'remember_token', 'email_verified_at', 'phone_verified_at',
    ];
    protected static array $hidden = ['password_hash', 'password_reset_token', 'email_verification_token', 'remember_token'];
    protected static array $casts = [
        'is_active' => 'boolean',
        'failed_login_attempts' => 'integer',
        'role_id' => 'integer',
    ];
    protected static array $dates = ['created_at', 'updated_at', 'deleted_at', 'email_verified_at', 'phone_verified_at', 'last_login_at', 'locked_until', 'password_reset_expires', 'email_verification_expires'];

    // Relationships
    public function role(): Role
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function profile(): ?CustomerProfile
    {
        return $this->hasOne(CustomerProfile::class, 'user_id');
    }

    public function addresses(): array
    {
        return $this->hasMany(Address::class, 'user_id');
    }

    public function orders(): array
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function quotations(): array
    {
        return $this->hasMany(Quotation::class, 'user_id');
    }

    public function enquiries(): array
    {
        return $this->hasMany(Enquiry::class, 'user_id');
    }

    public function cart(): ?Cart
    {
        $carts = $this->hasMany(Cart::class, 'user_id');
        return $carts[0] ?? null;
    }

    // Scopes
    public static function active(): Builder
    {
        return static::query()->where('is_active', true);
    }

    public static function customers(): Builder
    {
        return static::query()->where('role_id', 4); // customer role
    }

    public static function staff(): Builder
    {
        return static::query()->whereIn('role_id', [1, 2, 3]); // super_admin, admin, sales
    }

    // Accessors
    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInitials(): string
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }

    public function isSuperAdmin(): bool
    {
        return $this->role_id === 1;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role_id, [1, 2]);
    }

    public function isSales(): bool
    {
        return $this->role_id === 3;
    }

    public function isCustomer(): bool
    {
        return $this->role_id === 4;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        $role = $this->role;
        if (!$role) {
            return false;
        }
        
        $permissions = $role->getPermissions();
        return in_array('*', $permissions) || in_array($permission, $permissions);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    // Password handling
    public static function hashPassword(string $password): string
    {
        $cost = Config::get('security.bcrypt_cost', 10);
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    public function needsPasswordRehash(): bool
    {
        $cost = Config::get('security.bcrypt_cost', 10);
        return password_needs_rehash($this->password_hash, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    // Login tracking
    public function recordLogin(string $ip): void
    {
        $this->last_login_at = date('Y-m-d H:i:s');
        $this->last_login_ip = $ip;
        $this->failed_login_attempts = 0;
        $this->locked_until = null;
        $this->save();
    }

    public function recordFailedLogin(): void
    {
        $this->failed_login_attempts++;
        $maxAttempts = Config::get('security.max_login_attempts', 5);
        
        if ($this->failed_login_attempts >= $maxAttempts) {
            $lockoutDuration = Config::get('security.lockout_duration', 900);
            $this->locked_until = date('Y-m-d H:i:s', time() + $lockoutDuration);
        }
        
        $this->save();
    }

    public function isLocked(): bool
    {
        if (!$this->locked_until) {
            return false;
        }
        return strtotime($this->locked_until) > time();
    }

    // Email verification
    public function generateEmailVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->email_verification_token = $token;
        $this->email_verification_expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours
        $this->save();
        return $token;
    }

    public function verifyEmail(string $token): bool
    {
        if ($this->email_verification_token === $token 
            && $this->email_verification_expires 
            && strtotime($this->email_verification_expires) > time()) {
            $this->email_verified_at = date('Y-m-d H:i:s');
            $this->email_verification_token = null;
            $this->email_verification_expires = null;
            $this->save();
            return true;
        }
        return false;
    }

    // Password reset
    public function generatePasswordResetToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->password_reset_token = $token;
        $this->password_reset_expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        $this->save();
        return $token;
    }

    public function resetPassword(string $token, string $password): bool
    {
        if ($this->password_reset_token === $token 
            && $this->password_reset_expires 
            && strtotime($this->password_reset_expires) > time()) {
            $this->password_hash = self::hashPassword($password);
            $this->password_reset_token = null;
            $this->password_reset_expires = null;
            $this->failed_login_attempts = 0;
            $this->locked_until = null;
            $this->save();
            return true;
        }
        return false;
    }

    // Remember token
    public function generateRememberToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->remember_token = hash('sha256', $token);
        $this->save();
        return $token;
    }

    public function verifyRememberToken(string $token): bool
    {
        return hash_equals($this->remember_token ?? '', hash('sha256', $token));
    }

    public function clearRememberToken(): void
    {
        $this->remember_token = null;
        $this->save();
    }

    // Helpers for relationships (simplified)
    protected function belongsTo(string $class, string $foreignKey): ?Model
    {
        $key = $this->{$foreignKey};
        if (!$key) return null;
        return $class::find($key);
    }

    protected function hasOne(string $class, string $foreignKey): ?Model
    {
        return $class::query()->where($foreignKey, $this->getKey())->first();
    }

    protected function hasMany(string $class, string $foreignKey): array
    {
        return $class::query()->where($foreignKey, $this->getKey())->get();
    }
}