<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminProductController extends Controller
{
    // GET /admin/products
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images'])
            ->withCount('orderItems as sales_count');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('name', $request->category));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status === 'active');
        }

        $products = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $products->map(fn($p) => $this->formatProduct($p)),
        ]);
    }

    // GET /admin/products/{id}
    public function show($id)
    {
        $product = Product::with(['category', 'images'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'product' => $this->formatProduct($product),
        ]);
    }

    // POST /admin/products
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'sale_price'  => 'nullable|numeric|min:0',
            'sku'         => 'nullable|string|unique:products,sku',
            'stock'       => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'status'      => 'required|in:active,draft',
        ]);

        $product = Product::create([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']) . '-' . Str::random(6),
            'description' => $validated['description'] ?? null,
            'price'       => $validated['price'],
            'sale_price'  => $validated['sale_price'] ?? null,
            'sku'         => $validated['sku'] ?? null,
            'stock'       => $validated['stock'],
            'category_id' => $validated['category_id'],
            'status'      => $validated['status'] === 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'product' => $this->formatProduct($product->load('category', 'images')),
        ], 201);
    }

    // PUT /admin/products/{id}
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'sometimes|required|string|max:255',
            'description'      => 'nullable|string',
            'price'            => 'sometimes|required|numeric|min:0',
            'sale_price'       => 'nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'sku'              => 'nullable|string|unique:products,sku,' . $id,
            'stock'            => 'sometimes|required|integer|min:0',
            'category_id'      => 'sometimes|required|exists:categories,id',
            'status'           => 'sometimes|required|in:active,draft,archived',
        ]);

        $updateData = [];

        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        if (isset($validated['price'])) {
            $updateData['price'] = $validated['price'];
        }
        if (array_key_exists('sale_price', $validated)) {
            $updateData['sale_price'] = $validated['sale_price'];
        } elseif (array_key_exists('compare_at_price', $validated)) {
            $updateData['sale_price'] = $validated['compare_at_price'];
        }
        if (isset($validated['sku'])) {
            $updateData['sku'] = $validated['sku'];
        }
        if (isset($validated['stock'])) {
            $updateData['stock'] = $validated['stock'];
        }
        if (isset($validated['category_id'])) {
            $updateData['category_id'] = $validated['category_id'];
        }
        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'] === 'active';
        }

        $product->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'product' => $this->formatProduct($product->load('category', 'images')),
        ]);
    }

    // DELETE /admin/products/{id}
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    // POST /admin/products/{id}/duplicate
    public function duplicate($id)
    {
        $original  = Product::with('images')->findOrFail($id);
        $duplicate = $original->replicate();

        $duplicate->name   = $original->name . ' (Copy)';
        $duplicate->slug   = Str::slug($original->name) . '-copy-' . Str::random(6);
        $duplicate->sku    = $original->sku ? $original->sku . '-' . Str::random(4) : null;
        $duplicate->stock  = 0;
        $duplicate->status = false;
        $duplicate->save();

        return response()->json([
            'success' => true,
            'message' => 'Product duplicated successfully',
            'product' => $this->formatProduct($duplicate->load('category', 'images')),
        ]);
    }

    // POST /admin/products/{id}/images
    public function uploadImage(Request $request, $id)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $product   = Product::findOrFail($id);
        $path      = $request->file('image')->store('products', 'public');
        $image_url = Storage::disk('public')->url($path);
        $isPrimary = $product->images()->count() === 0;

        $image = $product->images()->create([
            'image_url'  => $image_url,
            'is_primary' => $isPrimary,
        ]);

        return response()->json([
            'success'    => true,
            'id'         => $image->id,
            'url'        => $image_url,
            'is_primary' => $isPrimary,
        ]);
    }

    // DELETE /admin/products/{id}/images/{imageId}
    public function removeImage($id, $imageId)
    {
        $image     = ProductImage::where('product_id', $id)->findOrFail($imageId);
        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary) {
            ProductImage::where('product_id', $id)->first()?->update(['is_primary' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Image removed',
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function formatProduct(Product $product): array
    {
        return [
            'id'               => $product->id,
            'name'             => $product->name,
            'slug'             => $product->slug,
            'description'      => $product->description,
            'price'            => $product->price,
            'compare_at_price' => $product->sale_price,
            'sale_price'       => $product->sale_price,
            'stock'            => $product->stock,
            'sku'              => $product->sku,
            'status'           => $product->status ? 'active' : 'draft',
            'category'         => $product->category ? [
                'id'   => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'images'      => $product->images->map(fn($img) => [
                'id'         => $img->id,
                'url'        => $img->image_url,
                'is_primary' => $img->is_primary,
            ])->values()->toArray(),
            'sales_count' => $product->sales_count ?? 0,
            'created_at'  => $product->created_at,
            'updated_at'  => $product->updated_at,
        ];
    }
}