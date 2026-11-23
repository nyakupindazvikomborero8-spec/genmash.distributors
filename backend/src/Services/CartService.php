<?php
/**
 * Cart Service
 */

class CartService
{
    public static function getCart(): Cart
    {
        $user = AuthService::user();
        
        if ($user) {
            $cart = Cart::getOrCreateForUser($user->id);
            
            // Merge session cart if exists
            $sessionId = session_id();
            $sessionCart = Cart::query()
                ->where('session_id', $sessionId)
                ->where('user_id', null)
                ->first();
            
            if ($sessionCart && $sessionCart->id !== $cart->id) {
                $cart->mergeWith($sessionCart);
            }
            
            return $cart;
        }

        $sessionId = session_id();
        return Cart::getOrCreateForSession($sessionId);
    }

    public static function addToCart(int $productId, int $quantity = 1, int $variantId = null): ?CartItem
    {
        $cart = self::getCart();
        $product = Product::find($productId);
        
        if (!$product || !$product->is_active) {
            throw new Exception('Product not found or unavailable.');
        }

        if ($product->track_inventory && !$product->allow_backorder && $product->stock_quantity < $quantity) {
            throw new Exception('Insufficient stock available.');
        }

        return $cart->addItem($productId, $quantity, $variantId);
    }

    public static function updateQuantity(int $itemId, int $quantity): bool
    {
        $cart = self::getCart();
        return $cart->updateItem($itemId, $quantity);
    }

    public static function removeFromCart(int $itemId): bool
    {
        $cart = self::getCart();
        return $cart->removeItem($itemId);
    }

    public static function clearCart(): bool
    {
        $cart = self::getCart();
        return $cart->clear();
    }

    public static function getCartSummary(): array
    {
        $cart = self::getCart();
        $currency = $cart->currency;
        $items = $cart->items();
        
        $subtotal = 0;
        $itemCount = 0;
        $itemData = [];

        foreach ($items as $item) {
            $price = $currency === 'ZWG' ? $item->price_zwg : $item->price_usd;
            $lineTotal = $price * $item->quantity;
            $subtotal += $lineTotal;
            $itemCount += $item->quantity;

            $itemData[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'name' => $item->product->name ?? 'Unknown Product',
                'sku' => $item->product->sku ?? '',
                'image' => $item->product->primaryImage()?->getUrl() ?? '',
                'quantity' => $item->quantity,
                'price' => $price,
                'formatted_price' => ($currency === 'ZWG' ? 'ZWG$' : '$') . number_format($price, 2, '.', ','),
                'line_total' => $lineTotal,
                'formatted_line_total' => ($currency === 'ZWG' ? 'ZWG$' : '$') . number_format($lineTotal, 2, '.', ','),
                'variant' => $item->variant?->name ?? null,
                'attributes' => $item->variant?->attributes ?? null,
                'stock_status' => $item->product->getStockStatus() ?? 'in_stock',
                'max_quantity' => $item->product->track_inventory && !$item->product->allow_backorder ? $item->product->stock_quantity : null,
            ];
        }

        return [
            'items' => $itemData,
            'item_count' => $itemCount,
            'subtotal' => $subtotal,
            'formatted_subtotal' => ($currency === 'ZWG' ? 'ZWG$' : '$') . number_format($subtotal, 2, '.', ','),
            'currency' => $currency,
            'currency_symbol' => $currency === 'ZWG' ? 'ZWG$' : '$',
        ];
    }

    public static function setCurrency(string $currency): void
    {
        $cart = self::getCart();
        $currency = strtoupper($currency);
        
        if (!in_array($currency, Config::get('currency.supported', ['USD', 'ZWG']))) {
            throw new Exception('Unsupported currency.');
        }

        $cart->currency = $currency;
        $cart->save();
    }

    public static function validateStock(): array
    {
        $cart = self::getCart();
        $issues = [];

        foreach ($cart->items() as $item) {
            $product = $item->product;
            if (!$product) {
                $issues[] = "Product (ID: {$item->product_id}) no longer exists.";
                continue;
            }

            if (!$product->is_active) {
                $issues[] = "{$product->name} is no longer available.";
                continue;
            }

            if ($product->track_inventory && !$product->allow_backorder) {
                if ($product->stock_quantity < $item->quantity) {
                    $issues[] = "{$product->name}: Only {$product->stock_quantity} in stock (you have {$item->quantity} in cart).";
                }
            }
        }

        return $issues;
    }

    public static function getItemCount(): int
    {
        $cart = self::getCart();
        return $cart->getItemCount();
    }

    public static function syncFromArray(array $items): void
    {
        $cart = self::getCart();
        $cart->clear();

        foreach ($items as $item) {
            $cart->addItem(
                $item['product_id'],
                $item['quantity'],
                $item['variant_id'] ?? null
            );
        }
    }
}