<?php
/**
 * Order Service
 */

class OrderService
{
    public static function createFromCart(Cart $cart, array $data): Order
    {
        $user = AuthService::user() ?? User::find($cart->user_id);
        
        if (!$user) {
            throw new Exception('User not found.');
        }

        $items = $cart->items();
        if (empty($items)) {
            throw new Exception('Cart is empty.');
        }

        // Validate stock
        $issues = CartService::validateStock();
        if (!empty($issues)) {
            throw new Exception(implode(' ', $issues));
        }

        $currency = $cart->currency;
        $taxRate = Config::get('tax_rate', 15) / 100;
        $taxEnabled = Config::get('tax_enabled', true);

        // Calculate totals
        $subtotal = 0;
        $tax = 0;
        
        foreach ($items as $item) {
            $price = $currency === 'ZWG' ? $item->price_zwg : $item->price_usd;
            $lineTotal = $price * $item->quantity;
            $subtotal += $lineTotal;
            
            if ($taxEnabled) {
                $tax += $lineTotal * $taxRate;
            }
        }

        $shipping = self::calculateShipping($subtotal, $currency);
        $discount = 0; // Could be extended for coupons
        $total = $subtotal + $tax + $shipping - $discount;

        // Prepare addresses
        $billingAddress = $data['billing_address'] ?? [];
        $shippingAddress = $data['shipping_address'] ?? $billingAddress;

        // Create order
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'currency' => $currency,
            'subtotal_usd' => $currency === 'USD' ? $subtotal : 0,
            'subtotal_zwg' => $currency === 'ZWG' ? $subtotal : 0,
            'tax_usd' => $currency === 'USD' && $taxEnabled ? $tax : 0,
            'tax_zwg' => $currency === 'ZWG' && $taxEnabled ? $tax : 0,
            'shipping_usd' => $currency === 'USD' ? $shipping : 0,
            'shipping_zwg' => $currency === 'ZWG' ? $shipping : 0,
            'discount_usd' => $currency === 'USD' ? $discount : 0,
            'discount_zwg' => $currency === 'ZWG' ? $discount : 0,
            'total_usd' => $currency === 'USD' ? $total : 0,
            'total_zwg' => $currency === 'ZWG' ? $total : 0,
            'billing_address' => $billingAddress,
            'shipping_address' => $shippingAddress,
            'shipping_method' => $data['shipping_method'] ?? 'standard',
            'notes' => $data['notes'] ?? '',
            'customer_notes' => $data['customer_notes'] ?? '',
        ]);

        // Create order items
        foreach ($items as $item) {
            $product = $item->product;
            $price = $currency === 'ZWG' ? $item->price_zwg : $item->price_usd;
            $lineTotal = $price * $item->quantity;
            $itemTax = $taxEnabled ? $lineTotal * $taxRate : 0;

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'variant_name' => $item->variant?->name,
                'quantity' => $item->quantity,
                'unit_price_usd' => $currency === 'USD' ? $price : 0,
                'unit_price_zwg' => $currency === 'ZWG' ? $price : 0,
                'total_price_usd' => $currency === 'USD' ? $lineTotal : 0,
                'total_price_zwg' => $currency === 'ZWG' ? $lineTotal : 0,
                'tax_rate' => $taxEnabled ? $taxRate : 0,
                'tax_usd' => $currency === 'USD' && $taxEnabled ? $itemTax : 0,
                'tax_zwg' => $currency === 'ZWG' && $taxEnabled ? $itemTax : 0,
            ]);

            // Decrease stock
            if ($product->track_inventory) {
                $product->decreaseStock(
                    $item->quantity,
                    'order',
                    $order->id,
                    $user->id
                );
            }
        }

        // Clear cart
        $cart->clear();

        // Send confirmation email
        self::sendConfirmationEmail($order);

        ActivityLog::log('order_created', "Order {$order->order_number} created", Order::class, $order->id);
        
        return $order;
    }

    public static function createFromQuotation(Quotation $quotation, array $data = []): Order
    {
        if (!$quotation->canBeAccepted()) {
            throw new Exception('Quotation cannot be converted to order.');
        }

        $user = $quotation->user ?? (AuthService::user() ?? null);
        
        if (!$user) {
            throw new Exception('User not found.');
        }

        $currency = $quotation->currency === 'BOTH' ? 'USD' : $quotation->currency;
        $items = $quotation->items();

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'user_id' => $user->id,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'currency' => $currency,
            'subtotal_usd' => $quotation->subtotal_usd,
            'subtotal_zwg' => $quotation->subtotal_zwg,
            'tax_usd' => $quotation->tax_usd,
            'tax_zwg' => $quotation->tax_zwg,
            'shipping_usd' => $quotation->delivery_usd,
            'shipping_zwg' => $quotation->delivery_zwg,
            'discount_usd' => $quotation->discount_usd,
            'discount_zwg' => $quotation->discount_zwg,
            'total_usd' => $quotation->total_usd,
            'total_zwg' => $quotation->total_zwg,
            'billing_address' => $data['billing_address'] ?? [],
            'shipping_address' => $data['shipping_address'] ?? [],
            'shipping_method' => $data['shipping_method'] ?? 'standard',
            'notes' => "Created from quotation {$quotation->quotation_number}",
            'customer_notes' => $data['customer_notes'] ?? '',
            'confirmed_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ($items as $item) {
            $product = $item->product;
            
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'quantity' => $item->quantity,
                'unit_price_usd' => $item->unit_price_usd,
                'unit_price_zwg' => $item->unit_price_zwg,
                'total_price_usd' => $item->total_usd,
                'total_price_zwg' => $item->total_zwg,
                'tax_rate' => $item->tax_rate,
                'tax_usd' => $item->tax_usd,
                'tax_zwg' => $item->tax_zwg,
            ]);

            if ($product && $product->track_inventory) {
                $product->decreaseStock(
                    $item->quantity,
                    'order',
                    $order->id,
                    $user->id
                );
            }
        }

        // Update quotation status
        $quotation->updateStatus('accepted', 'Converted to order');

        // Send confirmation email
        self::sendConfirmationEmail($order);

        ActivityLog::log('order_from_quotation', "Order {$order->order_number} created from quotation {$quotation->quotation_number}", Order::class, $order->id);
        
        return $order;
    }

    private static function calculateShipping(float $subtotal, string $currency): float
    {
        $freeThreshold = $currency === 'ZWG' 
            ? Config::get('shipping_free_threshold_zwg', 5000) 
            : Config::get('shipping_free_threshold_usd', 500);

        if ($subtotal >= $freeThreshold) {
            return 0;
        }

        // Default shipping cost
        return $currency === 'ZWG' ? 50 : 10;
    }

    private static function sendConfirmationEmail(Order $order): void
    {
        $user = $order->user;
        if (!$user) return;

        $url = Config::get('app.url') . "/account/orders/{$order->id}";
        
        EmailQueue::queue(
            $user->email,
            "Order Confirmation - {$order->order_number}",
            self::getOrderConfirmationTemplate($order, $url),
            $user->getFullName()
        );
    }

    public static function sendStatusUpdateEmail(Order $order): void
    {
        $user = $order->user;
        if (!$user) return;

        $url = Config::get('app.url') . "/account/orders/{$order->id}";
        $statusLabel = Order::getStatuses()[$order->status] ?? $order->status;
        
        EmailQueue::queue(
            $user->email,
            "Order Update - {$order->order_number}",
            self::getOrderStatusTemplate($order, $statusLabel, $url),
            $user->getFullName()
        );
    }

    private static function getOrderConfirmationTemplate(Order $order, string $url): string
    {
        $itemsHtml = '';
        foreach ($order->items() as $item) {
            $price = $order->currency === 'ZWG' ? $item->unit_price_zwg : $item->unit_price_usd;
            $total = $order->currency === 'ZWG' ? $item->total_price_zwg : $item->total_price_usd;
            $symbol = $order->currency === 'ZWG' ? 'ZWG$' : '$';
            
            $itemsHtml .= "
                <tr>
                    <td style='padding: 12px; border-bottom: 1px solid #eee;'>{$item->product_name}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: center;'>{$item->quantity}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'>{$symbol}" . number_format($price, 2) . "</td>
                    <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'>{$symbol}" . number_format($total, 2) . "</td>
                </tr>
            ";
        }

        $symbol = $order->currency === 'ZWG' ? 'ZWG$' : '$';
        
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1'>
            </head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #071030; color: #fff; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>Genmashy Distributors</h1>
                </div>
                <div style='background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;'>
                    <h2 style='color: #071030; margin-top: 0;'>Order Confirmation</h2>
                    <p>Hello {$order->user->getFullName()},</p>
                    <p>Thank you for your order! Your order <strong>{$order->order_number}</strong> has been received and is being processed.</p>
                    
                    <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                        <thead>
                            <tr style='background: #f5f5f5;'>
                                <th style='padding: 12px; text-align: left; border-bottom: 2px solid #ddd;'>Product</th>
                                <th style='padding: 12px; text-align: center; border-bottom: 2px solid #ddd;'>Qty</th>
                                <th style='padding: 12px; text-align: right; border-bottom: 2px solid #ddd;'>Price</th>
                                <th style='padding: 12px; text-align: right; border-bottom: 2px solid #ddd;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$itemsHtml}
                        </tbody>
                    </table>
                    
                    <div style='text-align: right; margin-top: 20px;'>
                        <p><strong>Subtotal:</strong> {$symbol}" . number_format($order->getSubtotal(), 2) . "</p>
                        <p><strong>Tax:</strong> {$symbol}" . number_format($order->getTax(), 2) . "</p>
                        <p><strong>Shipping:</strong> {$symbol}" . number_format($order->getShipping(), 2) . "</p>
                        <hr style='border: none; border-top: 1px solid #eee;'>
                        <p style='font-size: 18px;'><strong>Total: {$symbol}" . number_format($order->getTotal(), 2) . "</strong></p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$url}' style='background: #4b7bf5; color: #fff; padding: 14px 28px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>View Order</a>
                    </div>
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px;'>If you have any questions, please contact us at sales@genmashy.co.zw</p>
                </div>
            </body>
            </html>
        ";
    }

    private static function getOrderStatusTemplate(Order $order, string $statusLabel, string $url): string
    {
        $symbol = $order->currency === 'ZWG' ? 'ZWG$' : '$';
        
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1'>
            </head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #071030; color: #fff; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>Genmashy Distributors</h1>
                </div>
                <div style='background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;'>
                    <h2 style='color: #071030; margin-top: 0;'>Order Status Update</h2>
                    <p>Hello {$order->user->getFullName()},</p>
                    <p>Your order <strong>{$order->order_number}</strong> status has been updated to:</p>
                    <div style='background: #f0f4ff; border: 1px solid #4b7bf5; border-radius: 4px; padding: 20px; text-align: center; margin: 20px 0;'>
                        <span style='font-size: 24px; font-weight: bold; color: #4b7bf5;'>{$statusLabel}</span>
                    </div>
                    <p><strong>Total:</strong> {$symbol}" . number_format($order->getTotal(), 2) . "</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$url}' style='background: #4b7bf5; color: #fff; padding: 14px 28px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>View Order</a>
                    </div>
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px;'>If you have any questions, please contact us at sales@genmashy.co.zw</p>
                </div>
            </body>
            </html>
        ";
    }
}