<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShippingMethodController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $methods = ShippingMethod::query()
            ->when($request->boolean('all') !== true, fn ($q) => $q->active())
            ->orderBy('sort')
            ->get();

        return AnonymousResourceCollection::make($methods);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => ShippingMethod::create($this->validated($request)), 'message' => 'Método creado.'], 201);
    }

    public function update(Request $request, ShippingMethod $shippingMethod): JsonResponse
    {
        $shippingMethod->update($this->validated($request));

        return response()->json(['data' => $shippingMethod, 'message' => 'Método actualizado.']);
    }

    public function destroy(ShippingMethod $shippingMethod): JsonResponse
    {
        $shippingMethod->delete();

        return response()->json(['message' => 'Método eliminado.']);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:flat,free,pickup'],
            'cost_cents' => ['integer', 'min:0'],
            'free_over_cents' => ['nullable', 'integer', 'min:0'],
            'min_subtotal_cents' => ['nullable', 'integer', 'min:0'],
            'max_subtotal_cents' => ['nullable', 'integer', 'min:0'],
            'country' => ['nullable', 'string', 'size:2'],
            'is_active' => ['boolean'],
            'sort' => ['integer'],
        ]);
    }
}
