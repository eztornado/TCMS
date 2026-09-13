<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Taxonomías (categorías/etiquetas) aplicables a cualquier contenido.
     */
    public function up(): void
    {
        Schema::create('taxonomies', function (Blueprint $table) {
            $table->id();
            // null = taxonomía global aplicable a cualquier modelo taxonomizable.
            $table->foreignId('custom_model_id')->nullable()->constrained('custom_models')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('plural_label')->nullable();
            $table->boolean('is_hierarchical')->default(true);
            $table->timestamps();
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('parent_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['taxonomy_id', 'slug']);
        });

        Schema::create('termgables', function (Blueprint $table) {
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('termgable_type');
            $table->unsignedBigInteger('termgable_id')->index();
            $table->timestamps();

            $table->unique(['term_id', 'termgable_type', 'termgable_id'], 'termgables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('termgables');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('taxonomies');
    }
};
