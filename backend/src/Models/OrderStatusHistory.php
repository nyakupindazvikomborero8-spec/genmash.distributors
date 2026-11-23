<?php
/**
 * OrderStatusHistory Model
 */

class OrderStatusHistory extends Model
{
    protected static string $table = 'order_status_history';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'order_id', 'from_status', 'to_status', 'from_payment_status',
        'to_payment_status', 'notes', 'user_id',
    ];
    protected static array $casts = [
        'order_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function order(): ?Order
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user(): ?User
    {
        if (!$this->user_id) return null;
        return User::find($this->user_id);
    }
}