<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // ── Stats ──────────────────────────────────────────────────────────
        $totalRevenue   = Order::where('status', '!=', 'cancelled')->sum('total_amount');
        $totalOrders    = Order::count();
        $totalProducts  = Product::count();
        $totalCustomers = User::role('customer')->count();

        // ── Revenue this month vs last month ───────────────────────────────
        $revenueThisMonth = Order::where('status', '!=', 'cancelled')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        $revenueLastMonth = Order::where('status', '!=', 'cancelled')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('total_amount');

        $revenueGrowth = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : 0;

        // ── Orders this month vs last month ────────────────────────────────
        $ordersThisMonth = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $ordersLastMonth = Order::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        $ordersGrowth = $ordersLastMonth > 0
            ? round((($ordersThisMonth - $ordersLastMonth) / $ordersLastMonth) * 100, 1)
            : 0;

        // ── Recent Orders ──────────────────────────────────────────────────
        $recentOrders = Order::with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($order) => [
                'id'           => $order->id,
                'customer'     => $order->user?->name ?? 'Guest',
                'email'        => $order->user?->email ?? '',
                'total'        => $order->total_amount,
                'status'       => $order->status,
                'created_at'   => $order->created_at,
            ]);

        // ── Top Products ───────────────────────────────────────────────────
        $topProducts = Product::with('images')
            ->withCount('orderItems as sales_count')
            ->withSum('orderItems as revenue', 'price')
            ->orderByDesc('sales_count')
            ->take(5)
            ->get()
            ->map(fn($product) => [
                'id'          => $product->id,
                'name'        => $product->name,
                'sales_count' => $product->sales_count,
                'revenue'     => $product->revenue ?? 0,
                'stock'       => $product->stock,
                'image'       => $product->images->firstWhere('is_primary', true)?->image_url
                              ?? $product->images->first()?->image_url,
            ]);

        // ── Revenue Chart (last 6 months) ──────────────────────────────────
        $revenueChart = collect(range(5, 0))->map(function ($monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            $revenue = Order::where('status', '!=', 'cancelled')
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('total_amount');

            return [
                'month'   => $date->format('M Y'),
                'revenue' => $revenue,
            ];
        });

        // ── Order Status Breakdown ─────────────────────────────────────────
        $orderStatuses = Order::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn($item) => [$item->status => $item->count]);

        // ── Low Stock Alert ────────────────────────────────────────────────
        $lowStockProducts = Product::with('images')
            ->where('stock', '<=', 10)
            ->where('status', true)
            ->orderBy('stock')
            ->take(5)
            ->get()
            ->map(fn($product) => [
                'id'    => $product->id,
                'name'  => $product->name,
                'stock' => $product->stock,
                'image' => $product->images->firstWhere('is_primary', true)?->image_url
                        ?? $product->images->first()?->image_url,
            ]);

        // ── Recent Reviews ─────────────────────────────────────────────────
        $recentReviews = Review::with(['user', 'product'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($review) => [
                'id'         => $review->id,
                'customer'   => $review->user?->name ?? 'Anonymous',
                'product'    => $review->product?->name ?? '',
                'rating'     => $review->rating,
                'comment'    => $review->comment,
                'created_at' => $review->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'stats' => [
                    'total_revenue'    => $totalRevenue,
                    'total_orders'     => $totalOrders,
                    'total_products'   => $totalProducts,
                    'total_customers'  => $totalCustomers,
                    'revenue_growth'   => $revenueGrowth,
                    'orders_growth'    => $ordersGrowth,
                    'revenue_this_month' => $revenueThisMonth,
                    'orders_this_month'  => $ordersThisMonth,
                ],
                'recent_orders'    => $recentOrders,
                'top_products'     => $topProducts,
                'revenue_chart'    => $revenueChart,
                'order_statuses'   => $orderStatuses,
                'low_stock'        => $lowStockProducts,
                'recent_reviews'   => $recentReviews,
            ],
        ]);
    }
}