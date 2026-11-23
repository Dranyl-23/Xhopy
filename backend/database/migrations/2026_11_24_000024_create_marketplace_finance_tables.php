<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->unsignedInteger('rate_bps');
            $table->unsignedSmallInteger('hold_days')->default(7);
            $table->unsignedBigInteger('minimum_payout_centavos')->default(50000);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['store_id', 'is_active']);
        });

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->foreignId('commission_rule_id')
                ->nullable()
                ->after('total_amount')
                ->constrained('commission_rules')
                ->nullOnDelete();
            $table->unsignedInteger('commission_rate_bps')->nullable()->after('commission_rule_id');
            $table->decimal('commission_amount', 12, 2)->default('0.00')->after('commission_rate_bps');
            $table->decimal('net_seller_amount', 12, 2)->default('0.00')->after('commission_amount');
            $table->timestamp('hold_until')->nullable()->after('delivered_at');
        });

        Schema::create('commission_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->unsignedBigInteger('gross_amount_centavos');
            $table->unsignedInteger('rate_bps');
            $table->unsignedBigInteger('commission_centavos');
            $table->unsignedBigInteger('net_seller_centavos');
            $table->string('status', 24)->default('recorded')->index();
            $table->timestamp('recorded_at');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::create('seller_payouts', function (Blueprint $table): void {
            $table->id();
            $table->string('payout_number', 40)->unique();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->char('currency', 3)->default('PHP');
            $table->unsignedBigInteger('amount_centavos');
            $table->string('payout_method', 40);
            $table->string('payout_destination', 255);
            $table->string('request_notes', 255)->nullable();
            $table->string('review_notes', 500)->nullable();
            $table->string('external_reference', 120)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::create('seller_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->restrictOnDelete();
            $table->foreignId('seller_payout_id')->nullable()->constrained('seller_payouts')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entry_type', 40)->index();
            $table->bigInteger('amount_centavos');
            $table->char('currency', 3)->default('PHP');
            $table->string('description', 255);
            $table->string('idempotency_key', 120)->unique();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['store_id', 'entry_type']);
        });

        $now = now();
        $globalRuleId = DB::table('commission_rules')->insertGetId([
            'store_id' => null,
            'rate_bps' => 500,
            'hold_days' => 7,
            'minimum_payout_centavos' => 50000,
            'is_active' => true,
            'effective_from' => $now,
            'created_by' => null,
            'notes' => 'Owner-approved global marketplace commission (5%) with 7-day post-delivery hold and ₱500.00 min withdrawal.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $officialStoreId = DB::table('stores')->where('slug', 'xhopy-official')->value('id');
        $officialRuleId = null;
        if ($officialStoreId !== null) {
            $officialRuleId = DB::table('commission_rules')->insertGetId([
                'store_id' => $officialStoreId,
                'rate_bps' => 0,
                'hold_days' => 7,
                'minimum_payout_centavos' => 50000,
                'is_active' => true,
                'effective_from' => $now,
                'created_by' => null,
                'notes' => 'XHopy Official flagship storefront 0% platform commission.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Backfill any pre-MV-6 confirmed seller_orders idempotently.
        $stores = DB::table('stores')->get()->keyBy('id');
        $existingSuborders = DB::table('seller_orders')
            ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered', 'refunded'])
            ->orderBy('id')
            ->get();

        foreach ($existingSuborders as $suborder) {
            $storeRow = $stores->get($suborder->store_id);
            $isOfficial = $storeRow !== null && (bool) $storeRow->is_official;
            $ruleId = $isOfficial && $officialRuleId !== null ? $officialRuleId : $globalRuleId;
            $rateBps = $isOfficial ? 0 : 500;
            $grossCentavos = (int) round(((float) $suborder->total_amount) * 100);
            $commissionCentavos = (int) round(($grossCentavos * $rateBps) / 10000);
            $netCentavos = $grossCentavos - $commissionCentavos;
            $holdUntil = $suborder->delivered_at !== null
                ? CarbonImmutable::parse((string) $suborder->delivered_at)->addDays(7)
                : null;

            DB::table('seller_orders')->where('id', $suborder->id)->update([
                'commission_rule_id' => $ruleId,
                'commission_rate_bps' => $rateBps,
                'commission_amount' => number_format($commissionCentavos / 100, 2, '.', ''),
                'net_seller_amount' => number_format($netCentavos / 100, 2, '.', ''),
                'hold_until' => $holdUntil,
            ]);

            $isReversed = $suborder->status === 'refunded';

            DB::table('commission_entries')->insert([
                'seller_order_id' => $suborder->id,
                'store_id' => $suborder->store_id,
                'commission_rule_id' => $ruleId,
                'gross_amount_centavos' => $grossCentavos,
                'rate_bps' => $rateBps,
                'commission_centavos' => $commissionCentavos,
                'net_seller_centavos' => $netCentavos,
                'status' => $isReversed ? 'reversed' : 'recorded',
                'recorded_at' => $suborder->paid_at ?? $suborder->created_at ?? $now,
                'reversed_at' => $isReversed ? ($suborder->refunded_at ?? $now) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('seller_ledger_entries')->insert([
                'store_id' => $suborder->store_id,
                'seller_order_id' => $suborder->id,
                'seller_payout_id' => null,
                'actor_id' => null,
                'entry_type' => 'order_earning',
                'amount_centavos' => $netCentavos,
                'currency' => $suborder->currency ?? 'PHP',
                'description' => "Net seller earnings from {$suborder->seller_order_number} (rate ".number_format($rateBps / 100, 2).'%)',
                'idempotency_key' => "sale:{$suborder->id}",
                'created_at' => $suborder->paid_at ?? $suborder->created_at ?? $now,
            ]);

            if ($isReversed) {
                DB::table('seller_ledger_entries')->insert([
                    'store_id' => $suborder->store_id,
                    'seller_order_id' => $suborder->id,
                    'seller_payout_id' => null,
                    'actor_id' => null,
                    'entry_type' => 'order_reversal',
                    'amount_centavos' => -$netCentavos,
                    'currency' => $suborder->currency ?? 'PHP',
                    'description' => "Refund reversal for {$suborder->seller_order_number}",
                    'idempotency_key' => "reversal:{$suborder->id}",
                    'created_at' => $suborder->refunded_at ?? $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_ledger_entries');
        Schema::dropIfExists('seller_payouts');
        Schema::dropIfExists('commission_entries');

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('commission_rule_id');
            $table->dropColumn([
                'commission_rate_bps',
                'commission_amount',
                'net_seller_amount',
                'hold_until',
            ]);
        });

        Schema::dropIfExists('commission_rules');
    }
};
