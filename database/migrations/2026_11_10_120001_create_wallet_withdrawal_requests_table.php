<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('company_wallet_id')->constrained('company_wallet')->cascadeOnDelete();
            $table->string('request_number')->unique();
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('fee_percentage', 5, 2)->default(2.00);
            $table->decimal('fee_amount', 15, 2)->default(0.00);
            $table->decimal('net_amount', 15, 2);
            $table->string('payment_method')->default('bank_transfer'); // bank_transfer, easypaisa, jazzcash, raast
            $table->string('bank_name')->nullable();
            $table->string('account_title');
            $table->string('account_number');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->string('payout_reference')->nullable();
            $table->string('payout_receipt')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_withdrawal_requests');
    }
};

