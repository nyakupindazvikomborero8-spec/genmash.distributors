<?php
/**
 * ProductImage Model
 */

class ProductImage extends Model
{
    protected static string $table = 'product_images';
    protected static string $primaryKey = 'id';
    protected static array $fillable = ['product_id', 'image_path', 'alt_text', 'sort_order', 'is_primary'];
    protected static array $casts = [
        'product_id' => 'integer',
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function product(): ?Product
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getUrl(): string
    {
        $baseUrl = Config::get('app.url', '');
        return $baseUrl . '/uploads/products/' . $this->image_path;
    }

    public function setAsPrimary(): void
    {
        ProductImage::query()
            ->where('product_id', $this->product_id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
        
        $this->is_primary = true;
        $this->save();
    }
}