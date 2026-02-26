<?php

namespace App\Http\Controllers\Api\V1\Crochet;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // GET /products
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images'])
            ->where('status', true);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('sort')) {
            match ($request->sort) {
                'price-low'  => $query->orderBy('price', 'asc'),
                'price-high' => $query->orderBy('price', 'desc'),
                'name'       => $query->orderBy('name', 'asc'),
                default      => $query->latest(),
            };
        } else {
            $query->latest();
        }

        $products = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $products->map(fn($p) => $this->formatProductSummary($p)),
        ]);
    }

    // GET /products/{id}
    public function show($id)
    {
        $product = Product::with(['category', 'images', 'options.values'])
            ->where('status', true)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'product' => $this->formatProductDetail($product),
        ]);
    }

    // GET /categories
    public function categories()
    {
        $categories = Category::where('status', true)
            ->whereNull('parent_id')
            ->with(['children' => fn($q) => $q->where('status', true)])
            ->withCount(['products' => fn($q) => $q->where('status', true)])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $categories->map(fn($c) => $this->formatCategory($c)),
        ]);
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function formatProductSummary(Product $product): array
    {
        $primaryImage = $product->images->firstWhere('is_primary', true)
            ?? $product->images->first();

        return [
            'id'          => $product->id,
            'name'        => $product->name,
            'slug'        => $product->slug,
            'description' => $product->description,
            'price'       => $product->price,
            'sale_price'  => $product->sale_price,
            'final_price' => $product->final_price,
            'stock'       => $product->stock,
            'sku'         => $product->sku,
            'in_stock'    => $product->stock > 0,
            'category_id' => $product->category_id,
            'category'    => $product->category ? [
                'id'   => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'image'  => $primaryImage?->image_url,
            'images' => $this->formatImages($product),
        ];
    }

    private function formatProductDetail(Product $product): array
    {
        return array_merge($this->formatProductSummary($product), [
            'options' => $product->options->map(fn($option) => [
                'id'             => $option->id,
                'name'           => $option->name,
                'type'           => $option->type,
                'is_required'    => $option->is_required,
                'min_value'      => $option->min_value,
                'max_value'      => $option->max_value,
                'price_per_unit' => $option->price_per_unit,
                // number type has no predefined values — only price_per_unit
                'values' => in_array($option->type, ['radio', 'dropdown', 'checkbox'])
                    ? $option->values->map(fn($val) => [
                        'id'             => $val->id,
                        'label'          => $val->label,
                        'value'          => $val->value,
                        'price_modifier' => $val->price_modifier,
                    ])->values()->toArray()
                    : [],
            ])->values()->toArray(),
        ]);
    }

    private function formatImages(Product $product): array
    {
        return $product->images->map(fn($img) => [
            'id'         => $img->id,
            'url'        => $img->image_url,
            'is_primary' => $img->is_primary,
        ])->values()->toArray();
    }

    private function formatCategory(Category $category): array
    {
        return [
            'id'       => $category->id,
            'name'     => $category->name,
            'slug'     => $category->slug,
            'children' => $category->children->map(fn($child) => [
                'id'   => $child->id,
                'name' => $child->name,
                'slug' => $child->slug,
            ])->toArray(),
        ];
    }
}