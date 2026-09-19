<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devices registrados (instalaciones desktop/móvil) que sincronizan contra
 * este API central. Cada device tiene su token (personal_access_tokens.name
 * guarda el uuid del device) y su cursor de pull del sync log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Nullable: en el device local la fila de contexto se crea antes
            // de saber a qué usuario pertenece (se completa al enlazarlo).
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('platform')->nullable();
            $table->string('app_version')->nullable();
            $table->unsignedBigInteger('last_pull_cursor')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
