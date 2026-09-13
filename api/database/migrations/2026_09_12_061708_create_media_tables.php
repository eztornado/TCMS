<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biblioteca de medios central + adjuntos polimórficos ordenables.
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('file_name');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('mediables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->string('mediable_type');
            $table->unsignedBigInteger('mediable_id')->index();
            $table->string('collection_name')->default('default');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(
                ['media_id', 'mediable_type', 'mediable_id', 'collection_name'],
                'mediables_unique_attachment'
            );
            $table->index(['mediable_type', 'mediable_id', 'collection_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediables');
        Schema::dropIfExists('media');
    }
};
