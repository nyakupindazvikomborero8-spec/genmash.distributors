<?php
/**
 * Product Controller (API)
 */

class ProductController extends Controller
{
    public function index(): void
    {
        $query = Product::query()
            ->where('is_active', true)
            ->where('published_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('published_at', 'desc');

        // Category filter
        $categoryId = $this->getQuery('category_id');
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Price range
        $minPrice = $this->getQuery('min_price');
        $maxPrice = $this->getQuery('max_price');
        $currency = $this->getQuery('currency', 'USD');
        
        if ($minPrice) {
            $query->where("price_{$currency}", '>=', $minPrice);
        }
        if ($maxPrice) {
            $query->where("price_{$currency}", '<=', $maxPrice);
        }

        // Stock filter
        if ($this->getQuery('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        // Featured
        if ($this->getQuery('featured')) {
            $query->where('is_featured', true);
        }

        // Sorting
        $sort = $this->getQuery('sort', 'newest');
        switch ($sort) {
            case 'price_asc':
                $query->orderBy("price_{$currency}", 'asc');
                break;
            case 'price_desc':
                $query->orderBy("price_{$currency}", 'desc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('published_at', 'desc');
                break;
        }

        $result = $this->paginate($query);
        
        $this->success([
            'products' => array_map(fn($p) => $this->formatProduct($p, $currency), $result['data']),
            'pagination' => [
                'current_page' => $result['current_page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'last_page' => $result['last_page'],
                'has_more' => $result['has_more_pages'],
            ],
        ]);
    }

    public function featured(): void
    {
        $limit = (int)$this->getQuery('limit', 8);
        $currency = $this->getQuery('currency', 'USD');
        
        $products = Product::getFeatured($limit);
        
        $this->success([
            'products' => array_map(fn($p) => $this->formatProduct($p, $currency), $products),
        ]);
    }

    public function search(): void
    {
        $query = $this->getQuery('q', '');
        $filters = [
            'category_id' => $this->getQuery('category_id'),
            'min_price' => $this->getQuery('min_price'),
            'max_price' => $this->getQuery('max_price'),
            'currency' => $this->getQuery('currency', 'USD'),
            'in_stock' => $this->getQuery('in_stock'),
            'featured' => $this->getQuery('featured'),
            'sort' => $this->getQuery('sort', 'newest'),
        ];
        
        $limit = (int)$this->getQuery('limit', 20);
        
        $products = Product::search($query, $filters, $limit);
        
        $this->success([
            'products' => array_map(fn($p) => $this->formatProduct($p, $filters['currency']), $products),
        ]);
    }

    public function show(string $slug): void
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('published_at', '<=', date('Y-m-d H:i:s'))
            ->first();

        if (!$product) {
            $this->notFound('Product not found');
        }

        $currency = $this->getQuery('currency', 'USD');
        
        $this->success([
            'product' => $this->formatProductDetail($product, $currency),
        ]);
    }

    public function related(string $id): void
    {
        $product = Product::find($id);
        
        if (!$product) {
            $this->notFound('Product not found');
        }

        $currency = $this->getQuery('currency', 'USD');
        $limit = (int)$this->getQuery('limit', 4);
        
        $related = Product::query()
            ->where('is_active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        $this->success([
            'products' => array_map(fn($p) => $this->formatProduct($p, $currency), $related),
        ]);
    }

    private function formatProduct(Product $product, string $currency): array
    {
        $image = $product->primaryImage();
        
        return [
            'id' => $product->id,
            'uuid' => $product->uuid ?? null,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'price' => $product->getPrice($currency),
            'formatted_price' => $product->getFormattedPrice($currency),
            'currency' => $currency,
            'image' => $image?->getUrl() ?? null,
            'stock_status' => $product->getStockStatus(),
            'in_stock' => $product->isInStock(),
            'is_featured' => $product->is_featured,
            'category' => $product->category?->name ?? null,
            'category_slug' => $product->category?->slug ?? null,
        ];
    }

    private function formatProductDetail(Product $product, string $currency): array
    {
        $images = array_map(fn($img) => $img->getUrl(), $product->images());
        $primaryImage = $product->primaryImage()?->getUrl();
        
        $variants = [];
        foreach ($product->variants() as $variant) {
            $variants[] = [
                'id' => $variant->id,
                'name' => $variant->name,
                'attributes' => $variant->attributes,
                'price' => $variant->getPrice($currency),
                'formatted_price' => ($currency === 'ZWG' ? 'ZWG$' : '$') . number_format($variant->getPrice($currency), 2, '.', ','),
                'in_stock' => $variant->stock_quantity > 0 || !$variant->product?->track_inventory,
            ];
        }

        return [
            'id' => $product->id,
            'uuid' => $product->uuid ?? null,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'specifications' => $product->specifications ?? [],
            'price' => $product->getPrice($currency),
            'formatted_price' => $product->getFormattedPrice($currency),
            'currency' => $currency,
            'images' => $images,
            'primary_image' => $primaryImage,
            'stock_quantity' => $product->stock_quantity,
            'stock_status' => $product->getStockStatus(),
            'in_stock' => $product->isInStock(),
            'low_stock_threshold' => $product->low_stock_threshold,
            'track_inventory' => $product->track_inventory,
            'allow_backorder' => $product->allow_backorder,
            'weight' => $product->weight,
            'dimensions' => $product->dimensions,
            'requires_shipping' => $product->requires_shipping,
            'is_featured' => $product->is_featured,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'variants' => $variants,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
        ];
    }
}