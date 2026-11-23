<?php
/**
 * Order Model
 */

class Order extends Model
{
    protected static string $table = 'orders';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'order_number', 'user_id', 'status', 'payment_status', 'currency',
        'subtotal_usd', 'subtotal_zwg', 'tax_usd', 'tax_zwg',
        'shipping_usd', 'shipping_zwg', 'discount_usd', 'discount_zwg',
        'total_usd', 'total_zwg', 'billing_address', 'shipping_address',
        'shipping_method', 'shipping_tracking', 'notes', 'customer_notes',
        'confirmed_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'cancelled_reason',
    ];
    protected static array $casts = [
        'user_id' => 'integer',
        'subtotal_usd' => 'float',
        'subtotal_zwg' => 'float',
        'tax_usd' => 'float',
        'tax_zwg' => 'float',
        'shipping_usd' => 'float',
        'shipping_zwg' => 'float',
        'discount_usd' => 'float',
        'discount_zwg' => 'float',
        'total_usd' => 'float',
        'total_zwg' => 'float',
        'billing_address' => 'json',
        'shipping_address' => 'json',
    ];
    protected static array $dates = ['created_at', 'updated_at', 'confirmed_at', 'shipped_at', 'delivered_at', 'cancelled_at'];

    public static function generateOrderNumber(): string
    {
        $prefix = Config::get('orders.prefix', 'GMD-ORD');
        $date = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        return "{$prefix}-{$date}-{$random}";
    }

    public function user(): ?User
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): array
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments(): array
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function statusHistory(): array
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id');
    }

    public function getTotal(string $currency = null): float
    {
        $currency = $currency ?? $this->currency;
        return $currency === 'ZWG' ? (float)$this->total_zwg : (float)$this->total_usd;
    }

    public function getFormattedTotal(string $currency = null): string
    {
        $currency = $currency ?? $this->currency;
        $total = $this->getTotal($currency);
        $symbol = $currency === 'ZWG' ? 'ZWG$' : '$';
        return $symbol . number_format($total, 2, '.', ',');
    }

    public function getSubtotal(string $currency = null): float
    {
        $currency = $currency ?? $this->currency;
        return $currency === 'ZWG' ? (float)$this->subtotal_zwg : (float)$this->subtotal_usd;
    }

    public function getTax(string $currency = null): float
    {
        $currency = $currency ?? $this->currency;
        return $currency === 'ZWG' ? (float)$this->tax_zwg : (float)$this->tax_usd;
    }

    public function getShipping(string $currency = null): float
    {
        $currency = $currency ?? $this->currency;
        return $currency === 'ZWG' ? (float)$this->shipping_zwg : (float)$this->shipping_usd;
    }

    public function getDiscount(string $currency = null): float
    {
        $currency = $currency ?? $this->currency;
        return $currency === 'ZWG' ? (float)$this->discount_zwg : (float)$this->discount_usd;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public function canBeRefunded(): bool
    {
        return in_array($this->payment_status, ['paid', 'partial']) 
            && in_array($this->status, ['delivered', 'shipped']);
    }

    public function updateStatus(string $newStatus, string $notes = '', int $userId = null): bool
    {
        $oldStatus = $this->status;
        $oldPaymentStatus = $this->payment_status;

        $this->status = $newStatus;

        // Set timestamps based on status
        $now = date('Y-m-d H:i:s');
        switch ($newStatus) {
            case 'confirmed':
                $this->confirmed_at = $now;
                break;
            case 'shipped':
                $this->shipped_at = $now;
                break;
            case 'delivered':
                $this->delivered_at = $now;
                break;
            case 'cancelled':
                $this->cancelled_at = $now;
                $this->cancelled_reason = $notes;
                break;
        }

        $saved = $this->save();

        if ($saved) {
            OrderStatusHistory::create([
                'order_id' => $this->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'from_payment_status' => $oldPaymentStatus,
                'to_payment_status' => $this->payment_status,
                'notes' => $notes,
                'user_id' => $userId,
            ]);
        }

        return $saved;
    }

    public function updatePaymentStatus(string $newStatus): bool
    {
        $oldStatus = $this->payment_status;
        $this->payment_status = $newStatus;
        $saved = $this->save();

        if ($saved) {
            OrderStatusHistory::create([
                'order_id' => $this->id,
                'from_status' => $this->status,
                'to_status' => $this->status,
                'from_payment_status' => $oldStatus,
                'to_payment_status' => $newStatus,
                'notes' => "Payment status changed to {$newStatus}",
            ]);
        }

        return $saved;
    }

    public static function getStatuses(): array
    {
        return Config::get('orders.statuses', [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'failed' => 'Failed',
        ]);
    }

    public static function getPaymentStatuses(): array
    {
        return Config::get('orders.payment_statuses', [
            'pending' => 'Pending',
            'paid' => 'Paid',
            'partial' => 'Partial',
            'refunded' => 'Refunded',
            'failed' => 'Failed',
        ]);
    }
}