<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Sync\SyncEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints del CENTRAL para los devices (apps nativas). Autenticación:
 * token de device con ability `sync` (o sesión de panel en pruebas/manual).
 * El device al que sirve se infiere del token (su name es el uuid del device)
 * o llega en el body para clientes con sesión.
 */
class SyncController extends Controller
{
    public function __construct(private readonly SyncEndpoint $endpoint) {}

    /** GET /api/sync/manifest — qué tablas viajan y con qué política. */
    public function manifest(): JsonResponse
    {
        return response()->json($this->endpoint->manifest());
    }

    /** POST /api/sync/pull — delta desde el último cursor del device. */
    public function pull(Request $request): JsonResponse
    {
        $device = $this->deviceFrom($request);

        return response()->json(
            $this->endpoint->pull(
                $request->user(),
                $device,
                min((int) $request->input('limit', 500), 2000),
            ),
        );
    }

    /** POST /api/sync/push — cambios locales del device con resolución LWW. */
    public function push(Request $request): JsonResponse
    {
        $device = $this->deviceFrom($request);

        $changes = $request->validate([
            'changes' => ['required', 'array'],
            'changes.*.table' => ['required', 'string'],
            'changes.*.uuid' => ['required', 'uuid'],
            'changes.*.op' => ['required', 'in:upsert,delete'],
            'changes.*.payload' => ['nullable', 'array'],
        ])['changes'];

        return response()->json(
            $this->endpoint->push($request->user(), $device, $changes),
        );
    }

    private function deviceFrom(Request $request): Device
    {
        $uuid = $request->user()->currentAccessToken()?->name
            ?? $request->input('device_uuid');

        abort_if(((string) $uuid) === '', 422, 'Device no identificado.');

        return $request->user()->devices()
            ->where('uuid', $uuid)
            ->whereNull('revoked_at')
            ->firstOrFail();
    }
}
