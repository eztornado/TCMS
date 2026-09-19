<?php

namespace App\Services\Sync;

use App\Models\Device;
use App\Services\Sync\Contracts\SyncGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Transporte HTTP real del binario nativo contra el API central. El token de
 * device (ability `sync`) lo guarda la app al enlazarlo (config tcms.sync).
 */
final class HttpSyncGateway implements SyncGateway
{
    public function pull(Device $device): array
    {
        return $this->request('pull', ['device_uuid' => $device->uuid]);
    }

    public function push(Device $device, array $changes): array
    {
        return $this->request('push', [
            'device_uuid' => $device->uuid,
            'changes' => $changes,
        ]);
    }

    public function checkMedia(Device $device, array $files): array
    {
        return $this->request('media/check', [
            'device_uuid' => $device->uuid,
            'files' => $files,
        ]);
    }

    public function uploadMedia(Device $device, string $uuid, string $absolutePath): bool
    {
        $base = $this->baseUrl();

        Http::withToken($this->token())
            ->timeout(120)
            ->attach('file', fopen($absolutePath, 'r') ?: throw new RuntimeException("No se pudo leer [$absolutePath]."), basename($absolutePath))
            ->post($base."/api/sync/media/{$uuid}", ['device_uuid' => $device->uuid])
            ->throw();

        return true;
    }

    public function downloadMedia(Device $device, string $uuid): ?string
    {
        $base = $this->baseUrl();

        $response = Http::withToken($this->token())
            ->timeout(120)
            ->get($base."/api/sync/media/{$uuid}/download", ['device_uuid' => $device->uuid]);

        if ($response->failed()) {
            return null;
        }

        return $response->body();
    }

    private function baseUrl(): string
    {
        $base = rtrim((string) config('tcms.sync.central_url'), '/');

        if ($base === '') {
            throw new RuntimeException('TCMS_SYNC_CENTRAL_URL no está configurada.');
        }

        return $base;
    }

    private function token(): string
    {
        $token = (string) config('tcms.sync.token');

        if ($token === '') {
            throw new RuntimeException('El device no está enlazado (falta TCMS_SYNC_TOKEN).');
        }

        return $token;
    }

    /** @param array<string, mixed> $body */
    private function request(string $action, array $body): array
    {
        $response = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(120)
            ->post($this->baseUrl().'/api/sync/'.$action, $body);

        $response->throw();

        return (array) $response->json();
    }
}
