<?php
/**
 * Order Controller (API)
 */

class OrderController extends Controller
{
    public function index(): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $query = Order::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Status filter
        $status = $this->getQuery('status');
        if ($status) {
            $query->where('status', $status);
        }

        $result = $this->paginate($query);
        
        $this->success([
            'orders' => array_map(fn($o) => $this->formatOrder($o), $result['data']),
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
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'billing_address' => 'required|array',
            'billing_address.first_name' => 'required',
            'billing_address.last_name' => 'required',
            'billing_address.address_line_1' => 'required',
            'billing_address.city' => 'required',
            'billing_address.country' => 'required',
            'billing_address.phone' => 'required',
            'shipping_address' => 'array',
            'shipping_method' => 'string',
            'notes' => 'string',
            'customer_notes' => 'string',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        // Validate cart
        $cartIssues = CartService::validateStock();
        if (!empty($cartIssues)) {
            $this->error('Cart validation failed', 422, ['stock' => $cartIssues]);
        }

        try {
            $cart = CartService::getCart();
            $order = OrderService::createFromCart($cart, $input);
            
            $this->success([
                'order' => $this->formatOrderDetail($order),
            ], 'Order created successfully', 201);
            
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

        $order = Order::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            $this->notFound('Order not found');
        }

        $this->success([
            'order' => $this->formatOrderDetail($order),
        ]);
    }

    public function cancel(string $id): void
    {
        $user = AuthService::user();
        
        if (!$user) {
            $this->unauthorized();
        }

        $order = Order::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            $this->notFound('Order not found');
        }

        if (!$order->canBeCancelled()) {
            $this->error('Order cannot be cancelled in its current status', 422);
        }

        $order->updateStatus('cancelled', 'Cancelled by customer');
        
        // Restore stock
        foreach ($order->items() as $item) {
            if ($item->product && $item->product->track_inventory) {
                $item->product->increaseStock($item->quantity, 'order_cancellation', $order->id);
            }
        }

        $this->success([
            'order' => $this->formatOrder($order),
        ], 'Order cancelled successfully');
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => Order::getStatuses()[$order->status] ?? $order->status,
            'payment_status' => $order->payment_status,
            'payment_status_label' => Order::getPaymentStatuses()[$order->payment_status] ?? $order->payment_status,
            'currency' => $order->currency,
            'total' => $order->getTotal(),
            'formatted_total' => $order->getFormattedTotal(),
            'item_count' => count($order->items()),
            'created_at' => $order->created_at,
            'confirmed_at' => $order->confirmed_at,
            'shipped_at' => $order->shipped_at,
            'delivered_at' => $order->delivered_at,
        ];
    }

    private function formatOrderDetail(Order $order): array
    {
        $items = [];
        foreach ($order->items() as $item) {
            $items[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'variant_name' => $item->variant_name,
                'quantity' => $item->quantity,
                'unit_price' => $order->currency === 'ZWG' ? (float)$item->unit_price_zwg : (float)$item->unit_price_usd,
                'total_price' => $order->currency === 'ZWG' ? (float)$item->total_price_zwg : (float)$item->total_price_usd,
                'formatted_unit_price' => $order->currency === 'ZWG' ? 'ZWG$' . number_format($item->unit_price_zwg, 2) : '$' . number_format($item->unit_price_usd, 2),
                'formatted_total_price' => $order->currency === 'ZWG' ? 'ZWG$' . number_format($item->total_price_zwg, 2) : '$' . number_format($item->total_price_usd, 2),
            ];
        }

        $payments = [];
        foreach ($order->payments() as $payment) {
            $payments[] = [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'method' => $payment->method,
                'amount' => (float)$payment->amount,
                'fee' => (float)$payment->fee,
                'net_amount' => $payment->getNetAmount(),
                'status' => $payment->status,
                'paid_at' => $payment->paid_at,
            ];
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => Order::getStatuses()[$order->status] ?? $order->status,
            'payment_status' => $order->payment_status,
            'payment_status_label' => Order::getPaymentStatuses()[$order->payment_status] ?? $order->payment_status,
            'currency' => $order->currency,
            'subtotal' => $order->getSubtotal(),
            'tax' => $order->getTax(),
            'shipping' => $order->getShipping(),
            'discount' => $order->getDiscount(),
            'total' => $order->getTotal(),
            'formatted_subtotal' => $order->currency === 'ZWG' ? 'ZWG$' . number_format($order->getSubtotal(), 2) : '$' . number_format($order->getSubtotal(), 2),
            'formatted_tax' => $order->currency === 'ZWG' ? 'ZWG$' . number_format($order->getTax(), 2) : '$' . number_format($order->getTax(), 2),
            'formatted_shipping' => $order->currency === 'ZWG' ? 'ZWG$' . number_format($order->getShipping(), 2) : '$' . number_format($order->getShipping(), 2),
            'formatted_discount' => $order->currency === 'ZWG' ? 'ZWG$' . number_format($order->getDiscount(), 2) : '$' . number_format($order->getDiscount(), 2),
            'formatted_total' => $order->getFormattedTotal(),
            'billing_address' => $order->billing_address,
            'shipping_address' => $order->shipping_address,
            'shipping_method' => $order->shipping_method,
            'shipping_tracking' => $order->shipping_tracking,
            'notes' => $order->notes,
            'customer_notes' => $order->customer_notes,
            'items' => $items,
            'payments' => $payments,
            'created_at' => $order->created_at,
            'confirmed_at' => $order->confirmed_at,
            'shipped_at' => $order->shipped_at,
            'delivered_at' => $order->delivered_at,
            'cancelled_at' => $order->cancelled_at,
            'cancelled_reason' => $order->cancelled_reason,
        ];
    }
}