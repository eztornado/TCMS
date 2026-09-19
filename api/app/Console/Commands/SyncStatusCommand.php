<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sync:status {--device= : Uuid del device (o TCMS_SYNC_DEVICE_UUID)}')]
#[Description('Muestra el estado local de sincronización del device')]
class SyncStatusCommand extends Command
{
    public function handle(): int
    {
        $uuid = $this->option('device') ?: config('tcms.sync.device_uuid');

        if (((string) $uuid) === '') {
            $this->warn('Device sin identificar (TCMS_SYNC_DEVICE_UUID).');

            return self::FAILURE;
        }

        $device = Device::query()->where('uuid', $uuid)->first();

        if ($device === null) {
            $this->warn('Device no registrado localmente.');

            return self::FAILURE;
        }

        $this->table(
            ['uuid', 'label', 'último sync', 'cursor'],
            [[
                $device->uuid,
                $device->label,
                $device->last_synced_at?->format('Y-m-d H:i:s') ?? 'nunca',
                $device->last_pull_cursor,
            ]],
        );

        return self::SUCCESS;
    }
}
