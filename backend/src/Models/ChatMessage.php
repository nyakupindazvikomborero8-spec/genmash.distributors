<?php
/**
 * ChatMessage Model
 */

class ChatMessage extends Model
{
    protected static string $table = 'chat_messages';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'conversation_id', 'sender_type', 'sender_id', 'message', 'attachments', 'is_read', 'read_at',
    ];
    protected static array $casts = [
        'conversation_id' => 'integer',
        'sender_id' => 'integer',
        'attachments' => 'json',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];
    protected static array $dates = ['created_at', 'read_at'];

    public function conversation(): ?ChatConversation
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender(): ?User
    {
        if (!$this->sender_id || $this->sender_type !== 'agent') return null;
        return User::find($this->sender_id);
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
}