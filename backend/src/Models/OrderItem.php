<?php
/**
 * OrderItem Model
 */

class OrderItem extends Model
{
    protected static string $table = 'order_items';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'order_id', 'product_id', 'variant_id', 'product_name', 'product_sku',
        'variant_name', 'quantity', 'unit_price_usd', 'unit_price_zwg',
        'total_price_usd', 'total_price_zwg', 'tax_rate', 'tax_usd', 'tax_zwg',
        'metadata',
    ];
    protected static array $casts = [
        'order_id' => 'integer',
        'product_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'integer',
        'unit_price_usd' => 'float',
        'unit_price_zwg' => 'float',
        'total_price_usd' => 'float',
        'total_price_zwg' => 'float',
        'tax_rate' => 'float',
        'tax_usd' => 'float',
        'tax_zwg' => 'float',
        'metadata' => 'json',
    ];

    public function order(): ?Order
    {
        return $this->belongsTo(Order::class, 'order_id');
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

    public function getLineTotal(string $currency = null): float
    {
        $currency = $currency ?? $this->order?->currency ?? 'USD';
        return $currency === 'ZWG' ? (float)$this->total_price_zwg : (float)$this->total_price_usd;
    }

    public function getTax(string $currency = null): float
    {
        $currency = $currency ?? $this->order?->currency ?? 'USD';
        return $currency === 'ZWG' ? (float)$this->tax_zwg : (float)$this->tax_usd;
    }
}