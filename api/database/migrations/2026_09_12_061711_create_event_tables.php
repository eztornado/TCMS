<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sistema de eventos de ocio: eventos con múltiples sesiones y reservas.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('description')->nullable();
            $table->string('status')->default('draft')->index(); // draft|published|archived
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->unsignedInteger('capacity')->nullable();      // aforo por defecto (si no hay sesiones)
            $table->unsignedBigInteger('price_cents')->nullable(); // null = gratuito
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedBigInteger('price_cents')->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->string('status')->default('scheduled')->index(); // scheduled|cancelled
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();            // EV-2026-AB12CD
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->unsignedInteger('seats')->default(1);
            $table->string('status')->default('pending')->index(); // pending|confirmed|cancelled|attended|no_show
            $table->string('payment_status')->default('unpaid');
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('event_sessions');
        Schema::dropIfExists('events');
    }
};
