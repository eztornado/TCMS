<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Mediable;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MediaController extends Controller
{
    public function __construct(private readonly MediaService $mediaService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Media::query()
            ->with('uploader')
            ->when($request->q, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('alt', 'like', "%{$search}%")))
            ->when($request->mime, fn ($q, $mime) => $q->where('mime_type', 'like', "$mime%"))
            ->when($request->boolean('images_only'), fn ($q) => $q->where('mime_type', 'like', 'image/%'))
            ->latest()
            ->paginate($request->integer('per_page', 24));

        return MediaResource::collection($items);
    }

    public function store(Request $request): MediaResource
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.MediaService::MAX_SIZE_KB, 'mimes:'.MediaService::ALLOWED_MIMES],
        ]);

        return new MediaResource($this->mediaService->store($request->file('file')));
    }

    public function update(Request $request, Media $media): MediaResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $media->update($data);

        return new MediaResource($media);
    }

    public function destroy(Media $media): JsonResponse
    {
        // Solo se borra si no está en uso por ninguna entidad.
        $inUse = Mediable::query()->where('media_id', $media->id)->exists();

        if ($inUse) {
            abort(422, 'El fichero está en uso y no puede eliminarse.');
        }

        $media->delete();

        return response()->json(['message' => 'Fichero eliminado.']);
    }
}
