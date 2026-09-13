<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $coupons = Coupon::query()
            ->when($request->q, fn ($q, $search) => $q->where('code', 'like', "%{$search}%"))
            ->sortableBy(['id', 'code', 'usage_count', 'ends_at', 'created_at'], $request)
            ->paginate($request->integer('per_page', 25));

        return AnonymousResourceCollection::make($coupons)->preserveQuery();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['code'] ??= Str::upper(Str::random(8));

        return response()->json(['data' => Coupon::create($data), 'message' => 'Cupón creado.'], 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($this->validated($request, $coupon));

        return response()->json(['data' => $coupon, 'message' => 'Cupón actualizado.']);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json(['message' => 'Cupón eliminado.']);
    }

    protected function validated(Request $request, ?Coupon $existing = null): array
    {
        return $request->validate([
            'code' => ['sometimes', 'string', 'max:32', Rule::unique('coupons', 'code')->ignore($existing?->id)],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'percentage' => ['required_if:type,percentage', 'nullable', 'numeric', 'min:0', 'max:100'],
            'amount_cents' => ['required_if:type,fixed', 'nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'min_subtotal_cents' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
