<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Store category
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'status' => 'boolean',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'description' => $request->description,
            'status' => $request->status ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Show category
     */
    public function show($id)
    {
        $category = Category::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Update category
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'status' => 'boolean',
        ]);

        if ($request->name) {
            $category->slug = Str::slug($request->name);
        }

        $category->update($request->only([
            'name', 'parent_id', 'description', 'status'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Delete category (soft delete)
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }
}
