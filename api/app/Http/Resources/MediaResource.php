<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt,
            'title' => $this->title,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'is_image' => $this->isImage(),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader?->only(['id', 'name'])),
            'created_at' => $this->created_at,
        ];
    }
}
