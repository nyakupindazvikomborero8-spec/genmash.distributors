<?php
/**
 * ActivityLog Model
 */

class ActivityLog extends Model
{
    protected static string $table = 'activity_logs';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'user_id', 'action', 'description', 'subject_type', 'subject_id', 'properties', 'ip_address', 'user_agent',
    ];
    protected static array $casts = [
        'user_id' => 'integer',
        'subject_id' => 'integer',
        'properties' => 'json',
    ];
    protected static array $dates = ['created_at'];

    public function user(): ?User
    {
        if (!$this->user_id) return null;
        return User::find($this->user_id);
    }

    public function subject(): ?Model
    {
        if (!$this->subject_type || !$this->subject_id) return null;
        
        if (!class_exists($this->subject_type)) {
            return null;
        }
        
        return $this->subject_type::find($this->subject_id);
    }

    public static function log(
        string $action,
        string $description,
        string $subjectType = null,
        int $subjectId = null,
        array $properties = [],
        int $userId = null,
        string $ipAddress = null,
        string $userAgent = null
    ): self {
        $userId = $userId ?? (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
        $ipAddress = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $userAgent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);

        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $properties,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public static function logAction(string $action, Model $subject, string $description = '', array $properties = []): self
    {
        return static::log(
            $action,
            $description ?: "{$action} {$subject->getTable()}",
            get_class($subject),
            $subject->getKey(),
            $properties
        );
    }

    public static function getActivityFeed(int $limit = 50, int $userId = null): array
    {
        $query = static::query()
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }
}