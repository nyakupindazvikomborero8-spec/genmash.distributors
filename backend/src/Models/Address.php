<?php
/**
 * Address Model
 */

class Address extends Model
{
    protected static string $table = 'addresses';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'user_id', 'type', 'is_default', 'first_name', 'last_name', 'company',
        'address_line_1', 'address_line_2', 'city', 'state_province',
        'postal_code', 'country', 'phone', 'instructions',
    ];
    protected static array $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): User
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getFullAddress(): string
    {
        $parts = [
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state_province,
            $this->postal_code,
            $this->country,
        ];
        return implode(', ', array_filter($parts));
    }

    public function setAsDefault(): void
    {
        // Remove default from other addresses of same type
        Address::query()
            ->where('user_id', $this->user_id)
            ->where('type', $this->type)
            ->where('is_default', true)
            ->update(['is_default' => false]);
        
        $this->is_default = true;
        $this->save();
    }
}