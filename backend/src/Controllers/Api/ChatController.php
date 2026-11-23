<?php
/**
 * Chat Controller (API)
 */

class ChatController extends Controller
{
    public function index(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $query = ChatConversation::query();

        if ($user->isCustomer()) {
            $query->where('user_id', $user->id);
        }

        $status = $this->getQuery('status');
        if ($status) {
            $query->where('status', $status);
        }

        $query->orderBy('last_message_at', 'desc');

        $result = $this->paginate($query);
        
        $this->success([
            'conversations' => array_map(fn($c) => $this->formatConversation($c, $user), $result['data']),
            'pagination' => [
                'current_page' => $result['current_page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'last_page' => $result['last_page'],
                'has_more' => $result['has_more_pages'],
            ],
        ]);
    }

    public function create(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $input = $this->getInput();
        
        // Check if conversation already exists
        $existing = ChatConversation::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            $this->success([
                'conversation' => $this->formatConversationDetail($existing, $user),
            ]);
            return;
        }

        $conversation = ChatConversation::create([
            'user_id' => $user->id,
            'guest_name' => $user->getFullName(),
            'guest_email' => $user->email,
            'guest_phone' => $user->phone,
            'subject' => $input['subject'] ?? 'General Inquiry',
            'status' => 'waiting_agent',
            'priority' => $input['priority'] ?? 'normal',
        ]);

        // Add initial message if provided
        if (!empty($input['message'])) {
            $conversation->addMessage('customer', $input['message'], [], $user->id);
        }

        // Notify available agents
        $this->notifyAgents($conversation);

        $this->success([
            'conversation' => $this->formatConversationDetail($conversation, $user),
        ], 'Chat started', 201);
    }

    public function show(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $conversation = ChatConversation::query()->where('id', $id)->first();

        if (!$conversation) {
            $this->notFound('Conversation not found');
        }

        // Check access
        if ($user->isCustomer() && $conversation->user_id !== $user->id) {
            $this->forbidden();
        }

        // Mark as read for this user
        $messages = $conversation->messages();
        foreach ($messages as $msg) {
            if ($msg->sender_id !== $user->id && !$msg->is_read) {
                $msg->markAsRead();
            }
        }

        $this->success([
            'conversation' => $this->formatConversationDetail($conversation, $user),
        ]);
    }

    public function sendMessage(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $conversation = ChatConversation::query()->where('id', $id)->first();

        if (!$conversation) {
            $this->notFound('Conversation not found');
        }

        // Check access
        if ($user->isCustomer() && $conversation->user_id !== $user->id) {
            $this->forbidden();
        }

        if (!in_array($conversation->status, ['open', 'waiting_customer', 'waiting_agent'])) {
            $this->error('Conversation is closed', 422);
        }

        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'message' => 'required',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        $senderType = $user->isCustomer() ? 'customer' : 'agent';
        $message = $conversation->addMessage($senderType, $input['message'], $input['attachments'] ?? [], $user->id);

        // Update conversation status
        if ($senderType === 'customer') {
            $conversation->updateStatus('waiting_agent');
        } else {
            $conversation->updateStatus('waiting_customer');
        }

        // Notify other party
        if ($senderType === 'customer') {
            $this->notifyAgents($conversation);
        } else {
            // Notify customer (could be via email/push)
            if ($conversation->user_id) {
                Notification::createForUser(
                    $conversation->user_id,
                    'chat_message',
                    'New Message',
                    "You have a new message from our support team",
                    ['conversation_id' => $conversation->id],
                    Config::get('app.url') . "/chat/{$conversation->id}"
                );
            }
        }

        $this->success([
            'message' => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'sender_id' => $message->sender_id,
                'message' => $message->message,
                'attachments' => $message->attachments,
                'created_at' => $message->created_at,
            ],
        ], 'Message sent');
    }

    public function markAsRead(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $conversation = ChatConversation::query()->where('id', $id)->first();

        if (!$conversation) {
            $this->notFound('Conversation not found');
        }

        // Check access
        if ($user->isCustomer() && $conversation->user_id !== $user->id) {
            $this->forbidden();
        }

        $messages = $conversation->messages();
        foreach ($messages as $msg) {
            if ($msg->sender_id !== $user->id && !$msg->is_read) {
                $msg->markAsRead();
            }
        }

        $this->success(null, 'Messages marked as read');
    }

    private function formatConversation(ChatConversation $conversation, User $user): array
    {
        $latestMessage = $conversation->getLatestMessage();
        $unreadCount = $conversation->getUnreadCount($user->id);

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'status' => $conversation->status,
            'status_label' => ChatConversation::getStatuses()[$conversation->status] ?? $conversation->status,
            'priority' => $conversation->priority,
            'unread_count' => $unreadCount,
            'latest_message' => $latestMessage ? [
                'message' => $latestMessage->message,
                'sender_type' => $latestMessage->sender_type,
                'created_at' => $latestMessage->created_at,
            ] : null,
            'last_message_at' => $conversation->last_message_at,
            'assigned_to' => $conversation->assignedTo() ? [
                'id' => $conversation->assignedTo()->id,
                'name' => $conversation->assignedTo()->getFullName(),
            ] : null,
        ];
    }

    private function formatConversationDetail(ChatConversation $conversation, User $user): array
    {
        $messages = $conversation->messages();
        
        // Mark messages as read for current user
        foreach ($messages as $msg) {
            if ($msg->sender_id !== $user->id && !$msg->is_read) {
                $msg->markAsRead();
            }
        }

        $formattedMessages = array_map(fn($msg) => [
            'id' => $msg->id,
            'sender_type' => $msg->sender_type,
            'sender_id' => $msg->sender_id,
            'sender_name' => $msg->sender ? $msg->sender->getFullName() : ($msg->sender_type === 'customer' ? 'You' : 'Support'),
            'message' => $msg->message,
            'attachments' => $msg->attachments,
            'is_read' => $msg->is_read,
            'created_at' => $msg->created_at,
        ], $messages);

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'status' => $conversation->status,
            'status_label' => ChatConversation::getStatuses()[$conversation->status] ?? $conversation->status,
            'priority' => $conversation->priority,
            'guest_name' => $conversation->guest_name,
            'guest_email' => $conversation->guest_email,
            'guest_phone' => $conversation->guest_phone,
            'messages' => $formattedMessages,
            'assigned_to' => $conversation->assignedTo() ? [
                'id' => $conversation->assignedTo()->id,
                'name' => $conversation->assignedTo()->getFullName(),
            ] : null,
            'created_at' => $conversation->created_at,
            'last_message_at' => $conversation->last_message_at,
        ];
    }

    private function notifyAgents(ChatConversation $conversation): void
    {
        $agents = User::query()
            ->whereIn('role_id', [1, 2, 3]) // super_admin, admin, sales
            ->where('is_active', true)
            ->get();

        foreach ($agents as $agent) {
            Notification::createForUser(
                $agent->id,
                'chat_message',
                'New Chat Message',
                "New message in conversation: {$conversation->subject}",
                ['conversation_id' => $conversation->id],
                Config::get('app.url') . "/admin/chat/{$conversation->id}"
            );
        }
    }
}