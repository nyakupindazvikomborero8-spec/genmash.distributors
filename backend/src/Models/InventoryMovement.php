<?php
/**
 * InventoryMovement Model
 */

class InventoryMovement extends Model
{
    protected static string $table = 'inventory_movements';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'product_id', 'variant_id', 'type', 'quantity', 'reference_type',
        'reference_id', 'notes', 'user_id',
    ];
    protected static array $casts = [
        'product_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'integer',
        'reference_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function product(): ?Product
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): ?ProductVariant
    {
        if (!$this->variant_id) return null;
        return ProductVariant::find($this->variant_id);
    }

    public function user(): ?User
    {
        if (!$this->user_id) return null;
        return User::find($this->user_id);
    }

    public static function getStockSummary(int $productId): array
    {
        $movements = static::query()
            ->where('product_id', $productId)
            ->get();

        $summary = [
            'in' => 0,
            'out' => 0,
            'adjustment' => 0,
            'reserved' => 0,
            'released' => 0,
        ];

        foreach ($movements as $m) {
            if (isset($summary[$m->type])) {
                $summary[$m->type] += $m->quantity;
            }
        }

        $summary['current'] = $summary['in'] - $summary['out'] + $summary['adjustment'] - $summary['reserved'] + $summary['released'];
        
        return $summary;
    }
}