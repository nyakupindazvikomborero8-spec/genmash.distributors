<?php
/**
 * Product Model
 */

class Product extends Model
{
    protected static string $table = 'products';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'category_id', 'sku', 'name', 'slug', 'short_description', 'description',
        'specifications', 'price_usd', 'price_zwg', 'cost_price_usd', 'cost_price_zwg',
        'currency', 'stock_quantity', 'low_stock_threshold', 'track_inventory',
        'allow_backorder', 'weight', 'dimensions', 'is_active', 'is_featured',
        'is_digital', 'requires_shipping', 'tax_class', 'meta_title', 'meta_description',
        'meta_keywords', 'published_at',
    ];
    protected static array $hidden = ['cost_price_usd', 'cost_price_zwg'];
    protected static array $casts = [
        'category_id' => 'integer',
        'specifications' => 'json',
        'price_usd' => 'float',
        'price_zwg' => 'float',
        'cost_price_usd' => 'float',
        'cost_price_zwg' => 'float',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'track_inventory' => 'boolean',
        'allow_backorder' => 'boolean',
        'weight' => 'float',
        'dimensions' => 'json',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_digital' => 'boolean',
        'requires_shipping' => 'boolean',
    ];

    public function category(): ?Category
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function images(): array
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    public function primaryImage(): ?ProductImage
    {
        return ProductImage::query()
            ->where('product_id', $this->id)
            ->where('is_primary', true)
            ->first();
    }

    public function variants(): array
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function orderItems(): array
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    public function getPrice(string $currency = 'USD'): float
    {
        $currency = strtoupper($currency);
        if ($currency === 'ZWG') {
            return (float)$this->price_zwg;
        }
        return (float)$this->price_usd;
    }

    public function getFormattedPrice(string $currency = 'USD'): string
    {
        $price = $this->getPrice($currency);
        $symbol = $currency === 'ZWG' ? 'ZWG$' : '$';
        return $symbol . number_format($price, 2, '.', ',');
    }

    public function isInStock(): bool
    {
        if (!$this->track_inventory) {
            return true;
        }
        return $this->stock_quantity > 0;
    }

    public function isLowStock(): bool
    {
        if (!$this->track_inventory) {
            return false;
        }
        return $this->stock_quantity <= $this->low_stock_threshold && $this->stock_quantity > 0;
    }

    public function isOutOfStock(): bool
    {
        if (!$this->track_inventory) {
            return false;
        }
        return $this->stock_quantity <= 0;
    }

    public function getStockStatus(): string
    {
        if (!$this->track_inventory) {
            return 'in_stock';
        }
        if ($this->stock_quantity <= 0) {
            return $this->allow_backorder ? 'backorder' : 'out_of_stock';
        }
        if ($this->stock_quantity <= $this->low_stock_threshold) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    public function decreaseStock(int $quantity, string $referenceType = null, int $referenceId = null, int $userId = null): bool
    {
        if (!$this->track_inventory || !$this->allow_backorder && $this->stock_quantity < $quantity) {
            return false;
        }

        $this->stock_quantity -= $quantity;
        $result = $this->save();

        if ($result) {
            InventoryMovement::create([
                'product_id' => $this->id,
                'type' => 'out',
                'quantity' => $quantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => $userId,
                'notes' => 'Stock decreased for ' . ($referenceType ?? 'order'),
            ]);
        }

        return $result;
    }

    public function increaseStock(int $quantity, string $referenceType = null, int $referenceId = null, int $userId = null): bool
    {
        if (!$this->track_inventory) {
            return true;
        }

        $this->stock_quantity += $quantity;
        $result = $this->save();

        if ($result) {
            InventoryMovement::create([
                'product_id' => $this->id,
                'type' => 'in',
                'quantity' => $quantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => $userId,
                'notes' => 'Stock increased for ' . ($referenceType ?? 'restock'),
            ]);
        }

        return $result;
    }

    public function reserveStock(int $quantity, string $referenceType = null, int $referenceId = null): bool
    {
        if (!$this->track_inventory || $this->stock_quantity < $quantity) {
            return false;
        }

        // Create a reserved movement record
        return InventoryMovement::create([
            'product_id' => $this->id,
            'type' => 'reserved',
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => 'Stock reserved for ' . ($referenceType ?? 'order'),
        ]) !== false;
    }

    public function releaseReservedStock(int $quantity, string $referenceType = null, int $referenceId = null): bool
    {
        // Find and update the reserved movement
        $movement = InventoryMovement::query()
            ->where('product_id', $this->id)
            ->where('type', 'reserved')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->first();

        if ($movement) {
            $movement->type = 'released';
            $movement->quantity = -$quantity; // Negative to indicate release
            $movement->notes = 'Reserved stock released';
            return $movement->save();
        }

        return false;
    }

    public static function getFeatured(int $limit = 8): array
    {
        return static::query()
            ->where('is_active', true)
            ->where('is_featured', true)
            ->where('published_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function search(string $query, array $filters = [], int $limit = 20): array
    {
        $builder = static::query()
            ->where('is_active', true)
            ->where('published_at', '<=', date('Y-m-d H:i:s'));

        if (!empty($query)) {
            $builder->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%")
                  ->orWhere('short_description', 'LIKE', "%{$query}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $builder->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['min_price'])) {
            $currency = $filters['currency'] ?? 'USD';
            $builder->where("price_{$currency}", '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $currency = $filters['currency'] ?? 'USD';
            $builder->where("price_{$currency}", '<=', $filters['max_price']);
        }

        if (!empty($filters['in_stock'])) {
            $builder->where('stock_quantity', '>', 0);
        }

        if (!empty($filters['featured'])) {
            $builder->where('is_featured', true);
        }

        $sort = $filters['sort'] ?? 'newest';
        switch ($sort) {
            case 'price_asc':
                $currency = $filters['currency'] ?? 'USD';
                $builder->orderBy("price_{$currency}", 'asc');
                break;
            case 'price_desc':
                $currency = $filters['currency'] ?? 'USD';
                $builder->orderBy("price_{$currency}", 'desc');
                break;
            case 'name_asc':
                $builder->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $builder->orderBy('name', 'desc');
                break;
            case 'newest':
            default:
                $builder->orderBy('published_at', 'desc');
                break;
        }

        return $builder->limit($limit)->get();
    }
}