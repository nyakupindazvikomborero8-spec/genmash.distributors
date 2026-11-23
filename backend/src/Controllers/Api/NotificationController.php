<?php
/**
 * Notification Controller (API)
 */

class NotificationController extends Controller
{
    public function index(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $query = Notification::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        $unreadOnly = $this->getQuery('unread_only');
        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        $result = $this->paginate($query);
        
        $this->success([
            'notifications' => array_map(fn($n) => $this->formatNotification($n), $result['data']),
            'pagination' => [
                'current_page' => $result['current_page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'last_page' => $result['last_page'],
                'has_more' => $result['has_more_pages'],
            ],
            'unread_count' => Notification::getUnreadCount($user->id),
        ]);
    }

    public function markAsRead(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $notification = Notification::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            $this->notFound('Notification not found');
        }

        $notification->markAsRead();
        
        $this->success([
            'notification' => $this->formatNotification($notification),
        ], 'Notification marked as read');
    }

    public function markAllAsRead(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->get();

        foreach ($notifications as $notification) {
            $notification->markAsRead();
        }

        $this->success(null, 'All notifications marked as read');
    }

    private function formatNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data,
            'action_url' => $notification->action_url,
            'is_read' => $notification->is_read,
            'created_at' => $notification->created_at,
        ];
    }
}