<?php
/**
 * EnquiryResponse Model
 */

class EnquiryResponse extends Model
{
    protected static string $table = 'enquiry_responses';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'enquiry_id', 'user_id', 'message', 'is_internal', 'attachments',
    ];
    protected static array $casts = [
        'enquiry_id' => 'integer',
        'user_id' => 'integer',
        'is_internal' => 'boolean',
        'attachments' => 'json',
    ];

    public function enquiry(): ?Enquiry
    {
        return $this->belongsTo(Enquiry::class, 'enquiry_id');
    }

    public function user(): ?User
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}