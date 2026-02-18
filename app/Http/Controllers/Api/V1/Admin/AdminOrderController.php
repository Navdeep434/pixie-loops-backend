<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    // GET /admin/orders
    public function index(Request $request)
    {
        $query = Order::with(['user', 'orderItems.product', 'payment']);

        if ($request->filled('search')) {
            $query->where('order_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', fn($q) => $q->where('name', 'like', '%' . $request->search . '%')
                                                    ->orWhere('email', 'like', '%' . $request->search . '%'));
        }

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->order_status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $orders->map(fn($o) => $this->formatOrder($o)),
        ]);
    }

    // GET /admin/orders/{id}
    public function show($id)
    {
        $order = Order::with(['user', 'orderItems.product.images', 'payment'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'order'   => $this->formatOrder($order, detailed: true),
        ]);
    }

    // PUT /admin/orders/{id}
    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'order_status'   => 'sometimes|required|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'payment_status' => 'sometimes|required|in:pending,paid,failed,refunded',
        ]);

        $order->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'order'   => $this->formatOrder($order->load('user', 'orderItems.product', 'payment')),
        ]);
    }

    // DELETE /admin/orders/{id}
    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
        ]);
    }

    // ─── Private helper ───────────────────────────────────────────────────────

    private function formatOrder(Order $order, bool $detailed = false): array
    {
        $base = [
            'id'               => $order->id,
            'order_number'     => $order->order_number,
            'total_amount'     => $order->total_amount,
            'payment_status'   => $order->payment_status,
            'order_status'     => $order->order_status,
            'shipping_address' => $order->shipping_address,
            'created_at'       => $order->created_at,
            'updated_at'       => $order->updated_at,
            'customer' => [
                'id'    => $order->user?->id,
                'name'  => $order->user?->name,
                'email' => $order->user?->email,
                'phone' => $order->user?->phone,
            ],
            'payment' => $order->payment ? [
                'id'             => $order->payment->id,
                'method'         => $order->payment->payment_method,
                'transaction_id' => $order->payment->transaction_id,
                'amount'         => $order->payment->amount,
                'status'         => $order->payment->status,
            ] : null,
        ];

        if ($detailed) {
            $base['items'] = $order->orderItems->map(fn($item) => [
                'id'       => $item->id,
                'quantity' => $item->quantity,
                'price'    => $item->price,
                'subtotal' => $item->price * $item->quantity,
                'product'  => [
                    'id'    => $item->product?->id,
                    'name'  => $item->product?->name,
                    'sku'   => $item->product?->sku,
                    'image' => $item->product?->images
                        ->firstWhere('is_primary', true)?->image_url
                        ?? $item->product?->images->first()?->image_url,
                ],
            ])->toArray();
        }

        return $base;
    }
}