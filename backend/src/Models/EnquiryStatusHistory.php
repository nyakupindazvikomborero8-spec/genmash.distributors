<?php
/**
 * EnquiryStatusHistory Model
 */

class EnquiryStatusHistory extends Model
{
    protected static string $table = 'enquiry_status_history';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'enquiry_id', 'from_status', 'to_status', 'notes', 'user_id',
    ];
    protected static array $casts = [
        'enquiry_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function enquiry(): ?Enquiry
    {
        return $this->belongsTo(Enquiry::class, 'enquiry_id');
    }

    public function user(): ?User
    {
        if (!$this->user_id) return null;
        return User::find($this->user_id);
    }
}