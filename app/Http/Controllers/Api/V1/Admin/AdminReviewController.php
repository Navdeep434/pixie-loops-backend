<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    // GET /admin/reviews
    public function index(Request $request)
    {
        $query = Review::with(['user', 'product']);

        if ($request->filled('search')) {
            $query->whereHas('user', fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                  ->orWhereHas('product', fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                  ->orWhere('comment', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status === 'active');
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $reviews = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $reviews->map(fn($r) => $this->formatReview($r)),
        ]);
    }

    // GET /admin/reviews/{id}
    public function show($id)
    {
        $review = Review::with(['user', 'product'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'review'  => $this->formatReview($review),
        ]);
    }

    // PUT /admin/reviews/{id} — approve/reject
    public function update(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $review->update([
            'status' => $validated['status'] === 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully',
            'review'  => $this->formatReview($review->load('user', 'product')),
        ]);
    }

    // DELETE /admin/reviews/{id}
    public function destroy($id)
    {
        $review = Review::findOrFail($id);
        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully',
        ]);
    }

    // ─── Private helper ───────────────────────────────────────────────────────

    private function formatReview(Review $review): array
    {
        return [
            'id'         => $review->id,
            'rating'     => $review->rating,
            'comment'    => $review->comment,
            'status'     => $review->status ? 'active' : 'inactive',
            'created_at' => $review->created_at,
            'customer' => [
                'id'    => $review->user?->id,
                'name'  => $review->user?->name,
                'email' => $review->user?->email,
            ],
            'product' => [
                'id'   => $review->product?->id,
                'name' => $review->product?->name,
                'slug' => $review->product?->slug,
            ],
        ];
    }
}