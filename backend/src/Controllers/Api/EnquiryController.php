<?php
/**
 * Enquiry Controller (API)
 */

class EnquiryController extends Controller
{
    public function index(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $query = Enquiry::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        $status = $this->getQuery('status');
        if ($status) {
            $query->where('status', $status);
        }

        $result = $this->paginate($query);
        
        $this->success([
            'enquiries' => array_map(fn($e) => $this->formatEnquiry($e), $result['data']),
            'pagination' => [
                'current_page' => $result['current_page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'last_page' => $result['last_page'],
                'has_more' => $result['has_more_pages'],
            ],
        ]);
    }

    public function store(): void
    {
        $input = $this->getInput();
        $user = AuthService::user();
        
        $errors = $this->validate($input, [
            'name' => $user ? 'max:255' : 'required|max:255',
            'email' => $user ? 'email|max:255' : 'required|email|max:255',
            'phone' => 'max:30',
            'company' => 'max:255',
            'subject' => 'required|max:255',
            'message' => 'required',
            'type' => 'in:general,product,quotation,support,after_sales,complaint',
            'priority' => 'in:low,normal,high,urgent',
            'product_id' => 'integer',
            'quotation_id' => 'integer',
            'order_id' => 'integer',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        $data = [
            'user_id' => $user?->id,
            'name' => $input['name'] ?? $user?->getFullName(),
            'email' => $input['email'] ?? $user?->email,
            'phone' => $input['phone'] ?? $user?->phone,
            'company' => $input['company'] ?? null,
            'subject' => $input['subject'],
            'message' => $input['message'],
            'type' => $input['type'] ?? 'general',
            'priority' => $input['priority'] ?? 'normal',
            'product_id' => $input['product_id'] ?? null,
            'quotation_id' => $input['quotation_id'] ?? null,
            'order_id' => $input['order_id'] ?? null,
            'source' => 'website',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];

        try {
            $enquiry = Enquiry::create($data);
            
            // Send notification to admins
            $this->notifyAdmins($enquiry);
            
            // Send confirmation to customer
            $this->sendConfirmation($enquiry);
            
            $this->success([
                'enquiry' => $this->formatEnquiryDetail($enquiry),
            ], 'Enquiry submitted successfully', 201);
            
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    public function show(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $enquiry = Enquiry::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$enquiry) {
            $this->notFound('Enquiry not found');
        }

        $this->success([
            'enquiry' => $this->formatEnquiryDetail($enquiry),
        ]);
    }

    public function respond(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $enquiry = Enquiry::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$enquiry) {
            $this->notFound('Enquiry not found');
        }

        if (!in_array($enquiry->status, ['new', 'in_progress', 'responded'])) {
            $this->error('Cannot respond to enquiry in current status', 422);
        }

        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'message' => 'required',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        $response = EnquiryResponse::create([
            'enquiry_id' => $enquiry->id,
            'user_id' => $user->id,
            'message' => $input['message'],
            'is_internal' => false,
        ]);

        $enquiry->updateStatus('responded', 'Customer responded');

        $this->success([
            'response' => [
                'id' => $response->id,
                'message' => $response->message,
                'created_at' => $response->created_at,
            ],
        ], 'Response added');
    }

    private function formatEnquiry(Enquiry $enquiry): array
    {
        return [
            'id' => $enquiry->id,
            'enquiry_number' => $enquiry->enquiry_number,
            'subject' => $enquiry->subject,
            'type' => $enquiry->type,
            'type_label' => Enquiry::getTypes()[$enquiry->type] ?? $enquiry->type,
            'priority' => $enquiry->priority,
            'priority_label' => Enquiry::getPriorities()[$enquiry->priority] ?? $enquiry->priority,
            'status' => $enquiry->status,
            'status_label' => Enquiry::getStatuses()[$enquiry->status] ?? $enquiry->status,
            'has_unread_response' => $enquiry->getLatestResponse() && !$enquiry->getLatestResponse()->is_read,
            'created_at' => $enquiry->created_at,
            'responded_at' => $enquiry->responded_at,
        ];
    }

    private function formatEnquiryDetail(Enquiry $enquiry): array
    {
        $responses = [];
        foreach ($enquiry->responses() as $response) {
            $responses[] = [
                'id' => $response->id,
                'message' => $response->message,
                'is_internal' => $response->is_internal,
                'user' => $response->user ? [
                    'id' => $response->user->id,
                    'name' => $response->user->getFullName(),
                ] : null,
                'attachments' => $response->attachments,
                'created_at' => $response->created_at,
            ];
        }

        $history = [];
        foreach ($enquiry->statusHistory() as $h) {
            $history[] = [
                'id' => $h->id,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'notes' => $h->notes,
                'user' => $h->user ? [
                    'id' => $h->user->id,
                    'name' => $h->user->getFullName(),
                ] : null,
                'created_at' => $h->created_at,
            ];
        }

        return [
            'id' => $enquiry->id,
            'enquiry_number' => $enquiry->enquiry_number,
            'subject' => $enquiry->subject,
            'message' => $enquiry->message,
            'type' => $enquiry->type,
            'type_label' => Enquiry::getTypes()[$enquiry->type] ?? $enquiry->type,
            'priority' => $enquiry->priority,
            'priority_label' => Enquiry::getPriorities()[$enquiry->priority] ?? $enquiry->priority,
            'status' => $enquiry->status,
            'status_label' => Enquiry::getStatuses()[$enquiry->status] ?? $enquiry->status,
            'name' => $enquiry->name,
            'email' => $enquiry->email,
            'phone' => $enquiry->phone,
            'company' => $enquiry->company,
            'product' => $enquiry->product ? [
                'id' => $enquiry->product->id,
                'name' => $enquiry->product->name,
                'slug' => $enquiry->product->slug,
            ] : null,
            'quotation' => $enquiry->quotation ? [
                'id' => $enquiry->quotation->id,
                'quotation_number' => $enquiry->quotation->quotation_number,
            ] : null,
            'order' => $enquiry->order ? [
                'id' => $enquiry->order->id,
                'order_number' => $enquiry->order->order_number,
            ] : null,
            'assigned_to' => $enquiry->assignedTo() ? [
                'id' => $enquiry->assignedTo()->id,
                'name' => $enquiry->assignedTo()->getFullName(),
            ] : null,
            'responses' => $responses,
            'history' => $history,
            'created_at' => $enquiry->created_at,
            'responded_at' => $enquiry->responded_at,
            'resolved_at' => $enquiry->resolved_at,
        ];
    }

    private function notifyAdmins(Enquiry $enquiry): void
    {
        $admins = User::query()
            ->whereIn('role_id', [1, 2, 3]) // super_admin, admin, sales
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            Notification::createForUser(
                $admin->id,
                'new_enquiry',
                'New Enquiry',
                "New {$enquiry->type} enquiry from {$enquiry->name}",
                ['enquiry_id' => $enquiry->id],
                Config::get('app.url') . "/admin/enquiries/{$enquiry->id}"
            );
        }
    }

    private function sendConfirmation(Enquiry $enquiry): void
    {
        EmailQueue::queue(
            $enquiry->email,
            "Enquiry Received - {$enquiry->enquiry_number}",
            $this->getConfirmationTemplate($enquiry),
            $enquiry->name
        );
    }

    private function getConfirmationTemplate(Enquiry $enquiry): string
    {
        return "
            <!DOCTYPE html>
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #071030; color: #fff; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>Genmashy Distributors</h1>
                </div>
                <div style='background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;'>
                    <h2 style='color: #071030; margin-top: 0;'>Enquiry Received</h2>
                    <p>Hello {$enquiry->name},</p>
                    <p>Thank you for contacting Genmashy Distributors. We have received your enquiry and our team will respond shortly.</p>
                    <p><strong>Reference:</strong> {$enquiry->enquiry_number}</p>
                    <p><strong>Subject:</strong> {$enquiry->subject}</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px;'>Genmashy Distributors - 63 Mendel Road, Avondale, Harare, Zimbabwe</p>
                </div>
            </body>
            </html>
        ";
    }
}