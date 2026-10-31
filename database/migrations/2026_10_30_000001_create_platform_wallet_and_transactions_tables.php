<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Platform Master Wallet
        Schema::create('platform_wallet', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->string('currency', 10)->default('PKR');
            $table->decimal('total_revenue', 15, 2)->default(0.00);
            $table->decimal('total_commission_paid', 15, 2)->default(0.00);
            $table->timestamps();
        });

        // Seed default singleton platform wallet
        DB::table('platform_wallet')->insert([
            'id' => 1,
            'balance' => 0.00,
            'currency' => 'PKR',
            'total_revenue' => 0.00,
            'total_commission_paid' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Platform Wallet Ledger Transactions
        Schema::create('platform_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 15, 2);
            $table->decimal('gross_amount', 15, 2)->default(0.00);
            $table->decimal('commission_deducted', 15, 2)->default(0.00);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('source')->default('subscription_sale'); // 'subscription_sale', 'manual_adjustment', 'withdrawal', etc.
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('company_name')->nullable();
            $table->text('description')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('currency', 10)->default('PKR');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'type']);
            $table->index(['company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_wallet_transactions');
        Schema::dropIfExists('platform_wallet');
    }
};
