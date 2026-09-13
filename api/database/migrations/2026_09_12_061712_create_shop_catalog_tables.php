<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de la tienda: productos, variantes, cupones, impuestos y envíos.
     * Los importes se almacenan SIEMPRE en céntimos (enteros).
     */
    public function up(): void
    {
        // tax_rates va primero: products.tax_rate_id tiene FK hacia aquí.
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 6, 3);                // 21.000 = 21 %
            $table->char('country', 2)->nullable();       // ISO-3166; null = todos
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('description')->nullable();
            $table->string('status')->default('draft')->index(); // draft|active|archived
            $table->string('sku')->nullable()->unique();          // producto simple (sin variantes)
            $table->unsignedBigInteger('price_cents')->nullable();
            $table->unsignedBigInteger('compare_at_cents')->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('track_stock')->default(true);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('name')->nullable();           // "Talla M / Rojo"
            $table->json('options')->nullable();          // {"talla": "M", "color": "Rojo"}
            $table->unsignedBigInteger('price_cents');
            $table->unsignedBigInteger('compare_at_cents')->nullable();
            $table->unsignedBigInteger('cost_cents')->nullable();
            $table->integer('stock')->default(0);
            $table->boolean('track_stock')->default(true);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('flat');      // flat|free|pickup
            $table->unsignedBigInteger('cost_cents')->default(0);
            $table->unsignedBigInteger('free_over_cents')->nullable();
            $table->unsignedBigInteger('min_subtotal_cents')->nullable();
            $table->unsignedBigInteger('max_subtotal_cents')->nullable();
            $table->char('country', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');                       // percentage|fixed
            $table->decimal('percentage', 5, 2)->nullable();
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->unsignedBigInteger('min_subtotal_cents')->nullable();
            $table->json('applies_to')->nullable();       // {"product_ids": []} o {"taxonomy_slugs": []}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
