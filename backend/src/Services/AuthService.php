<?php
/**
 * Authentication Service
 */

class AuthService
{
    public static function attempt(string $email, string $password, bool $remember = false): ?User
    {
        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            self::logFailedAttempt($email);
            return null;
        }

        if ($user->isLocked()) {
            throw new Exception('Account is temporarily locked due to too many failed attempts. Please try again later.');
        }

        if (!$user->verifyPassword($password)) {
            $user->recordFailedLogin();
            self::logFailedAttempt($email);
            return null;
        }

        // Check if email verification is required
        if (Config::get('security.require_email_verification', true) && !$user->email_verified_at) {
            throw new Exception('Please verify your email address before logging in.');
        }

        // Rehash password if needed
        if ($user->needsPasswordRehash()) {
            $user->password_hash = User::hashPassword($password);
            $user->save();
        }

        // Login successful
        $user->recordLogin($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        self::clearFailedAttempts($email);

        if ($remember) {
            $token = $user->generateRememberToken();
            self::setRememberCookie($token);
        }

        self::setUserSession($user);
        
        ActivityLog::log('login', "User {$user->email} logged in", User::class, $user->id);
        
        return $user;
    }

    public static function register(array $data): User
    {
        $user = User::create([
            'uuid' => self::generateUuid(),
            'role_id' => 4, // customer role
            'email' => $data['email'],
            'password_hash' => User::hashPassword($data['password']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
        ]);

        // Create customer profile
        CustomerProfile::create([
            'user_id' => $user->id,
            'preferred_currency' => Config::get('currency.default', 'USD'),
        ]);

        // Send verification email if required
        if (Config::get('security.require_email_verification', true)) {
            $token = $user->generateEmailVerificationToken();
            self::sendVerificationEmail($user, $token);
        }

        // Create cart for user
        Cart::getOrCreateForUser($user->id);

        ActivityLog::log('register', "New user registered: {$user->email}", User::class, $user->id);
        
        return $user;
    }

    public static function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($userId) {
            ActivityLog::log('logout', "User ID {$userId} logged out");
        }

        // Clear session
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        
        session_destroy();
        
        // Clear remember cookie
        self::clearRememberCookie();
    }

