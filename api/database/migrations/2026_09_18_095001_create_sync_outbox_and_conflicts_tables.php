<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sync_outbox: cambios locales del device pendientes de push (escritos en la
 * misma transacción que el cambio de dominio). Cada device limpia su outbox
 * según los acks del central.
 *
 * sync_conflicts: auditoría de conflictos LWW (central ganó: el payload
 * central queda registrado; el device converge en el siguiente pull).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->index();
            $table->uuid('uuid');
            $table->string('op', 10);
            $table->json('payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->index();
            $table->uuid('uuid');
            $table->uuid('origin_device_uuid')->nullable();
            $table->timestamp('local_updated_at')->nullable();
            $table->timestamp('central_updated_at')->nullable();
            $table->json('central_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('sync_outbox');
    }
};
