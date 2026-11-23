<?php
/**
 * Cart Model
 */

class Cart extends Model
{
    protected static string $table = 'carts';
    protected static string $primaryKey = 'id';
    protected static array $fillable = ['user_id', 'session_id', 'currency', 'expires_at'];
    protected static array $casts = [
        'user_id' => 'integer',
        'expires_at' => 'datetime',
    ];
    protected static array $dates = ['created_at', 'updated_at', 'expires_at'];

    public function user(): ?User
    {
        if (!$this->user_id) return null;
        return User::find($this->user_id);
    }

    public function items(): array
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }

    public function getItemCount(): int
    {
        $items = $this->items();
        return array_sum(array_map(fn($item) => $item->quantity, $items));
    }

    public function getSubtotal(string $currency = 'USD'): float
    {
        $total = 0;
        foreach ($this->items() as $item) {
            $price = $currency === 'ZWG' ? $item->price_zwg : $item->price_usd;
            $total += $price * $item->quantity;
        }
        return $total;
    }

    public function getTotal(string $currency = 'USD'): float
    {
        return $this->getSubtotal($currency);
    }

    public function addItem(int $productId, int $quantity = 1, int $variantId = null): ?CartItem
    {
        // Check if item already exists
        $existing = CartItem::query()
            ->where('cart_id', $this->id)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId ?? 0)
            ->first();

        $product = Product::find($productId);
        if (!$product) {
            return null;
        }

        $priceUsd = $product->price_usd;
        $priceZwg = $product->price_zwg;

        if ($variantId) {
            $variant = ProductVariant::find($variantId);
            if ($variant) {
                $priceUsd = $variant->getPrice('USD');
                $priceZwg = $variant->getPrice('ZWG');
            }
        }

        if ($existing) {
            $existing->quantity += $quantity;
            $existing->save();
            return $existing;
        }

        return CartItem::create([
            'cart_id' => $this->id,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'price_usd' => $priceUsd,
            'price_zwg' => $priceZwg,
        ]);
    }

    public function updateItem(int $itemId, int $quantity): bool
    {
        $item = CartItem::query()
            ->where('cart_id', $this->id)
            ->where('id', $itemId)
            ->first();

        if (!$item) {
            return false;
        }

        if ($quantity <= 0) {
            return $item->delete();
        }

        $item->quantity = $quantity;
        return $item->save();
    }

    public function removeItem(int $itemId): bool
    {
        $item = CartItem::query()
            ->where('cart_id', $this->id)
            ->where('id', $itemId)
            ->first();

        if (!$item) {
            return false;
        }

        return $item->delete();
    }

    public function clear(): bool
    {
        $items = $this->items();
        foreach ($items as $item) {
            $item->delete();
        }
        return true;
    }

    public function mergeWith(Cart $otherCart): void
    {
        foreach ($otherCart->items() as $item) {
            $this->addItem($item->product_id, $item->quantity, $item->variant_id);
        }
        $otherCart->clear();
    }

    public static function getOrCreateForSession(string $sessionId): self
    {
        $cart = static::query()
            ->where('session_id', $sessionId)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();

        if (!$cart) {
            $cart = static::create([
                'session_id' => $sessionId,
                'currency' => Config::get('currency.default', 'USD'),
                'expires_at' => date('Y-m-d H:i:s', time() + 86400 * 30), // 30 days
            ]);
        }

        return $cart;
    }

    public static function getOrCreateForUser(int $userId): self
    {
        $cart = static::query()
            ->where('user_id', $userId)
            ->first();

        if (!$cart) {
            $cart = static::create([
                'user_id' => $userId,
                'currency' => Config::get('currency.default', 'USD'),
                'expires_at' => date('Y-m-d H:i:s', time() + 86400 * 365), // 1 year
            ]);
        }

        return $cart;
    }

    public function associateWithUser(int $userId): void
    {
        $this->user_id = $userId;
        $this->session_id = null;
        $this->expires_at = date('Y-m-d H:i:s', time() + 86400 * 365);
        $this->save();
    }
}