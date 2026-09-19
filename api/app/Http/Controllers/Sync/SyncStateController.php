<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\SyncOutbox;
use App\Services\Sync\PushService;
use App\Support\Runtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Endpoints LOCALES del device para la UI del panel (sesión por cookies,
 * contra la BD sqlite embebida). En el central (runtime web) el estado
 * indica que no aplica sincronización.
 */
class SyncStateController extends Controller
{
    /** GET /api/sync/state — estado para el indicador del panel. */
    public function state(Request $request): JsonResponse
    {
        $device = $this->localDevice();

        return response()->json([
            'data' => [
                'runtime' => Runtime::context(),
                'available' => Runtime::isNative(),
                'connected' => ((string) config('tcms.sync.central_url')) !== '',
                'pending' => $device ? SyncOutbox::query()->count() : 0,
                'device' => $device?->only(['uuid', 'label', 'last_synced_at', 'last_pull_cursor']),
                'last_run' => Cache::get('sync.last_run'),
            ],
        ]);
    }

    /** POST /api/sync/run — lanza el ciclo push+pull+files ahora mismo. */
    public function run(Request $request, PushService $sync): JsonResponse
    {
        abort_unless(Runtime::isNative(), 422, 'La sincronización solo aplica en la app nativa.');

        $device = $this->localDevice();

        abort_unless($device !== null, 422, 'Device no configurado todavía.');

        $summary = $sync->sync($device);

        Cache::put('sync.last_run', [
            'at' => now()->toIso8601String(),
            ...$summary,
        ], now()->addDays(7));

        return response()->json(['message' => 'Sincronización completada.', 'data' => $summary]);
    }

    /** Fila local de contexto del device (puede no existir aún). */
    private function localDevice(): ?Device
    {
        $uuid = (string) config('tcms.sync.device_uuid');

        if ($uuid === '') {
            return null;
        }

        return Device::query()->where('uuid', $uuid)->first();
    }
}
