<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminContentController extends Controller
{
    // GET /admin/contents
    public function index()
    {
        $contents = Content::latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $contents,
        ]);
    }

    // GET /admin/contents/{key}  ← fetch by key not ID
    public function show($key)
    {
        $content = Content::where('key', $key)->firstOrFail();

        return response()->json([
            'success' => true,
            'content' => $content,
        ]);
    }

    // POST /admin/contents
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key'    => 'required|string|unique:contents,key',
            'title'  => 'nullable|string|max:255',
            'body'   => 'nullable|string',
            'status' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('contents', 'public');
            $validated['image'] = Storage::disk('public')->url($path);
        }

        $content = Content::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Content created successfully',
            'content' => $content,
        ], 201);
    }

    // PUT /admin/contents/{id}
    public function update(Request $request, $id)
    {
        $content = Content::findOrFail($id);

        $validated = $request->validate([
            'title'  => 'nullable|string|max:255',
            'body'   => 'nullable|string',
            'status' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('contents', 'public');
            $validated['image'] = Storage::disk('public')->url($path);
        }

        $content->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Content updated successfully',
            'content' => $content,
        ]);
    }

    // DELETE /admin/contents/{id}
    public function destroy($id)
    {
        $content = Content::findOrFail($id);
        $content->delete();

        return response()->json([
            'success' => true,
            'message' => 'Content deleted successfully',
        ]);
    }
}