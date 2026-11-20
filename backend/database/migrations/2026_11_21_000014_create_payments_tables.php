<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('provider', 40)->default('paymongo');
            $table->string('provider_reference', 120)->nullable()->unique();
            $table->string('provider_payment_id', 120)->nullable()->index();
            $table->string('checkout_url', 1024)->nullable();
            $table->string('status', 32)->index();
            $table->string('currency', 3)->default('PHP');
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('amount_centavos');
            $table->string('payment_method_type', 40)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40)->default('paymongo');
            $table->string('provider_event_id', 120)->unique();
            $table->string('event_type', 100)->index();
            $table->string('status', 32)->default('processed');
            $table->json('payload');
            $table->dateTime('processed_at');
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};
