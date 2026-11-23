<?php
/**
 * Category Model
 */

class Category extends Model
{
    protected static string $table = 'categories';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image', 'icon',
        'sort_order', 'is_active', 'is_featured', 'meta_title', 'meta_description', 'meta_keywords',
    ];
    protected static array $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function parent(): ?Category
    {
        if (!$this->parent_id) return null;
        return Category::find($this->parent_id);
    }

    public function children(): array
    {
        return Category::query()
            ->where('parent_id', $this->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function products(): array
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function getActiveProductsCount(): int
    {
        return Product::query()
            ->where('category_id', $this->id)
            ->where('is_active', true)
            ->count();
    }

    public function getFullPath(): string
    {
        $path = [$this->name];
        $parent = $this->parent();
        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent();
        }
        return implode(' > ', $path);
    }

    public function getUrl(): string
    {
        return '/category/' . $this->slug;
    }

    public static function getActiveTree(): array
    {
        $categories = static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $tree = [];
        $map = [];

        foreach ($categories as $cat) {
            $map[$cat->id] = $cat;
            $cat->children_list = [];
        }

        foreach ($categories as $cat) {
            if ($cat->parent_id && isset($map[$cat->parent_id])) {
                $map[$cat->parent_id]->children_list[] = $cat;
            } else {
                $tree[] = $cat;
            }
        }

        return $tree;
    }
}