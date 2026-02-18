<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomRequest;
use Illuminate\Http\Request;

class AdminCustomRequestController extends Controller
{
    // GET /admin/custom-requests
    public function index(Request $request)
    {
        $query = CustomRequest::with('user');

        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', fn($q) => $q->where('name', 'like', '%' . $request->search . '%'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $requests->map(fn($r) => $this->formatRequest($r)),
        ]);
    }

    // GET /admin/custom-requests/{id}
    public function show($id)
    {
        $request = CustomRequest::with('user')->findOrFail($id);

        return response()->json([
            'success' => true,
            'request' => $this->formatRequest($request),
        ]);
    }

    // PUT /admin/custom-requests/{id}
    public function update(Request $request, $id)
    {
        $customRequest = CustomRequest::findOrFail($id);

        $validated = $request->validate([
            'status'     => 'sometimes|required|in:pending,reviewing,approved,rejected,completed',
            'admin_note' => 'nullable|string',
        ]);

        $customRequest->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Custom request updated successfully',
            'request' => $this->formatRequest($customRequest->load('user')),
        ]);
    }

    // DELETE /admin/custom-requests/{id}
    public function destroy($id)
    {
        $customRequest = CustomRequest::findOrFail($id);
        $customRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Custom request deleted successfully',
        ]);
    }

    // ─── Private helper ───────────────────────────────────────────────────────

    private function formatRequest(CustomRequest $customRequest): array
    {
        return [
            'id'              => $customRequest->id,
            'description'     => $customRequest->description,
            'reference_image' => $customRequest->reference_image,
            'budget'          => $customRequest->budget,
            'status'          => $customRequest->status,
            'admin_note'      => $customRequest->admin_note,
            'created_at'      => $customRequest->created_at,
            'updated_at'      => $customRequest->updated_at,
            'customer' => [
                'id'    => $customRequest->user?->id,
                'name'  => $customRequest->user?->name,
                'email' => $customRequest->user?->email,
                'phone' => $customRequest->user?->phone,
            ],
        ];
    }
}