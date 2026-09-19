<?php

namespace App\Services\Sync\Contracts;

use App\Models\Device;

/**
 * Transporte entre el device y el central. Dos implementaciones:
 *  - LoopbackSyncGateway: invoca el endpoint central en memoria (tests,
 *    runtime web).
 *  - HttpSyncGateway: HTTP contra config('tcms.sync.central_url') (nativo).
 */
interface SyncGateway
{
    /** Delta desde el último cursor del device (ver SyncEndpoint::pull). */
    public function pull(Device $device): array;

    /**
     * Push de cambios locales del device (ver SyncEndpoint::push).
     *
     * @param  array<int, array{table: string, uuid: string, op: string, payload?: ?array}>  $changes
     * @return array{results: array<int, array{uuid: string, status: string, reason?: string}>}
     */
    public function push(Device $device, array $changes): array;

    /**
     * Canal de ficheros de media: qué uuids le faltan al central.
     *
     * @param  array<int, array{uuid: string, hash?: ?string}>  $files
     * @return array{missing: array<int, string>}
     */
    public function checkMedia(Device $device, array $files): array;

    /** Sube al central los bytes del fichero de un media local. */
    public function uploadMedia(Device $device, string $uuid, string $absolutePath): bool;

    /** Descarga del central los bytes del fichero de un media. */
    public function downloadMedia(Device $device, string $uuid): ?string;
}
