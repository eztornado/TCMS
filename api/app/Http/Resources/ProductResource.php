<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'description' => $this->description,
            'status' => $this->status,
            'sku' => $this->sku,
            'price_cents' => $this->price_cents,
            'compare_at_cents' => $this->compare_at_cents,
            'stock' => $this->stock,
            'track_stock' => $this->track_stock,
            'weight_grams' => $this->weight_grams,
            'is_featured' => $this->is_featured,
            'tax_rate_id' => $this->tax_rate_id,
            'tax_rate' => $this->whenLoaded('taxRate', fn () => $this->taxRate?->only(['id', 'name', 'rate'])),
            'published_at' => $this->published_at,
            'cover' => $this->whenLoaded('cover', fn () => new MediaResource($this->cover->first())),
            'gallery' => $this->whenLoaded('gallery', fn () => MediaResource::collection($this->gallery)),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'name' => $v->name,
                'options' => $v->options,
                'price_cents' => $v->price_cents,
                'compare_at_cents' => $v->compare_at_cents,
                'cost_cents' => $v->cost_cents,
                'stock' => $v->stock,
                'track_stock' => $v->track_stock,
                'is_default' => $v->is_default,
            ])),
            'terms' => $this->whenLoaded('terms', fn () => $this->terms->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'taxonomy' => $t->taxonomy?->slug,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
