<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->string('idempotency_key', 120);
            $table->string('status', 32)->index();
            $table->string('payment_status', 32)->index();
            $table->string('currency', 3)->default('PHP');
            $table->decimal('subtotal_amount', 12, 2);
            $table->decimal('shipping_amount', 12, 2)->default('0.00');
            $table->decimal('discount_amount', 12, 2)->default('0.00');
            $table->decimal('tax_amount', 12, 2)->default('0.00');
            $table->decimal('total_amount', 12, 2);
            $table->string('shipping_recipient_name', 120);
            $table->string('shipping_phone', 32);
            $table->string('shipping_country_code', 2)->default('PH');
            $table->string('shipping_province', 120);
            $table->string('shipping_city', 120);
            $table->string('shipping_barangay', 120);
            $table->string('shipping_postal_code', 20);
            $table->string('shipping_address_line_1', 255);
            $table->string('shipping_address_line_2', 255)->nullable();
            $table->string('shipping_delivery_notes', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedBigInteger('seller_id')->nullable()->index();
            $table->string('seller_name', 120);
            $table->string('product_name', 160);
            $table->string('variant_title', 160)->nullable();
            $table->string('sku', 80);
            $table->string('currency', 3)->default('PHP');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
