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
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->unsignedBigInteger('user_id')->nullable()->after('company_id');
            $table->string('order_number')->unique()->after('user_id');
            $table->string('status')->default('pending')->after('order_number'); // pending, processing, completed, cancelled
            $table->string('payment_status')->default('pending')->after('status'); // pending, paid, failed
            $table->string('payment_method')->default('cod')->after('payment_status'); // cod, stripe, card, etc.
            
            // Customer & Delivery info
            $table->string('customer_name')->after('payment_method');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone')->after('customer_email');
            $table->text('shipping_address')->after('customer_phone');
            $table->string('city')->nullable()->after('shipping_address');
            $table->string('postal_code')->nullable()->after('city');
            $table->text('notes')->nullable()->after('postal_code');

            // Amounts
            $table->decimal('subtotal', 10, 2)->default(0)->after('notes');
            $table->decimal('shipping_fee', 10, 2)->default(0)->after('subtotal');
            $table->decimal('tax', 10, 2)->default(0)->after('shipping_fee');
            $table->decimal('discount', 10, 2)->default(0)->after('tax');
            $table->decimal('total_amount', 10, 2)->default(0)->after('discount');

            // Cancellation details
            $table->text('cancel_reason')->nullable()->after('total_amount');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');

            // Indexes
            $table->index(['company_id', 'user_id']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'user_id']);
            $table->dropIndex(['company_id', 'status']);
            $table->dropColumn([
                'company_id',
                'user_id',
                'order_number',
                'status',
                'payment_status',
                'payment_method',
                'customer_name',
                'customer_email',
                'customer_phone',
                'shipping_address',
                'city',
                'postal_code',
                'notes',
                'subtotal',
                'shipping_fee',
                'tax',
                'discount',
                'total_amount',
                'cancel_reason',
                'cancelled_at',
            ]);
        });
    }
};
