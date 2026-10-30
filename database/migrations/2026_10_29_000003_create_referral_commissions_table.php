<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('referred_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('company_subscription_id')->nullable()->constrained('company_subscription')->nullOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('transaction_history')->nullOnDelete();
            
            $table->decimal('subscription_amount', 15, 2)->default(0.00);
            $table->decimal('commission_percentage', 5, 2)->default(5.00);
            $table->decimal('commission_amount', 15, 2)->default(0.00);
            $table->string('currency', 10)->default('PKR');
            
            $table->string('status', 30)->default('credited'); // 'credited', 'pending', 'cancelled'
            $table->text('notes')->nullable();
            
            $table->timestamps();

            $table->index(['referrer_company_id', 'created_at']);
            $table->index(['referred_company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};
