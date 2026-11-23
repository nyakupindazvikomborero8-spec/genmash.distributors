<?php
/**
 * Notification Model
 */

class Notification extends Model
{
    protected static string $table = 'notifications';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'user_id', 'type', 'title', 'message', 'data', 'action_url', 'is_read', 'read_at', 'sent_at', 'failed_at', 'failure_reason',
    ];
    protected static array $casts = [
        'user_id' => 'integer',
        'data' => 'json',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
    protected static array $dates = ['created_at', 'read_at', 'sent_at', 'failed_at'];

    public function user(): ?User
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }
        $this->is_read = true;
        $this->read_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    public function markAsSent(): bool
    {
        $this->sent_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    public function markAsFailed(string $reason): bool
    {
        $this->failed_at = date('Y-m-d H:i:s');
        $this->failure_reason = $reason;
        return $this->save();
    }

    public static function createForUser(int $userId, string $type, string $title, string $message, array $data = [], string $actionUrl = null): self
    {
        return static::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'action_url' => $actionUrl,
        ]);
    }

    public static function getUnreadCount(int $userId): int
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }
}