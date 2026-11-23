<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('store_id')
                ->nullable()
                ->after('seller_id')
                ->constrained('stores')
                ->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('store_id')
                ->nullable()
                ->after('seller_id')
                ->constrained('stores')
                ->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('store_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('stores')
                ->nullOnDelete();
        });

        $existingOfficial = DB::table('stores')->where('slug', 'xhopy-official')->first();
        if ($existingOfficial === null) {
            $firstAdminId = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');
            $now = now();
            $storeId = DB::table('stores')->insertGetId([
                'owner_user_id' => $firstAdminId,
                'name' => 'XHopy Official',
                'slug' => 'xhopy-official',
                'description' => 'Official flagship storefront operated by XHopy.',
                'status' => 'active',
                'is_official' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $storeId = (int) $existingOfficial->id;
        }

        DB::table('products')->whereNull('store_id')->update(['store_id' => $storeId]);
        DB::table('order_items')->whereNull('store_id')->update(['store_id' => $storeId]);
        DB::table('stock_movements')->whereNull('store_id')->update(['store_id' => $storeId]);
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('store_id');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('store_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
