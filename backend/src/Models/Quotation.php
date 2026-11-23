<?php
/**
 * Quotation Model
 */

class Quotation extends Model
{
    protected static string $table = 'quotations';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'quotation_number', 'user_id', 'customer_name', 'customer_email', 'customer_phone',
        'customer_company', 'customer_address', 'status', 'currency',
        'subtotal_usd', 'subtotal_zwg', 'discount_usd', 'discount_zwg',
        'tax_usd', 'tax_zwg', 'delivery_usd', 'delivery_zwg',
        'total_usd', 'total_zwg', 'validity_days', 'notes', 'terms_conditions',
        'internal_notes', 'prepared_by', 'approved_by', 'sent_at', 'accepted_at',
        'rejected_at', 'rejected_reason', 'expires_at', 'pdf_path',
    ];
    protected static array $casts = [
        'user_id' => 'integer',
        'prepared_by' => 'integer',
        'approved_by' => 'integer',
        'subtotal_usd' => 'float',
        'subtotal_zwg' => 'float',
        'discount_usd' => 'float',
        'discount_zwg' => 'float',
        'tax_usd' => 'float',
        'tax_zwg' => 'float',
        'delivery_usd' => 'float',
        'delivery_zwg' => 'float',
        'total_usd' => 'float',
        'total_zwg' => 'float',
        'validity_days' => 'integer',
    ];
    protected static array $dates = ['created_at', 'updated_at', 'sent_at', 'accepted_at', 'rejected_at', 'expires_at'];

    public static function generateQuotationNumber(): string
    {
        $prefix = Config::get('quotation.prefix', 'GMD-Q');
        $date = date('Ymd');
        $seq = static::query()->where('created_at', '>=', date('Y-m-d 00:00:00'))->count() + 1;
        return "{$prefix}-{$date}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function user(): ?User
    {
        if (!$this->user_id) return null;
        return User::find($this->user_id);
    }

    public function items(): array
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }

    public function preparedBy(): ?User
    {
        if (!$this->prepared_by) return null;
        return User::find($this->prepared_by);
    }

    public function approvedBy(): ?User
    {
        if (!$this->approved_by) return null;
        return User::find($this->approved_by);
    }

    public function statusHistory(): array
    {
        return $this->hasMany(QuotationStatusHistory::class, 'quotation_id');
    }

    public function getTotal(string $currency = null): float
    {
        $currency = $currency ?? $this->currency;
        if ($currency === 'BOTH') {
            return (float)$this->total_usd + (float)$this->total_zwg;
        }
        return $currency === 'ZWG' ? (float)$this->total_zwg : (float)$this->total_usd;
    }

    public function getFormattedTotal(string $currency = null): string
    {
        $currency = $currency ?? $this->currency;
        if ($currency === 'BOTH') {
            return '$' . number_format((float)$this->total_usd, 2, '.', ',') . ' / ZWG$' . number_format((float)$this->total_zwg, 2, '.', ',');
        }
        $total = $this->getTotal($currency);
        $symbol = $currency === 'ZWG' ? 'ZWG$' : '$';
        return $symbol . number_format($total, 2, '.', ',');
    }

    public function calculateTotals(): void
    {
        $subtotalUsd = 0;
        $subtotalZwg = 0;
        $discountUsd = 0;
        $discountZwg = 0;
        $taxUsd = 0;
        $taxZwg = 0;

        foreach ($this->items() as $item) {
            $subtotalUsd += $item->total_usd + $item->discount_usd;
            $subtotalZwg += $item->total_zwg + $item->discount_zwg;
            $discountUsd += $item->discount_usd;
            $discountZwg += $item->discount_zwg;
            $taxUsd += $item->tax_usd;
            $taxZwg += $item->tax_zwg;
        }

        $this->subtotal_usd = $subtotalUsd;
        $this->subtotal_zwg = $subtotalZwg;
        $this->discount_usd = $discountUsd;
        $this->discount_zwg = $discountZwg;
        $this->tax_usd = $taxUsd;
        $this->tax_zwg = $taxZwg;
        $this->total_usd = $subtotalUsd - $discountUsd + $taxUsd + (float)$this->delivery_usd;
        $this->total_zwg = $subtotalZwg - $discountZwg + $taxZwg + (float)$this->delivery_zwg;
    }

    public function updateStatus(string $newStatus, string $notes = '', int $userId = null): bool
    {
        $oldStatus = $this->status;
        $this->status = $newStatus;

        $now = date('Y-m-d H:i:s');
        switch ($newStatus) {
            case 'sent':
                $this->sent_at = $now;
                break;
            case 'accepted':
                $this->accepted_at = $now;
                break;
            case 'rejected':
                $this->rejected_at = $now;
                $this->rejected_reason = $notes;
                break;
            case 'expired':
                $this->expires_at = $now;
                break;
        }

        $saved = $this->save();

        if ($saved) {
            QuotationStatusHistory::create([
                'quotation_id' => $this->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'notes' => $notes,
                'user_id' => $userId,
            ]);
        }

        return $saved;
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return strtotime($this->expires_at) < time();
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && in_array($this->status, ['sent', 'accepted']);
    }

    public function canBeAccepted(): bool
    {
        return $this->status === 'sent' && !$this->isExpired();
    }

    public function accept(): bool
    {
        return $this->updateStatus('accepted', 'Quotation accepted by customer');
    }

    public function reject(string $reason = ''): bool
    {
        return $this->updateStatus('rejected', $reason ?: 'Quotation rejected by customer');
    }

    public static function getStatuses(): array
    {
        return Config::get('enquiries.statuses', [
            'pending' => 'Pending',
            'under_review' => 'Under Review',
            'prepared' => 'Prepared',
            'sent' => 'Sent',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
        ]);
    }
}