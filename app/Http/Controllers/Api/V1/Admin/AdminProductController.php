<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $product = Product::with(['category', 'images', 'options.values'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'product' => $this->formatProduct($product, detailed: true),
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
            // Options
            'options'                              => 'nullable|array',
            'options.*.name'                       => 'required|string|max:255',
            'options.*.type'                       => 'required|in:radio,dropdown,number,checkbox',
            'options.*.is_required'                => 'boolean',
            'options.*.min_value'                  => 'nullable|integer|min:0',
            'options.*.max_value'                  => 'nullable|integer|min:0',
            'options.*.price_per_unit'             => 'nullable|numeric|min:0',
            'options.*.values'                     => 'nullable|array',
            'options.*.values.*.label'             => 'required|string|max:255',
            'options.*.values.*.value'             => 'nullable|string|max:255',
            'options.*.values.*.price_modifier'    => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
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

            if (!empty($validated['options'])) {
                $this->syncOptions($product, $validated['options']);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'product' => $this->formatProduct(
                $product->load('category', 'images', 'options.values'),
                detailed: true
            ),
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
            // Options — if present, full sync
            'options'                              => 'sometimes|nullable|array',
            'options.*.id'                         => 'nullable|integer|exists:product_options,id',
            'options.*.name'                       => 'required|string|max:255',
            'options.*.type'                       => 'required|in:radio,dropdown,number,checkbox',
            'options.*.is_required'                => 'boolean',
            'options.*.min_value'                  => 'nullable|integer|min:0',
            'options.*.max_value'                  => 'nullable|integer|min:0',
            'options.*.price_per_unit'             => 'nullable|numeric|min:0',
            'options.*.values'                     => 'nullable|array',
            'options.*.values.*.id'                => 'nullable|integer|exists:product_option_values,id',
            'options.*.values.*.label'             => 'required|string|max:255',
            'options.*.values.*.value'             => 'nullable|string|max:255',
            'options.*.values.*.price_modifier'    => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $updateData = [];
            if (isset($validated['name']))        $updateData['name']        = $validated['name'];
            if (array_key_exists('description', $validated)) $updateData['description'] = $validated['description'];
            if (isset($validated['price']))       $updateData['price']       = $validated['price'];
            if (array_key_exists('sale_price', $validated))  $updateData['sale_price']  = $validated['sale_price'];
            elseif (array_key_exists('compare_at_price', $validated)) $updateData['sale_price'] = $validated['compare_at_price'];
            if (isset($validated['sku']))         $updateData['sku']         = $validated['sku'];
            if (isset($validated['stock']))       $updateData['stock']       = $validated['stock'];
            if (isset($validated['category_id'])) $updateData['category_id'] = $validated['category_id'];
            if (isset($validated['status']))      $updateData['status']      = $validated['status'] === 'active';

            $product->update($updateData);

            if (array_key_exists('options', $validated)) {
                $this->syncOptions($product, $validated['options'] ?? []);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'product' => $this->formatProduct(
                $product->load('category', 'images', 'options.values'),
                detailed: true
            ),
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
        $original = Product::with(['images', 'options.values'])->findOrFail($id);

        DB::beginTransaction();
        try {
            $duplicate         = $original->replicate();
            $duplicate->name   = $original->name . ' (Copy)';
            $duplicate->slug   = Str::slug($original->name) . '-copy-' . Str::random(6);
            $duplicate->sku    = $original->sku ? $original->sku . '-' . Str::random(4) : null;
            $duplicate->stock  = 0;
            $duplicate->status = false;
            $duplicate->save();

            // Duplicate options + values
            foreach ($original->options as $option) {
                $newOption = $duplicate->options()->create([
                    'name'          => $option->name,
                    'type'          => $option->type,
                    'is_required'   => $option->is_required,
                    'min_value'     => $option->min_value,
                    'max_value'     => $option->max_value,
                    'price_per_unit'=> $option->price_per_unit,
                ]);
                foreach ($option->values as $value) {
                    $newOption->values()->create([
                        'label'          => $value->label,
                        'value'          => $value->value,
                        'price_modifier' => $value->price_modifier,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Product duplicated successfully',
            'product' => $this->formatProduct(
                $duplicate->load('category', 'images', 'options.values'),
                detailed: true
            ),
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
        $image      = ProductImage::where('product_id', $id)->findOrFail($imageId);
        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary) {
            ProductImage::where('product_id', $id)->first()?->update(['is_primary' => true]);
        }

        return response()->json(['success' => true, 'message' => 'Image removed']);
    }

    // ─── Options CRUD ─────────────────────────────────────────────────────────

    // GET /admin/products/{id}/options
    public function getOptions($id)
    {
        $product = Product::with('options.values')->findOrFail($id);

        return response()->json([
            'success' => true,
            'options' => $product->options->map(fn($o) => $this->formatOption($o)),
        ]);
    }

    // POST /admin/products/{id}/options
    public function storeOption(Request $request, $id)
    {
        $product   = Product::findOrFail($id);
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:radio,dropdown,number,checkbox',
            'is_required'    => 'boolean',
            'min_value'      => 'nullable|integer|min:0',
            'max_value'      => 'nullable|integer|min:0',
            'price_per_unit' => 'nullable|numeric|min:0',
            'values'                      => 'nullable|array',
            'values.*.label'              => 'required|string|max:255',
            'values.*.value'              => 'nullable|string|max:255',
            'values.*.price_modifier'     => 'nullable|numeric|min:0',
        ]);

        $option = $product->options()->create([
            'name'           => $validated['name'],
            'type'           => $validated['type'],
            'is_required'    => $validated['is_required'] ?? false,
            'min_value'      => $validated['min_value'] ?? null,
            'max_value'      => $validated['max_value'] ?? null,
            'price_per_unit' => $validated['price_per_unit'] ?? null,
        ]);

        foreach ($validated['values'] ?? [] as $val) {
            $option->values()->create([
                'label'          => $val['label'],
                'value'          => $val['value'] ?? $val['label'],
                'price_modifier' => $val['price_modifier'] ?? 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Option created',
            'option'  => $this->formatOption($option->load('values')),
        ], 201);
    }

    // PUT /admin/products/{id}/options/{optionId}
    public function updateOption(Request $request, $id, $optionId)
    {
        $option    = ProductOption::where('product_id', $id)->findOrFail($optionId);
        $validated = $request->validate([
            'name'           => 'sometimes|required|string|max:255',
            'type'           => 'sometimes|required|in:radio,dropdown,number,checkbox',
            'is_required'    => 'boolean',
            'min_value'      => 'nullable|integer|min:0',
            'max_value'      => 'nullable|integer|min:0',
            'price_per_unit' => 'nullable|numeric|min:0',
            'values'                      => 'sometimes|nullable|array',
            'values.*.id'                 => 'nullable|integer|exists:product_option_values,id',
            'values.*.label'              => 'required|string|max:255',
            'values.*.value'              => 'nullable|string|max:255',
            'values.*.price_modifier'     => 'nullable|numeric|min:0',
        ]);

        $option->update(array_filter([
            'name'           => $validated['name'] ?? null,
            'type'           => $validated['type'] ?? null,
            'is_required'    => $validated['is_required'] ?? null,
            'min_value'      => $validated['min_value'] ?? null,
            'max_value'      => $validated['max_value'] ?? null,
            'price_per_unit' => $validated['price_per_unit'] ?? null,
        ], fn($v) => $v !== null));

        if (array_key_exists('values', $validated)) {
            $this->syncOptionValues($option, $validated['values'] ?? []);
        }

        return response()->json([
            'success' => true,
            'message' => 'Option updated',
            'option'  => $this->formatOption($option->fresh('values')),
        ]);
    }

    // DELETE /admin/products/{id}/options/{optionId}
    public function destroyOption($id, $optionId)
    {
        $option = ProductOption::where('product_id', $id)->findOrFail($optionId);
        $option->values()->delete();
        $option->delete();

        return response()->json(['success' => true, 'message' => 'Option deleted']);
    }

    // ─── Sync Helpers ─────────────────────────────────────────────────────────

    private function syncOptions(Product $product, array $options): void
    {
        $incomingIds = collect($options)->pluck('id')->filter()->toArray();

        // Soft-delete options not in the incoming list
        $product->options()->whereNotIn('id', $incomingIds)->each(function ($opt) {
            $opt->values()->delete();
            $opt->delete();
        });

        foreach ($options as $i => $optData) {
            $option = isset($optData['id']) ? ProductOption::find($optData['id']) : null;

            $attrs = [
                'name'           => $optData['name'],
                'type'           => $optData['type'],
                'is_required'    => $optData['is_required'] ?? false,
                'min_value'      => $optData['min_value'] ?? null,
                'max_value'      => $optData['max_value'] ?? null,
                'price_per_unit' => $optData['price_per_unit'] ?? null,
                'product_id'     => $product->id,
            ];

            if ($option) {
                $option->restore();
                $option->update($attrs);
            } else {
                $option = ProductOption::create($attrs);
            }

            $this->syncOptionValues($option, $optData['values'] ?? []);
        }
    }

    private function syncOptionValues(ProductOption $option, array $values): void
    {
        $incomingIds = collect($values)->pluck('id')->filter()->toArray();

        $option->values()->whereNotIn('id', $incomingIds)->delete();

        foreach ($values as $valData) {
            $attrs = [
                'label'             => $valData['label'],
                'value'             => $valData['value'] ?? $valData['label'],
                'price_modifier'    => $valData['price_modifier'] ?? 0,
                'product_option_id' => $option->id,
            ];

            if (!empty($valData['id'])) {
                $val = ProductOptionValue::find($valData['id']);
                if ($val) {
                    $val->restore();
                    $val->update($attrs);
                    continue;
                }
            }

            ProductOptionValue::create($attrs);
        }
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function formatOption(ProductOption $option): array
    {
        return [
            'id'             => $option->id,
            'name'           => $option->name,
            'type'           => $option->type,
            'is_required'    => $option->is_required,
            'min_value'      => $option->min_value,
            'max_value'      => $option->max_value,
            'price_per_unit' => $option->price_per_unit,
            'values'         => $option->values->map(fn($v) => [
                'id'             => $v->id,
                'label'          => $v->label,
                'value'          => $v->value,
                'price_modifier' => $v->price_modifier,
            ])->values()->toArray(),
        ];
    }

    private function formatProduct(Product $product, bool $detailed = false): array
    {
        $base = [
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

        if ($detailed && $product->relationLoaded('options')) {
            $base['options'] = $product->options
                ->map(fn($o) => $this->formatOption($o))
                ->values()
                ->toArray();
        }

        return $base;
    }
}