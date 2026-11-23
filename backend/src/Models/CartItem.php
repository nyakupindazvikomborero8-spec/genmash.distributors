<?php
/**
 * CartItem Model
 */

class CartItem extends Model
{
    protected static string $table = 'cart_items';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'cart_id', 'product_id', 'variant_id', 'quantity',
        'price_usd', 'price_zwg', 'metadata',
    ];
    protected static array $casts = [
        'cart_id' => 'integer',
        'product_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'integer',
        'price_usd' => 'float',
        'price_zwg' => 'float',
        'metadata' => 'json',
    ];

    public function cart(): ?Cart
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function product(): ?Product
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): ?ProductVariant
    {
        if (!$this->variant_id) return null;
        return ProductVariant::find($this->variant_id);
    }

    public function getLineTotal(string $currency = 'USD'): float
    {
        $price = $currency === 'ZWG' ? $this->price_zwg : $this->price_usd;
        return $price * $this->quantity;
    }

    public function getFormattedLineTotal(string $currency = 'USD'): string
    {
        $total = $this->getLineTotal($currency);
        $symbol = $currency === 'ZWG' ? 'ZWG$' : '$';
        return $symbol . number_format($total, 2, '.', ',');
    }
}