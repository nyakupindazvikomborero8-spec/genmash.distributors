<?php
/**
 * Category Controller (API)
 */

class CategoryController extends Controller
{
    public function index(): void
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $this->success([
            'categories' => array_map(fn($c) => $this->formatCategory($c), $categories),
        ]);
    }

    public function tree(): void
    {
        $tree = Category::getActiveTree();
        
        $this->success([
            'categories' => array_map(fn($c) => $this->formatCategoryTree($c), $tree),
        ]);
    }

    public function show(string $slug): void
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$category) {
            $this->notFound('Category not found');
        }

        $query = $category->products();
        
        // Apply filters
        $currency = $this->getQuery('currency', 'USD');
        $sort = $this->getQuery('sort', 'newest');
        
        switch ($sort) {
            case 'price_asc':
                $query = collect($query)->sortBy(fn($p) => $p->getPrice($currency))->values()->all();
                break;
            case 'price_desc':
                $query = collect($query)->sortByDesc(fn($p) => $p->getPrice($currency))->values()->all();
                break;
            case 'name_asc':
                $query = collect($query)->sortBy('name')->values()->all();
                break;
            case 'name_desc':
                $query = collect($query)->sortByDesc('name')->values()->all();
                break;
            case 'newest':
            default:
                $query = collect($query)->sortByDesc('published_at')->values()->all();
                break;
        }

        $limit = (int)$this->getQuery('limit', 20);
        $query = array_slice($query, 0, $limit);

        $this->success([
            'category' => $this->formatCategoryDetail($category),
            'products' => array_map(fn($p) => $this->formatProduct($p, $currency), $query),
        ]);
    }

    private function formatCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => $category->image,
            'icon' => $category->icon,
            'product_count' => $category->getActiveProductsCount(),
            'is_featured' => $category->is_featured,
            'parent_id' => $category->parent_id,
        ];
    }

    private function formatCategoryDetail(Category $category): array
    {
        $children = $category->children();
        
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => $category->image,
            'icon' => $category->icon,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'meta_keywords' => $category->meta_keywords,
            'product_count' => $category->getActiveProductsCount(),
            'is_featured' => $category->is_featured,
            'parent_id' => $category->parent_id,
            'children' => array_map(fn($c) => $this->formatCategory($c), $children),
        ];
    }

    private function formatCategoryTree(Category $category): array
    {
        $formatted = $this->formatCategory($category);
        
        if (!empty($category->children_list)) {
            $formatted['children'] = array_map(fn($c) => $this->formatCategoryTree($c), $category->children_list);
        }
        
        return $formatted;
    }

    private function formatProduct(Product $product, string $currency): array
    {
        $image = $product->primaryImage();
        
        return [
            'id' => $product->id,
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
        ];
    }
}