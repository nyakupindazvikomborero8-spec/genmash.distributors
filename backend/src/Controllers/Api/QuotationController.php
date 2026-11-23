<?php
/**
 * Quotation Controller (API)
 */

class QuotationController extends Controller
{
    public function index(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $query = Quotation::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        $status = $this->getQuery('status');
        if ($status) {
            $query->where('status', $status);
        }

        $result = $this->paginate($query);
        
        $this->success([
            'quotations' => array_map(fn($q) => $this->formatQuotation($q), $result['data']),
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
        
        $errors = $this->validate($input, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|max:30',
            'company' => 'max:255',
            'address' => 'string',
            'currency' => 'in:USD,ZWG,BOTH',
            'notes' => 'string',
            'items' => 'array',
            'items.*.product_id' => 'integer',
            'items.*.name' => 'required_with:items.*.product_id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price_usd' => 'numeric',
            'items.*.unit_price_zwg' => 'numeric',
            'items.*.discount_percent' => 'numeric|min:0|max:100',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        try {
            $quotation = QuotationService::createFromRequest($input);
            
            $this->success([
                'quotation' => $this->formatQuotationDetail($quotation),
            ], 'Quotation request submitted successfully', 201);
            
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

        $quotation = Quotation::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$quotation) {
            $this->notFound('Quotation not found');
        }

        $this->success([
            'quotation' => $this->formatQuotationDetail($quotation),
        ]);
    }

    public function accept(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $quotation = Quotation::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$quotation) {
            $this->notFound('Quotation not found');
        }

        if (!$quotation->canBeAccepted()) {
            $this->error('Quotation cannot be accepted', 422);
        }

        $quotation->accept();
        
        $this->success([
            'quotation' => $this->formatQuotation($quotation),
        ], 'Quotation accepted successfully');
    }

    public function reject(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $input = $this->getInput();
        
        $quotation = Quotation::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$quotation) {
            $this->notFound('Quotation not found');
        }

        if (!in_array($quotation->status, ['sent', 'under_review', 'prepared'])) {
            $this->error('Quotation cannot be rejected in current status', 422);
        }

        $quotation->reject($input['reason'] ?? '');
        
        $this->success([
            'quotation' => $this->formatQuotation($quotation),
        ], 'Quotation rejected');
    }

    private function formatQuotation(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'status' => $quotation->status,
            'status_label' => Quotation::getStatuses()[$quotation->status] ?? $quotation->status,
            'currency' => $quotation->currency,
            'total_usd' => (float)$quotation->total_usd,
            'total_zwg' => (float)$quotation->total_zwg,
            'formatted_total' => $quotation->getFormattedTotal(),
            'customer_name' => $quotation->customer_name,
            'customer_email' => $quotation->customer_email,
            'validity_days' => $quotation->validity_days,
            'expires_at' => $quotation->expires_at,
            'is_expired' => $quotation->isExpired(),
            'created_at' => $quotation->created_at,
            'sent_at' => $quotation->sent_at,
        ];
    }

    private function formatQuotationDetail(Quotation $quotation): array
    {
        $items = [];
        foreach ($quotation->items() as $item) {
            $items[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price_usd' => (float)$item->unit_price_usd,
                'unit_price_zwg' => (float)$item->unit_price_zwg,
                'discount_percent' => (float)$item->discount_percent,
                'discount_usd' => (float)$item->discount_usd,
                'discount_zwg' => (float)$item->discount_zwg,
                'tax_rate' => (float)$item->tax_rate,
                'tax_usd' => (float)$item->tax_usd,
                'tax_zwg' => (float)$item->tax_zwg,
                'total_usd' => (float)$item->total_usd,
                'total_zwg' => (float)$item->total_zwg,
            ];
        }

        $history = [];
        foreach ($quotation->statusHistory() as $h) {
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
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'status' => $quotation->status,
            'status_label' => Quotation::getStatuses()[$quotation->status] ?? $quotation->status,
            'currency' => $quotation->currency,
            'subtotal_usd' => (float)$quotation->subtotal_usd,
            'subtotal_zwg' => (float)$quotation->subtotal_zwg,
            'discount_usd' => (float)$quotation->discount_usd,
            'discount_zwg' => (float)$quotation->discount_zwg,
            'tax_usd' => (float)$quotation->tax_usd,
            'tax_zwg' => (float)$quotation->tax_zwg,
            'delivery_usd' => (float)$quotation->delivery_usd,
            'delivery_zwg' => (float)$quotation->delivery_zwg,
            'total_usd' => (float)$quotation->total_usd,
            'total_zwg' => (float)$quotation->total_zwg,
            'formatted_total' => $quotation->getFormattedTotal(),
            'validity_days' => $quotation->validity_days,
            'expires_at' => $quotation->expires_at,
            'is_expired' => $quotation->isExpired(),
            'can_accept' => $quotation->canBeAccepted(),
            'notes' => $quotation->notes,
            'terms_conditions' => $quotation->terms_conditions,
            'internal_notes' => $quotation->internal_notes,
            'customer_name' => $quotation->customer_name,
            'customer_email' => $quotation->customer_email,
            'customer_phone' => $quotation->customer_phone,
            'customer_company' => $quotation->customer_company,
            'customer_address' => $quotation->customer_address,
            'items' => $items,
            'history' => $history,
            'prepared_by' => $quotation->preparedBy() ? [
                'id' => $quotation->preparedBy()->id,
                'name' => $quotation->preparedBy()->getFullName(),
            ] : null,
            'approved_by' => $quotation->approvedBy() ? [
                'id' => $quotation->approvedBy()->id,
                'name' => $quotation->approvedBy()->getFullName(),
            ] : null,
            'created_at' => $quotation->created_at,
            'sent_at' => $quotation->sent_at,
            'accepted_at' => $quotation->accepted_at,
            'rejected_at' => $quotation->rejected_at,
        ];
    }
}