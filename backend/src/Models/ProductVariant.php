<?php
/**
 * ProductVariant Model
 */

class ProductVariant extends Model
{
    protected static string $table = 'product_variants';
    protected static string $primaryKey = 'id';
    protected static array $fillable = ['product_id', 'sku', 'name', 'attributes', 'price_usd', 'price_zwg', 'stock_quantity', 'is_active'];
    protected static array $casts = [
        'product_id' => 'integer',
        'attributes' => 'json',
        'price_usd' => 'float',
        'price_zwg' => 'float',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): ?Product
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getPrice(string $currency = 'USD'): float
    {
        $currency = strtoupper($currency);
        $price = $currency === 'ZWG' ? $this->price_zwg : $this->price_usd;
        return $price !== null ? (float)$price : $this->product?->getPrice($currency) ?? 0;
    }
}