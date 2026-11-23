<?php
/**
 * Enquiry Model
 */

class Enquiry extends Model
{
    protected static string $table = 'enquiries';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'enquiry_number', 'user_id', 'name', 'email', 'phone', 'company',
        'subject', 'message', 'type', 'priority', 'status', 'assigned_to',
        'product_id', 'quotation_id', 'order_id', 'source', 'ip_address', 'user_agent',
        'responded_at', 'resolved_at', 'closed_at',
    ];
    protected static array $casts = [
        'user_id' => 'integer',
        'assigned_to' => 'integer',
        'product_id' => 'integer',
        'quotation_id' => 'integer',
        'order_id' => 'integer',
    ];
    protected static array $dates = ['created_at', 'updated_at', 'responded_at', 'resolved_at', 'closed_at'];

    public static function generateEnquiryNumber(): string
    {
        $prefix = Config::get('enquiries.prefix', 'GMD-ENQ');
        $date = date('Ymd');
        $seq = static::query()->where('created_at', '>=', date('Y-m-d 00:00:00'))->count() + 1;
        return "{$prefix}-{$date}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function user(): ?User
    {
        if (!$this->user_id) return null;
        return User::find($this->user_id);
    }

    public function assignedTo(): ?User
    {
        if (!$this->assigned_to) return null;
        return User::find($this->assigned_to);
    }

    public function product(): ?Product
    {
        if (!$this->product_id) return null;
        return Product::find($this->product_id);
    }

    public function quotation(): ?Quotation
    {
        if (!$this->quotation_id) return null;
        return Quotation::find($this->quotation_id);
    }

    public function order(): ?Order
    {
        if (!$this->order_id) return null;
        return Order::find($this->order_id);
    }

    public function responses(): array
    {
        return $this->hasMany(EnquiryResponse::class, 'enquiry_id');
    }

    public function statusHistory(): array
    {
        return $this->hasMany(EnquiryStatusHistory::class, 'enquiry_id');
    }

    public function getLatestResponse(): ?EnquiryResponse
    {
        return EnquiryResponse::query()
            ->where('enquiry_id', $this->id)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function updateStatus(string $newStatus, string $notes = '', int $userId = null): bool
    {
        $oldStatus = $this->status;
        $this->status = $newStatus;

        $now = date('Y-m-d H:i:s');
        switch ($newStatus) {
            case 'responded':
                $this->responded_at = $now;
                break;
            case 'resolved':
                $this->resolved_at = $now;
                break;
            case 'closed':
                $this->closed_at = $now;
                break;
        }

        $saved = $this->save();

        if ($saved) {
            EnquiryStatusHistory::create([
                'enquiry_id' => $this->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'notes' => $notes,
                'user_id' => $userId,
            ]);
        }

        return $saved;
    }

    public function assignTo(int $userId): bool
    {
        $this->assigned_to = $userId;
        return $this->save();
    }

    public static function getTypes(): array
    {
        return Config::get('enquiries.types', [
            'general' => 'General',
            'product' => 'Product Enquiry',
            'quotation' => 'Quotation Request',
            'support' => 'Support',
            'after_sales' => 'After Sales',
            'complaint' => 'Complaint',
        ]);
    }

    public static function getPriorities(): array
    {
        return Config::get('enquiries.priorities', [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
        ]);
    }

    public static function getStatuses(): array
    {
        return Config::get('enquiries.statuses', [
            'new' => 'New',
            'in_progress' => 'In Progress',
            'responded' => 'Responded',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ]);
    }
}