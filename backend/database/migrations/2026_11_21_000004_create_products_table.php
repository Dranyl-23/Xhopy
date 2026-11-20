<?php

use App\Domain\Catalog\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();
            // Multi-vendor boundary placeholder (D3)
            $table->unsignedBigInteger('seller_id')->nullable()->index();
            $table->string('seller_name', 120)->default('XHopy Official');
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->string('sku', 64)->unique();
            $table->string('short_description', 400)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default(ProductStatus::Draft->value);
            $table->boolean('is_featured')->default(false);
            $table->char('currency', 3)->default('PHP');
            $table->decimal('base_price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->json('specifications')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_featured', 'published_at']);
            $table->index(['category_id', 'status']);
            $table->index(['brand_id', 'status']);
            $table->index('base_price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
