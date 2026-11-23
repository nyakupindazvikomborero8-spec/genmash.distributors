<?php
/**
 * Auth Controller (API)
 */

class AuthController extends Controller
{
    public function register(): void
    {
        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'email' => 'required|email|max:255',
            'password' => 'required|min:8|confirmed',
            'phone' => 'max:30',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        // Check if email already exists
        if (User::query()->where('email', $input['email'])->exists()) {
            $this->error('Email already registered', 422, ['email' => ['This email is already registered.']]);
        }

        try {
            $user = AuthService::register([
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'phone' => $input['phone'] ?? null,
            ]);

            $this->success([
                'user' => [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->getFullName(),
                    'email' => $user->email,
                    'role' => $user->role_id,
                ],
                'message' => Config::get('security.require_email_verification', true) 
                    ? 'Registration successful. Please check your email to verify your account.'
                    : 'Registration successful.',
            ], 'Registration successful', 201);
            
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function login(): void
    {
        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        try {
            $remember = filter_var($input['remember'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $user = AuthService::attempt($input['email'], $input['password'], $remember);
            
            if (!$user) {
                $this->error('Invalid credentials', 401, ['email' => ['The provided credentials are incorrect.']]);
            }

            $this->success([
                'user' => [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->getFullName(),
                    'email' => $user->email,
                    'role' => $user->role_id,
                    'avatar' => $user->avatar,
                    'email_verified' => (bool)$user->email_verified_at,
                ],
                'token' => session_id(), // Using session ID as token for simplicity
            ], 'Login successful');
            
        } catch (Exception $e) {
            $this->error($e->getMessage(), 401);
        }
    }

    public function logout(): void
    {
        AuthService::logout();
        $this->success(null, 'Logged out successfully');
    }

    public function forgotPassword(): void
    {
        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'email' => 'required|email',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        try {
            AuthService::sendPasswordResetEmail($input['email']);
            $this->success(null, 'If the email exists, a password reset link has been sent.');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function resetPassword(): void
    {
        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        try {
            $result = AuthService::resetPassword($input['token'], $input['email'], $input['password']);
            
            if (!$result) {
                $this->error('Invalid or expired reset token', 422, ['token' => ['The reset token is invalid or has expired.']]);
            }

            $this->success(null, 'Password has been reset successfully.');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function verifyEmail(): void
    {
        $token = $_GET['token'] ?? '';
        $email = $_GET['email'] ?? '';
        
        if (!$token || !$email) {
            $this->error('Invalid verification link', 400);
        }

        try {
            $result = AuthService::verifyEmail($token, $email);
            
            if (!$result) {
                $this->error('Invalid or expired verification token', 422);
            }

            $this->success(null, 'Email verified successfully. You can now log in.');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function resendVerification(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        if ($user->email_verified_at) {
            $this->error('Email is already verified', 422);
        }

        try {
            $token = $user->generateEmailVerificationToken();
            AuthService::sendVerificationEmail($user, $token);
            $this->success(null, 'Verification email sent.');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}