    public static function user(): ?User
    {
        if (!isset($_SESSION['user_id'])) {
            // Check remember cookie
            $user = self::checkRememberCookie();
            if ($user) {
                self::setUserSession($user);
                return $user;
            }
            return null;
        }

        $user = User::find($_SESSION['user_id']);
        
        if (!$user || !$user->is_active) {
            self::logout();
            return null;
        }

        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function id(): ?int
    {
        return self::user()?->id;
    }

    public static function setUserSession(User $user): void
    {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_role'] = $user->role_id;
        $_SESSION['user_name'] = $user->getFullName();
        $_SESSION['user_email'] = $user->email;
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
    }

    public static function sendVerificationEmail(User $user, string $token): void
    {
        $url = Config::get('app.url') . "/verify-email?token={$token}&email=" . urlencode($user->email);
        
        EmailQueue::queue(
            $user->email,
            'Verify your email address',
            self::getVerificationEmailTemplate($user, $url),
            $user->getFullName()
        );
    }

    public static function verifyEmail(string $token, string $email): bool
    {
        $user = User::query()->where('email', $email)->first();
        
        if (!$user) {
            return false;
        }

        return $user->verifyEmail($token);
    }

    public static function sendPasswordResetEmail(string $email): bool
    {
        $user = User::query()->where('email', $email)->first();
        
        if (!$user) {
            // Don't reveal if email exists
            return true;
        }

        $token = $user->generatePasswordResetToken();
        $url = Config::get('app.url') . "/reset-password?token={$token}&email=" . urlencode($email);
        
        EmailQueue::queue(
            $user->email,
            'Password Reset Request',
            self::getPasswordResetEmailTemplate($user, $url),
            $user->getFullName()
        );

        return true;
    }

    public static function resetPassword(string $token, string $email, string $password): bool
    {
        $user = User::query()->where('email', $email)->first();
        
        if (!$user) {
            return false;
        }

        $minLength = Config::get('security.password_min_length', 8);
        if (strlen($password) < $minLength) {
            throw new Exception("Password must be at least {$minLength} characters long.");
        }

        return $user->resetPassword($token, $password);
    }

    public static function updateProfile(User $user, array $data): bool
    {
        $allowed = ['first_name', 'last_name', 'phone', 'avatar'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        
        if (empty($updateData)) {
            return true;
        }

        $user->fill($updateData);
        return $user->save();
    }

    public static function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (!$user->verifyPassword($currentPassword)) {
            throw new Exception('Current password is incorrect.');
        }

        $minLength = Config::get('security.password_min_length', 8);
        if (strlen($newPassword) < $minLength) {
            throw new Exception("Password must be at least {$minLength} characters long.");
        }

        $user->password_hash = User::hashPassword($newPassword);
        $user->save();

        ActivityLog::log('password_change', "User {$user->email} changed password", User::class, $user->id);
        
        return true;
    }

    // Remember me
    private static function setRememberCookie(string $token): void
    {
        $lifetime = Config::get('session.lifetime', 7200) * 30; // 30x session lifetime
        setcookie('remember_token', $token, time() + $lifetime, '/', '', true, true);
    }

    private static function clearRememberCookie(): void
    {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    private static function checkRememberCookie(): ?User
    {
        $token = $_COOKIE['remember_token'] ?? null;
        if (!$token) return null;

        $hashedToken = hash('sha256', $token);
        $user = User::query()->where('remember_token', $hashedToken)->first();
        
        if (!$user || !$user->is_active) {
            self::clearRememberCookie();
            return null;
        }

        return $user;
    }

    // Rate limiting for failed attempts
    private static function logFailedAttempt(string $email): void
    {
        $key = "failed_login:{$email}";
        $attempts = (int)(Cache::get($key) ?? 0) + 1;
        Cache::set($key, $attempts, 3600); // 1 hour
    }

    private static function clearFailedAttempts(string $email): void
    {
        Cache::delete("failed_login:{$email}");
    }

    private static function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private static function getVerificationEmailTemplate(User $user, string $url): string
    {
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1'>
            </head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #071030; color: #fff; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>Genmashy Distributors</h1>
                </div>
                <div style='background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;'>
                    <h2 style='color: #071030; margin-top: 0;'>Verify Your Email Address</h2>
                    <p>Hello {$user->getFullName()},</p>
                    <p>Thank you for registering with Genmashy Distributors. Please click the button below to verify your email address:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$url}' style='background: #4b7bf5; color: #fff; padding: 14px 28px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>Verify Email</a>
                    </div>
                    <p>Or copy this link to your browser:</p>
                    <p style='word-break: break-all; color: #666; font-size: 14px;'>{$url}</p>
                    <p>This link will expire in 24 hours.</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px;'>If you didn't create an account, please ignore this email.</p>
                </div>
            </body>
            </html>
        ";
    }

    private static function getPasswordResetEmailTemplate(User $user, string $url): string
    {
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1'>
            </head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #071030; color: #fff; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>Genmashy Distributors</h1>
                </div>
                <div style='background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;'>
                    <h2 style='color: #071030; margin-top: 0;'>Password Reset Request</h2>
                    <p>Hello {$user->getFullName()},</p>
                    <p>You requested to reset your password. Click the button below to create a new password:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$url}' style='background: #4b7bf5; color: #fff; padding: 14px 28px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>Reset Password</a>
                    </div>
                    <p>Or copy this link to your browser:</p>
                    <p style='word-break: break-all; color: #666; font-size: 14px;'>{$url}</p>
                    <p>This link will expire in 1 hour.</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px;'>If you didn't request this, please ignore this email or contact support.</p>
                </div>
            </body>
            </html>
        ";
    }
}