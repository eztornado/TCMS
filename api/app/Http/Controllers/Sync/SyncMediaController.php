<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Services\Media\MediaService;
use App\Services\Sync\MediaSyncEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Canal de ficheros de media del sync (central). La metadata viaja por el
 * protocolo normal; los bytes por aquí.
 */
class SyncMediaController extends Controller
{
    public function __construct(private readonly MediaSyncEndpoint $media) {}

    /** POST /api/sync/media/check — ¿qué ficheros le faltan al central? */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array'],
            'files.*.uuid' => ['required', 'uuid'],
            'files.*.hash' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json($this->media->missing($data['files']));
    }

    /** POST /api/sync/media/{uuid} — subida de bytes desde el device. */
    public function upload(Request $request, string $uuid): JsonResponse
    {
        $file = $request->validate([
            'file' => ['required', 'file', 'max:'.MediaService::MAX_SIZE_KB],
        ])['file'];

        $media = $this->media->receive($uuid, $file);

        return response()->json([
            'message' => 'Fichero recibido.',
            'media' => $media->only(['uuid', 'hash', 'size']),
        ]);
    }

    /** GET /api/sync/media/{uuid}/download — descarga para el device. */
    public function download(string $uuid): StreamedResponse
    {
        return $this->media->stream($uuid);
    }
}
