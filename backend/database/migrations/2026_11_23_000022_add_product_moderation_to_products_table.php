<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('moderation_notes', 500)
                ->nullable()
                ->after('status');
            $table->timestamp('submitted_for_review_at')
                ->nullable()
                ->after('published_at');
            $table->timestamp('moderated_at')
                ->nullable()
                ->after('submitted_for_review_at');
            $table->foreignId('moderated_by')
                ->nullable()
                ->after('moderated_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['store_id', 'status'], 'products_store_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_store_status_idx');
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn([
                'moderation_notes',
                'submitted_for_review_at',
                'moderated_at',
            ]);
        });
    }
};
