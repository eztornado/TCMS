<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Métricas del panel. Solo calcula lo que el usuario puede ver. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'users' => $user->can('list-users') ? User::count() : null,
                'products' => $user->can('list-products') ? Product::count() : null,
                'orders' => $user->can('list-orders') ? Order::count() : null,
                'pending_orders' => $user->can('list-orders')
                    ? Order::where('status', OrderStatus::Pending->value)->count()
                    : null,
                'revenue_cents' => $user->can('list-orders')
                    ? (int) Order::where('payment_status', PaymentStatus::Paid->value)->sum('total_cents')
                    : null,
                'upcoming_events' => $user->can('list-events')
                    ? Event::published()->upcoming()->count()
                    : null,
                'bookings_last_30_days' => $user->can('list-bookings')
                    ? Booking::where('created_at', '>=', now()->subDays(30))->count()
                    : null,
            ],
        ]);
    }
}
