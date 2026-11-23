<?php
/**
 * QuotationItem Model
 */

class QuotationItem extends Model
{
    protected static string $table = 'quotation_items';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'quotation_id', 'product_id', 'product_name', 'product_sku', 'description',
        'quantity', 'unit_price_usd', 'unit_price_zwg', 'discount_percent',
        'discount_usd', 'discount_zwg', 'tax_rate', 'tax_usd', 'tax_zwg',
        'total_usd', 'total_zwg', 'sort_order',
    ];
    protected static array $casts = [
        'quotation_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'unit_price_usd' => 'float',
        'unit_price_zwg' => 'float',
        'discount_percent' => 'float',
        'discount_usd' => 'float',
        'discount_zwg' => 'float',
        'tax_rate' => 'float',
        'tax_usd' => 'float',
        'tax_zwg' => 'float',
        'total_usd' => 'float',
        'total_zwg' => 'float',
        'sort_order' => 'integer',
    ];

    public function quotation(): ?Quotation
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function product(): ?Product
    {
        if (!$this->product_id) return null;
        return Product::find($this->product_id);
    }

    public function calculateTotals(): void
    {
        $baseUsd = $this->unit_price_usd * $this->quantity;
        $baseZwg = $this->unit_price_zwg * $this->quantity;

        $this->discount_usd = $baseUsd * ($this->discount_percent / 100);
        $this->discount_zwg = $baseZwg * ($this->discount_percent / 100);

        $afterDiscountUsd = $baseUsd - $this->discount_usd;
        $afterDiscountZwg = $baseZwg - $this->discount_zwg;

        $this->tax_usd = $afterDiscountUsd * $this->tax_rate;
        $this->tax_zwg = $afterDiscountZwg * $this->tax_rate;

        $this->total_usd = $afterDiscountUsd + $this->tax_usd;
        $this->total_zwg = $afterDiscountZwg + $this->tax_zwg;
    }

    public function save(array $options = []): bool
    {
        $this->calculateTotals();
        return parent::save($options);
    }
}