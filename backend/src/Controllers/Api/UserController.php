<?php
/**
 * User Controller (API)
 */

class UserController extends Controller
{
    public function profile(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $profile = $user->profile;
        
        $this->success([
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->getFullName(),
                'email' => $user->email,
                'email_verified' => (bool)$user->email_verified_at,
                'phone' => $user->phone,
                'phone_verified' => (bool)$user->phone_verified_at,
                'avatar' => $user->avatar,
                'role' => $user->role_id,
                'role_name' => $this->getRoleName($user->role_id),
                'preferred_currency' => $profile?->preferred_currency ?? Config::get('currency.default', 'USD'),
                'company_name' => $profile?->company_name,
                'tax_number' => $profile?->tax_number,
                'created_at' => $user->created_at,
                'last_login_at' => $user->last_login_at,
            ],
        ]);
    }

    public function updateProfile(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'phone' => 'max:30',
            'avatar' => 'string',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        $data = [
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'phone' => $input['phone'] ?? null,
            'avatar' => $input['avatar'] ?? null,
        ];

        if (!AuthService::updateProfile($user, $data)) {
            $this->error('Failed to update profile', 500);
        }

        $user->refresh();
        
        $this->success([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->getFullName(),
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
            ],
        ], 'Profile updated successfully');
    }

    public function changePassword(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        try {
            AuthService::changePassword($user, $input['current_password'], $input['password']);
            $this->success(null, 'Password changed successfully');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 422, ['current_password' => [$e->getMessage()]]);
        }
    }

    public function addresses(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $addresses = $user->addresses();
        
        $this->success([
            'addresses' => array_map(fn($a) => $this->formatAddress($a), $addresses),
        ]);
    }

    public function storeAddress(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'type' => 'required|in:billing,shipping,both',
            'is_default' => 'boolean',
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'company' => 'max:255',
            'address_line_1' => 'required|max:255',
            'address_line_2' => 'max:255',
            'city' => 'required|max:100',
            'state_province' => 'max:100',
            'postal_code' => 'max:20',
            'country' => 'required|max:100',
            'phone' => 'max:30',
            'instructions' => 'string',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        $address = Address::create(array_merge($input, ['user_id' => $user->id]));
        
        if ($input['is_default'] ?? false) {
            $address->setAsDefault();
        }

        $this->success([
            'address' => $this->formatAddress($address),
        ], 'Address added', 201);
    }

    public function updateAddress(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $address = Address::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            $this->notFound('Address not found');
        }

        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'type' => 'in:billing,shipping,both',
            'is_default' => 'boolean',
            'first_name' => 'max:100',
            'last_name' => 'max:100',
            'company' => 'max:255',
            'address_line_1' => 'max:255',
            'address_line_2' => 'max:255',
            'city' => 'max:100',
            'state_province' => 'max:100',
            'postal_code' => 'max:20',
            'country' => 'max:100',
            'phone' => 'max:30',
            'instructions' => 'string',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        $address->fill($input);
        
        if ($input['is_default'] ?? false) {
            $address->setAsDefault();
        }

        $address->save();

        $this->success([
            'address' => $this->formatAddress($address),
        ], 'Address updated');
    }

    public function deleteAddress(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $address = Address::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            $this->notFound('Address not found');
        }

        $address->delete();
        
        $this->success(null, 'Address deleted');
    }

    public function setDefaultAddress(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $address = Address::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            $this->notFound('Address not found');
        }

        $address->setAsDefault();
        
        $this->success([
            'address' => $this->formatAddress($address),
        ], 'Default address updated');
    }

    private function formatAddress(Address $address): array
    {
        return [
            'id' => $address->id,
            'type' => $address->type,
            'is_default' => $address->is_default,
            'first_name' => $address->first_name,
            'last_name' => $address->last_name,
            'company' => $address->company,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'city' => $address->city,
            'state_province' => $address->state_province,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'phone' => $address->phone,
            'instructions' => $address->instructions,
            'full_address' => $address->getFullAddress(),
        ];
    }

    private function getRoleName(int $roleId): string
    {
        return match ($roleId) {
            1 => 'super_admin',
            2 => 'admin',
            3 => 'sales',
            4 => 'customer',
            default => 'unknown',
        };
    }
}