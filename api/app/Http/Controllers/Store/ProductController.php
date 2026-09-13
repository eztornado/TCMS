<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Catálogo público (headless). Filtros server-side, nada de "traérselo todo". */
class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->active()
            ->with(['cover', 'variants', 'terms.taxonomy'])
            ->when($request->q, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$search}%")
                ->orWhere('excerpt', 'like', "%{$search}%")))
            ->when($request->term, fn ($q, $term) => $q->whereHas(
                'terms',
                fn ($t) => $t->where('terms.slug', $term),
            ))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->when($request->price_min_cents, fn ($q, $min) => $q->where('price_cents', '>=', $min))
            ->when($request->price_max_cents, fn ($q, $max) => $q->where('price_cents', '<=', $max))
            ->sortableBy(['id', 'title', 'price_cents', 'published_at'], $request)
            ->paginate($request->integer('per_page', 12));

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        abort_unless($product->status === 'active', 404);

        return new ProductResource(
            $product->load(['cover', 'gallery', 'variants', 'terms.taxonomy', 'taxRate']),
        );
    }
}
