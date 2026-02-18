<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminCustomerController extends Controller
{
    // GET /admin/customers
    public function index(Request $request)
    {
        $query = User::with('customer')
            ->withCount('orders')
            ->withSum('orders', 'total_amount')
            ->whereHas('roles', fn($q) => $q->where('slug', 'customer'));

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $customers = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $customers->map(fn($c) => $this->formatCustomer($c)),
        ]);
    }

    // GET /admin/customers/{id}
    public function show($id)
    {
        $user = User::with(['customer', 'orders.orderItems.product', 'reviews'])
            ->withCount('orders')
            ->withSum('orders', 'total_amount')
            ->findOrFail($id);

        return response()->json([
            'success'  => true,
            'customer' => $this->formatCustomer($user, detailed: true),
        ]);
    }

    // PUT /admin/customers/{id}
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'email'     => 'sometimes|required|email|unique:users,email,' . $id,
            'phone'     => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'success'  => true,
            'message'  => 'Customer updated successfully',
            'customer' => $this->formatCustomer($user->load('customer')),
        ]);
    }

    // DELETE /admin/customers/{id}
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }

    // ─── Private helper ───────────────────────────────────────────────────────

    private function formatCustomer(User $user, bool $detailed = false): array
    {
        $base = [
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'is_active'    => $user->is_active,
            'created_at'   => $user->created_at,
            'orders_count' => $user->orders_count ?? 0,
            'total_spent'  => $user->orders_sum_total_amount ?? 0,
            'address' => $user->customer ? [
                'address' => $user->customer->address,
                'city'    => $user->customer->city,
                'state'   => $user->customer->state,
                'country' => $user->customer->country,
                'pincode' => $user->customer->pincode,
            ] : null,
        ];

        if ($detailed) {
            $base['orders'] = $user->orders->map(fn($order) => [
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'order_status' => $order->order_status,
                'created_at'   => $order->created_at,
            ])->toArray();

            $base['reviews'] = $user->reviews->map(fn($review) => [
                'id'         => $review->id,
                'rating'     => $review->rating,
                'comment'    => $review->comment,
                'created_at' => $review->created_at,
            ])->toArray();
        }

        return $base;
    }
}