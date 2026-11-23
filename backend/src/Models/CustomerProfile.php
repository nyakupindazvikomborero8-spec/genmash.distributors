<?php
/**
 * CustomerProfile Model
 */

class CustomerProfile extends Model
{
    protected static string $table = 'customer_profiles';
    protected static string $primaryKey = 'id';
    protected static array $fillable = ['user_id', 'company_name', 'tax_number', 'preferred_currency', 'marketing_consent', 'notes'];
    protected static array $casts = [
        'marketing_consent' => 'boolean',
    ];

    public function user(): User
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}