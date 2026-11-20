<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_contents', function (Blueprint $table): void {
            $table->id();
            $table->string('placement', 40);
            $table->string('tag', 80);
            $table->string('title', 180);
            $table->string('summary', 500)->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_href', 255)->nullable();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();
            $table->string('image_path', 255)->nullable();
            $table->string('image_alt', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['placement', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_contents');
    }
};
