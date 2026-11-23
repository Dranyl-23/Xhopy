<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('contact_email', 160)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->boolean('is_official')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('store_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24)->default('owner');
            $table->timestamps();

            $table->unique(['store_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('seller_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->unique()->constrained('stores')->nullOnDelete();
            $table->string('business_name', 160);
            $table->string('business_type', 40)->default('sole_proprietor');
            $table->string('contact_phone', 32);
            $table->string('business_address', 255);
            $table->string('tax_identifier', 64)->nullable();
            $table->string('payout_notes', 255)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_verification_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('original_filename', 255);
            $table->string('disk', 32)->default('local');
            $table->string('path', 512);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index(['seller_profile_id', 'document_type'], 'seller_docs_profile_type_idx');
        });

        Schema::create('seller_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_status_history');
        Schema::dropIfExists('seller_verification_documents');
        Schema::dropIfExists('seller_profiles');
        Schema::dropIfExists('store_memberships');
        Schema::dropIfExists('stores');
    }
};
