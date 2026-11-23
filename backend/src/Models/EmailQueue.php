<?php
/**
 * EmailQueue Model
 */

class EmailQueue extends Model
{
    protected static string $table = 'email_queue';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'to_email', 'to_name', 'subject', 'body_html', 'body_text',
        'from_email', 'from_name', 'reply_to', 'cc', 'bcc', 'attachments',
        'priority', 'status', 'attempts', 'max_attempts', 'error_message',
        'sent_at', 'scheduled_at',
    ];
    protected static array $casts = [
        'cc' => 'json',
        'bcc' => 'json',
        'attachments' => 'json',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
    protected static array $dates = ['created_at', 'updated_at', 'scheduled_at', 'sent_at'];

    public static function queue(
        string $toEmail,
        string $subject,
        string $bodyHtml,
        string $toName = '',
        string $bodyText = '',
        array $options = []
    ): self {
        return static::create([
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText ?: strip_tags($bodyHtml),
            'from_email' => $options['from_email'] ?? Config::get('mail.from.address'),
            'from_name' => $options['from_name'] ?? Config::get('mail.from.name'),
            'reply_to' => $options['reply_to'] ?? Config::get('mail.reply_to.address'),
            'cc' => $options['cc'] ?? [],
            'bcc' => $options['bcc'] ?? [],
            'attachments' => $options['attachments'] ?? [],
            'priority' => $options['priority'] ?? 'normal',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => $options['max_attempts'] ?? 3,
            'scheduled_at' => $options['scheduled_at'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function markAsProcessing(): bool
    {
        $this->status = 'processing';
        $this->attempts++;
        return $this->save();
    }

    public function markAsSent(): bool
    {
        $this->status = 'sent';
        $this->sent_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    public function markAsFailed(string $error): bool
    {
        $this->error_message = $error;
        if ($this->attempts >= $this->max_attempts) {
            $this->status = 'failed';
        }
        return $this->save();
    }

    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->attempts < $this->max_attempts;
    }

    public function retry(): bool
    {
        $this->status = 'pending';
        $this->error_message = null;
        return $this->save();
    }

    public static function getPending(int $limit = 100): array
    {
        return static::query()
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('priority', 'desc')
            ->orderBy('scheduled_at', 'asc')
            ->limit($limit)
            ->get();
    }
}