<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infraestructura de sincronización.
 *
 * sync_log: registro append-only de cambios del CENTRAL. El id autoincremental
 * es el cursor de pull de cada device (devices.last_pull_cursor): nunca se
 * borra una fila sin que todos los devices hayan pasado de ella (retención 30
 * días; un device fuera de rango hace resync).
 *
 * sync_tombstones: origen de replays para devices nuevos (pull inicial):
 * qué filas se borraron y cuándo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('table_name')->index();
            $table->uuid('uuid')->index();
            $table->string('op', 10); // upsert | delete
            $table->json('payload')->nullable(); // null en delete
            $table->uuid('origin_device_uuid')->nullable()->index(); // push desde device
            $table->timestamps();
        });

        Schema::create('sync_tombstones', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->index();
            $table->uuid('uuid');
            $table->uuid('origin_device_uuid')->nullable();
            $table->timestamp('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_tombstones');
        Schema::dropIfExists('sync_log');
    }
};
