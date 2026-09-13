<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Metadatos de presentación para roles (nombre visible editable).
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->text('description')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['label', 'description']);
        });
    }
};
