<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Emisión y revocación de tokens de device para la sincronización de las
 * apps nativas contra el central. Dos caminos de alta:
 *  - POST /auth/device: desde una sesión web ya iniciada (panel).
 *  - POST /auth/device/login: con credenciales, sin cookies (bootstrap
 *    del binario nativo).
 * El token (Sanctum PAT, ability `sync`) lleva como `name` el uuid del
 * device: revocar el device revoca su token.
 */
class DeviceTokenController extends Controller
{
    /** GET /auth/devices — devices del usuario autenticado. */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->devices()
                ->orderByDesc('last_synced_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Device $device) => $this->present($device)),
        ]);
    }

    /** POST /auth/device — alta del device actual desde sesión web. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateDeviceData($request);

        $device = $this->registerDevice($request->user(), $data);

        return response()->json([
            'message' => 'Device registrado.',
            'device' => $this->present($device),
            'token' => $request->user()->createToken($device->uuid, ['sync'])->plainTextToken,
        ], 201);
    }

    /** POST /auth/device/login — alta con credenciales, sin cookies. */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $data = $this->validateDeviceData($request);

        $user = User::query()->where('email', $credentials['email'])->first();

        // Solo email+password: otras claves se convertirían en filtros de
        // búsqueda del provider.
        if (! $user || ! Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'La cuenta está desactivada. Contacta con el administrador.',
            ]);
        }

        $device = $this->registerDevice($user, $data);

        return response()->json([
            'message' => 'Device registrado.',
            'user' => $user->only(['id', 'name', 'email']),
            'device' => $this->present($device),
            'token' => $user->createToken($device->uuid, ['sync'])->plainTextToken,
        ], 201);
    }

    /** DELETE /auth/devices/{device} — revoca el device y todos sus tokens. */
    public function destroy(Request $request, Device $device): JsonResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);

        $device->forceFill(['revoked_at' => now()])->save();
        $request->user()->tokens()->where('name', $device->uuid)->delete();

        return response()->json(['message' => 'Device revocado.']);
    }

    /** @return array{label: string, platform: ?string, app_version: ?string} */
    private function validateDeviceData(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'max:20'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function registerDevice(User $user, array $data): Device
    {
        return $user->devices()->create($data);
    }

    /** @return array<string, mixed> */
    private function present(Device $device): array
    {
        return $device->only([
            'id', 'uuid', 'label', 'platform', 'app_version',
            'last_pull_cursor', 'last_synced_at', 'revoked_at',
        ]);
    }
}
