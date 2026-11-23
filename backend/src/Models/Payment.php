<?php
/**
 * Payment Model
 */

class Payment extends Model
{
    protected static string $table = 'payments';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'payment_number', 'order_id', 'user_id', 'method', 'provider',
        'provider_transaction_id', 'currency', 'amount', 'fee', 'status',
        'reference', 'notes', 'paid_at', 'failed_at', 'refunded_at',
        'refund_reason', 'metadata',
    ];
    protected static array $casts = [
        'order_id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'float',
        'fee' => 'float',
        'metadata' => 'json',
    ];
    protected static array $dates = ['created_at', 'updated_at', 'paid_at', 'failed_at', 'refunded_at'];

    public static function generatePaymentNumber(): string
    {
        $prefix = Config::get('payments.prefix', 'GMD-PAY');
        $date = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        return "{$prefix}-{$date}-{$random}";
    }

    public function order(): ?Order
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user(): ?User
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getNetAmount(): float
    {
        return (float)$this->amount - (float)$this->fee;
    }

    public function markAsPaid(): bool
    {
        $this->status = 'completed';
        $this->paid_at = date('Y-m-d H:i:s');
        $saved = $this->save();

        if ($saved && $this->order) {
            // Update order payment status
            $totalPaid = array_sum(array_map(
                fn($p) => $p->getNetAmount(),
                array_filter($this->order->payments(), fn($p) => $p->status === 'completed')
            ));

            $orderTotal = $this->order->getTotal($this->currency);
            
            if ($totalPaid >= $orderTotal) {
                $this->order->updatePaymentStatus('paid');
            } elseif ($totalPaid > 0) {
                $this->order->updatePaymentStatus('partial');
            }
        }

        return $saved;
    }

    public function markAsFailed(string $reason = ''): bool
    {
        $this->status = 'failed';
        $this->failed_at = date('Y-m-d H:i:s');
        $this->notes = $reason;
        return $this->save();
    }

    public function refund(string $reason = ''): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        $this->status = 'refunded';
        $this->refunded_at = date('Y-m-d H:i:s');
        $this->refund_reason = $reason;
        $saved = $this->save();

        if ($saved && $this->order) {
            $remainingPaid = array_sum(array_map(
                fn($p) => $p->getNetAmount(),
                array_filter($this->order->payments(), fn($p) => $p->status === 'completed')
            ));

            if ($remainingPaid <= 0) {
                $this->order->updatePaymentStatus('refunded');
            } else {
                $this->order->updatePaymentStatus('partial');
            }
        }

        return $saved;
    }

    public static function getMethods(): array
    {
        return [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'mobile_money' => 'Mobile Money',
            'card' => 'Card',
            'ecocash' => 'EcoCash',
            'one_money' => 'OneMoney',
            'paynow' => 'Paynow',
            'other' => 'Other',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            'cancelled' => 'Cancelled',
        ];
    }
}