<?php
/**
 * ChatConversation Model
 */

class ChatConversation extends Model
{
    protected static string $table = 'chat_conversations';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'user_id', 'guest_name', 'guest_email', 'guest_phone', 'subject',
        'status', 'priority', 'assigned_to', 'last_message_at',
        'last_message_by', 'closed_at', 'closed_by',
    ];
    protected static array $casts = [
        'user_id' => 'integer',
        'assigned_to' => 'integer',
        'closed_by' => 'integer',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];
    protected static array $dates = ['created_at', 'updated_at', 'last_message_at', 'closed_at'];

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

    public function messages(): array
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function getLatestMessage(): ?ChatMessage
    {
        return ChatMessage::query()
            ->where('conversation_id', $this->id)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getUnreadCount(int $userId = null): int
    {
        $query = ChatMessage::query()
            ->where('conversation_id', $this->id)
            ->where('is_read', false);

        if ($userId) {
            $query->where('sender_id', '!=', $userId);
        }

        return $query->count();
    }

    public function updateStatus(string $newStatus): bool
    {
        $this->status = $newStatus;
        if ($newStatus === 'closed') {
            $this->closed_at = date('Y-m-d H:i:s');
        }
        return $this->save();
    }

    public function addMessage(string $senderType, string $message, array $attachments = [], int $senderId = null): ChatMessage
    {
        $chatMessage = ChatMessage::create([
            'conversation_id' => $this->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $message,
            'attachments' => $attachments,
        ]);

        $this->last_message_at = date('Y-m-d H:i:s');
        $this->last_message_by = $senderType;
        $this->save();

        return $chatMessage;
    }

    public static function getStatuses(): array
    {
        return Config::get('chat.statuses', [
            'open' => 'Open',
            'waiting_customer' => 'Waiting Customer',
            'waiting_agent' => 'Waiting Agent',
            'closed' => 'Closed',
        ]);
    }
}