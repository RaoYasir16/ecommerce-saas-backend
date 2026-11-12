<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wholesale_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('wholesale_product_id')
                ->nullable()
                ->constrained('wholesale_products')
                ->nullOnDelete();
            $table->string('product_title');
            $table->string('product_sku', 100)->nullable();
            $table->string('product_image')->nullable();
            $table->string('selected_color', 100)->nullable();
            $table->string('selected_size', 100)->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 10)->default('PKR');
            $table->string('payment_method', 60)->default('Bank Transfer');
            $table->string('payment_status', 30)->default('pending');
            $table->string('status', 40)->default('pending');
            $table->text('shipping_address');
            $table->string('whatsapp_phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_orders');
    }
};

