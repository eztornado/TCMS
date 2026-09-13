<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motor de Custom Models: definición de entidades dinámicas (esquema activo).
     * Cada modelo crea además su propia tabla física mediante CustomModelSchemaService.
     */
    public function up(): void
    {
        Schema::create('custom_models', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->string('plural_label')->nullable();
            $table->string('icon')->default('folder');
            $table->string('table_name')->unique();
            $table->unsignedInteger('per_page')->default(25);
            $table->string('show_field')->default('id');
            $table->boolean('is_active')->default(true);
            $table->boolean('has_status')->default(true);      // estado editorial (draft/published)
            $table->boolean('is_taxonomizable')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('custom_model_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_model_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label');
            $table->string('type'); // text|textarea|richtext|number|decimal|boolean|date|datetime|select|multiselect|media|relation|repeater|slug|color|json
            $table->json('options')->nullable();   // opciones de select, destino de relation, config
            $table->json('rules')->nullable();     // reglas de validación Laravel
            $table->string('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_sortable')->default(true);
            $table->boolean('show_in_list')->default(true);
            $table->boolean('show_in_form')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['custom_model_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_model_fields');
        Schema::dropIfExists('custom_models');
    }
};
