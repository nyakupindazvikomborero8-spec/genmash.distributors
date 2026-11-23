<?php
/**
 * Quotation Service
 */

class QuotationService
{
    public static function createFromRequest(array $data): Quotation
    {
        $user = AuthService::user();
        
        $quotation = Quotation::create([
            'quotation_number' => Quotation::generateQuotationNumber(),
            'user_id' => $user?->id,
            'customer_name' => $data['name'],
            'customer_email' => $data['email'],
            'customer_phone' => $data['phone'],
            'customer_company' => $data['company'] ?? null,
            'customer_address' => $data['address'] ?? null,
            'status' => 'pending',
            'currency' => $data['currency'] ?? 'BOTH',
            'validity_days' => Config::get('quotation.validity_days', 30),
            'notes' => $data['notes'] ?? '',
            'expires_at' => date('Y-m-d H:i:s', time() + 86400 * Config::get('quotation.validity_days', 30)),
        ]);

        // Add items if provided
        if (!empty($data['items'])) {
            foreach ($data['items'] as $index => $item) {
                $product = null;
                if (!empty($item['product_id'])) {
                    $product = Product::find($item['product_id']);
                }

                $unitPriceUsd = $item['unit_price_usd'] ?? ($product->price_usd ?? 0);
                $unitPriceZwg = $item['unit_price_zwg'] ?? ($product->price_zwg ?? 0);
                $taxRate = Config::get('tax_rate', 15) / 100;

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $item['product_id'] ?? null,
                    'product_name' => $item['name'] ?? ($product->name ?? 'Custom Item'),
                    'product_sku' => $item['sku'] ?? ($product->sku ?? ''),
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price_usd' => $unitPriceUsd,
                    'unit_price_zwg' => $unitPriceZwg,
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'tax_rate' => $taxRate,
                    'sort_order' => $index,
                ]);
            }
        }

        // Calculate totals
        $quotation->calculateTotals();
        $quotation->save();

        // Send notification to admins
        self::notifyAdmins($quotation);

        // Send confirmation to customer
        self::sendCustomerConfirmation($quotation);

        ActivityLog::log('quotation_created', "Quotation {$quotation->quotation_number} requested", Quotation::class, $quotation->id);
        
        return $quotation;
    }

    public static function prepare(Quotation $quotation, int $preparedBy, array $items = []): bool
    {
        if (!in_array($quotation->status, ['pending', 'under_review'])) {
            throw new Exception('Quotation cannot be prepared in current status.');
        }

        if (!empty($items)) {
            // Clear existing items
            $existingItems = $quotation->items();
            foreach ($existingItems as $item) {
                $item->delete();
            }

            // Add new items
            foreach ($items as $index => $item) {
                $product = null;
                if (!empty($item['product_id'])) {
                    $product = Product::find($item['product_id']);
                }

                $unitPriceUsd = $item['unit_price_usd'] ?? ($product->price_usd ?? 0);
                $unitPriceZwg = $item['unit_price_zwg'] ?? ($product->price_zwg ?? 0);
                $taxRate = Config::get('tax_rate', 15) / 100;

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $item['product_id'] ?? null,
                    'product_name' => $item['name'] ?? ($product->name ?? 'Custom Item'),
                    'product_sku' => $item['sku'] ?? ($product->sku ?? ''),
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price_usd' => $unitPriceUsd,
                    'unit_price_zwg' => $unitPriceZwg,
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'tax_rate' => $taxRate,
                    'sort_order' => $index,
                ]);
            }
        }

        $quotation->calculateTotals();
        $quotation->prepared_by = $preparedBy;
        $quotation->updateStatus('prepared', 'Quotation prepared for review', $preparedBy);

        // Notify customer
        self::sendPreparedNotification($quotation);

        return true;
    }

    public static function approve(Quotation $quotation, int $approvedBy): bool
    {
        if ($quotation->status !== 'prepared') {
            throw new Exception('Quotation must be prepared before approval.');
        }

        $quotation->approved_by = $approvedBy;
        return $quotation->updateStatus('sent', 'Quotation approved and sent to customer', $approvedBy);
    }

    public static function send(Quotation $quotation): bool
    {
        if (!in_array($quotation->status, ['prepared', 'sent'])) {
            throw new Exception('Quotation cannot be sent in current status.');
        }

        // Generate PDF (placeholder - would need a PDF library)
        // $pdfPath = self::generatePdf($quotation);
        // $quotation->pdf_path = $pdfPath;
        // $quotation->save();

        return $quotation->updateStatus('sent', 'Quotation sent to customer');
    }

    public static function accept(Quotation $quotation): bool
    {
        if (!$quotation->canBeAccepted()) {
            throw new Exception('Quotation cannot be accepted.');
        }

        return $quotation->accept();
    }

    public static function reject(Quotation $quotation, string $reason = ''): bool
    {
        if (!in_array($quotation->status, ['sent', 'under_review', 'prepared'])) {
            throw new Exception('Quotation cannot be rejected in current status.');
        }

        return $quotation->reject($reason);
    }

    public static function convertToOrder(Quotation $quotation, array $data = []): Order
    {
        return OrderService::createFromQuotation($quotation, $data);
    }

    private static function notifyAdmins(Quotation $quotation): void
    {
        $admins = User::query()
            ->whereIn('role_id', [1, 2]) // super_admin, admin
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            Notification::createForUser(
                $admin->id,
                'new_quotation',
                'New Quotation Request',
                "New quotation {$quotation->quotation_number} from {$quotation->customer_name}",
                ['quotation_id' => $quotation->id],
                Config::get('app.url') . "/admin/quotations/{$quotation->id}"
            );

            EmailQueue::queue(
                $admin->email,
                "New Quotation - {$quotation->quotation_number}",
                self::getAdminNotificationTemplate($quotation),
                $admin->getFullName()
            );
        }
    }

    private static function sendCustomerConfirmation(Quotation $quotation): void
    {
        EmailQueue::queue(
            $quotation->customer_email,
            "Quotation Request Received - {$quotation->quotation_number}",
            self::getCustomerConfirmationTemplate($quotation),
            $quotation->customer_name
        );
    }

    private static function sendPreparedNotification(Quotation $quotation): void
    {
        EmailQueue::queue(
            $quotation->customer_email,
            "Quotation Ready - {$quotation->quotation_number}",
            self::getPreparedTemplate($quotation),
            $quotation->customer_name
        );
    }

    private static function getAdminNotificationTemplate(Quotation $quotation): string
    {
        $itemsHtml = '';
        foreach ($quotation->items() as $item) {
            $itemsHtml .= "<li>{$item->product_name} x{$item->quantity} - $" . number_format($item->unit_price_usd, 2) . " / ZWG$" . number_format($item->unit_price_zwg, 2) . "</li>";
        }

        return "
            <!DOCTYPE html>
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #071030; color: #fff; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>Genmashy Distributors</h1>
                </div>
                <div style='background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;'>
                    <h2 style='color: #071030; margin-top: 0;'>New Quotation Request</h2>
                    <p><strong>Quotation:</strong> {$quotation->quotation_number}</p>
                    <p><strong>Customer:</strong> {$quotation->customer_name}</p>
                    <p><strong>Email:</strong> {$quotation->customer_email}</p>
                    <p><strong>Phone:</strong> {$quotation->customer_phone}</p>
                    <p><strong>Company:</strong> {$quotation->customer_company ?? 'N/A'}</p>
                    <p><strong>Currency:</strong> {$quotation->currency}</p>
                    
                    <h3>Items:</h3>
                    <ul>{$itemsHtml}</ul>
                    
                    <p><strong>Total (USD):</strong> $" . number_format($quotation->total_usd, 2) . "</p>
                    <p><strong>Total (ZWG):</strong> ZWG$" . number_format($quotation->total_zwg, 2) . "</p>
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px;'>View in admin panel to prepare and send.</p>
                </div>
            </body>
            </html>
        ";
    }

    private static function getCustomerConfirmationTemplate(Quotation $quotation): string
    {
        return "
            <!DOCTYPE html>
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #071030; color: #fff; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>Genmashy Distributors</h1>
                </div>
                <div style='background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;'>
                    <h2 style='color: #071030; margin-top: 0;'>Quotation Request Received</h2>
                    <p>Hello {$quotation->customer_name},</p>
                    <p>Thank you for requesting a quotation from Genmashy Distributors. We have received your request and our team will prepare a detailed quotation for you.</p>
                    <p><strong>Reference:</strong> {$quotation->quotation_number}</p>
                    <p>We will contact you shortly with your quotation. If you have any urgent questions, please contact us on WhatsApp at +263 718 751 792.</p>
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px;'>Genmashy Distributors - 63 Mendel Road, Avondale, Harare, Zimbabwe</p>
                </div>
            </body>
            </html>
        ";
    }

    private static function getPreparedTemplate(Quotation $quotation): string
    {
        $itemsHtml = '';
        foreach ($quotation->items() as $item) {
            $itemsHtml .= "
                <tr>
                    <td style='padding: 12px; border-bottom: 1px solid #eee;'>{$item->product_name}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: center;'>{$item->quantity}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'>\$" . number_format($item->unit_price_usd, 2) . "</td>
                    <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'>ZWG\$" . number_format($item->unit_price_zwg, 2) . "</td>
                    <td style='padding: 12px; border-bottom: 1px solid #eee; text-align: right;'>\$" . number_format($item->total_usd, 2) . " / ZWG\$" . number_format($item->total_zwg, 2) . "</td>
                </tr>
            ";
        }

        return "
            <!DOCTYPE html>
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #071030; color: #fff; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>Genmashy Distributors</h1>
                </div>
                <div style='background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;'>
                    <h2 style='color: #071030; margin-top: 0;'>Your Quotation is Ready</h2>
                    <p>Hello {$quotation->customer_name},</p>
                    <p>Your quotation <strong>{$quotation->quotation_number}</strong> has been prepared and is ready for your review.</p>
                    
                    <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                        <thead>
                            <tr style='background: #f5f5f5;'>
                                <th style='padding: 12px; text-align: left; border-bottom: 2px solid #ddd;'>Item</th>
                                <th style='padding: 12px; text-align: center; border-bottom: 2px solid #ddd;'>Qty</th>
                                <th style='padding: 12px; text-align: right; border-bottom: 2px solid #ddd;'>Price (USD)</th>
                                <th style='padding: 12px; text-align: right; border-bottom: 2px solid #ddd;'>Price (ZWG)</th>
                                <th style='padding: 12px; text-align: right; border-bottom: 2px solid #ddd;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$itemsHtml}
                        </tbody>
                    </table>
                    
                    <div style='text-align: right; margin-top: 20px;'>
                        <p><strong>Subtotal (USD):</strong> \$" . number_format($quotation->subtotal_usd, 2) . "</p>
                        <p><strong>Subtotal (ZWG):</strong> ZWG\$" . number_format($quotation->subtotal_zwg, 2) . "</p>
                        <p><strong>Discount (USD):</strong> \$" . number_format($quotation->discount_usd, 2) . "</p>
                        <p><strong>Discount (ZWG):</strong> ZWG\$" . number_format($quotation->discount_zwg, 2) . "</p>
                        <p><strong>Tax (USD):</strong> \$" . number_format($quotation->tax_usd, 2) . "</p>
                        <p><strong>Tax (ZWG):</strong> ZWG\$" . number_format($quotation->tax_zwg, 2) . "</p>
                        <p><strong>Delivery (USD):</strong> \$" . number_format($quotation->delivery_usd, 2) . "</p>
                        <p><strong>Delivery (ZWG):</strong> ZWG\$" . number_format($quotation->delivery_zwg, 2) . "</p>
                        <hr style='border: none; border-top: 1px solid #eee;'>
                        <p style='font-size: 18px;'><strong>Total (USD): \$" . number_format($quotation->total_usd, 2) . "</strong></p>
                        <p style='font-size: 18px;'><strong>Total (ZWG): ZWG\$" . number_format($quotation->total_zwg, 2) . "</strong></p>
                    </div>
                    
                    <p><strong>Validity:</strong> {$quotation->validity_days} days</p>
                    <p><strong>Expires:</strong> " . date('F j, Y', strtotime($quotation->expires_at)) . "</p>
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px;'>To accept this quotation, please contact us or reply to this email.</p>
                </div>
            </body>
            </html>
        ";
    }
}