<?php
/**
 * QuotationStatusHistory Model
 */

class QuotationStatusHistory extends Model
{
    protected static string $table = 'quotation_status_history';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'quotation_id', 'from_status', 'to_status', 'notes', 'user_id',
    ];
    protected static array $casts = [
        'quotation_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function quotation(): ?Quotation
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function user(): ?User
    {
        if (!$this->user_id) return null;
        return User::find($this->user_id);
    }
}