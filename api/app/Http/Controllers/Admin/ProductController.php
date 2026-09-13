<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Shop\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with(['cover', 'variants', 'terms.taxonomy'])
            ->when($request->q, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->term_id, fn ($q, $term) => $q->whereHas(
                'terms',
                fn ($t) => $t->where('terms.id', (int) $term),
            ))
            ->sortableBy(['id', 'title', 'price_cents', 'stock', 'created_at'], $request)
            ->paginate($request->integer('per_page', 25));

        return ProductResource::collection($products);
    }

    public function store(Request $request): ProductResource
    {
        $data = $this->validated($request);
        $data['slug'] ??= SlugService::from($data['title'], 'products');
        $data['user_id'] = auth()->id();

        $product = DB::transaction(function () use ($request, $data) {
            /** @var Product $product */
            $product = Product::create($data);
            $this->syncVariants($product, $request->input('variants', []));
            $product->terms()->sync($request->input('term_ids', []));
            $this->syncMedia($product, $request);

            return $product;
        });

        return new ProductResource($product->load(['cover', 'gallery', 'variants', 'terms.taxonomy']));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['cover', 'gallery', 'variants', 'terms.taxonomy', 'taxRate']));
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $data = $this->validated($request, $product);

        DB::transaction(function () use ($request, $product, $data) {
            $product->update($data);
            $this->syncVariants($product, $request->input('variants', []));
            if ($request->has('term_ids')) {
                $product->terms()->sync($request->input('term_ids', []));
            }
            $this->syncMedia($product, $request);
        });

        return new ProductResource($product->refresh()->load(['cover', 'gallery', 'variants', 'terms.taxonomy']));
    }

    public function destroy(Product $product): JsonResponse
    {
        // Soft delete: el histórico de pedidos conserva el snapshot.
        $product->delete();

        return response()->json(['message' => 'Producto eliminado.']);
    }

    protected function validated(Request $request, ?Product $existing = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255',
                Rule::unique('products', 'slug')->ignore($existing?->id)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'status' => [Rule::in(['draft', 'active', 'archived'])],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($existing?->id)],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'compare_at_cents' => ['nullable', 'integer', 'min:0'],
            'stock' => ['integer', 'min:0'],
            'track_stock' => ['boolean'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['boolean'],
            'tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')],
            'term_ids' => ['array'],
            'cover_media_id' => ['nullable', 'integer'],
            'gallery_media_ids' => ['array'],
            'variants' => ['array'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:64'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.price_cents' => ['required_with:variants', 'integer', 'min:0'],
        ]);
    }

    /** Adjunta portada y galería por colección. */
    protected function syncMedia(Product $product, Request $request): void
    {
        if ($request->has('cover_media_id')) {
            $ids = array_filter([$request->integer('cover_media_id')]);
            $product->cover()->sync(
                collect($ids)->mapWithKeys(fn ($id, $i) => [$id => ['collection_name' => 'cover', 'sort' => $i]])->all(),
            );
        }

        if ($request->has('gallery_media_ids')) {
            $ids = collect($request->input('gallery_media_ids', []))->map(intval(...))->filter()->values();
            $product->gallery()->sync(
                $ids->mapWithKeys(fn ($id, $i) => [$id => ['collection_name' => 'gallery', 'sort' => $i]])->all(),
            );
        }
    }

    /** Sincroniza variantes: actualiza existentes por sku, crea nuevas, borra ausentes. */
    protected function syncVariants(Product $product, array $variants): void
    {
        if ($variants === []) {
            return;
        }

        DB::transaction(function () use ($product, $variants) {
            $keep = [];

            foreach (array_values($variants) as $index => $variant) {
                $payload = [...collect($variant)->except(['id'])->all(), 'sort' => $index];
                $existing = $product->variants()->where('sku', $variant['sku'])->first();

                $model = $existing
                    ? tap($existing)->update($payload)
                    : $product->variants()->create($payload);

                $keep[] = $model->id;
            }

            $product->variants()->whereNotIn('id', $keep)->delete();
        });
    }
}
