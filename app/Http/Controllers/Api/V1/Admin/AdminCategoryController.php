<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    // GET /admin/categories
    public function index(Request $request)
    {
        $query = Category::withCount('products')
            ->with('parent');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status === 'active');
        }

        $categories = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $categories->map(fn($c) => $this->formatCategory($c)),
        ]);
    }

    // GET /admin/categories/{id}
    public function show($id)
    {
        $category = Category::withCount('products')
            ->with(['parent', 'children'])
            ->findOrFail($id);

        return response()->json([
            'success'  => true,
            'category' => $this->formatCategory($category, detailed: true),
        ]);
    }

    // POST /admin/categories
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'status'      => 'boolean',
        ]);

        $category = Category::create([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'parent_id'   => $validated['parent_id'] ?? null,
            'status'      => $validated['status'] ?? true,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Category created successfully',
            'category' => $this->formatCategory($category->load('parent')),
        ], 201);
    }

    // PUT /admin/categories/{id}
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255|unique:categories,name,' . $id,
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'status'      => 'boolean',
        ]);

        $updateData = [];

        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
            $updateData['slug'] = Str::slug($validated['name']);
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        if (array_key_exists('parent_id', $validated)) {
            $updateData['parent_id'] = $validated['parent_id'];
        }
        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }

        $category->update($updateData);

        return response()->json([
            'success'  => true,
            'message'  => 'Category updated successfully',
            'category' => $this->formatCategory($category->load('parent')),
        ]);
    }

    // DELETE /admin/categories/{id}
    public function destroy($id)
    {
        $category = Category::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing products. Reassign products first.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function formatCategory(Category $category, bool $detailed = false): array
    {
        $base = [
            'id'             => $category->id,
            'name'           => $category->name,
            'slug'           => $category->slug,
            'description'    => $category->description,
            'status'         => $category->status ? 'active' : 'inactive',
            'products_count' => $category->products_count ?? 0,
            'parent'         => $category->parent ? [
                'id'   => $category->parent->id,
                'name' => $category->parent->name,
            ] : null,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];

        if ($detailed && $category->relationLoaded('children')) {
            $base['children'] = $category->children->map(fn($child) => [
                'id'   => $child->id,
                'name' => $child->name,
                'slug' => $child->slug,
            ])->toArray();
        }

        return $base;
    }
}